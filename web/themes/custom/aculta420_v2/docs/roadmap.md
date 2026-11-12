# Roadmap do fórum Growroom420 (ACULTA420 V2)

## Auditoria, PurgeCSS e limpeza de CSS legado (2026-11-12)

- Infraestrutura `scripts/css-audit/` (inventário, coletores dinâmicos,
  relatório) e PurgeCSS 9.1.0 fixado como ferramenta de desenvolvimento.
- Removidos `studio-identity.css` (sem `html.studio-identity` no V2) e
  642 linhas de `studio.css` presas a `.aculta-main`; regras mortas saíram
  de content, utilities, forum, wiki, account, messenger, biblioteca de
  componentes, barra multidomínio e mapas `portal-ui-*`.
- Tema: 106 arquivos, 451.112 bytes-fonte (−45.319), 2.499 regras
  (−192), 3.098 seletores (−298). `:has()` 178 → 73; `!important` 17 → 14.
- Homologação: matriz 100/100 em dez hosts, cinco larguras e dois modos,
  sem overflow; axe com 7 falhas de contraste pré-existentes no rodapé e
  breadcrumb do Fórum. Runtime Sync BLOCKED por worktree concorrente/RX,
  sem drift de updatedb/config/deploy. Sessão autenticada, modais, gavetas,
  teclado e leitor de tela: DEFERRED.
- Pendente: homologação visual do CSS restante, aceite de claro/escuro e
  a migração dos utilitários `aculta-u-*` emitidos por `PortalUiHooks` para
  composição SDC. Captura e navegador: DEFERRED.

## Títulos de cards e dívida de gates (2026-11-12)

- `forum-topic-card`, `revista-teaser`, `wiki-entry` teaser, `forum-category`
  e o cabeçalho do Fórum usam o SDC `heading` nos títulos (H5/H6 conforme o
  papel do cartão); CSS de tipografia próprio removido.
- Criados os dois templates de View de categorias exigidos pelo gate de
  fundação (fallback por campos e grupos com H2).
- Stylelint do tema zerado (breadcrumb e utilitários); literal de cor e
  durações de movimento fora de `tokens.css` corrigidos.
- Estado: design foundations 1381 PASS, foundation 495 PASS, Stylelint 0
  erros, SDC/UI Styles/escopo PASS; browser 56/56 em claro/escuro.

## Sistema de títulos ACULTA420 V2 (2026-11-12)

- SDC `heading` implementa a escala H1–H6 da referência de tipografia, com
  faixa tricolor, barra lateral colorida, superfícies semânticas e stories.
  Tokens `--heading-*` concentram tamanho, peso, altura de linha e espaçamento.
- Títulos de rota de Revista, Wiki, Eventos, Fórum, Loja e Cursos passam pelo
  SDC; a listagem do Fórum tem o H1 no `forum-category-header` e não duplica
  o título da rota. `revista-listing`, `wiki-entry`, `account-shell` e
  `social-links-page` também foram migrados; CSS legado removido.
- UI Styles `heading_accent` e `heading_surface` disponíveis para blocos de
  título.
- Estado: gates SDC, foundations e UI Styles PASS; browser Firefox 42/42 e
  axe 14/14 sem bloqueio em claro/escuro, 1280/1920/390 px. Conta
  autenticada, zoom de 200% e leitor de tela seguem pendentes.

## Auditoria de consistência CSS e layouts (2026-11-11)

- Corrigido o par institucional do SDC `surface-panel`: texto escuro sobre
  verde `#689427` no modo claro, preservando texto claro sobre a superfície
  escura. O gate `validate-aculta420-v2-foundations.php` passa a medir o par
  nos dois modos.
- `layout-page--aside` e o fallback `page__main--with-sidebar` colocam a
  coluna lateral à esquerda no desktop, conservando conteúdo antes de aside
  no DOM. O layout mobile permanece em coluna única.
