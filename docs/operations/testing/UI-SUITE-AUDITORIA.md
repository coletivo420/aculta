# Auditoria UI Suite, ferramentas e resultados (2026-11-11)

Skills responsáveis: `ui-suite-integrations`, `ui-icons`, `ui-patterns`, `ui-styles`, `aculta-acessibilidade`, `aculta-regressao-visual`, `aculta-seguranca-estatica`, `aculta-shell-qualidade`, `aculta-navegador-testes`.

Base: `origin/dev` em `9d06d2167`, na worktree `skills-seguranca-a11y-visual`. O checkout principal tem 50 alterações de outros agentes, preservadas e não incluídas nesta entrega.

## 1. Evidência UI Suite (quatro fontes)

Regra: declaração não prova runtime. Cada módulo é classificado pelas fontes **disponíveis**.

| Módulo | `composer.json` | `composer.lock` | Config Sync | Filesystem / Runtime | Situação |
|---|---|---|---|---|---|
| `drupal/ui_icons` | `^2.1` | 2.1.0 | habilitado (+ `ui_icons_field`, `_menu`, `_patterns`, `_picker`) | não verificado | declarado e habilitado |
| `drupal/ui_skins` | `^1.2` | 1.2.1 | habilitado | não verificado | declarado e habilitado |
| `drupal/ui_styles` | `^1.22` | 1.22.0 | habilitado (+ `_page`, `_ckeditor5`, `_entity_status`, `_block`, `_layout_builder`, `_ui_patterns`, `_views`) | não verificado | declarado e habilitado; `_library` ausente |
| `drupal/ui_patterns` | `2.0.x-dev@dev` | dev-2.0.x | habilitado (+ `_blocks`, `_field`, `_field_formatters`, `_layouts`, `_library`, `_views`) | não verificado | desenvolvimento |
| `drupal/ui_patterns_field_group` | `2.0.x-dev@dev` | dev-2.0.x | **não** habilitado | não verificado | declarado, não habilitado |
| `drupal/ui_patterns_layouts` | `^2.0` | 2.0.23 | habilitado | não verificado | declarado e habilitado |
| `drupal/security_review` | `^3.1` | 3.1.3 | habilitado | não verificado | declarado e habilitado |
| UI Examples | ausente | ausente | ausente | — | não instalado |
| Display Builder | ausente | ausente | ausente | — | não instalado |

**Correções feitas nas skills:** `ui-icons` afirmava que `drupal/ui_icons` não constava no projeto; `ui-styles` tratava `ui_styles_page` como não habilitado; `ui-suite-integrations` dizia que UI Skins não estava instalado e que UI Patterns Field Group estava em `2.0.0-beta2`. Todas as afirmações foram corrigidas com a evidência acima.

**Não verificado:** estado efetivo no Runtime (`drush pm:list`, filesystem contrib). Este checkout não tem `web/modules/contrib` nem `vendor/`. Sem essa consulta, o estado é "habilitado no código", não "ativo no Runtime".

## 2. Matriz de ferramentas

Estados: INSTALLED, CONFIGURED, EXECUTABLE, CI, DEFERRED, MISSING.

