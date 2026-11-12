# Gates por escopo

Regra: rode **somente** os gates do escopo do diff e dos módulos que dependem dele. Gate fora do escopo vai ao relatório como **N/A**, com o motivo. Gate que não pode rodar no ambiente vai como **DEFERRED**, com o motivo. Não declare PASS sem execução e cole a saída relevante (AGENTS, "Antes de concluir").

## CI

`.github/workflows/php-quality.yml` roda lint PHP, descoberta PHPUnit e as suítes Unit e Kernel (Kernel com MariaDB de serviço) em push e PR para `main` e `dev`. O PHPStan roda `analyse` com `phpstan-baseline.neon`: novos erros e entradas de baseline resolvidas falham o job. Nenhum validador de `scripts/validate-*.php` roda em CI. Os gates abaixo são executados pelo agente ou pelo responsável.

## Como rodar

- Gate que depende do Drupal bootstrap: `php vendor/drush/drush/drush.php php:script scripts/<gate>.php`.
- Gate que aceita host: informe o `--uri` do ambiente em que roda (servidor de desenvolvimento ou local). `validate-cross-domain-request-policy.php` falha sem ele.
- Saída verbosa: `python3 scripts/agent-output.py --lines 60 -- COMANDO` (`docs/operations/AGENT-OUTPUT.md`).

## Gates por escopo

| Escopo do diff | Gates | Observação |
| --- | --- | --- |
| Qualquer mudança no Portal (`aculta_portal`) | `php scripts/validate-aculta-portal-drupal12.php` | Obrigatório. Elimine, não expanda, a dívida registrada |
| Rotas `/painel-administrativo/**` ou `/integracoes/**` | `validate-admin-routes-main-domain.php` | Somente MAIN; subdomínio responde 404 |
| Catálogo de blocos e prévias da UI Library | `php scripts/validate-aculta-ui-library.php` (delega ao Drush) | Rotas MAIN, acesso por perfil, catálogo real, prévia com `max-age 0` |
| Configuração UI Patterns Views (row/style/display) | `php scripts/validate-ui-patterns-views-cutover.php` | Contrato da View, slots/sources SDC, filtros, ordenação, pager e acesso; evidência Runtime/browser continua necessária |
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
| Studio e fronteira módulo/tema | `validate-module-theme-boundary.php`, `validate-project-status-index.php` | Aceite visual Playwright do Studio: `tests/browser/studio/` |
| Galeria | `validate-aculta-gallery.php` | |
| Envio de e-mail | `validate-mail-transport.php` | |
| Testes de navegador (`tests/browser/`): criar, mover, alterar ou corrigir teste, ou documento que cite `tests/browser/` | `php scripts/validate-browser-index.php` (executa `python3 tests/browser/gerar-indice.py --check`) | Antes do commit. Falha se o índice estiver defasado ou se um documento citar caminho inexistente |
| Aceite único de termos | `validate-single-acceptance.php` | |
| Wiki | Unit do `aculta_wiki` (comando em `web/modules/custom/aculta_portal/modules/aculta_wiki/AGENTS.md`) | |
| Tema ACULTA420 V2 | Gates listados em `web/themes/custom/aculta420_v2/docs/gates.md` | Regras do tema ficam lá |
| Tema ACULTA420 (V1 removido) | `validate-aculta420-sdc-schemas.php`, `validate-aculta420-design-foundations.php`, `validate-browser-validators.php`; foundation via `php:script` | O V1 não existe mais no checkout: o design-foundations valida o `aculta420_v2`. O antigo `validate-aculta420-shell-contract.php` foi aposentado |
| Qualquer teste PHPUnit, `phpunit.xml`, `phpunit.performance.xml` ou `scripts/phpunit-suites.php` | `php scripts/phpunit-suites.php --check` e `vendor/bin/phpunit --testsuite unit` | A guarda falha se um `*Test.php` ficar fora das suítes ou se uma suíte obrigatória esvaziar. Benchmarks: `vendor/bin/phpunit -c phpunit.performance.xml` (opt-in) |
| Análise estática PHP | `vendor/bin/phpstan analyse` | Usa `phpstan-baseline.neon` revisado; não regenere o baseline no CI. Dívida por módulo/identificador: `docs/operations/testing/phpstan-debt.md` |
| Qualquer arquivo PHP alterado | `php -l` nos arquivos alterados | Projeto inteiro: via `agent-output.py` |
| Skills dos agentes e sincronização do Runtime | `python3 scripts/validate-agent-skills.py`, `php scripts/validate-agent-token-budget.php`; para alterações no shell ou no Runtime Sync v2, `bash -n scripts/runtime-sync.sh` e `bash scripts/test-runtime-sync.sh` | Matrizes em `docs/operations/TESTES-SKILLS-RUNTIME-SYNC.md`; não apresentar testes falsos como Runtime real |
| Documentos de leitura de partida (`AGENTS.md`, `CLAUDE.md`, `TODO.md`, TODO de componente, handoff) | `php scripts/validate-agent-token-budget.php` | Limites de bytes, versão do TODO igual ao `info.yml`, roadmap citado existe, handoff com até 3 entradas, cada skill com link em `.claude/skills/`, trechos exigidos por gates no AGENTS |
| Tabela "Não ler por padrão" (`AGENT-TOKEN-ECONOMY.md`) | `php scripts/validate-agent-economy-paths.php` | Caminhos existem e tamanhos declarados batem com o real (±2x); `vendor/` ausente é aceito |

