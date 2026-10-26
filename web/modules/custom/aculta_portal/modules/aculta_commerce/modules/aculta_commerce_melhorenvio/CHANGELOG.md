# Changelog — ACULTA Commerce Melhor Envio

## 0.1.0-dev.4 (em andamento)

### Corrigido

- Valores monetários da cotação são lidos como texto decimal, sem `float`. Valores com até duas casas são preservados;
  precisão além de centavos é recusada em vez de arredondada. Coberto por `testMoneyStaysExactAndRejectsSubCentPrecision`.
- O cliente e o controller do webhook ganharam regressões de 403, resposta vazia, 5xx, timeout, assinatura, corpo
  inválido, segredo ausente e repetição. A suíte Unit reexecutada na PR: 85 testes / 204 assertions PASS.
- Kernel OAuth cobre retorno sem código e replay de `state`; a classe completa reexecutada na PR: 11 testes / 57
  assertions PASS em MariaDB dedicado, sem rede externa.
- PHPCS Drupal/DrupalPractice nos quatro arquivos PHP tocados pela integração: zero erros; avisos apenas de linhas longas.
- O adaptador resolve credenciais primeiro pelo Drupal Key e usa `aculta_portal.secrets_manager` como fallback. Nenhum
  valor foi colocado no Config Sync.

### Verificado, sem alteração de código

- Kernel de wiring no MariaDB dedicado com `commerce_shipping` ausente: resultado desta integração é 11 testes / 57
  assertions PASS; a evidência anterior de 9 / 45 é substituída.
- Retentativas: a cotação (`POST /api/v2/me/shipment/calculate`) é enviada como idempotente. Não há no repositório
  prova contratual disso; a compra (`/shipment/checkout`), a geração e o cancelamento não são idempotentes e não são
  repetidos.
- Webhook: a documentação oficial confirma HMAC-SHA256 do corpo com o segredo do aplicativo, mas não diz a codificação.
  O exemplo publicado tem 44 caracteres terminados em `=`, o que indica base64; hex nunca apareceu como evidência.
  Pendente: confirmar com um webhook real do sandbox.

## 0.1.0-dev.3

### Alterado

- Provider de frete integrado ao plugin manager, à remessa e às tarifas do motor proprietário `aculta_commerce`; removida a dependência funcional de `commerce_shipping` no manifesto.
- O teste Kernel de wiring carrega a cadeia real de módulos incluindo Studio e Messenger, sem `commerce_shipping`, e passou nesta branch: 9 testes / 45 assertions, sem rede externa. Reexecutado na PR #298 com Core/vendor físicos do worktree: 9 testes / 45 assertions; a tentativa anterior com Core symlinkado ao checkout compartilhado foi descartada como bootstrap inválido.
- A página de status usa o contrato neutro `PortalLinkElement` e o token `data-portal-ui="button-primary"`, sem classes visuais do tema dentro do módulo.
- A suíte Unit passou: 74 testes / 181 assertions com root isolado da PR. A tentativa anterior com Core symlinkado ao checkout compartilhado foi descartada como bootstrap inválido. PHPCS Drupal/DrupalPractice não encontrou erros nos arquivos verificados; permanecem avisos de linhas acima de 80 colunas, incluindo comentários preexistentes.
- O teste Kernel foi executado em banco isolado. O módulo permanece fora do Config Sync conforme decisão operacional; nenhum Runtime compartilhado foi alterado. Homologação visual e integração real no sandbox continuam pendentes.

## 0.1.0-dev.1 (em andamento)

### Adicionado

- Cliente HTTP OAuth2 do Melhor Envio (`Http\MelhorEnvioClient`): `authorization_code`, renovação de token, timeouts,
  espaçamento mínimo entre requisições e retentativas apenas em operações idempotentes.
- Ambientes `sandbox` e `production` com tokens separados (`StateMelhorEnvioTokenStore`).
- Configuração `MelhorEnvioConfig` com nomes de chaves do Drupal Key por ambiente. Nenhum valor de credencial no código
  nem na configuração exportada.
- Exceção `MelhorEnvioException` com categorias de erro e mensagens fixas.
- Dependência explícita do módulo `key`.
- 19 testes Unit do cliente, sem rede (`MockHandler`).

### Pendente

- Caminhos de autorização, token e cotação não confirmados na documentação pública (ME-PEND-01). Validar no sandbox.
- Campo `expires_in` e formato do cabeçalho `Authorization` a confirmar no sandbox.
- Criptografia em repouso dos tokens (ME-PEND-02).
- Kernel e Functional.