- Removida a trilha vazia `navigation` do grid do shell v2; comentários
  alinham-se às cinco regiões finais. Alias `--aculta-surface-raised` agora
  acompanha `--color-surface-raised` em claro/escuro.
- Cascata: CSS exclusivo da UI Patterns Library saiu de `layout.css` para
  `component-library-mode.css`; o container do `site_key` account foi para
  `portal-account.css`; `ui-library-admin.css` não repete a moldura dos
  cartões. `layout-page--stack` usa `--aculta-narrow-container-width` e a
  leitura de 68–70 ch fica no conteúdo. Stories sem título e sem sidebar
  foram adicionadas.
- Estado: gates estáticos, Stylelint e PHPUnit aprovados. Homologação de
  navegador em 774/774 checks PASS (10 domínios públicos — incluindo o Fórum em
  `growroom420` —, 7 larguras, claro/escuro, Conta autenticada e UI Library),
  registrada em
  `tests/browser/capturas/aculta420-v2-audit/2026-11-11/manifest.json`.
  Pendentes: zoom 200%, teclado, `prefers-reduced-motion` e aceite visual
  humano. Ver `docs/architecture/ACULTA420-V2-AUDITORIA-CSS-LAYOUT-2026-11-11.md`.

## CSP — remoção das violações de estilo inline (2026-11-11)

- HTMX passou a usar a folha de estilos externa do tema para indicadores; UI Skins recebe nonce por resposta; o placeholder Drupal dialog é estático e não precisa do atributo inline. Nenhuma fonte global `unsafe-inline` foi adicionada.
- Playwright encontrou zero eventos `style-src` em `/`, `/galleries`, `/transparencia`, `/categorias` e nas homes válidas de Eventos, Loja e Conta. O componente admin anônimo respondeu 403; login do UID de teste não existe no Runtime e a verificação autenticada fica DEFERRED.
- Stylelint focal, foundations 83 pares, PHPUnit focal 27/27, PHP lint, cache rebuild e diff check passaram.

## UI Patterns Library: abas e erro de script (2026-11-11)

- Overview agora registra a biblioteca `ui-library-admin` que já era anexada
  pelo hook. As abas relacionam painel e grupo por `data-group-id`, e o filtro
  também considera o grupo. CSS mantém duas colunas em desktop (60rem úteis) e
  uma coluna em telas menores.
- Gate UI Library: 25 checks PASS; Firefox ESR autenticado abriu o catálogo com
  84 SDCs e carregou uma story. Erros JS agregados antigos não foram reproduzidos
  após cache rebuild. A rodada completa do lote via WebDriver ficou inconclusiva;
  teclado e zoom 200% permanecem pendentes. Avisos CSP são report-only; não foi
  necessário habilitar `unsafe-inline`.

## ADR-055 — UI Styles Block (2026-11-11)

- `/transparencia` no MAIN usa UI Styles `surface_style` raised e
  `spacing_section` normal na instância de bloco de Documentos.
- Import Runtime isolado após snapshot; três rows, acesso anônimo, foco e
  landmarks preservados. Firefox validou computed CSS e reflow em cinco larguras
  claras/escuras. Cache-hit e zoom 200% seguem pendentes.
- UI Styles Page permanece sem atribuição: a API 1.22 configura regiões
  globalmente por tema, sem seletor por Domain ID.

## ADR-054/055 — ComponentLayout SDC nos displays elegíveis (2026-11-10)

- As 22 configs elegíveis usam `layout-section` via UI Patterns Layouts; 21
  saídas exibem o wrapper e Wiki full usa seu adapter SDC direto. As duas
  exclusões por perda de conteúdo seguem intactas.
- Importação parcial no Runtime foi precedida por snapshot; nenhuma entidade
  ou dado editorial foi alterado.
- Gate Layout Builder: 276 checks PASS, com renderização de entidades, paridade
  Runtime/Sync e autorização das rotas de defaults. Homologação visual ampla,
  teclado, zoom e cache-hit continuam pendentes.

