# ACULTA420 V2 — P8: homologação (relatório parcial)

**Data:** 2026-11-09 · **Branch:** `dev` · **Ambiente:** servidor de desenvolvimento (`*.aculta.toca.net.br`, HTTPS em 127.0.0.1) · **Drupal:** 11.5.0-beta1 · **Drush:** local.

Este relatório registra apenas o que foi executado. Item não executado é **DEFERRED**, não PASS.

## 1. Gates executados

| Gate | Resultado | Observação |
| --- | --- | --- |
| `validate-aculta420-theme-settings.php` | **PASS** (14) | Painel de container ausente; paletas e schema preservados |
| `validate-aculta420-shell-regions.php` | **PASS** (30) | Cinco regiões finais; 5 blocos em `highlighted` como ponte |
| `validate-aculta420-sdc-schemas.php` | **PASS** (69 componentes, 113 includes, 1527 checks) | Inclui os 5 layouts novos |
| `validate-aculta420-layouts.php` | **PASS** (42) | Descoberta pelo `plugin.manager.core.layout` e render real |
| `validate-aculta420-layout-builder-batch.php` | **PASS** (138) | 18 displays com LB, `allow_custom=false`, config ativa = Sync, render array e acesso às rotas |
| `validate-aculta-ui-library.php` | **PASS** (23) | Rotas MAIN, acesso por perfil, `max-age 0`, prévia real |
| `validate-aculta420-css-scope.php` | **PASS** (8) | `statistics.css` fora do global |
| `validate-admin-routes-main-domain.php` | **PASS** (175 rotas) | Inclui as duas rotas novas |
| `validate-project-status-index.php` | **PASS** (43 componentes) | README raiz regenerado |
| `php -l` nos arquivos alterados | **PASS** | |
| `git diff --check` | **PASS** | |
| `validate-aculta420-design-foundations.php` | **PASS** (1112) | Analisador passou a entender listas de seletor; literais dos componentes substituídos por tokens; exceção documentada só para a prévia da UI Library |
| `validate-aculta420-mapped-classes.php` | **PASS** (462 classes) | CSS adicionado para as reações do perfil público |
| `validate-module-theme-boundary.php` | **FAIL** (64/7314) | Dívida pré-existente de módulos do Portal que citam o tema; fora do diff |
| `validate-aculta-portal-drupal12.php` | **PASS** (1205) | Contrato `aculta_learning_contracts` (P5.5) e injeção do `class_resolver` no `InstitutionBarBlock` |
| `validate-agent-token-budget.php` | **PASS** | `AGENTS.md` 5000/5000, TODO do `aculta_domain_config`, versão do TODO do Fórum |
| `npm run lint:css` | **DEFERRED** | `node_modules` sem o binário `stylelint`; falha antes de analisar arquivos |

## 2. Matriz HTTP dos dez purposes (shell clássico e v2)

Método: `curl --resolve` com SNI do host, para preservar o VirtualHost.

| Purpose | Host testado | Clássico | Shell v2 | H1 | `<main>` |
| --- | --- | --- | --- | --- | --- |
| main | `aculta.toca.net.br` | 200 | 200 | 1 | 1 |
| links | `links.aculta.toca.net.br` | 200 | 200 | 1 | 1 |
| eventos | `eventos.aculta.toca.net.br` | 200 | 200 | 1 | 1 |
| wiki | `wiki420.aculta.toca.net.br` | 200 | 200 | 1 | 1 |
| magazine | `revista.aculta.toca.net.br` | 200 | 200 | 1 | 1 |
| shop | `loja.aculta.toca.net.br` | 200 | 200 | 1 | 1 |
| courses | `cursos.aculta.toca.net.br` | 200 | 200 | 1 | 1 |
| support | `apoio.aculta.toca.net.br` | 200 | 200 | 1 | 1 |
| forum | `growroom420.aculta.toca.net.br` | 200 | 200 | 1 | 1 |
| account | `conta.aculta.toca.net.br` | 302 (visitante → login) | 302 | — | — |

