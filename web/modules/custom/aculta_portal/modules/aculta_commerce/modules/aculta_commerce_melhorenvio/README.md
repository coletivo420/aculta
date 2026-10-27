# ACULTA Commerce Melhor Envio

Integração logística com o Melhor Envio. Submódulo do `aculta_commerce`, versão declarada no manifesto (`0.1.0-dev.4`). O motor de frete e o armazenamento de métodos, tarifas e remessas pertencem ao `aculta_commerce`; este módulo fornece o adaptador Melhor Envio.

Status: OAuth2, configuração por ambiente, credenciais via Drupal Key com fallback no gerenciador central de segredos, cliente HTTP, cotação simulada e integração com o plugin manager do motor ACULTA estão cobertos por testes Unit e Kernel sem rede externa. Contratação de etiquetas e rastreamento não foram homologados; chamadas externas e checkout ainda exigem validação autorizada no sandbox. Ver
[ROADMAP](docs/ROADMAP.md) e [CHANGELOG](CHANGELOG.md).

## Responsabilidades

- Transformar o endereço do ACULTA em dados logísticos para o Melhor Envio (cotação, contratação, etiqueta).
- Usar a consulta de CEP do Portal (`aculta_portal_cep`). Não existe segundo sistema de consulta.
- Adaptar os serviços elegíveis do Melhor Envio ao motor de frete proprietário do `aculta_commerce`; contratação e etiquetas permanecem etapas futuras.

Fora de escopo, por regra de arquitetura:

- **Formulário de endereço:** não existe. O Melhor Envio não tem formulário para o comprador. O formulário único do ACULTA
  (`AccountAddressForm`) é a fonte de verdade.
- **Endereço persistente:** fica no Profile/Order do Commerce. O submódulo não guarda cópia nem declara campos.
- **Carrinho, pedido, produto e pagamento:** continuam no Drupal Commerce, no Portal e no Mercado Pago.
- **Checkout:** nenhum campo novo. Preservados `CartConsolidator`, `CartUtility`, `CartSite`,
  `AcultaShippingInformation` e `BillingAddressPane`.

Contrato completo e matriz de campos: [docs/ADDRESS-CONTRACT.md](docs/ADDRESS-CONTRACT.md).

## Componentes

| Serviço | Classe | Papel |
|---|---|---|
| `aculta_commerce_melhorenvio.address_adapter` | `Address\ShippingAddressAdapter` | Valida o CEP do perfil e pede sugestão à consulta só quando faltam campos. Não grava. |
| `aculta_commerce_melhorenvio.address_mapper` | `Address\MelhorEnvioAddressMapper` | Mapeia o endereço do ACULTA para o logístico por finalidade (cotação ou contratação). Puro: sem HTTP e sem gravação. |
| `aculta_commerce_melhorenvio.shipment_address_binder` | `Address\ShipmentAddressBinder` | Monta origem (configuração) e destino (perfil de envio do pedido) para cotação e contratação. |
| `aculta_commerce_melhorenvio.client` | `Http\MelhorEnvioClient` | Cliente OAuth2: autorização, troca de código, renovação, retentativas seguras e erros por categoria. Único ponto de chamada à API. |
| `aculta_commerce_melhorenvio.config` | `Http\MelhorEnvioConfig` | Ambiente, URL de retorno, escopos, e-mail de contato e nomes das chaves do Drupal Key. |
| `aculta_commerce_melhorenvio.token_store` | `Http\StateMelhorEnvioTokenStore` | Tokens por ambiente, no State API. Nunca em configuração exportada. |

### Cliente OAuth2 (`MelhorEnvioClient`)

- **Ambientes:** `sandbox` (`https://sandbox.melhorenvio.com.br`) e `production` (`https://melhorenvio.com.br`). Cada um tem
  cadastro, tokens e chaves próprios. Valor inválido em `environment` cai no sandbox.
- **Fluxo:** `authorization_code`. `authorizationUrl($state)` gera a tela de consentimento; `exchangeAuthorizationCode()`
  troca o código e grava os tokens. O `state` deve ser aleatório e validado pelo retorno, o que fica para a rota de callback.
- **Renovação:** o token é renovado 60 s antes de expirar. Um 401 provoca uma renovação e uma nova tentativa. Se o
  refresh_token for recusado, os tokens são limpos e a conexão exige nova autorização.
- **Retentativas:** só em operações marcadas como `idempotent`, para falhas de rede, 429 e 5xx, com backoff de 100 ms,
  200 ms. Operações POST não são repetidas.
- **Timeouts:** conexão 5 s, total 20 s. **Espaçamento mínimo** de 200 ms entre requisições, por processo.
- **Credenciais:** `client_id` e `client_secret` são resolvidos pelos nomes em `keys.<ambiente>`: Drupal Key tem
  precedência e o serviço central `aculta_portal.secrets_manager` é fallback para credenciais do servidor. Os valores
  não entram em configuração exportada nem em logs.
- **Erros:** `MelhorEnvioException` com `category()` (`unauthenticated`, `unauthorized`, `rate_limited`, `server`,
  `network`, `invalid_response`, `not_configured` etc.) e mensagem fixa. Nenhum corpo de resposta entra na exceção.