## UI Skins — cutover Runtime e piloto Links (2026-11-11)

- `ui_skins` está habilitado no Config Sync; o gate exige o módulo, mapa de dez
  domínios, cinco variáveis, vinte scopes por variável e contraste ≥4,5:1.
- UI Skins 1.2.1, mapa Domain ID → skin e scopes das cinco variáveis estão
  ativos no Runtime. Firefox ESR passou 300/300 nos dez domínios em cinco larguras e modos, sem
  overflow, H1/main únicos e contraste mínimo 7,16:1. Links resolve Ruderalis;
  os demais Skunk. Cache-hit, zoom, teclado e aceite visual amplo pendem.

## UI Library administrativa (2026-11-11)

- Catálogo autenticado: 16 grupos, 84 SDCs e story sob demanda, sem overflow
  no conteúdo em 320/1280; anônimo bloqueado por 403 como esperado.
- Drawer do Claro pode manter itens fora da viewport a 320 sem rolagem do
  documento; este diagnóstico é separado do conteúdo do catálogo. Zoom e teclado
  administrativo seguem DEFERRED.

## UI Icons — integração oficial em andamento (2026-11-10)

- UI Icons Menu, Picker, Fields e Patterns estão configurados no Config Sync;
  seleções de menu são limitadas aos packs Phosphor Regular/Duotone.
- Importação no Runtime, verificação de permissões/foco e prévia real dos SDCs
  ainda estão pendentes; Fluent Emoji permanece no catálogo editorial do tema.

## UI Styles Entity Status — sinal visual editorial (2026-11-11)

- Runtime/Sync habilitados após snapshot; import parcial aplicou somente o
  settings do tema. `unpublished_preview` usa contorno tracejado sem alterar
  permissões ou moderação. Teste upstream isolado 1/7 passou; saída visual
  real, revisão por perfis e cache seguem DEFERRED.

## ADR-054/055 — Views row/style/display (2026-11-10)

- A View Álbuns usa `revista-album-card`, `layout-grid` e `views-display` nos
  níveis row, style e display. O piloto Runtime manteve acesso anônimo, três
  resultados e a rota HTTP 200; browser focado confirmou layout claro/escuro
  em 1280/320 px e alvos de toque ≥44 px.
- Paginação multi-página, cache-hit, teclado, zoom, demais Views e homologação
  dos dez domínios seguem pendentes; ADR-054/055 permanecem abertas.

## Views — categorias do Fórum (2026-11-11)

- `/categorias` usa row `forum-category`, mantém o agrupamento Views nativo e
  compõe o display com `views-display`. Os 12 resultados e o acesso anônimo
  permaneceram; Firefox validou cinco larguras claras, duas escuras, foco e
  alvos ≥44 px. O bloco `block_landing` continua sem aceite porque seu placement
  está oculto na home. Cache-hit e zoom 200% seguem pendentes.

## Views — catálogo público de Eventos (2026-11-11)

- `eventos_publicos:block_1` usa o style UI Patterns `layout-grid` sobre as
  rows nativas `aculta_events_catalogue_card`; ids filtrados, labels, ordem,
  acesso e dados continuam sob o módulo Eventos e a View.
- Importação Runtime isolada após snapshot; HTTP 200 e dois resultados antes e
  depois. Firefox ESR validou grid, dois SDCs, foco visível e sem overflow em
  320/1440 px claro/escuro. Zoom 200%, cache-hit e revisão visual humana pendem.

> **Marcos P8/P9 implementados (2026-11-09); ADR-054/055 ainda abertas:** o tema tem as cinco regiões finais, o shell v2 é o padrão e o motor de container foi removido. Layout Builder cobre 22 dos 24 displays compatíveis, a landing Links está publicada no LB e a UI Library oferece prévias reais. Pendências: homologação visual, conteúdo editorial das demais landings, migração integral dos visuais para UI Suite e dívida de boundary dos módulos do Portal. Rollback documentado em `docs/architecture/ACULTA420-V2-P9-CUTOVER.md`.


