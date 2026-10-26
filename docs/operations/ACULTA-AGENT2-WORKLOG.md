# ACULTA — Worklog do agente executor 2

Branch: `feat/aculta-frentes-independentes`, criada a partir de `origin/chore/aculta-m0-integridade` (HEAD `b574a182b`).
Worktree: `/tmp/aculta-frentes-independentes`. Nenhum commit foi feito no worktree principal nem na PR #298.

## Frente 1 — `aculta_commerce_melhorenvio` (ME-0.1-C, parte de código)

| Campo | Registro |
|---|---|
| Módulo | `aculta_commerce_melhorenvio` |
| Fase | ME-0.1-C: cliente OAuth2 (código e Unit). Cotação (ME-0.2-A) ainda não iniciada |
| HEAD | `docs` da frente, ver SHAs abaixo |
| Implementado | Cliente `Http\MelhorEnvioClient`; configuração `MelhorEnvioConfig`; `StateMelhorEnvioTokenStore` (tokens por ambiente); `MelhorEnvioException` (categorias); `MelhorEnvioEnvironment` e `MelhorEnvioEndpoints`. Serviços, schema e settings atualizados. Dependência `key:key` |
| Testes | Unit do módulo: **48 PASS** (29 existentes + 19 novos do cliente), 125 asserções. Kernel e Functional: não executados |
| PHPCS | Arquivos novos: 0 erros (avisos de tamanho de linha). Os arquivos antigos de `src/Address` já tinham erros antes desta frente e não foram alterados |
| Bloqueadores | ME-PEND-01: caminhos `/oauth/authorize`, `/oauth/token` e `/api/v2/me/shipment/calculate` não aparecem nas páginas públicas consultadas (docs.melhorenvio.com.br). Ficam isolados em `MelhorEnvioEndpoints`, marcados como não confirmados. Sem credenciais: nenhuma chamada real foi feita |
| Integração central | (1) Chave `key` do Drupal Key para client_id e client_secret por ambiente, provisionada conforme a política (produção no banco criptografado). (2) Rota de callback OAuth2 para validar `state` e chamar `exchangeAuthorizationCode()`. (3) Campos de `settings` (`redirect_uri`, `contact_email`, `scopes`) precisam de valores reais do cadastro do app. (4) Decisão sobre criptografia dos tokens (ME-PEND-02) |
| Próxima tarefa | Validar ME-PEND-01 no sandbox com credenciais autorizadas; depois ME-0.2-A (plugin Commerce Shipping e cotação com fixtures) |
| Estado | **Dev.** Não candidata a RC: falta validação no sandbox e Kernel |

### Commits (prontos para cherry-pick, em ordem)

| SHA | Mensagem |
|---|---|
| `f812ff495` | feat(melhorenvio): implement OAuth2 HTTP client |
| `71f85bb0a` | test(melhorenvio): cover OAuth2 client without network |
| (este documento e a docs do módulo) | docs(melhorenvio): atualizar README, roadmap e CHANGELOG |

## Frentes 2 a 8 — não iniciadas nesta execução

| Frente | Módulo | Estado |
|---|---|---|
| 2 | `aculta_deployer` | Pendente. Não auditado nesta execução |
| 3 | `aculta_patcher` | Pendente |
| 4 | `aculta_portal_sitemap` | Pendente |
| 5 | `aculta_qrcode` | Pendente |
| 6 | `aculta_links` | Pendente |
| 7 | `aculta_activity` | Pendente |
| 8 | `aculta_editor` | Pendente |

A revisão transversal (seção 12 da missão) depende das oito frentes e não foi feita.

## Notas de ambiente

- Os testes Unit rodam com `vendor/` e `web/core/` do checkout principal. O worktree recebe cópia real de `web/core`
  (ignorada pelo git). O `vendor` é symlink (também ignorado). Sem a cópia real do core, o bootstrap do Drupal carrega as
  classes do módulo a partir do checkout principal e os testes não cobrem o código do worktree.
- Comando: `php vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_commerce/modules/aculta_commerce_melhorenvio/tests/src/Unit`
