# Gates por escopo

Regra: rode **somente** os gates do escopo do diff e dos módulos que dependem dele. Gate fora do escopo vai ao relatório como **N/A**, com o motivo. Gate que não pode rodar no ambiente vai como **DEFERRED**, com o motivo. Não declare PASS sem execução e cole a saída relevante (AGENTS, "Antes de concluir").

## CI

`.github/workflows/php-quality.yml` roda só lint PHP e PHPStan em push e PR para `main`. Nenhum validador de `scripts/validate-*.php` roda em CI. Os gates abaixo são executados pelo agente ou pelo responsável.

## Como rodar

- Gate que depende do Drupal bootstrap: `php vendor/drush/drush/drush.php php:script scripts/<gate>.php`.
- Gate que aceita host: informe o `--uri` do ambiente em que roda (servidor de desenvolvimento ou local). `validate-cross-domain-request-policy.php` falha sem ele.
- Saída verbosa: `python3 scripts/agent-output.py --lines 60 -- COMANDO` (`docs/operations/AGENT-OUTPUT.md`).

## Gates por escopo

| Escopo do diff | Gates | Observação |
| --- | --- | --- |
| Qualquer mudança no Portal (`aculta_portal`) | `php scripts/validate-aculta-portal-drupal12.php` | Obrigatório. Elimine, não expanda, a dívida registrada |
| Rotas `/painel-administrativo/**` ou `/integracoes/**` | `validate-admin-routes-main-domain.php` | Somente MAIN; subdomínio responde 404 |
| Domínio, purpose, caminhos de entrada, apresentação | `validate-domain-presentation-contract.php`, `validate-domain-front-paths.php` | |
| Checkout, pagamento, loja | `validate-payment-domain-policy.php`, `validate-portal-commerce-security.php` | Pagamento só com pedido explícito |
| Cross-domain, sessão, cookies | `validate-cross-domain-request-policy.php` (com `--uri`, Runtime), `validate-session-cookie-policy.php` | |
| Login, anti-bot, Turnstile, links de uso único | `validate-turnstile-exceptions.php`, `validate-single-use-links.php` | |
| Popup e modal | `validate-portal-popup-rule.php` | |
| Credenciais e integrações | `validate-secrets-contract.php` | |
| Endereço e CEP | `validate-account-address.php` | |
| Foto de perfil | `validate-aculta-user-photo.php` | |
| Imagem | `validate-aculta-image.php` | |
| Papéis e hierarquia | `validate-role-hierarchy.php`, `validate-aculta-user-roles.php` | |
| URLs públicas | `validate-public-slugs.php`, `validate-no-temporary-route-aliases.php` | |
| Conta raiz e perfil | `validate-aculta-portal-conta-root.php`, `validate-profile-links.php` | |
| Studio e fronteira módulo/tema | `validate-module-theme-boundary.php`, `validate-project-status-index.php` | Aceite visual do Studio: `tests/browser/aculta-studio/` |
| Galeria | `validate-aculta-gallery.php` | |
| Envio de e-mail | `validate-mail-transport.php` | |
| Aceite único de termos | `validate-single-acceptance.php` | |
| Wiki | Unit do `aculta_wiki` (comando em `web/modules/custom/aculta_portal/modules/aculta_wiki/AGENTS.md`) | |
| Tema ACULTA420 V2 | Gates listados em `web/themes/custom/aculta420_v2/docs/gates.md` | Regras do tema ficam lá |
| Tema ACULTA420 V1 (somente referência) | `validate-aculta420-sdc-schemas.php`, `validate-aculta420-design-foundations.php`, `validate-browser-validators.php`; foundation e shell via `php:script` | Só se a tarefa tocar o tema V1, que é somente leitura |
| Qualquer arquivo PHP alterado | `php -l` nos arquivos alterados | Projeto inteiro: via `agent-output.py` |
| Documentos de leitura de partida (`AGENTS.md`, `CLAUDE.md`, `TODO.md`, TODO de componente, handoff) | `php scripts/validate-agent-token-budget.php` | Limites de bytes, versão do TODO igual ao `info.yml`, roadmap citado existe, handoff com até 3 entradas, cada skill com link em `.claude/skills/`, trechos exigidos por gates no AGENTS |
| Tabela "Não ler por padrão" (`AGENT-TOKEN-ECONOMY.md`) | `php scripts/validate-agent-economy-paths.php` | Caminhos existem e tamanhos declarados batem com o real (±2x); `vendor/` ausente é aceito |

## Manutenção

- Gate novo ou removido: atualize esta tabela no mesmo commit. Gate sem referência em documento de política nem em CI é candidato a remoção (auditoria de 2026-11-05: `docs/history/operations/AUDITORIA-TOKENS-TESTES-2026-11-05.md`).
- Gate de Olivero não existe e não deve ser criado (AGENTS, "Arquitetura e validação").
