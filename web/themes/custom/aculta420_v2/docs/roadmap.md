# Roadmap do fórum Growroom420 (ACULTA420 V2)

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
  ativos no Runtime. Links resolve Ruderalis e os outros domínios testados
  resolvem o padrão Skunk; A/B/A anônimo não mostrou reaproveitamento entre
  hosts. Contraste e aceite visual dos dez domínios permanecem pendentes.

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

