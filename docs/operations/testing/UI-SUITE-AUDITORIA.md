# Auditoria UI Suite, ferramentas e resultados (2026-11-11)

Skills responsáveis: `ui-suite-integrations`, `ui-icons`, `ui-patterns`, `ui-styles`, `aculta-acessibilidade`, `aculta-regressao-visual`, `aculta-seguranca-estatica`, `aculta-shell-qualidade`, `aculta-navegador-testes`, `aculta-gates-por-escopo`.

Base: `origin/dev` em `9d06d2167`. Entregas: `87bec2be4`, `257adfb03` e a terceira publicada ao final desta sessão (ver histórico do branch). O checkout principal tem 50 alterações de outros agentes, preservadas e fora desta entrega.

## 1. Evidência UI Suite (quatro fontes)

Declaração não prova runtime. Cada módulo é classificado pelas fontes disponíveis.

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

**Correções feitas nas skills:** `ui-icons` afirmava que `drupal/ui_icons` não constava; `ui-styles` tratava `ui_styles_page` como não habilitado; `ui-suite-integrations` dizia que UI Skins não estava instalado e que UI Patterns Field Group estava em `2.0.0-beta2`. Corrigidas com a evidência acima.

**Não verificado:** estado efetivo no Runtime (`drush pm:list`, filesystem contrib). Esta worktree não tem `web/modules/contrib` nem `vendor/`. "Habilitado" significa "habilitado no código", não "ativo no Runtime".

## 2. Matriz de ferramentas

Estados: INSTALLED, CONFIGURED, EXECUTABLE, CI, DEFERRED, MISSING. Ferramenta só é PASS quando executada.

| Ferramenta | Estado | Local | Skill | Gate | CI | Resultado real |
|---|---|---|---|---|---|---|
| Semgrep 1.183.0 | INSTALLED, EXECUTABLE | `~/.local/bin/semgrep` | `aculta-seguranca-estatica` | regras locais | `quality-static.yml` (security-static, baseline) | 474 achados, triagem em `SEGURANCA-ESTATICA-2026-11-11.md` |
| Fixtures de regra | CONFIGURED, EXECUTABLE | `.agents/skills/aculta-seguranca-estatica/tests/` | idem | bloqueante | idem | PASS 17/17 |
| composer audit | EXECUTABLE (`--locked`) | repositório | idem | informativo (`continue-on-error`) | idem | PASS após atualização autorizada de `entity_print` 2.20.0 e `symfony/http-client` v7.4.23 |
| npm audit | EXECUTABLE | `tests/browser` | idem | informativo | não integrado | PASS: 0 vulnerabilidades |
| gitleaks 8.30.1 | INSTALLED, EXECUTABLE (release oficial, SHA256 verificado) | `$SUITE_OUT`/ferramentas | `aculta-seguranca-estatica` | triagem de achado | não integrado | executado em arquivos da entrega: 0 achados nela; 4 achados fora dela, PENDENTE; histórico Git não executado (guarda de worktree) |
| drupal/security_review | DEFERRED | Runtime (Drush) | idem | somente leitura | não | não executado (sem Drush/Runtime) |
| ShellCheck | INSTALLED, EXECUTABLE | `/usr/bin/shellcheck` | `aculta-shell-qualidade` | `-S warning` em arquivos alterados | `quality-static.yml` | legado: 27 avisos (dívida) |
| shfmt | INSTALLED, EXECUTABLE | `/usr/bin/shfmt` | idem | `-d` em arquivos alterados | idem | legado sem formatação (dívida); arquivo novo `.bats` formatado |
| actionlint 1.7.12 | INSTALLED, EXECUTABLE (wheel `actionlint-py==1.7.12.25`, venv de teste) | fora do repositório | `aculta-shell-qualidade` | sintaxe dos workflows | não integrado no CI ainda | PASS nos 2 workflows (`php-quality.yml`, `quality-static.yml`) |
| Bats-core | INSTALLED, EXECUTABLE | `/usr/bin/bats` | `aculta-shell-qualidade` | `bats tests/shell/` | `quality-static.yml` ainda não chama Bats | PASS 5/5 em `tests/shell/runtime-sync.bats` |
| `bash -n` + `runtime-sync` | EXECUTABLE | `scripts/` | `aculta-runtime-sync` | obrigatório | não | PASS |
| `scripts/test-runtime-sync.sh` | EXECUTABLE | `scripts/` | `aculta-runtime-sync` | obrigatório | não | PASS 24/24 (simulado) |
| Stylelint 13 (`npm run lint:css`) | EXECUTABLE | `node_modules` da raiz | `css` | `lint:css` | não integrado | PASS (exit 0) |
| ESLint 9.39.1 (`npm run lint:js`) | INSTALLED, CONFIGURED (`eslint.config.mjs`), EXECUTABLE | raiz | `css`, `ui-suite-integrations` | `lint:js` (tema) | não integrado | PASS no tema (`js` e `components`); legado de módulos fora do gate (ver seção 5) |
| `validate-aculta420-v2-foundations.php` | EXECUTABLE (estático) | `scripts/` | `css` | contraste de tokens | não | PASS: 85 pares de texto, 6 de faixa |
| `@axe-core/playwright` 4.14.0 | INSTALLED (devDependency fixa) | `tests/browser` | `aculta-acessibilidade` | `test:a11y` | `browser-discovery` só lista | ver seção 3 |
| Playwright 1.56.1 | INSTALLED | `tests/browser` | `aculta-navegador-testes` | runner existente | discovery | `test:list`: 130 testes; navegadores em cache |
| Suíte visual | CONFIGURED, EXECUTED (1 frente) | `tests/browser/playwright.visual.config.mjs` | `aculta-regressao-visual` | 10 capturas (2 temas × 5 viewports) | não | ver seção 4 |
| Suíte funcional de acesso | CONFIGURED, EXECUTED | `tests/browser/playwright.functional.spec.mjs` | `aculta-navegador-testes` | anônimo em rota admin: PASS 2/2 | não | ver seção 4 |
| k6 | INSTALLED | `/usr/bin/k6` | — | fora de escopo | não | não executado |
| PHPUnit / PHPStan / PHPCS / Rector | não executados nesta entrega | `vendor/` ausente | `aculta-gates-por-escopo` | — | `php-quality.yml` | DEFERRED |