| Ferramenta | Estado | Local | Skill | Gate | CI | Resultado real |
|---|---|---|---|---|---|---|
| Semgrep 1.183.0 | INSTALLED, EXECUTABLE | `~/.local/bin/semgrep` | `aculta-seguranca-estatica` | regras locais + fixtures | `quality-static.yml` (job security-static, baseline) | 474 achados; fixtures 17/17 PASS |
| Fixtures de regra | CONFIGURED, EXECUTABLE | `.agents/skills/aculta-seguranca-estatica/tests/` | idem | bloqueante no CI | idem | PASS |
| composer audit | EXECUTABLE (`--locked`) | repositório | idem | informativo | idem (`continue-on-error`) | 3 advisories em 2 pacotes |
| npm audit | EXECUTABLE | `tests/browser` | idem | informativo | não integrado | 0 vulnerabilidades |
| gitleaks | MISSING | — | `aculta-seguranca-estatica` | DEFERRED | não | não executado |
| drupal/security_review | DEFERRED | Runtime (Drush) | idem | somente leitura | não | não executado (sem Drush/Runtime consultável) |
| ShellCheck | INSTALLED, EXECUTABLE | `/usr/bin/shellcheck` | `aculta-shell-qualidade` | `-S warning` em arquivos alterados | `quality-static.yml` | legado: 27 avisos (dívida) |
| shfmt | INSTALLED, EXECUTABLE | `/usr/bin/shfmt` | idem | `-d` em arquivos alterados | idem | legado sem formatação: dívida |
| Bats-core | INSTALLED | `/usr/bin/bats` | idem | suíte futura em `tests/shell/` | DEFERRED (sem suíte ainda) | não há teste Bats novo nesta entrega |
| actionlint | MISSING | — | `aculta-shell-qualidade` | gate no CI pretendido | não | DEFERRED: instalação de binário via pip foi negada pelo classificador de ações; nenhum PASS registrado |
| `bash -n` + `runtime-sync` | EXECUTABLE | `scripts/` | `aculta-runtime-sync` | obrigatório | não | PASS |
| `scripts/test-runtime-sync.sh` | EXECUTABLE | `scripts/` | `aculta-runtime-sync` | obrigatório | não | 24 PASS, 0 FAIL |
| `@axe-core/playwright` 4.14.0 | INSTALLED (devDependency fixa) | `tests/browser` | `aculta-acessibilidade` | `test:a11y` | `browser-discovery` só lista | ver seção 3 |
| Playwright `@playwright/test` 1.56.1 | INSTALLED | `tests/browser` | `aculta-navegador-testes` | runner existente | discovery | 130 testes descobertos |
| Regressão visual | DEFERRED | — | `aculta-regressao-visual` | — | não | suíte não criada |
| ESLint | MISSING | — | — | — | não | DEFERRED |
| Stylelint 13 (`npm run lint:css`) | EXECUTABLE, declarado em `package.json` | `node_modules` da raiz (`npm ci --ignore-scripts`) | `css` | `lint:css` | não integrado ainda | PASS (exit 0) |
| `validate-aculta420-v2-foundations.php` | EXECUTABLE (estático) | `scripts/` | `css` | contraste de tokens | não | PASS: 85 pares de texto, 6 pares de faixa |
| PHPUnit / PHPStan / PHPCS / Rector | não executados nesta entrega | `vendor/` ausente | `aculta-gates-por-escopo` | — | `php-quality.yml` (PHPUnit, PHPStan) | não reexecutado |
| k6 | INSTALLED | `/usr/bin/k6` | — | fora de escopo | não | não executado |

## 3. Acessibilidade executada (origem `https://aculta.toca.net.br/`, Firefox headless)

Comando: `ACULTA_SITE_ORIGIN=https://aculta.toca.net.br/ SUITE_OUT=<fora-do-repo> npx playwright test playwright.a11y.spec.mjs`.

| Teste | Resultado |
|---|---|
| axe WCAG 2.0/2.1/2.2 A e AA (impacto serious/critical bloqueia) | **14 FAIL**, todos pela regra `color-contrast` |
| reflow 320 CSS px sem rolagem horizontal | **14 PASS** |
| rotas sem origem configurada | 32 SKIPPED (não contam como PASS) |

Os 14 FAIL estão em `main/aculta-patcher`, `main/aculta-portal-cep`, `main/aculta-qrcode`, `gallery/aculta-gallery`, `shop/aculta-frete`, `support/aculta-faq`, `transversal/aculta-document-viewer`, `transversal/aculta-editor`, `transversal/aculta-visual`, `transversal/aculta420-v2-audit-2026-11`, `transversal/aculta420-v2-card-accent`, `transversal/aculta420-v2-data-table`, `transversal/aculta420-v2-empty-state` e `transversal/aculta420-v2-navigation`.