- **Sem rede nos testes:** o transporte é o `http_client`, e os testes usam `MockHandler`.
- **Pendências:** caminhos e resposta da API, validade de `expires_in` e codificação real da assinatura do webhook
  ainda precisam de validação externa autorizada (ME-PEND-01 e registro de pendências).

### Mapeamento (`MelhorEnvioAddressMapper`)

- Rua e número: `StreetNumberField::split()` do `aculta_portal`. Não há regex própria aqui.
- Complemento (`address_line2`): preservado e opcional.
- Cotação (`PURPOSE_QUOTE`): exige só CEP.
- Contratação (`PURPOSE_SHIPMENT`): exige rua, número (1 a 10 dígitos), bairro, cidade, UF e país BR.
- S/N: bloqueia a contratação com `sn_not_accepted_by_api`. Nunca vira zero. A cotação aceita.
- O resultado é `LogisticsAddress`. Quando `errors` não está vazio, nenhum campo deve ser usado.

### Origem e destino (`ShipmentAddressBinder`)

- Destino: o perfil de envio da remessa, recebido pelo chamador. Não consulta o cadastro atual do usuário.
- Origem: `aculta_commerce_melhorenvio.settings:origin`. Vazia por padrão, então as operações bloqueiam até ser preenchida.
  Não há CEP de origem fixado no código.

### Regras do adaptador de CEP (`ShippingAddressAdapter`)

1. Endereço completo não consulta a base de CEP.
2. CEP com máscara é normalizado antes de qualquer uso.
3. Nunca sobrescreve campo já preenchido, nem número ou complemento.
4. CEP não encontrado não gera endereço inventado.
5. Falha da consulta resulta em `unavailable` e não interrompe o checkout.
6. Disponibilidade de frete não é decidida aqui. Ela vem da cotação real do Melhor Envio (ME-0.2-A).

## Dependências

- `aculta_portal` (`StreetNumberField`, perfis e formulário de endereço).
- `aculta_portal_cep` (`CepLookupInterface`, serviço `aculta_portal_cep.lookup`).
- Motor de frete: `aculta_commerce`, proprietário dos métodos, remessas e tarifas.

## Testes

- Unit: 85 testes / 204 assertions PASS na PR #298.
- Kernel `MelhorEnvioWiringKernelTest`: 11 testes / 57 assertions PASS em MariaDB isolado, sem rede externa; inclui verificações de wiring, OAuth, state de uso único e rejeição de callback sem código. PHPCS Drupal/DrupalPractice nos arquivos alterados: zero erros; avisos remanescentes são de linhas longas. Functional e homologação visual permanecem pendentes. Ver `docs/ROADMAP.md` e `docs/PENDENCIAS.md`.

## Segurança e LGPD

- Nenhum token, CPF, CNPJ ou endereço vai para log. O cliente também não registra query string, headers nem corpo.
- Tokens OAuth2 ficam no State API, separados por ambiente. Credenciais são lidas do Drupal Key ou, como fallback, do gerenciador central de segredos.
- O CEP é o único dado do endereço enviado à consulta externa, como já acontecia com o endpoint AJAX.
- O mapper e o binder não fazem HTTP nem gravação.

## Rotas e webhook (padrão `/integracoes/<nome>/`)

| Caminho | Método | Acesso | Função |
|---|---|---|---|
| `/integracoes/melhorenvio/configuracao` | GET | permissão `administer melhorenvio` | estado da conexão e botão que abre a autorização em popup |
| `/integracoes/melhorenvio/configuracao/origem` | GET, POST | permissão `administer melhorenvio` | CEP e endereço de origem da cotação |
| `/frete/simular` | GET | acesso ao conteúdo | JSON do simulador de frete (`cep`) |
| `/integracoes/melhorenvio/autorizar` | GET | permissão `administer melhorenvio` | inicia a autorização OAuth2 (gera `state` novo; sem cache) |
| `/integracoes/melhorenvio/retorno` | GET | permissão `administer melhorenvio` | valida `state`, troca o código, grava a mensagem do Drupal e responde com a página padrão de popup (`PopupReturnPage`), que leva à página de estado |
| `/integracoes/melhorenvio/webhook` | POST | público (`_access: TRUE`) | recebe as notificações de etiqueta; autenticidade pelo `X-ME-Signature` |

Webhook: a assinatura é HMAC-SHA256 do corpo bruto, com o segredo do aplicativo (`keys.<ambiente>.client_secret`, lido do
Drupal Key ou pelo gerenciador central de segredos). A documentação oficial não diz a codificação do hash; o exemplo
publicado é base64 (termina em `=`). O código aceita base64 e hexadecimal; hexadecimal não tem evidência de uso e
precisa ser confirmado com um webhook real do sandbox. Respostas: 503 sem segredo
configurado, 403 com assinatura inválida, 400 com corpo fora do formato, 204 quando o evento é aceito.

Eventos aceitos (`order.*`): created, pending, released, generated, received, posted, delivered, cancelled,
undelivered, paused, suspended. Só evento, id da etiqueta e status vão para o log.

Cadastro no Melhor Envio: "Integrações" → "Área Dev." → "Seus aplicativos" → "Novo Webhook", com a URL
`https://aculta.toca.net.br/integracoes/melhorenvio/webhook` (teste) ou o host de produção. A documentação diz que o webhook
só recebe etiquetas geradas pelo mesmo aplicativo, e que há 5 tentativas com intervalo de 15 minutos.
