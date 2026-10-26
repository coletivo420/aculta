# Changelog — ACULTA Commerce Melhor Envio

## 0.1.0-dev.3

### Alterado

- Provider de frete integrado ao plugin manager, à remessa e às tarifas do motor proprietário `aculta_commerce`; removida a dependência funcional de `commerce_shipping` no manifesto.
- Teste de wiring atualizado para verificar o plugin ACULTA. A suíte Unit completa passou: 74 testes / 181 assertions; PHPCS Drupal/DrupalPractice dos arquivos alterados passou.
- O teste Kernel integrado ainda precisa ser executado em docroot de teste que carregue os fontes desta branch. O módulo permanece fora do Config Sync conforme decisão operacional; nenhum Runtime foi alterado.

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