## 3. Acessibilidade executada (origem `https://aculta.toca.net.br/`, Firefox headless)

| Teste | Resultado |
|---|---|
| axe WCAG 2.0/2.1/2.2 A e AA (serious/critical bloqueia) | **14 FAIL**, todos `color-contrast` |
| reflow 320 CSS px | **14 PASS** |
| rotas sem origem configurada | 32 SKIPPED (não contam como PASS) |

**Causa-raiz e correção:** texto `#0c3c29` sobre `#689427` (razão 3.46:1; axe 3.45:1) em `.aculta-category--green` (`web/themes/custom/aculta420_v2/src/styles/content.css`). Trocado para `--color-black` (5.85:1). Stylelint e validador de fundações passam.

**Pendente:** a reexecução do axe contra `aculta.toca.net.br` **não foi feita após a correção**, porque esse host serve o checkout do Runtime, ainda sem a alteração. Sem essa reexecução, a correção está provada por cálculo e não por teste de browser. Também não confirmei que todas as 14 páginas contêm a categoria verde.

## 4. Regressão visual e funcional

**Visual (`playwright.visual.config.mjs`):** frente `aculta420-v2-navigation`, tema claro e escuro, viewports 320/390/768/1024/1440, `maxDiffPixelRatio: 0`, animações desligadas, dados voláteis mascarados.