Status espelhado de `TODO.md`. Plano visual: `docs/architecture/GROWROOM420-VISUAL-PLANO.md`.

## UI Skins — fonte visual ativa (2026-11-11)

- Configuração/formulário por purpose removidos do tema. O Portal resolve skin por Domain ID e entrega `aculta_skin_id`; UI Skins declara cinco variáveis e vinte scopes.
- Mapa, CSS variables e scopes ativos; A/B/A validado para Links/Skunk sem vazamento entre hosts. Capturas e aceite visual amplo ainda pendem; esta etapa não fecha ADR-054/055.

## ADR-054/055 — SDCs transversais e Views (2026-11-10)

- `aculta_section` e o aviso de pagamento foram ligados aos SDCs `layout-section` e `surface`; dados, render arrays e destino externo continuam no Portal.
- Inventário gerado: 381 registros/83 templates Twig; 15 templates fallback de módulos relacionados a adapters SDC do tema.
- Categorias do Fórum: página `/categorias` usa `forum-category` + `views-display`; agrupamento nativo mantido, Runtime 200/12 resultados e browser cinco larguras claros + duas escuras PASS. O block landing tem row SDC, mas placement está oculto na home e não recebeu aceite visual.
- Stylelint focal, PHPUnit, SDC schemas, Layouts e classes mapeadas passaram. Gate Stylelint global segue com falhas preexistentes em `surface.css`, `surface-accent.css` e `surface-panel.css`.

## Entregas

| Entrega | Escopo | Estado |
| --- | --- | --- |
| 1 | Lista de tópicos, página de tópico, cabeçalho de categoria | Aplicada e publicada. Captura Firefox ESR: 76/76 |
| 2 | Painel lateral da lista, diretrizes e acabamento do `/criar-topico` | Aplicada em CSS. Captura do formulário pendente |
| 3 | SDCs de estatísticas do Fórum (FST-08) | Schema PASS; renderização e captura pendentes |

## Pendências

- Migração do vocabulário de tokens para `--aculta-*`, conforme decidido. Pendente, em etapas.
- Painéis de destaque, moderadores e relacionados: aguardam o contrato com o Portal.
- Refatoração de fronteira do `aculta_studio`: não commitada, aguarda renderização e testes Kernel/Unit.


## ADR-054/055 — Revista News em UI Patterns Views (2026-11-11)

- `aculta_news:block_1` usa `revista-listing` no style, mantém teaser SDC e a consulta nativa. Quatro conteúdos, acesso anônimo e contratos de filtro/ordem/cache mantidos; browser 10/10 claro/escuro, 320–1440.
- Pendentes neste piloto: cache-hit, zoom 200% e revisão visual humana. A fila das demais Views permanece aberta.

## ADR-054/055 — Home editorial em UI Patterns Views (2026-11-11)

- `home_editorial_highlights:block_1` troca `views_vvjb` pelo SDC existente `layout-grid`; os três destaques, sua ordem, Layout Builder, acesso e query nativos seguem intactos.
- Runtime HTTP 200 e três rows antes/depois. Firefox ESR A/B 10/10 em cinco larguras e claro/escuro; foco, contraste ≥8.80:1, H1/main e overflow passaram.
- Cache-hit não é mensurável enquanto a home responder private/no-cache/UNCACHEABLE; zoom 200% e revisão ampliada pendem. ADR-054/055 seguem abertas.

## ADR-054/055 — Galeria editorial em UI Patterns (2026-11-11)

