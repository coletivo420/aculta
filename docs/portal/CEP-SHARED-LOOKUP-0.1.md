# Consulta de CEP compartilhada (ME-CEP 0.1)

Auditoria da fase ME-CEP 0.1-A. Objetivo: o `aculta_commerce_melhorenvio` reutiliza o sistema de CEP que já existe no
`aculta_portal_cep`, sem criar um segundo sistema de consulta.

Status: auditoria concluída. Refatoração (0.1-B), adaptador Melhor Envio (0.1-C) e testes (0.1-T) estão descritos nas
seções de plano abaixo.

## 1. Contratos encontrados

| Componente | Responsabilidade atual | Contrato público |
|---|---|---|
| `aculta_portal_cep.lookup` (rota) | Endpoint AJAX `/aculta_portal_cep/{cep}`, `cep: '\d{8}'`, `_permission: access content` | JSON `{ok, data}` ou `{ok: false, message}` com 404 |
| `CepLookupController::lookup()` | Exige `X-Requested-With`, normaliza dígitos, percorre fontes, aplica cache e headers | Mantido sem alteração no comportamento |
| `CepLookupController::external()` | Cache por CEP em `cache.data` (`aculta_portal:cep:{cep}`, 30 dias) | Só guarda resultado positivo |
| `CepLookupController::fromViaCep()` | ViaCEP, timeout 5 s, duas tentativas | Retorna mapeado ou NULL |
| `CepLookupController::fromBrasilApi()` | BrasilAPI v2, timeout 5 s, uma tentativa | Retorna mapeado ou NULL |
| `CepLocalStore::lookup()` | Base local em `private://aculta_portal_cep/shards/{prefixo}.tsv` | Mesmo formato dos externos |
| `CepSources::ordered()` | Ordem e ativação das fontes, da configuração `aculta_portal_cep.settings` | Lista de IDs (`local`, `viacep`, `brasilapi`) |
| `CepLookupRateLimitSubscriber` | `flood`: 60 consultas/min por IP, só na rota `aculta_portal_cep.lookup` | Responde 429 ao exceder |
| `CepStatusReport`, `CepApiProbe`, `CepStatusCronHooks` | Diagnóstico e status da base e das fontes | Sem uso pelo lookup |

### Formato de dados (Drupal Address)

O mapeamento é o mesmo em `CepLookupController::mapped()` e `CepLocalStore::mapped()`:

- `postal_code` (sempre os 8 dígitos)
- `address_line1` (logradouro)
- `dependent_locality` (bairro)
- `locality` (cidade)
- `administrative_area` (UF)

Número, complemento e país não fazem parte do resultado. O campo `country_code` é fixado em BR pelo formulário de endereço
(`AccountAddressForm`), não pela consulta.

### Consumidores atuais

- `js/cep-address.js` (biblioteca `aculta_portal_cep/cep-address`), usada por `AccountAddressForm`, que atende Minha conta,
  checkout (`BillingAddressPane`), loja e perfis de equipe.
- `AcultaShippingInformation` (checkout) herda de `ShippingInformation` e reutiliza o perfil de cobrança copiado por
  `BillingAddressPane::billingCopy()`. Não há campo de CEP próprio no pane de envio.
- Nenhum código PHP chama a consulta. O `aculta_commerce_melhorenvio` ainda não existe no repositório (verificado em
  `web/modules/custom/aculta_portal/modules/aculta_commerce/`, que só tem `src`, `tests`, `docs`).

## 2. Achados

1. **A lógica de consulta vive dentro do Controller.** Fontes, cache, retries e mapeamento estão em métodos privados do
   `CepLookupController`. Um serviço PHP não consegue reaproveitá-la sem duplicar código. A refatoração precisa mover essa
   lógica para um serviço e deixar o Controller apenas com o papel HTTP.
2. **O gate de conta depende do texto do Controller.** `scripts/validate-account-address.php` procura:
   - `isXmlHttpRequest()` no Controller;
   - `aculta_portal_cep.local_store` e a chamada `->local->lookup(` antes de `fromViaCep(` no Controller.
   Ao mover a lógica, esses dois trechos mudam de arquivo. O gate precisa ser atualizado para verificar o serviço
   compartilhado, mantendo a mesma regra: base local antes dos externos e acesso só por AJAX.
3. **Nome de serviço e de rota.** O serviço proposto `aculta_portal_cep.lookup` tem o mesmo nome da rota
   `aculta_portal_cep.lookup`. Drupal usa namespaces separados (rotas e serviços), então não há colisão técnica, mas a
   confusão é real. Decisão: o serviço usa o ID pedido, e a rota permanece com o nome atual, porque o rate limiter
   verifica `_route === 'aculta_portal_cep.lookup'`.
4. **Resultados negativos não são cacheados.** CEP inexistente consulta ViaCEP e BrasilAPI a cada requisição. O rate limit
   (60/min por IP) limita o abuso, mas não evita chamadas repetidas para o mesmo CEP inválido. Registrado como dívida.
5. **O módulo não tem testes.** Não existe `tests/` em `aculta_portal_cep`. Os testes de 0.1-T são a primeira cobertura.
6. **Licença da base local.** A base versionada em `data/cep/` vem do DNE dos Correios. O risco foi aceito pelo
   responsável (ver `docs/portal/CEP-LOCAL.md`). Esta auditoria não altera essa decisão, mas o uso por um novo submódulo
   herda essa pendência.
7. **Sem fonte de endereço paralela.** O `aculta_commerce_melhorenvio` não deve guardar cópia de endereço. O endereço
   continua no Profile/Order do Drupal Commerce.

## 3. Decisões para as fases seguintes

- **0.1-B (serviço):** criar `aculta_portal_cep.lookup` como serviço com `lookup(string $cep): ?array` e
  `normalize(string $cep): ?string`. O Controller passa a delegar ao serviço e mantém:
  - verificação `isXmlHttpRequest()`;
  - respostas `{ok, data}` e 404 com a mesma mensagem;
  - `Cache-Control: private, no-store` e `Vary: X-Requested-With, Cookie`;
  - rate limit na subscriber, inalterado.
  O serviço não usa requisição HTTP interna para a rota AJAX.
- **0.1-C (adaptador):** o submódulo Melhor Envio recebe o serviço por injeção. O adaptador só lê o CEP do perfil de envio
  e valida os 8 dígitos. Não grava endereço e não substitui número ou complemento. Se o endereço estiver incompleto, o
  adaptador pode sugerir os campos da consulta, sem salvar. A disponibilidade de frete continua vindo da cotação real.
- **Gate:** atualizar `scripts/validate-account-address.php` para checar o serviço compartilhado, sem enfraquecer a regra.

## 4. Limitações de runtime

- Os testes Kernel dependem de banco e de um site Drupal instalado. Este ambiente pode não ter o homelab disponível.
  Quando não houver, a execução fica registrada como dívida, não como aprovada.
- A chamada real ao ViaCEP/BrasilAPI depende de rede. Os testes usam dublês de HTTP.