**Causa-raiz confirmada (par único, 84 nós):** texto `#0c3c29` (`--color-dark` → `--color-text-primary`) sobre fundo `#689427` (`--palette-green`), razão medida pelo axe 3.45:1; recalculada pela fórmula WCAG: 3.46:1. Origem: a classe `.aculta-category--green` em `web/themes/custom/aculta420_v2/src/styles/content.css`. A variante vermelha do mesmo bloco já usa `--color-black`.

**Correção aplicada nesta entrega:** `.aculta-category--green` passa a usar `color: var(--color-black)`. Razão calculada: 5.85:1 (≥ 4.5:1). `lint:css` PASS; `validate-aculta420-v2-foundations.php` PASS.

**Verificação pendente:** o axe contra `aculta.toca.net.br` não prova a correção, porque esse host serve o checkout do Runtime, ainda sem esta alteração. Confirmar que os 14 FAIL somem exige deploy/sincronização controlada e nova execução. Também não confirmei, rota a rota, que todas as 14 páginas contêm a categoria verde; a causa-raiz está provada pelo par de cores, não pela presença do elemento em cada página.

Relatórios brutos (JSON, trace, screenshot) ficaram em `SUITE_OUT`, fora do repositório.

## 4. Gates e validadores desta entrega

| Comando | Resultado |
|---|---|
| `python3 scripts/validate-agent-skills.py` | PASS: 23 skills, 2 índices de agente, 18 casos |
| `php scripts/validate-agent-token-budget.php` | FAIL (6): `TODO.md`, `css`, `htmx`, `sdc`, `ui-patterns`. **Pré-existente**, arquivos não tocados nesta entrega, exceto as descrições de `css` e `htmx` que não foram alteradas. As skills novas foram ajustadas e passam. |
| `php scripts/validate-browser-index.php` | FAIL: `docs/architecture/GROWROOM420-VISUAL-PLANO.md` cita `tests/browser/aculta-forum/`, que não existe em `origin/dev`. **Pré-existente**, fora do escopo. |
| `php scripts/validate-browser-validators.php` | PASS (9 checks) |
| `php scripts/phpunit-suites.php --check` | PASS (unit 674, kernel 250) |
| `bash -n scripts/runtime-sync.sh` | PASS |
| `bash scripts/test-runtime-sync.sh` | PASS: 24 PASS, 0 FAIL |
| `git diff --check` | PASS |
| `shellcheck -S warning` (legado) | 27 avisos em `runtime-sync.sh` e `test-runtime-sync.sh` (dívida; não reformatado) |
| `shfmt -d` (legado) | diferença de formatação (dívida; não reformatado) |
| `npm audit` (`tests/browser`) | 0 vulnerabilidades |
| `composer audit --locked` | 3 advisories (ver `SEGURANCA-ESTATICA-2026-11-11.md`) |
| `npm run test:list` | PASS: 130 testes descobertos |

## 5. Dívida e próximas ações

1. Após sincronização controlada do Runtime, reexecutar o axe e confirmar que os 14 `color-contrast` desaparecem; se restar qualquer um, investigar a rota pelo seletor.
2. Confirmar estado efetivo dos módulos UI Suite no Runtime (`drush pm:list`) em modo somente leitura.
3. Triar os 24 `#markup` restantes e os itens PENDENTE de segurança.
4. Decidir sobre a atualização de `entity_print` (2.19.0 → 2.20.0) e `symfony/http-client` (7.4.22 → 7.4.23), que alteram o lock.
5. Instalar gitleaks e actionlint em versão fixada; pinar ações por SHA verificado.
6. Criar a suíte `playwright.visual.spec.mjs` com screenshots revisados, e a suíte Bats de `tests/shell/`.
7. Instalar ESLint com configuração própria e confirmar a configuração do Stylelint do projeto.
8. Reduzir o tamanho das skills `css`, `htmx`, `sdc` e `ui-patterns` (orçamento de token).