- `aculta_album_fotos:block_1` substitui o style VVJS pelo SDC `layout-grid`, preservando os 63 resultados PhotoSwipe, ordenação, acesso e metadados de cache. A View do Fórum e a galeria do Commerce também usam `layout-grid`; o Fórum não tem fixture publicada e a galeria de produto ainda aguarda aceite visual.
- Firefox ESR passou 10/10 antes e 11/11 depois em 320/390/768/1024/1440 e claro/escuro; foco, alt, H1/main, reflow e abertura/fechamento PhotoSwipe por Escape passaram. Cache-hit, zoom 200%, outros perfis e aceite visual humano pendem; a extensão VVJS permanece apenas por demonstração upstream `vvjs_example`, classificada `BLOCKED_UPSTREAM` no inventário.
- Snapshot/rollback e hashes de captura estão em `tests/browser/capturas/ui-patterns-gallery/2026-11-11/manifest.json`.
## ADR-054/055 — Documentos em UI Patterns Views (2026-11-11)

- `/transparencia` usa UI Patterns `layout-grid` e o SDC `document-card` para
  as rows nativas de node, preservando consulta e Field API.
- Runtime anônimo retornou três rows com cache contextual. Fixtures atuais não
  expõem descrição de arquivo no teaser; browser visual, outros perfis, zoom e
  cache-hit permanecem pendentes.

## ADR-054/055 — catálogo Commerce em UI Patterns (2026-11-11)

- `aculta_shop_catalog` aplica `layout-grid` às saídas pública block/page sem
  mudar o display nativo do produto, formulário de compra, filtros, ordenação,
  ACL e pager; adapter Twig compõe o SDC `product-card`. Sync/Runtime parseados
  coincidem; HTTP 200 e produto 1 antes e depois. Firefox 10/10 em cinco larguras
  e dois modos, foco e contraste mínimo 12,4:1. Zoom, cache-hit, outros perfis e
  aceite humano pendentes.


### Atualização US-12 — Wiki pública (2026-11-11)

`wiki_entries` usa UI Patterns Views + `layout-grid`; `wiki-entry` continua no teaser SDC e os dados/acesso/order/pager da View permanecem. Runtime/Sync coincidem, seis IDs preservados e Firefox passou 215/215 em cinco larguras e dois modos. Zoom 200%, cache-hit, editor/admin, tecnologia assistiva e aceite visual permanecem DEFERRED.

### Atualização US-12 — categorias Wiki (2026-11-11)

O bloco `wiki_categories` da home usa UI Patterns + `layout-grid`; termos, links, filtro, acesso, ordenação e pager permanecem nativos. Runtime/Sync coincidem; 15 termos preservados e Firefox passou 247/247. Zoom 200%, cache-hit, perfis e aceite humano seguem DEFERRED.
## CSP — remoção das violações de estilo inline (2026-11-11)

- Corrigida a folha inline do HTMX, o `<style>` dinâmico UI Skins com nonce por resposta, e o atributo inline no placeholder Drupal dialog. Nenhuma fonte global `unsafe-inline` foi adicionada.
- Playwright encontrou zero eventos `style-src` nas rotas válidas `/`, `/galleries`, `/transparencia`, `/categorias` do Portal e nas homes válidas de Eventos, Loja e Conta. O componente admin anônimo retornou 403; login do UID de teste não existe no Runtime e a verificação autenticada fica DEFERRED.
- Stylelint focal, foundations 83 pares, PHPUnit focal 27/27, PHP lint, cache rebuild e diff check passaram.


## Homologação da inversão branco/verde - lacuna identificada (2026-11-12)

O canvas branco e os três SDCs `surface*` foram publicados; uma auditoria
posterior confirmou que os 22 displays do Layout Builder usam
`layout-section`, ainda sem o tom verde. Incluída variante `green-soft`
opcional nesse SDC, story e UI Styles, preservando seções transparentes
existentes. A homologação de navegador, a seleção editorial por seção e
o aceite visual humano seguem pendentes. O relatório fica em
`docs/architecture/ACULTA420-V2-HOMOLOGACAO-SUPERFICIES-2026-11-12.md`.
