# Changelog — ACULTA Commerce Melhor Envio

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
