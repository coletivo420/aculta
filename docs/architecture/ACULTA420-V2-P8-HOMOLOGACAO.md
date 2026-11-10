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
| `validate-aculta420-design-foundations.php` | **FAIL** (38/1057) | Pré-existente: o validador lê `css/tokens.css`, a V2 publica `src/styles/tokens.css` |
| `validate-aculta420-mapped-classes.php` | **FAIL** (5 classes `portal-profile__reactions*`) | Pré-existente, fora do diff |
| `validate-module-theme-boundary.php` | **FAIL** (64/7310) | Pré-existente em módulos do Portal; a sugestão de template saiu de `src/Hook` |
| `validate-aculta-portal-drupal12.php` | **FAIL** | Pré-existente: `AccountCoursesManager` (P5.5) e `InstitutionBarBlock` (service locator) |
| `validate-agent-token-budget.php` | **FAIL** (3) | Pré-existente: `AGENTS.md` 5333 B, `aculta_domain_config` sem TODO, `aculta_forum` com versão divergente |
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

- **Layout Builder por display:** **22 dos 24 displays compatíveis** com Layout Builder, incluindo nós `full`/`default`, oito `block_content` e `taxonomy_term.editorial_author.default`. Gate 170 checks PASS; ativação comparada em sete páginas e revalidada por multiconjunto de tokens (a ordenação do bloco "Alterações recentes" da Wiki gerava falsos positivos). Excluídos `node.document.full` e `block_content.basic.default`, que perdem conteúdo de forma reproduzível. `node.page.full` tem `allow_custom: true` para landing pages; override por nó testado em `/institucional` (texto e layout renderizados) e removido em seguida.
- **Landing por purpose (P5):** depende de conteúdo editorial e do mesmo ambiente isolado. Mecanismo e piloto Links documentados em `ACULTA420-V2-P5-LANDING-PAGES.md`.
- **Visual:** leitura humana das capturas e aceitação do responsável.
- **Prévia autenticada em navegador:** validada por Drush e HTTP anônimo, não em sessão autenticada real.
- **Cache cross-domain em ordem inversa:** feitas requisições sequenciais corretas, sem teste de troca de cache aquecido entre hosts.
- **Import/export de configuração em staging e rollback de configuração:** não executados.
- **`npm run lint:css`:** binário ausente.

## 6. P9 — cutover

**Não executado.** Critérios não atendidos: homologação visual, Layout Builder por display, landing pages e import/export em staging.

O que já existe para o cutover:

- **Rollback do shell:** `drush config:set aculta420_v2.settings aculta_shell_variant classic` + `drush cr`; testado duas vezes nesta sessão (v2 → clássico), com HTTP 200 e 1 H1/1 `<main>`.
- **Código:** o shell v2 é um template adicional; `git revert` do commit `e990a8edc` remove a estrutura sem afetar o clássico. O `info.yml` continua com as nove regiões; nenhuma região foi removida.
- **Container legado:** painel removido do formulário; `aculta_purpose_container`, `PurposeContainerLayout` e CSS permanecem para leitura até o corte, conforme ADR-054.
