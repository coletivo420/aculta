# ACULTA Commerce Melhor Envio

Integração logística com o Melhor Envio. Submódulo do `aculta_commerce`, versão `0.1.0-dev.1`.

Status: **fundação**, **endereço único** (ME-ADDRESS 0.1), **consulta de CEP compartilhada** (ME-CEP 0.1) e **cliente
HTTP OAuth2** (ME-0.1-C, somente código e Unit). Ainda não há cotação de frete, contratação, etiqueta, rastreamento. Os
caminhos de API de autorização, token e cotação **não estão confirmados** (ME-PEND-01) e não foram usados em rede. Ver
[ROADMAP](docs/ROADMAP.md) e [CHANGELOG](CHANGELOG.md).

## Responsabilidades

- Transformar o endereço do ACULTA em dados logísticos para o Melhor Envio (cotação, contratação, etiqueta).
- Usar a consulta de CEP do Portal (`aculta_portal_cep`). Não existe segundo sistema de consulta.
- Futuro: método de envio do Drupal Commerce Shipping, cotação, contratação e etiquetas (fases ME-0.2 em diante).

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
- **Credenciais:** `client_id` e `client_secret` vêm do Drupal Key pelos nomes em `keys.<ambiente>`. Os valores ficam no
  Key; em produção, no banco criptografado, conforme a política do projeto.
- **Erros:** `MelhorEnvioException` com `category()` (`unauthenticated`, `unauthorized`, `rate_limited`, `server`,
  `network`, `invalid_response`, `not_configured` etc.) e mensagem fixa. Nenhum corpo de resposta entra na exceção.
- **Sem rede nos testes:** o transporte é o `http_client`, e os testes usam `MockHandler`.
- **Pendências:** os caminhos de `MelhorEnvioEndpoints` e o campo `expires_in` precisam de validação no sandbox (ME-PEND-01).

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
- Previsto para ME-0.2-A: `commerce_shipping` e `aculta_commerce`.

## Testes

- Unit: 48 testes em `tests/src/Unit/` (adaptador de CEP 7, mapper 17, binder 5, cliente OAuth2 19). Executados e aprovados.
- Kernel e Functional: pendentes. Ver a matriz em `docs/ROADMAP.md` e as dívidas em `docs/operations/DEBT-REGISTER.md`.

## Segurança e LGPD

- Nenhum token, CPF, CNPJ ou endereço vai para log. O cliente também não registra query string, headers nem corpo.
- Tokens OAuth2 ficam no State API, separados por ambiente. Credenciais ficam no Drupal Key.
- O CEP é o único dado do endereço enviado à consulta externa, como já acontecia com o endpoint AJAX.
- O mapper e o binder não fazem HTTP nem gravação.