Correções feitas durante esta homologação: a landing Links tinha `<main>`/`<h1>` próprios dentro do `layout-page`; `revista-listing` e `forum-category-header` tinham `h1` fixo. Agora a landing Links não duplica landmark e os dois componentes usam `title_level` (`h2` por padrão). A landing Links também passou a ser composta no tema com `aculta420_v2:layout-section`; verificado nos shells clássico e v2 (200, `.layout-section`, 1 H1, 1 `<main>`), sem alterar o controller.

## 3. Evidência de navegador

**Método 1 (Playwright Firefox)**, headless, `ignoreHTTPSErrors` no modo local.

### 3.1. Runner do projeto

`SUITE_OUT=/home/piradopirata/suite-out/aculta-p8-20261109 ACULTA_BROWSER_ENV=local ACULTA_BROWSER_FRONTS=shell-v2 ACULTA_BROWSER_URLS="https://aculta.toca.net.br/,https://links.aculta.toca.net.br/" npm --prefix tests/browser run test:firefox` → **2/2 PASS**.

Capturas full-page: `$SUITE_OUT/shell-v2-main-320.png` e `shell-v2-main-1440.png`.

### 3.2. Suíte ampliada (script externo, credenciais de `secrets/`, sem impressão de valores)

**86 checks, 0 falhas.** Cobertura:

- Anônimo, shell v2, `main` e `links`, larguras 320/390/768/1024/1440: shell presente, `data-theme=dark` (padrão do site), exatamente 1 H1 e 1 `<main>`, sem rolagem horizontal, CSS do Studio ausente.
- Teclado a 320 px: foco visível encontrado em até 10 Tabs.
- Autenticado (`teste_comum`), `/conta`, mesmas cinco larguras: shell v2, no máximo 1 H1, 1 `<main>`, sem rolagem horizontal (medida sem a barra administrativa Gin), CSS do Studio presente.
- **Nota:** com a barra Gin visível, o administrador tem 16 px de rolagem horizontal a 320 px por causa de `.top-bar__actions` do Gin. É dívida do admin toolbar, fora do tema; não foi alterada.

Modo escuro autenticado: a conta de teste tem preferência própria `light`, que prevalece sobre o padrão do site; o modo escuro foi verificado no estado anônimo, que usa o padrão `dark`. Isso confirma a precedência documentada em `ColorModePreference`.

A suíte de 86 checks foi repetida após a restauração dos blocos de cabeçalho e a ativação do Layout Builder em 18 displays: **86 checks, 0 falhas**.

## 4. Testes negativos executados

| Teste | Resultado |
| --- | --- |
| Visitante abrindo prévia real no MAIN | **403** |
| Visitante abrindo prévia em `links` e `wiki420` | **404** |
| Usuário autenticado sem `administer blocks` | Negado pelo gate de acesso (23 checks) |
| Bloco com contexto obrigatório ausente | "Prévia indisponível" com o nome do contexto |
| Cache da prévia | `max-age 0` + `user.roles` no catálogo, prévia e indisponibilidade |
| CSS de estatísticas na home | Ausente do CSS servido após `drush cr` |
| CSS do Studio na home | Ausente; presente em `/conta` autenticado (verificado por Playwright) |
| `global-styling` local | Caiu de 268.036 para **107.609 bytes (−60%)**; `statistics`, `studio`, `portal-account`, `public-profile`, `event-organization`, `events`, `wiki` e `forum` são contextuais por rota |
| Escopo por CSS servido | `event-organization` ausente na home e presente em Eventos; `wiki` ausente na home e presente na Wiki; `forum` ausente na home e presente no Fórum; `portal-account`/`studio` presentes em `/conta` autenticado; `public-profile` presente no perfil público |
| Drift de blocos de cabeçalho | `main_menu`, `page_title`, `site_branding`, `help` e `breadcrumbs` estavam no Config Sync e ausentes da config ativa (cache mascarava). Restaurados do Sync; home com 1 H1, menu e marca presentes. `institution_bar` segue só na config ativa. |
| Landing Links no Layout Builder | Nó 635 publicado com alias `/redes-sociais`, duas seções SDC, links sociais reais, apoio, documentos e menu institucional com URLs absolutas para o MAIN. Playwright: 35 checks em cinco larguras, claro/escuro, shells clássico e v2, 0 falhas. |

## 5. DEFERRED (com causa)