- Rodada 1 (`ACULTA_VISUAL_BASELINE=1`, saída em `SUITE_OUT`): gerou 10 candidatas. Falhou por design, porque snapshot novo exige revisão.
- Rodada 2 (sem baseline): **10 esperados, 0 inesperados, 0 flaky**. Prova de determinismo nesse ambiente (duas execuções idênticas pixel a pixel).
- Candidatas em `SUITE_OUT`, **não versionadas**: falta revisão humana de conteúdo e de dados pessoais antes de qualquer commit de imagem.
- Cobertura restante (demais frentes, UI Skins, Layout Builder, Views, Fórum, Conta, Studio, barra multidomínio): **DEFERRED**.

**Funcional (`playwright.functional.spec.mjs`):** anônimo em `/painel-administrativo` e `/painel-administrativo/` deve receber redirecionamento ou negação (301/302/303/307/308/401/403/404), nunca 200. Resultado: **2 PASS**.

Escopo funcional restante (login/logout, sessões multidomínio, modais HTMX, formulários com erro, seleção de ícones, troca de tema e preferências, previews reais): **DEFERRED**, exige contas de teste e origem de ambiente.

## 5. Gates e validadores

| Comando | Resultado |
|---|---|
| `python3 scripts/validate-agent-skills.py` | PASS: 23 skills, 2 índices, 18 casos |
| `php scripts/validate-agent-token-budget.php` | FAIL pré-existente: `TODO.md`, `css`, `htmx`, `sdc`, `ui-patterns`. Skills novas dentro do limite |
| `php scripts/validate-browser-index.php` | FAIL pré-existente: `GROWROOM420-VISUAL-PLANO.md` cita `tests/browser/aculta-forum/`, inexistente em `origin/dev` |
| `php scripts/validate-browser-validators.php` | PASS (9 checks) |
| `php scripts/phpunit-suites.php --check` | PASS (unit 674, kernel 250) |
| `bash -n scripts/runtime-sync.sh` | PASS |
| `bash scripts/test-runtime-sync.sh` | PASS 24/24 |
| `bats tests/shell/` | PASS 5/5 |
| `npm run lint:css` | PASS |
| `npm run lint:js` | PASS (tema) |
| `actionlint` (workflows) | PASS |
| `composer audit --locked` | PASS (0 advisories) |
| `npm audit` (`tests/browser`) | PASS (0) |
| `git diff --check` | PASS |

**Dívida de JavaScript fora do gate:** 2440 dos 2559 erros ESLint vinham de bibliotecas de terceiros embutidas (`dhtmlxscheduler.js`, editor H5P). Excluídas por caminho, com motivo no `eslint.config.mjs`. Dívida real restante em módulos próprios (`no-var`, `eqeqeq`, `no-unused-vars`, `prefer-const`) em `aculta_portal/**/js`: não corrigida nesta entrega, por ser legado fora do escopo tocado.

**Dívida de shell:** 27 avisos ShellCheck e diferença de `shfmt` nos scripts Runtime Sync legados, não reformatados.

## 6. Ferramentas faltantes ou bloqueadas

- **Runtime (Fase 14):** DEFERRED. `runtime-sync.sh plan` recusa a worktree (não está em `dev`). Rodar no checkout compartilhado foi bloqueado pelo isolamento da sessão. Próximo passo: `plan` a partir do checkout servido, fora da worktree, sem `sync-code` até revisar o delta.
- **gitleaks no histórico:** não executado (guarda de worktree recusou `git` com `-C`). Rodar a partir da worktree com `gitleaks git`.
- **Security Review (Fase 3):** DEFERRED (sem Drush).

## 7. Próximas ações

1. Reexecutar o axe após sincronização controlada do Runtime e confirmar que os 14 `color-contrast` desaparecem.
2. Revisar as candidatas visuais e decidir o local versionado das referências.
3. Triar os 16 `#markup` PENDENTE e os 4 achados gitleaks fora da entrega.
4. Aplicar `entity_print` 2.20.0 e `symfony/http-client` v7.4.23 no Runtime por fluxo controlado.
5. Integrar Bats, Stylelint e ESLint ao `quality-static.yml` depois do primeiro run verde no GitHub.
6. Pinar ações do GitHub por SHA verificado.
7. Reduzir `css`, `htmx`, `sdc`, `ui-patterns` para o orçamento de token.
