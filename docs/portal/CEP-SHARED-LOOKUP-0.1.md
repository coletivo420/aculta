# Consulta de CEP compartilhada (ME-CEP 0.1)

Auditoria da fase ME-CEP 0.1-A. Objetivo: o `aculta_commerce_melhorenvio` reutiliza o sistema de CEP que já existe no
`aculta_portal_cep`, sem criar um segundo sistema de consulta.

Status em 2026-10-25: refatoração compartilhada (0.1-B) e teste Unit (0.1-T) implementados em `aculta_portal_cep`;
o submódulo Melhor Envio existe e consome `CepLookupInterface` nos adaptadores PHP. O teste do endereço (9 testes/36
assertions) e `scripts/validate-account-address.php` passaram. O runtime está habilitado no Homelab MAIN; não foi
realizada homologação de navegador nesta retomada.

## 1. Contratos encontrados

| Componente | Responsabilidade atual | Contrato público |
|---|---|---|
| `aculta_portal_cep.lookup` (rota) | Endpoint AJAX `/aculta_portal_cep/{cep}`, `cep: '\d{8}'`, `_permission: access content` | JSON `{ok, data}` ou `{ok: false, message}` com 404 |
| `CepLookupController::lookup()` | Exige `X-Requested-With`, delega ao serviço e monta resposta HTTP | Não repete normalização, cache, fontes nem mapeamento |
| `CepLookupService::lookup()` | Normaliza, percorre fontes configuradas, cacheia resultados externos positivos | Formato Drupal Address; não armazena endereço |
| `CepLookupService::fromViaCep()` | ViaCEP, timeout 5 s, duas tentativas | Retorna mapeado ou NULL |
| `CepLookupService::fromBrasilApi()` | BrasilAPI v2, timeout 5 s, uma tentativa | Retorna mapeado ou NULL |
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
- `ShippingAddressAdapter` do Melhor Envio injeta `CepLookupInterface` e consulta somente quando o endereço está
  incompleto; devolve sugestão limitada aos campos vazios e não grava o endereço. `MelhorEnvioAddressMapper` reutiliza
  `normalize()` para cotação/contratação sem fazer HTTP. O contrato do domínio logístico e seus testes pertencem ao
  submódulo Melhor Envio.

## 2. Achados

1. **Nome de serviço e de rota.** O serviço `aculta_portal_cep.lookup` tem o mesmo nome da rota
   `aculta_portal_cep.lookup`. Drupal usa namespaces separados (rotas e serviços), então não há colisão técnica, mas a
   confusão é real. O serviço usa esse ID e a rota permanece com o nome atual, porque o rate limiter
   verifica `_route === 'aculta_portal_cep.lookup'`.
2. **Resultados negativos não são cacheados.** CEP inexistente consulta ViaCEP e BrasilAPI a cada requisição. O rate limit
   (60/min por IP) limita o abuso, mas não evita chamadas repetidas para o mesmo CEP inválido. Registrado como dívida.
3. **Licença da base local.** A base versionada em `data/cep/` vem do DNE dos Correios. O risco foi aceito pelo
   responsável (ver `docs/portal/CEP-LOCAL.md`). Esta auditoria não altera essa decisão, mas o uso por um novo submódulo
   herda essa pendência.
4. **Sem fonte de endereço paralela.** O `aculta_commerce_melhorenvio` não deve guardar cópia de endereço. O endereço
   continua no Profile/Order do Drupal Commerce.

## 3. Decisões para as fases seguintes

- **0.1-B (serviço):** concluído. `CepLookupService` é a única implementação de fontes, cache, retry e mapeamento; o
  Controller continua responsável por AJAX, resposta HTTP e cabeçalhos. O gate verifica essa fronteira.
- **0.1-C (adaptador):** contrato implementado no Melhor Envio com injeção, sugestão somente para campos vazios e sem
  persistência; revisão de qualidade/fechamento do adaptador continua sob o owner do módulo.
- **0.1-T (testes):** Unit cobre normalização, ordem, cache positivo, fallback, fonte desabilitada e falha externa (9/36).
  `validate-account-address.php` PASS; PHPCS no código existente do módulo CEP apresenta 105 erros e 36 warnings, sem
  alterações nesta PR frente a `origin/main`.

## 4. Limitações de runtime

- Os testes Kernel dependem de banco e de um site Drupal instalado. Este ambiente pode não ter o homelab disponível.
  Quando não houver, a execução fica registrada como dívida, não como aprovada.
- A chamada real ao ViaCEP/BrasilAPI depende de rede. Os testes usam dublês de HTTP.