- **Layout Builder por display:** as **22 configs elegíveis** apontam para o ComponentLayout UI Patterns `layout-section`. Gate reforçado: 276 checks PASS, cobrindo paridade, render arrays publicados, acesso e saída visual; 21 saídas usam o wrapper ComponentLayout, enquanto `node.wiki_entry.full` usa o adapter SDC `wiki-entry`, que consome os campos diretamente. `node.page.full` mantém `allow_custom: true`; demais displays não permitem overrides. Excluídos `node.document.full` e `block_content.basic.default`, que perdem conteúdo de forma reproduzível. Snapshot e rollback seletivo registrados na ADR-054; nenhum dado foi alterado.
- **Landing por purpose (P5):** depende de conteúdo editorial e do mesmo ambiente isolado. Mecanismo e piloto Links documentados em `ACULTA420-V2-P5-LANDING-PAGES.md`.
- **Wiki full browser:** DEFERRED. `/verbete/proibicionismo` retornou 403 anônimo; Firefox após o fluxo de login não recebeu título nem landmark `main`, então a captura em branco foi descartada. O adapter SDC direto passou no gate de renderização Drupal; isso não substitui a homologação visual.
- **Visual:** leitura humana das capturas e aceitação do responsável.
- **UI Components autenticado:** provider `/painel-administrativo/aparencia/ui/components/aculta420_v2` renderizou 16 grupos e 82 componentes; uma story SDC sob demanda carregou (HTTP 200), com Firefox ESR em 1280/320 e dark DOM. A 320 px, itens da listagem administrativa ficam fora da viewport, embora `documentWidth=320` e `maxScrollX=0`; aceite responsivo permanece DEFERRED. A rota antiga `aculta420` não contém grupos e não é evidência do catálogo. Acesso por perfil não-admin permanece DEFERRED.
- **Views row/style/display:** `aculta_albuns:page_1` usa `revista-album-card`, `layout-grid` e `views-display`; HTTP 200 e 3 resultados antes/depois, acesso anônimo permitido e Firefox 1280/320 claro/escuro sem overflow ou alvo abaixo de 44 px. `aculta_forum_categories:page_categories` usa `forum-category` + `views-display`, preserva o agrupamento nativo e mantém 12 resultados; Firefox passou em 320/390/768/1024/1440 claro e 320/1440 escuro, sem overflow, com um H1/main, foco visível e alvos ≥44 px. A mesma View tem `block_landing` com row SDC, mas seu placement está oculto na home e não recebeu aceite visual. Manifesto: `tests/browser/capturas/ui-patterns-views/2026-11-10/manifest.json`. Cache-hit, zoom 200% e navegação multi-page permanecem DEFERRED.
- **Cache cross-domain em ordem inversa:** feitas requisições sequenciais corretas, sem teste de troca de cache aquecido entre hosts.
- **Import/export de configuração em staging e rollback de configuração:** não executados.
- **`npm run lint:css`:** binário ausente.

## 6. P9 — cutover

**Executado no desenvolvimento em 2026-11-09** com autorização do responsável. As cinco regiões finais estão no `info.yml`, o shell v2 é o padrão, os cinco blocos foram migrados para `content` e o motor de container foi removido. Backup em `$SUITE_OUT/aculta-cutover-20261109/`; rollback em `ACULTA420-V2-P9-CUTOVER.md`. Validação final: gates do tema, fundação 454, Portal Drupal 12 1.205, Playwright 86/86 e landing 35/35 PASS. Restam homologação visual ampla, conteúdo de landing pages e import/export em staging.

O que já existe para o cutover:

- **Rollback do shell:** `drush config:set aculta420_v2.settings aculta_shell_variant classic` + `drush cr`; testado duas vezes nesta sessão (v2 → clássico), com HTTP 200 e 1 H1/1 `<main>`.
- **Código:** o shell v2 é um template adicional; `git revert` do commit `e990a8edc` remove a estrutura sem afetar o clássico. O `info.yml` continua com as nove regiões; nenhuma região foi removida.
- **Container legado:** painel removido do formulário; `aculta_purpose_container`, `PurposeContainerLayout` e CSS permanecem para leitura até o corte, conforme ADR-054.
