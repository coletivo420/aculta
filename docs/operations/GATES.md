# Gates por escopo

Regra: rode **somente** os gates do escopo do diff e dos módulos que dependem dele. Gate fora do escopo vai ao relatório como **N/A**, com o motivo. Gate que não pode rodar no ambiente vai como **DEFERRED**, com o motivo. Não declare PASS sem execução e cole a saída relevante (AGENTS, "Antes de concluir").

## CI

`.github/workflows/php-quality.yml` roda lint PHP, descoberta PHPUnit e as suítes Unit e Kernel (Kernel com MariaDB de serviço) em push e PR para `main` e `dev`. O PHPStan roda `analyse` com `phpstan-baseline.neon`: novos erros e entradas de baseline resolvidas falham o job. Os validadores de contratos dos agentes rodam tambem no job PHP lint; os demais validadores de `scripts/validate-*.php` sao por escopo. Verificar execucao efetiva dos jobs: skipped/cancelled nao equivalem a PASS.

## Como rodar

- Gate que depende do Drupal bootstrap: `php vendor/drush/drush/drush.php php:script scripts/<gate>.php`.
- Gate que aceita host: informe o `--uri` do ambiente em que roda (servidor de desenvolvimento ou local). `validate-cross-domain-request-policy.php` falha sem ele.
- Saída verbosa: `python3 scripts/agent-output.py --lines 60 -- COMANDO` (`docs/operations/AGENT-OUTPUT.md`).

## Gates por escopo

| Escopo do diff | Gates | Observação |
| --- | --- | --- |
| Mudanca de codigo no Portal (`aculta_portal`) | `php scripts/validate-aculta-portal-drupal12.php` | Trate regressao nova; divida anterior documentada nao bloqueia fix independente |
| Rotas `/painel-administrativo/**` ou `/integracoes/**` | `validate-admin-routes-main-domain.php` | Somente MAIN; subdomínio responde 404 |
| Alias legado da UI Library para UI Patterns | `php scripts/validate-aculta-ui-library.php` (delega ao Drush) | Rota MAIN/admin, acesso por perfil, redirecionamento ao overview oficial e ausência de preview próprio |
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
| Skills e instrucoes dos agentes | `python3 scripts/validate-agent-skills.py`, `php scripts/validate-agent-token-budget.php` | Verificar tambem comportamento nas CLIs quando mudar roteamento/hook; limite de bytes e aviso, nao bloqueio |
| Scripts Runtime Sync | `bash -n scripts/runtime-sync.sh`, `bash scripts/test-runtime-sync.sh` | Apenas ao modificar o mecanismo de sincronizacao, nao por edicao de skill/docs |
| Documentos de leitura de partida (`AGENTS.md`, `CLAUDE.md`, `TODO.md`, TODO de componente, handoff) | `php scripts/validate-agent-token-budget.php` | Estrutura essencial de contratos e alertas de custo de contexto; tamanho de TODO/skill nao bloqueia entrega. Symlinks e frontmatter sao verificados em `validate-agent-skills.py` |
| Tabela "Não ler por padrão" (`AGENT-TOKEN-ECONOMY.md`) | `php scripts/validate-agent-economy-paths.php` | Caminhos citados existem; desvio de tamanho historico vira WARN e nao FAIL; `vendor/` ausente e aceito |

## Matriz por tipo de arquivo (2026-11-11)

Critérios: PASS (executado e verde), FAIL, DEFERRED (não roda aqui; motivo), N/A (fora do escopo). Nunca declare PASS sem execução. A coluna seguinte é fotografia da auditoria de 2026-11-11, **não** indica resultados do CI atual; verificar os jobs e a evidência da revisão em questão. Histórico: `docs/operations/testing/UI-SUITE-AUDITORIA.md`.

| Escopo | Gates | Estado histórico de 2026-11-11 (não usar como PASS atual) |
| --- | --- | --- |
| PHP alterado | `php -l` nos arquivos; `php scripts/phpunit-suites.php --check`; `phpstan analyse` (com `vendor/`) | `php -l` e suites: PASS. PHPStan: DEFERRED (sem `vendor/`) |
| Twig | validadores SDC do tema; `php -l` em módulos com PHP | DEFERRED onde o validador depende de Drush |
| CSS (tema) | `npm run lint:css` (versão efetiva no `package.json`) | PASS histórico em 2026-11-11; verificar CI corrente, que registrou falhas posteriores |
| JavaScript novo ou alterado | `npm run lint:js` (ESLint 9 na raiz) | Executar no ambiente da entrega; consultar o CI atual para PASS/FAIL |
| SDC | `validate-aculta420-sdc-schemas.php`, `validate-aculta420-v2-sdc.php` | DEFERRED (depende do ambiente Drupal) |
| UI Patterns | `validate-ui-patterns-views-cutover.php` | DEFERRED (Runtime/browser) |
| UI Styles | `validate-aculta420-ui-styles.php` | DEFERRED (ambiente Drupal) |
| UI Skins | `php scripts/validate-aculta420-ui-skins.php` e regressao visual focal | Verificar cobertura do componente e ambiente efetivo |
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
| GitHub Actions | `actionlint .github/workflows/*.yml` local e no CI (`quality-static.yml`, versão fixada) | PASS local em 2026-11-13 (v1.7.12); job de CI já integrado |
| Skills | `python3 scripts/validate-agent-skills.py`; `php scripts/validate-agent-token-budget.php` | Resultado deve ser obtido nesta entrega; avisos de tamanho nao sao FAIL |
| Teste de navegador | `php scripts/validate-browser-index.php` e `npm run test:list` | `test:list`: PASS (130); índice: FAIL pré-existente |

Mudança em contrato compartilhado (SDC, UI Styles, UI Library, serviço de pagamento) exige também os consumidores dependentes listados na skill `aculta-gates-por-escopo`.

## Manutenção

- Gate novo ou removido: atualize esta tabela no mesmo commit. Gate sem referência em documento de política nem em CI é candidato a remoção (auditoria de 2026-11-05: `docs/history/operations/AUDITORIA-TOKENS-TESTES-2026-11-05.md`).