## Matriz por tipo de arquivo (2026-11-11)

Critérios: PASS (executado e verde), FAIL, DEFERRED (não roda aqui; motivo), N/A (fora do escopo). Nunca declare PASS sem execução. Evidência e estado das ferramentas: `docs/operations/testing/UI-SUITE-AUDITORIA.md`.

| Escopo | Gates | Estado neste ambiente |
| --- | --- | --- |
| PHP alterado | `php -l` nos arquivos; `php scripts/phpunit-suites.php --check`; `phpstan analyse` (com `vendor/`) | `php -l` e suites: PASS. PHPStan: DEFERRED (sem `vendor/`) |
| Twig | validadores SDC do tema; `php -l` em módulos com PHP | DEFERRED onde o validador depende de Drush |
| CSS (tema) | `npm run lint:css` (Stylelint 13 do projeto) | PASS (0 erros) |
| JavaScript novo ou alterado | ESLint com configuração própria do projeto | DEFERRED: ESLint não declarado no projeto; criar configuração é pendência |
| SDC | `validate-aculta420-sdc-schemas.php`, `validate-aculta420-v2-sdc.php` | DEFERRED (depende do ambiente Drupal) |
| UI Patterns | `validate-ui-patterns-views-cutover.php` | DEFERRED (Runtime/browser) |
| UI Styles | `validate-aculta420-ui-styles.php` | DEFERRED (ambiente Drupal) |
| UI Skins | sem validador dedicado; regressão visual (`aculta-regressao-visual`) | DEFERRED: sem suíte visual |
| UI Icons | teste Unit `UiIconsPackTest` do tema (`phpunit --testsuite unit`) | DEFERRED (sem `vendor/`) |
| HTMX | `aculta-navegador-testes` + suíte `playwright.a11y.spec.mjs` | DEFERRED (sem origem de ambiente) |
| Rotas | `validate-admin-routes-main-domain.php` | DEFERRED (Drush) |
| Controllers e serviços | `php -l`; teste Unit/Kernel focal; PHPStan | `php -l`: PASS quando aplicável; demais DEFERRED |
| Formulários | `php -l`; `playwright.a11y.spec.mjs` quando houver rota | DEFERRED para acessibilidade |
| Pagamentos | `validate-payment-domain-policy.php`, `validate-portal-commerce-security.php` | DEFERRED (Drush) |
| Uploads | `validate-aculta-image.php`; regras Semgrep de `aculta-seguranca-estatica` | Semgrep: executado; validador: DEFERRED |
| Autenticação e sessão | `validate-session-cookie-policy.php`, `validate-turnstile-exceptions.php` | DEFERRED (Drush/Runtime) |
| Runtime Sync | `bash -n scripts/runtime-sync.sh`; `bash scripts/test-runtime-sync.sh` | PASS (30/30, simulado; inclui `structural-complete`) |
| Scripts shell | `shellcheck -S warning` e `shfmt -d` nos arquivos alterados; `bash -n` | ShellCheck e shfmt: executados; legado com dívida |
| GitHub Actions | `actionlint` (gate no CI pretendido) | DEFERRED: binário não instalado neste ambiente |
| Skills | `python3 scripts/validate-agent-skills.py`; `php scripts/validate-agent-token-budget.php` | Skills: PASS; orçamento: FAIL pré-existente (`TODO.md`, `css`, `htmx`, `sdc`, `ui-patterns`) |
| Teste de navegador | `php scripts/validate-browser-index.php` e `npm run test:list` | `test:list`: PASS (130); índice: FAIL pré-existente |

Mudança em contrato compartilhado (SDC, UI Styles, UI Library, serviço de pagamento) exige também os consumidores dependentes listados na skill `aculta-gates-por-escopo`.

## Manutenção

- Gate novo ou removido: atualize esta tabela no mesmo commit. Gate sem referência em documento de política nem em CI é candidato a remoção (auditoria de 2026-11-05: `docs/history/operations/AUDITORIA-TOKENS-TESTES-2026-11-05.md`).
- Gate de Olivero não existe e não deve ser criado (AGENTS, "Arquitetura e validação").
