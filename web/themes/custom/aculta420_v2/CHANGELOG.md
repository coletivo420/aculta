## Não versionado — auditoria, PurgeCSS e limpeza de CSS legado (2026-11-12)

- Infraestrutura de auditoria em `scripts/css-audit/`: inventário reproduzível, coletor de classes dinâmicas (220 classes em 86 componentes, incluindo `aculta-surface--tone-*`, `aculta-surface-panel--elevation-*`, `aculta-icon--*`, `data-table--*` e variantes do `auth-dialog`), extratores por tipo de fonte e relatório de classes sem referência textual.
- PurgeCSS 9.1.0 fixado no `package-lock.json` com `postcss` 8.5.32. Scripts: `css:audit` (somente relatório), `css:purge` (staging, sem tocar na fonte) e `css:verify` (gate de integridade). Nenhum CSS gerado entra no runtime.
- Removido `src/styles/studio-identity.css`: o V2 nunca aplica `html.studio-identity`, portanto a camada inteira era inalcançável. `studio` passa a carregar somente `studio.css`.
- `studio.css`: removidas 642 linhas presas a `.aculta-main` (o shell V2 renderiza `.page__main`) e regras de classes sem consumidor (`.aculta-card*`, `.aculta-context-header*`, `.studio__tab*`, `.aculta-empty-state`, `.aculta-container`, `alert-*`). 80.133 → 56.741 bytes (−23.392).
- Regras mortas removidas de `content.css`, `utilities.css`, `forum.css`, `wiki.css`, `portal-account.css`, `messenger-drawer.css`, `component-library-mode.css`, `multidomain-bar.css`, `portal-ui-map.css` e `portal-ui-extras.css`. Inclui os diálogos de Fórum/Revista que não têm mais renderização própria (`.aculta-forum-topic__visitor`, `__replies-count`, `__category-pill`, list-row e `aculta-forum-new-topic-btn`).
- Tokens: `donation-account-invite` trocou `alert alert-info` (Bootstrap) por `messages messages--info`; `we-sativa__workspace` saiu dos três tokens `we-sativa-*` por não ter CSS nem consumidor. `messages--info` ganhou estilo semântico no mapa.
- Infraestrutura endurecida em 2026-11-12: `js-yaml@4.3.2` substituiu regex na leitura de metadados SDC e bibliotecas; `css:verify` exige relatório com `sourceHash` atual e falha se estiver ausente, incompleto ou desatualizado; `--write` exige `--confirm-write`, cria backup e recusa CSS inválido; `css:test` cobre classes dinâmicas, safelist e hash.
- Totais do tema: 107 → 106 arquivos CSS e 496.431 → 452.414 bytes-fonte (−44.017, −8,9%); regras 2.691 → 2.509; seletores 3.396 → 3.115; `:has()` 178 → 73; `!important` 17 → 14.
- Gates: `css:verify` PASS, Stylelint PASS, `validate-aculta420-mapped-classes.php` PASS (480 classes), `validate-aculta420-css-scope.php` PASS (50 checks). Captura visual e navegador: **DEFERRED**.
- Documento da auditoria: `docs/architecture/ACULTA420-V2-AUDITORIA-LIMPEZA-CSS-2026-11-12.md`.

## Não versionado — piloto de realocação de CSS: aculta_events (2026-11-12)

- 9 regras de `portal-ui-map.css` (14 classes exclusivas de `aculta_events`: Locais, widgets de Local e Organização, painel administrativo) saíram do global e foram para a biblioteca `aculta_events/events-portal-ui`, anexada por `#attached` nos pontos que as renderizam.
- Ficaram no tema as regras de seletores compartilhados e as classes sem dono exclusivo. Excluídos do piloto: `sponsor-name` e `sponsor-cota` (presenter só usado em teste) e `aculta-map__location-route` (também definido em `aculta_maps`).
- Gate `validate-aculta420-mapped-classes.php` passou a varrer também o CSS dos módulos em `aculta_portal/modules/*/css`. PASS, 482 classes, 0 exceções.
- Estado: `validate-aculta420-css-scope.php` PASS (49 checks). Captura visual 320/1440 px claro e escuro: DEFERRED (sem execução de browser nesta sessão). Kernel de `aculta_events`: DEFERRED (requer banco de teste).

## Não versionado — títulos de cards e dívida de gates (2026-11-12)

- `forum-topic-card`, `revista-teaser` (teaser da Revista), `wiki-entry`
  (teaser), `forum-category` e o cabeçalho da listagem do Fórum renderizam
  seus títulos pelo SDC `heading` (H5 para cards, H6 no cartão de tópico,
  nível conforme `title_level` no Fórum). O CSS de tipografia próprio desses
  títulos foi removido; links herdam a cor do título e o sublinhado no
  hover/foco.
- Templates de View exigidos pelo gate de fundação: criados
  `views-view-fields--aculta-forum-categories--page-categories.html.twig`
  (fallback por campos) e
  `views-view-unformatted--aculta-forum-categories--page-categories.html.twig`
  (grupo com H2 pelo SDC).
- Dívida de Stylelint zerada no tema: ordem de propriedades, quebras e linha
  vazia em `breadcrumb.css` e `utilities.css`.
- Dívida de fundações zerada: literal de cor em comentário de `content.css`
  trocado por tokens; `#fff` de fallback removido do breadcrumb; durações
  `200ms` e fallbacks `180ms` trocados por `--motion-slow` e
  `--motion-base`.
- Gates: Stylelint do tema PASS (0 erros), design foundations PASS (1381
  checks), foundation PASS (495 checks), SDC schemas PASS (86 componentes),
  V2 SDC PASS, UI Styles PASS, CSS scope PASS. Browser 56/56 (42 de títulos
  + 14 de axe).

## Não versionado — sistema de títulos ACULTA420 V2 (2026-11-12)

- Novo SDC `heading` (H1–H6) com escala Oswald da referência "Tipografia do
  Portal ACULTA420 V2", faixa tricolor em H1/H2, barra lateral colorida em
  H3–H6, superfícies semânticas (`canvas`, `raised`, `institution`,
  `highlight`) e nove stories. Schema estrito, sem JavaScript.
- Tokens `--heading-h1-*` a `--heading-h6-*` e `--heading-space-*` com
  `clamp()`: mínimo mobile em 320/390 px e máximo desktop em 1280/1920 px.
- O título de rota (`templates/page-title.html.twig`) passa a ser o H1 do
  SDC; Revista, Wiki, Eventos, Fórum, Loja e Cursos mantêm um único H1.
  Na listagem do Fórum, o `forum-category-header` renderiza o H1 e o título
  da rota deixa de ser duplicado; `revista-listing`, `wiki-entry`,
  `account-shell` e `social-links-page` também usam o SDC.
- Novos UI Styles `heading_accent` e `heading_surface`, aplicáveis a blocos
  de título. CSS legado desses títulos foi removido.
- Gates: SDC schemas PASS, V2 SDC PASS (43 componentes), foundations PASS,
  UI Styles PASS, Stylelint PASS nos arquivos alterados. Browser Firefox
  42/42 (7 áreas × 3 viewports × claro/escuro) e axe 14/14 sem bloqueio;
  manifesto em `tests/browser/capturas/titulos/2026-11-12/manifest.json`.
- O gate `validate-aculta420-v2-sdc.php` teve a amostra do `breadcrumb`
  atualizada para o contrato `trail`/`current` (estava desatualizada e
  interrompia a suíte antes dos demais componentes).

## Não versionado — cascata, tokens e layouts da auditoria (2026-11-11)

- `layout.css` volta a ser base do shell: as regras exclusivas da UI Patterns
  Library foram para `component-library-mode.css` (carregada em toda rota da
  biblioteca) e o container do `site_key` account para `portal-account.css`.
  `ui-library-admin.css` deixa de repetir a moldura de `.ui_patterns_component`.
- Removida a declaração duplicada de `--button-primary-bg-hover`; o token
  `--aculta-surface-raised` segue como alias por `var()` nos dois modos.
- `layout-page--stack` adota o container estreito configurável
  (`--aculta-narrow-container-width`) e deixa a medida de leitura (68–70 ch)
  para o conteúdo textual, alinhado ao fallback `.page__main--stack`.
- Novas stories `aside-no-title`, `stack-no-title` e `full-no-title` cobrem
  containers sem título e sem barra lateral.
- Stylelint passou a zero erros nos CSS do tema; o gate de escopo foi
  atualizado ao contrato `site_key` (RN-00..RN-05).
- Homologação Playwright/Firefox: 774/774 checks PASS em 10 domínios públicos
  (incluindo Fórum em `growroom420`), 7 larguras e claro/escuro, mais Conta
  autenticada e UI Library. Manifest em
  `tests/browser/capturas/aculta420-v2-audit/2026-11-11/manifest.json`.

## Não versionado - auditoria de consistência CSS e layouts (2026-11-11)

- Corrigido o contraste do cabeçalho institucional no SDC `surface-panel`
  nos dois modos. O validador de fundações passa a cobrir explicitamente
  texto sobre superfície do painel.
- `layout-page--aside` e o fallback `page__main--with-sidebar` exibem
  a barra lateral à esquerda no desktop, sem inverter a ordem do DOM.
  `page.html.twig` e `page--shell-v2.html.twig` adotam variantes no fallback.
- Shell v2 sem área fantasma de navegação e comentários alinhados às cinco
  regiões. Alias `--aculta-surface-raised` unificado com o token semântico.
- Alterações verificadas estaticamente; Stylelint, PHPUnit/Drush e browser
  ainda dependem de homologação. Referência:
  `docs/architecture/ACULTA420-V2-AUDITORIA-CSS-LAYOUT-2026-11-11.md`.

## Não versionado — matriz UI Skins e catálogo administrativo (2026-11-11)

- Os dez domínios passaram 300/300 checks em cinco larguras e claro/escuro; Links resolve `ruderalis`, os outros nove `skunk`, H1/main únicos, sem overflow e contraste mínimo 7,16:1.
- Catálogo UI Patterns autenticado carregou 84 componentes/16 grupos e uma story sob demanda. A medição de overflow foi limitada ao conteúdo do catálogo; o drawer Claro fica registrado separadamente.
- Zoom 200%, teclado, perfis, cache-hit e aceite visual humano permanecem pendentes.

## Não versionado — UI Patterns style no catálogo público de Eventos (2026-11-11)

- Envolve as rows nativas da View `eventos_publicos:block_1` com o SDC
  `layout-grid`, mantendo o plugin `aculta_events_catalogue_card`, ids com
  acesso validado e ordenação do catálogo.
- Runtime anônimo manteve HTTP 200 e dois resultados antes/depois. Firefox ESR
  confirmou grid, dois `card-cultural`, foco visível e sem overflow a 320/1440
  px claro/escuro; zoom, cache-hit e revisão visual seguem pendentes.

## Não versionado — UI Styles Block na transparência (2026-11-11)

- A instância `aculta420_v2_aculta_documents`, visível em `/transparencia` no
  MAIN, recebeu `surface_style` raised e `spacing_section` normal pelo UI Styles.
- Runtime anônimo preservou três rows. Firefox ESR passou em cinco larguras e
  dois modos, com foco visível, H1/main únicos e zero overflow. Zoom 200%,
  cache-hit e revisão visual humana permanecem pendentes.

## Não versionado — UI Styles Entity Status (2026-11-11)

- Habilita Entity Status no Sync/Runtime e aplica `unpublished_preview` como
  sinal visual de conteúdo não publicado com token semântico de aviso.
- A configuração não altera acesso/moderação. Teste funcional upstream 1/7
  passou; saída visual real, revisão de perfis e cache permanecem pendentes.

## Não versionado — Inventário Views da Revista (2026-11-11)

- Classifica os seis displays de página de `revista_sessoes` como `UI_SUITE_NATIVE`: a row nativa `entity:node`/`teaser` é composta pelo SDC `revista-teaser` através do adapter Twig existente, sem alterar dados ou configuração Views.
- Runtime, acesso, pager/cache e navegador para estas rotas seguem pendentes.

## Não versionado — View de categorias do Fórum na UI Suite (2026-11-11)

- `/categorias` agora compõe o display pelo SDC `views-display`, mantendo o row
  `forum-category`, agrupamento nativo, acesso e pager da View.
- A comparação Runtime antes/depois preservou HTTP 200 e 12 resultados. Firefox
  ESR: cinco larguras claras, duas escuras, um H1/main, sem overflow, foco visível
  e alvos mínimos de 44 px. Um título duplicado foi removido antes da captura.
- `block_landing` continua com UI Patterns row, mas seu placement está oculto;
  não há aceite visual desse bloco. Zoom 200% e cache-hit ainda pendem.

## Não versionado — Views row/style/display com UI Patterns (2026-11-10)

- A View `aculta_albuns:page_1` agora usa o SDC `revista-album-card` por row,
  `layout-grid` no style e `views-display` no display, reutilizando seus field
  handlers e sources nativos.
- Import parcial no Runtime aplicou somente a View; HTTP 200 e três resultados
  antes/depois, acesso anônimo preservado. Firefox ESR 1280/320 em claro/escuro
  confirmou três cartões, sem overflow nem alvo abaixo de 44 px.
- Pager multi-página, cache-hit, teclado e zoom 200% permanecem DEFERRED.

## Não versionado — ComponentLayout SDC nos 22 displays elegíveis (2026-11-10)

- Substitui `layout_onecol` pelo SDC `layout-section` via UI Patterns Layouts
  nos 20 displays restantes; 2 displays de página já estavam migrados.
- Importação parcial aplicada após snapshot do Runtime. Preserva seções,
  componentes, UUIDs, `allow_custom`, campos e dados; as duas exclusões
  documentadas permanecem fora do lote.
- Gate Layout Builder aprovado com 276 checks: configuração Runtime/Sync,
  saídas ComponentLayout e adapter SDC Wiki, render arrays e acesso.
- O runner Firefox falha se a rota não apresentar título, `main` e corpo;
  o provider V2 exibiu 15 grupos/80 componentes e carregou uma story real. A
  listagem ultrapassa a viewport a 320 px (DEFERRED); a rota Wiki retornou 403
  e segue sem captura visual aceita.

## Não versionado — Layout Builder usa ComponentLayout SDC em páginas (2026-11-10)

- `node.page.default` e `node.page.full` usam `layout-section` pelo UI Patterns
  Layouts; body e links mantêm região, ordem, acesso e dados. Import parcial
  aplicado somente a esses displays, sem alterar conteúdo.
- O snapshot Runtime registra UI Skins e o mapa Links ativos. Firefox anônimo
  conferiu Links → MAIN → Links: skins/tokens não se misturam, H1/main únicos,
  HTTP 200; Links 1280/320 sem overflow. Cache dinâmico está `UNCACHEABLE`,
  portanto cache-hit e aceite visual completo continuam pendentes.
- O inventário agora distingue ComponentLayout UI Suite de layouts Core ainda
  em migração. Gate SDC corrigido para reconhecer tokens `.btn` exatos e não
  strings/comentários como SVG/jQuery; 41 componentes passaram.

## Não versionado — status do carrinho no SDC compartilhado (2026-11-10)

- O adapter visual do Portal compõe `status-messages`, converte `success` para
  `status` e preserva `alert`, link e o contrato de dispensa usado pelo JS.
- Acrescenta props estritas, story acessível e controle com alvo mínimo de 44px.
- Render Runtime, PHPUnit focal, schema SDC e Stylelint focal passaram.

## Não versionado — SDCs nos adapters editoriais e de pagamento (2026-11-10)

- O adapter `aculta_section` compõe `layout-section`, mantendo variante, cor,
  títulos e corpo fornecidos pelo Portal.
- O aviso de pagamento usa `surface`; texto, URL, marcador e abertura segura em
  nova aba continuam sob contrato do Portal.

## Não versionado — SDC `card-cultural` no catálogo público (2026-11-10)

- Reutiliza `card-cultural` no template adapter do catálogo público de Eventos.
- Acrescenta props estritas para estado cancelado e texto de status acessível;
  stories editoriais cobrem essa combinação.
- A renderização real no Runtime e visual responsivo aguardam cutover autorizado.

## Não versionado — inventário de consumidores Twig da View (2026-11-10)

- O gerador audita individualmente os templates Twig presentes;
  classifica os adaptadores com inclusão direta de SDC como nativos e mantém os
  demais como migração em andamento para mapeamento e revisão funcional.
- Os field/unformatted overrides de categorias têm owner, display, SDC
  substituto e critério de retirada pós-cutover.
- UI Patterns CKEditor 5 consta como obsoleto sem fallback; estado Enabled é
  apenas fotografia de 2026-11-10, e uninstall Runtime fica DEFERRED.

## Não versionado — superfícies UI Styles e integração editorial (2026-11-10)

- Corrige opções de superfície/espaçamento para classes reais do SDC `surface`.
- UI Styles CKEditor 5 usa esses grupos no formato `revista_editorial`; CSS do
  SDC já está no `global-styling`. Runtime/browser dependem de cutover autorizado.

## Não versionado — piloto UI Patterns Views Fórum (2026-11-10)

- `forum-category` aceita nível semântico `h2`/`h3` e recebe títulos linkados,
  pai, descrição e contagem por sources do UI Patterns Views nos displays de
  categorias. Sync e defaults do Fórum estão alinhados; render isolado Drupal
  passou. Runtime/browser aguardam cutover autorizado.

## Não versionado — corte do motor visual para UI Skins (2026-11-10)

- Remove do tema `aculta_purpose_palette`, schema, formulário administrativo, preview JS/CSS, `PurposePaletteSchemes`, seletores `data-purpose` e tokens CSS por purpose.
- Declara cinco variáveis CSS no plugin oficial UI Skins e scopes `data-skin` × `data-theme` para dez skins nos modos claro/escuro.
- Tema aplica `data-skin` e `data-site-key` entregues pelo Portal; a opção de skin é resolvida por Domain ID, sem mutação de configuração global.
- UI Skins 1.2.1 foi instalado no Runtime após a captura inicial; plugins foram
  descobertos, mas mapa/scopes não foram importados. Render real, cache,
  contraste/capturas e aceite visual seguem pendentes. ADRs continuam abertas.
- Sincroniza `ui_styles_page` no `core.extension.yml`, refletindo sua ativação já existente no Runtime; estilos regionais e captura autenticada permanecem pendentes.

## Não versionado — cutover de configuração UI Suite e rollout Links (2026-11-10)

- Migra settings de flood e confirmação de e-mail para os módulos proprietários,
  habilita UI Styles CKEditor 5 e integra `forum-category` na View de categorias.
- Import parcial aplicado após snapshot e autorizações para `updatedb`/`cim`;
  valores migrados foram verificados e as chaves antigas não existem mais.
- Mapa UI Skins ativo apenas para Links → Ruderalis; os demais domínios usam o
  default Skunk até comparação visual do piloto. Gates UI Skins (134), painel do
  tema (16), foundations (83 contrastes) e Portal (1.243) PASS.
- Aceite no navegador, CSS calculado e isolamento de cache DEFERRED: sessão X11
  não estava disponível. Gate de comércio falha no rótulo público do login.

## Não versionado — dependências reproduzíveis da UI Suite (2026-11-10)

- Adiciona `drupal/ui_skins:^1.2` e `drupal/ui_icons:^2.1` como dependências diretas do Composer após `composer install` limpo demonstrar que ambos não estavam no lock.
- Trava `drupal/ui_patterns_field_group:2.0.x-dev@dev` em `4403e7b0e27679f47113e96fcdc5349c95e164d1`; atualização foi avaliada como `BLOCKED_UPSTREAM` para Drupal 12 e risco de segurança, sem ativação/configuração nova.
- O inventário registra 298 itens (159 View displays, 22 displays Layout Builder, 80 SDCs e 36 integrações), mais snapshot de 36 módulos ativos/desativados e drift Config Sync.
- Runtime atualizado por `composer install --no-scripts`; `drush cr` PASS. UI Skins estava Disabled nesta captura histórica; foi instalado depois, sem importar mapa/scopes. UI Patterns CKEditor 5 e Field Group estavam Enabled na captura; nenhuma configuração foi importada ou desinstalada.

## Não versionado — correção da UI Patterns Library (2026-11-11)

- Registra a biblioteca administrativa que o hook da overview já anexava; abas
  agora filtram cards pelo grupo correto usando atributos no DOM, e busca inclui
  o nome do grupo. Prévia em lote permanece com duas requisições concorrentes.
- CSS oferece duas colunas a partir de 60rem de largura útil e uma coluna em
  telas menores. O gate `validate-aculta-ui-library.php` passou (25 checks);
  browser autenticado carregou 84 componentes e uma story. A rodada do lote via
  WebDriver terminou sem saída conclusiva. CSP permanece report-only; nenhuma
  permissão `unsafe-inline` foi acrescentada.

## 1.5.0 — marcos do shell e Layout Builder no desenvolvimento (2026-11-09)

> Esta entrega não encerra ADR-054/055; a homologação integral continua pendente.

- Tema com as cinco regiões finais (`institution`, `header`, `content`, `sidebar_first`, `footer`); `navigation`, `highlighted`, `help` e `breadcrumb` removidas do `info.yml`.
- Shell v2 é o padrão (`aculta_shell_variant: v2`), com ajuda e breadcrumb contextuais; o shell clássico permanece como rollback até o aceite final.
- Layout Builder em 22 dos 24 displays compatíveis, com `allow_custom` apenas em `page.full`; landing Links publicada no LB.
- Biblioteca UI com prévias reais administrativas e `max-age 0`.
- CSS contextual por rota: `global-styling` em 107 KB (−60%). `statistics`, `studio`, `portal-account`, `public-profile`, `events`, `event-organization`, `wiki` e `forum` saem do global.
- Motor de container removido: `aculta_purpose_container`, schema, `PurposeContainerLayout`, `data-purpose-layout`, `data-purpose-style` e CSS legado. `Purposes` e `ComponentLibraryRoutes` preservam as responsabilidades restantes.
- Fundações de design voltaram a PASS (1.110 checks), com literais substituídos por tokens e durações centralizadas.
- Validado com gates do tema, fundação (454), Portal Drupal 12 (1.205), Playwright 86/86 e landing 35/35. Dívida restante: boundary módulo/tema (64 itens pré-existentes).

## Não versionado — ADR-054 P8 gates e tokens (2026-11-09)

- `validate-aculta420-design-foundations.php`: PASS (1112 checks). Analisador reconhece listas de seletor e blocos base com tokens `--color-*`; literais de seis componentes substituídos por tokens (cores de marca do Google centralizadas em `tokens.css`).
- `validate-aculta420-mapped-classes.php`: PASS (462 classes), com CSS semântico para as reações do perfil público.
- `validate-aculta-portal-drupal12.php`: PASS (1205 checks), com o contrato neutro `aculta_learning_contracts` e injeção de `class_resolver` na barra institucional.
- CSS da conta e da Wiki passou a ser anexado pelo purpose neutro (`account`, `wiki`), removendo nomes de rotas do Portal do hook do tema.
- `validate-module-theme-boundary.php` segue FAIL por dívida pré-existente dos módulos do Portal (64 itens).

## Não versionado — ADR-054 P5 landing Links publicada no dev (2026-11-09)

- Nó 635 "Links da ACULTA" publicado no host Links, alias `/redes-sociais`, com duas seções SDC no Layout Builder: apresentação + links sociais; apoio + documentos institucionais + menu institucional com URLs absolutas para o MAIN.
- `node.page.full` com `allow_custom: true` e override por nó comprovado.
- Playwright: 35 checks em 320/390/768/1024/1440, claro/escuro, shells clássico e v2, 0 falhas (1 H1, 1 `<main>`, sem overflow).
- Conteúdo editorial vive no banco do desenvolvimento, não no Git; revisão de redação pendente.

## Não versionado — ADR-054 P5 mecanismo de landing por nó (2026-11-09)

- `node.page.full` passa a ter `allow_custom: true` (exceção para landing pages), permitindo composição própria por nó no Layout Builder.
- Override por nó testado no nó 6 (`/institucional`): seção `onecol` com o corpo renderizou o texto e o layout; o override foi removido em seguida. O nó temporário de teste foi excluído.
- `LinksLandingController` continua no view mode `full`; quando o responsável criar o nó de landing Links, a composição própria já funciona. Gate do lote: 170 checks.

## Não versionado — ADR-054 P7 CSS multidomínio por rota (2026-11-09)

- `portal-account`, `public-profile`, `event-organization`, `events`, `wiki` e `forum` saíram do `global-styling` e passaram a ser bibliotecas contextuais por rota/purpose.
- `public-profile.css` dividido: `.portal-article-author*` em `article-authors.css` global; o restante só na rota do perfil público.
- `global-styling` local: 268.036 → 107.609 bytes (−60%). Gate `validate-aculta420-css-scope.php`: PASS (43 checks).
- Evidência por CSS servido em home, Eventos, Wiki, Fórum, `/conta` autenticado e perfil público; dez frentes com 200/1 H1/1 `<main>`; Playwright 86/86.

## Não versionado — ADR-054 P4 cobertura final de Layout Builder (2026-11-09)

- Revalidação por multiconjunto de tokens: `wiki_entry.default`, `editorial_highlight.default`, `revista.default` e `aculta_notice.default` não alteram a renderização; os quatro foram mantidos com Layout Builder e `allow_custom=false`.
- Cobertura: **22 dos 24 displays compatíveis**; gate `validate-aculta420-layout-builder-batch.php` PASS (170 checks).
- Excluídos por perda de conteúdo reproduzível: `node.document.full` e `block_content.basic.default`, restaurados do Config Sync.

## Não versionado — ADR-054 P4 lote 3 de Layout Builder (2026-11-09)

- Layout Builder habilitado em `node.activity.default/full`, `node.aculta_adm.default`, `node.document.default`, `node.page.default` e `taxonomy_term.editorial_author.default`.
- Revertidos por alterarem a listagem da Wiki: `aculta_notice.default`, `editorial_highlight.default`, `revista.default` e `wiki_entry.default`.
- Gate do lote: PASS (138 checks, 18 displays). Cobertura de 18 dos 24 displays compatíveis; os 6 restantes têm causa documentada e backup.

## Não versionado — ADR-054 P4 lote 2 de Layout Builder (2026-11-09)

- Layout Builder habilitado em oito displays de `block_content` (`aculta_editorial.default` e `aculta_institution.contact/default/footer/full/home/institutional/registration`), com `allow_custom: false`. Ativação um a um, comparando o texto visível da home e de `/institucional`.
- `block_content.basic.default` alterou a home e foi restaurado do Config Sync, permanecendo sem Layout Builder.
- Gate `validate-aculta420-layout-builder-batch.php` ampliado: PASS (96 checks, 12 displays).
- Drift antigo exposto: `main_menu`, `page_title`, `site_branding`, `help` e `breadcrumbs` estavam no Config Sync e ausentes da config ativa. Restaurados do Sync; a home voltou a ter 1 H1 com menu e marca.

## Não versionado — ADR-054 P4 lote inicial de Layout Builder (2026-11-09)

- Layout Builder habilitado com `allow_custom: false` em `node.page.full`, `node.revista.full`, `node.wiki_entry.full` e `node.aculta_faq.default`, com backup em `$SUITE_OUT/aculta-lb-20261109/` e autorização do responsável.
- As seções padrão preservam os campos; a renderização HTTP permaneceu equivalente. Config ativa e Config Sync conferem; gate `validate-aculta420-layout-builder-batch.php` (34 checks).
- `node.document.full` excluído: os plugins `field_block:node:document:*` não foram encontrados e o display foi restaurado integralmente do Config Sync.
- `wiki-entry` ganhou `title_level` (`h2` padrão), removendo o H1 duplicado nos verbetes (o H1 pertence ao título da rota).

## Não versionado — ADR-054 P5 piloto Links com layout SDC (2026-11-09)

- O tema sobrescreve `aculta-links-landing.html.twig` e compõe a landing Links/LinkWeed com `aculta420_v2:layout-section`; dados, acesso e cache permanecem no `aculta_linkweed`.
- Verificado com HTTP real no host Links nos shells clássico e v2: 200, `.layout-section`, 1 H1 e 1 `<main>`; o controller não foi alterado.

## Não versionado — ADR-054 P7 CSS do Studio contextual (2026-11-09)

- `studio.css` (80 KB) e `studio-identity.css` saíram do `global-styling` e formam a biblioteca `studio`, anexada apenas nas rotas `aculta_studio.*`.
- Regras do Studio na biblioteca de componentes saíram de `layout.css` para `component-library-mode.css`.
- `global-styling` local caiu de 268.036 para 180.160 bytes (−88 KB, −33%).
- Verificação Playwright/Firefox autenticada: `/conta` carrega o CSS do Studio; a home não. Suíte ampliada: 86 checks, 0 falhas (anônimo e autenticado, cinco larguras, escuro, foco, sem overflow do tema).
- Nota: a barra Gin tem 16 px de overflow a 320 px, fora do tema.

## Não versionado — ADR-054 P7 escopo de CSS (2026-11-09)

- `src/styles/statistics.css` saiu do `global-styling` e virou a biblioteca contextual `statistics`, anexada apenas em `aculta_statistics.dashboard` e `aculta_statistics.area`.
- Os seletores `.aculta-statistics__controls`, `__wiki-ranking` e `__wiki-revisions` saíram de `portal-ui-map.css` e foram para `statistics.css`.
- Verificação por requisição real na home do MAIN: nenhum CSS servido contém `.aculta-statistics`. Redução de ~2,7 KB por página.
- Gate novo `validate-aculta420-css-scope.php`: PASS (8 checks).

## Não versionado — ADR-054 P6 prévias reais de blocos (2026-11-09)

- Novo módulo do Portal `aculta_ui_library`: catálogo dos blocos reais do `aculta420_v2` e prévia por plugin original, com `administer blocks`, `_aculta_domain_purpose: main`, `_admin_route`, `max-age 0` e contexto `user.roles`.
- Visitante no MAIN recebe 403; subdomínios respondem 404. Contexto obrigatório ausente e falha do plugin viram "Prévia indisponível", sem conteúdo fictício.
- Gate `validate-aculta-ui-library.php`: PASS (23 checks) — rotas MAIN, acesso por perfil, catálogo com 37 blocos e prévia real de `site_branding`.
- A sugestão `page__shell_v2` foi movida de `src/Hook` para `aculta420_v2.theme`, conforme a fronteira módulo/tema; smoke do shell v2 repetido após a mudança.
- **RUNTIME STATUS: PARCIAL** — prévia validada via Drush e HTTP anônimo; navegador autenticado pendente.

## Não versionado — ADR-054 P4 layouts SDC e descoberta pelo Layout Builder (2026-11-09)

- Cinco layouts SDC novos em `group: Layouts`: `layout-section`, `layout-two-columns`, `layout-grid`, `layout-narrow` e `layout-full`, com schema estrito, CSS local e story.
- `ui_patterns_layouts` 2.0.23 descobre automaticamente os seis layouts do tema (incluindo `layout-page`) como plugins do Layout Builder, com categoria `Layouts` e regiões iguais aos slots. Gate `validate-aculta420-layouts.php` verifica descoberta e renderização real (42 checks).
- Planejador `scripts/audit-aculta420-layout-builder.php` classifica os 95 displays: 24 compatíveis, 48 com adaptador, 23 incompatíveis. Nenhuma configuração foi alterada.
- Habilitação do Layout Builder por display: **DEFERRED** até importação em ambiente isolado com teste e rollback.
- **RUNTIME STATUS: PARCIAL** — layouts renderizados via Drush; sem captura visual.

## Não versionado — ADR-054 P3 shell paralelo (2026-11-09)

- Novo shell opt-in `page--shell-v2.html.twig` com as cinco áreas finais (`institution`, `header`, `content`, `sidebar_first`, `footer`). Ativado por `aculta_shell_variant = v2`; o padrão continua `classic`, com rollback pela mesma opção.
- As regiões `navigation`, `highlighted`, `help` e `breadcrumb` seguem renderizadas como ponte no shell v2, para não deixar bloco órfão antes do corte. O `info.yml` não foi alterado.
- `PagePresentationHooks::themeSuggestionsPageAlter` adiciona a sugestão; `composeShellTitle` move `page_title_block` de regiões antigas para o título do `layout-page`, preservando um único H1.
- Biblioteca `shell-v2` carrega `src/styles/shell-v2.css` somente no shell v2.
- Correção de H1 duplicado: `revista-listing` e `forum-category-header` ganharam o prop `title_level` (padrão `h2`); a landing Links/LinkWeed deixou de renderizar `<main>`/`<h1>` próprios, pois o shell já fornece ambos.
- Smoke no Runtime: 10 requisições HTTPS em `aculta`, `links`, `eventos`, `wiki420`, `revista`, `loja`, `cursos`, `apoio` e `growroom420` com HTTP 200, 1 H1 e 1 `<main>`. Rollback para `classic` testado. Capturas Firefox e demais gates visuais: pendentes.
- **RUNTIME STATUS: PARCIAL** — código e configuração do tema validados via Drush local; sem captura visual e sem homologação do piloto Links.

## Não versionado - ADR-054 e retirada da opção Layout de container (2026-11-09)

- Decisões das três rodadas consolidadas em ADR-054 e plano executivo SDC First, Layout Builder e dez purposes.
- Administração: removida a subaba `Layout de container` de `theme-settings.php`, mantendo `aculta_purpose_container` somente como compatibilidade temporária no renderizador; sem migração de dados.
- Documentação e AGENTS atualizados para jQuery 4 sob demanda em módulos e SDCs e preservação do HTMX.
- **RUNTIME STATUS: DEFERRED** - ainda não foram executados gates, `drush cr` ou capturas visuais desta alteração. Sem mudança de versão.

## Não versionado — padrões visuais de botões primário e secundário no login da barra institucional (2026-11-09)

- Diálogo de login da barra institucional (`multidomain-bar.css` e `portal-ui-extras.css`): remoção das regras legadas que sobrescreviam o visual com cantos quase retos (`--radius-sm`) e cores fixas de destaque.
- Botão primário ("Entrar", `[data-drupal-selector="edit-submit"]`): alinhado aos padrões da UI Library/SDC `button` (`--button-primary-*`, `border-radius: var(--radius-control-button)`, borda 2px, tipografia e estados `:hover`, `:active` e `:focus-visible`).
- Botão secundário ("Cadastre-se", `[data-portal-ui="button-secondary"]` / `a:last-of-type`): alinhado aos padrões da UI Library/SDC `button` (`--button-secondary-*`, borda 2px sólida, `border-radius: var(--radius-control-button)` e estados acessíveis).
- `LoginPopupForm`: marcação semântica com `data-portal-ui="button-primary"` no envio e `data-portal-ui="button-secondary"` no link de cadastro, respeitando a fronteira módulo x tema.
- Link "Esqueceu sua senha?": cor ajustada para branco sobre a superfície verde institucional do diálogo de login.
- Rótulos dos campos ("Nome de usuário, e-mail ou CPF" e "Senha"): cor ajustada para branco sobre o fundo do diálogo.
- Divisores horizontais (`data-portal-ui="login-popup-divider"`): comprimento reduzido e cor ajustada para linhas brancas semitransparentes centralizadas.
- Suporte a `prefers-reduced-motion: reduce` nas transições dos botões do diálogo de login.

## Não versionado — mapa com altura responsiva (2026-11-07)

- `.aculta-map`: 18rem/24rem/30rem (mobile/768/1024), borda e superfície por tokens; atribuição Leaflet legível e foco visível em controles e marcadores. Antes, `min-block-size: 12rem` do tema reduzia o mapa a 192 px.

## Não versionado — fórum Growroom420 (entregas 1 e 2 do plano visual)

- Lista pública (`/discussoes` e variantes): cabeçalho Growroom420 com botão "+ Novo tópico", abas ligadas às rotas existentes, cabeçalho de colunas e linhas em `forum-topic-row` com dados reais. Painel lateral com regras em `forum-sidebar-panels`. Zero respostas aparece como 0.
- Página de tópico: breadcrumb e pílula de categoria no cabeçalho.
- `forum-category-header` aceita a ação principal (prop `actions`).
- Validadores apontam para `aculta420_v2`; componentes ganham `status`; `validate-aculta420-sdc-schemas.php` PASS.
- Pendente (DEFERRED): captura visual Firefox ESR em 320/390/1440 px (sem `FORUM_BASE_URL` nem `SUITE_OUT` nesta sessão); painéis de destaque, moderadores e relacionados com placeholder até o contrato com o Portal; `validate-aculta420-design-foundations.php` ainda exige a família `--aculta-*`; `validate-module-theme-boundary.php` acusa 21 falhas em módulos do Portal.

## Não versionado — tokens de cor e movimento, reset de links e gate de fundação V2

- Cores literais fora de `src/styles/tokens.css` viram tokens semânticos: 27 ocorrências em `forum.css`, `theme-settings.css`, `lms-tour.css`, `studio-calendar.css` e `forum-tabs.css`. Opacidades `rgba()` passam a `color-mix()` sobre tokens existentes.
- Fallbacks `var(--token, #literal)` viram `var(--token)`. Os tokens `--button-primary-bg-hover` e `--color-surface-subtle`, usados sem definição, passam a existir em `tokens.css` com o valor que já era aplicado.
- Durações fixas viram tokens `--motion-instant`, `--motion-fast` (150 ms), `--motion-base` (180 ms) e `--motion-slow` (200 ms). Aproximação registrada: a transição de 160 ms do diálogo de login vai para 180 ms.
- A reset global de `a` e `a:hover` passa para `.page a` e `.page a:hover`, para não vazar para fora do contêiner da página.
- O CSS do widget Turnstile (`.captcha-type-challenge--turnstile`) sai do tema e vira a biblioteca `aculta_portal/captcha-presentation`, anexada pelo `CaptchaPresentationHooks`. O tema não cita mais CAPTCHA.
- `MailEnvelopeHooks` e o template `aculta-mailer-wrap.html.twig` migram da V1 para o V2: cores dos tokens do próprio tema e logo do asset do V2.
- Correção de lint: `color-mix(in srgb, ...)` vira `sRGB`, conforme `value-keyword-case`. `npm run lint:css` PASS.
- Gate `scripts/validate-aculta420-foundation.php` reescrito para o V2 (sem Bootstrap, sem allowlist da V1, com tokens `--color-*` e blocos `aculta420_v2_*`). Última execução: 410 checks PASS, com Runtime sem V1 e sem Bootstrap.
- Pendente: captura visual em 320, 390 e 1440 px, claro e escuro. Estado: DEFERRED.

## 1.4.0 — Redesenho Visual Fórum Growroom420 (Entrega 2)

- Conclusão do redesenho visual do Fórum Growroom420 no tema ACULTA420 V2 (versão MINOR conforme ADR-046).
- Componentes SDC complementares: `forum-sidebar-panels` e `forum-composer-guidelines`, com schemas estritos, stories funcionais e documentação completa.
- Adapters de criação de tópico (`/criar-topico`) com diretrizes e normas em duas colunas no desktop e uma coluna no mobile, além de botões e card com faixa editorial vermelha.
- Adição da exibição da View `/categorias/{termo}` (`page_category_term` em `config/sync/views.view.aculta_forum_public_topics.yml`), permitindo a navegação contextual por categoria integrada aos novos cabeçalhos e painéis laterais.
- Gates de lint CSS, schemas SDC, design foundations e mapeamento de classes verificados com aprovação.

## Não versionado — Redesenho Visual Fórum Growroom420 (Entrega 1)

- Novos componentes SDC com schema estrito: `forum-category-header`, `forum-tabs`, `forum-topic-row`, `forum-topic-card` e `forum-post-card`. Cada componente inclui `<nome>.component.yml`, `<nome>.twig`, `<nome>.css`, `<nome>.default.story.yml` e `README.md`.
- Apresentação visual alinhada às referências de design do Growroom420 (`referencia-1.webp` a `referencia-8.webp`) e padrões Discourse (pílulas de status, miniatura em cartões, prévia de mensagem e contadores).
- Tratamento explícito de dados não fornecidos pelo Drupal como placeholders acessíveis ("—" e `<span class="visually-hidden">Dado indisponível</span>`) e botões/ações desabilitados com rótulo "Em breve". Sem inventar dados em páginas reais.
- Adapters de templates `node--aculta-forum-topic.html.twig`, `comment--aculta-discussion-post.html.twig` e estilos de listagem e categorias atualizados em conformidade com as regras mobile-first (320 px a 1440 px).
- Gates CSS e SDC validados com sucesso (`npm run lint:css` e `validate-aculta420-sdc-schemas.php`).

## Não versionado — seleção administrativa de accent da Revista

- `aculta_magazine` armazena a escolha em campo nativo, expõe três opções pelo widget oficial UI Styles e aplica a classe ao wrapper do teaser. O CSS do componente assegura precedência administrativa sobre o accent do Portal.
- Gate UI Styles: 24 verificações PASS; PHPUnit focal: 6 testes/33 assertions PASS (sete depreciações PHPUnit). A atualização de campo foi aplicada somente ao Drupal local e a atualização pendente de outro módulo foi preservada.
- Firefox ESR no servidor com esta versão ainda não executado; a validação visual continua pendente.

# Changelog — ACULTA420 V2

## Não versionado — galeria da UI Patterns Library

- As rotas da biblioteca saem dos containers por purpose e usam largura total. O overview apresenta cada componente em um bloco próprio, sem criar colunas para stories.
- A barra institucional permanece com a marca e omite os utilitários nesta interface administrativa, evitando a saída textual quebrada dos render arrays.
- A rota respondeu 200 e o CSS local injetado no Chromium mostrou 108 blocos verticais, container por purpose ausente, barra sem markup quebrado e sem overflow horizontal em 320/1440 px. O CSS agregado e os templates Twig novos ainda aguardam rebuild de cache no Runtime; aceite visual final pendente.
- Os previews agora usam o wrapper oficial de stories da UI Patterns; a contenção dos cartões deixa de recortar pintura de elementos posicionados.
- As prévias de Conexões e Calendário anulam, só na galeria, o posicionamento absoluto do dashboard; slots das stories de Detalhes do perfil e Navegação de suíte usam render arrays `markup` para produzir HTML no componente real, sem duplicar markup no template da galeria. Renderer Drupal: 4/4 componentes produziram a estrutura esperada.
- Stories sem preview agora têm dados; o menu do Studio usa os itens atuais do `AccountExperienceProvider`, stories PHP recebem props/slots resolvidas pelo preprocess do tema, e a barra institucional do preview flui dentro do bloco. O diálogo Selecionar local declara a biblioteca de interação e usa o destino de rota real. Renderer Drupal e dependências de JS: PASS nos quatro componentes desta rodada.
- Badge, Button e Ícone ganharam stories para renderizar seus SDCs; slots de Conexões do Studio, Rodapé, Verbete, Data Table e Form Field passaram a usar render arrays Drupal (`markup` ou `html_tag`) em vez de texto escapado. Renderer Drupal: 12/12 stories/componentes PASS, incluindo variantes de rodapé, Wiki, tabela e formulário.
- Breadcrumb, Cartão de autoria e Página de links sociais agora fornecem slots como render arrays `#markup`, para o Drupal renderizar a trilha, avatar e conteúdo de links sem escapar tags. Cabeçalho Wiki420 comprovado com busca gerada pela apresentação atual do domínio. Renderer: 3/3 SDCs mais busca Wiki PASS.

## Não versionado — conformidade Stylelint

- CSS dos SDCs e estilos globais reordenados e normalizados conforme o preset Drupal; sem mudança intencional de comportamento.
- O preset mantém regras de comunidade. Regras antigas incompatíveis com o CSS usado pela V2 foram ajustadas para preservar regras compactas existentes e aceitar `dvh`, `@container` e `@starting-style`.
- `npm run lint:css`: PASS.

## Não versionado — `profile-details` no Studio

- Adicionado o SDC `profile-details` com título acessível (`aria-labelledby`) e
  slots proprietários separados para biografia, redes e ações. O preprocess
  mapeia `profile-bio` e `profile-social` preservando seus render arrays.
- PHPUnit do hook: 7 testes/33 assertions. Gates: schema 764 checks, classes
  mapeadas, fundações e render isolado de 41 SDCs passaram.
- Firefox ESR comparou o fallback e o SDC em `/meu-perfil`: 97/97 checks em
  cada rodada, 22 ignorados, zero falhas; claro/escuro, 320/1440 px, foco e
  zero overflow. Capturas em
  `$SUITE_OUT/layouts-purpose-studio-profile-details-paired-20261106/{before,after}/`.
- Loja continua aguardando autorização documentada.

## Não versionado — Studio `profile-identity` com `author-card`

- O preprocess do tema adapta somente `profile-identity` à variante `profile_full`; nome, hint, avatar, ações e link público continuam vindos do Portal. `profile-bio` e `profile-social` seguem no fallback.
- Comparação Firefox ESR antes/depois em `/meu-perfil`: 89/89 verificações, 22 ignoradas, zero falhas por execução; claro/escuro, 320/1440 px, foco por teclado e sem overflow. Capturas em `$SUITE_OUT/layouts-purpose-studio-profile-identity-paired-20261106/{before,after}/`.
- PHPUnit focal do hook: 6 testes/23 assertions; renderização isolada: 40 SDCs PASS.

## Não versionado — validação focal do SDC `tabs` no Studio

- Comparado `/marley` antes e depois do mapeamento `context-tabs`→`tabs`:
  baseline pré-`91c1b5af` em `layouts-purpose-studio-before-esr-20261105/` e
  estado atual em `layouts-purpose-studio-context-tabs-paired-r2-20261106/`.
- Firefox ESR: 77/77 verificações, 22 ignoradas, zero falhas. DOM confirmou o
  SDC e os três links; teclado alcançou cada link com foco visível nos modos
  claro/escuro e 320/1440 px. Sem overflow da página.

## Não versionado — card-cultural na listagem pública de Eventos

- A View pública `eventos_publicos` é consumida pela raiz do purpose Eventos e
  renderiza os cartões pelo hook de tema, compondo o SDC `card-cultural`.
- O template mantém o wrapper de estado/cancelamento e o template de linha
  mantém as classes de lista. `events.css` remove a caixa duplicada ao redor do
  SDC e aplica o estado cancelado ao título do cartão.
- Firefox ESR no Runtime: listagem e página de evento em claro/escuro, 320 e
  1440 px, foco visível e zero overflow. Capturas em
  `$SUITE_OUT/layouts-purpose-events-cards-20261106-r2/`. A listagem não existia
  na baseline, então não há comparação anterior válida para ela.

## Não versionado — mapeamento focal dos componentes do Studio

- `PagePresentationHooks::preprocessPortalComponent()` adapta `context-tabs` ao SDC existente `tabs` e `empty-state` ao SDC homônimo, preservando props/slots e mantendo o fallback para componentes fora do escopo ou contratos incompletos.
- PHPUnit focal (5 testes/15 assertions) e renderização Drupal dos dois SDCs passaram. Firefox ESR comparou antes/depois do `empty-state` em página real autorizada, nos dois modos e 320/1440 px; DOM identificou SDC e mensagem, sem overflow, com foco visível. Evidência em `$SUITE_OUT/layouts-purpose-studio-empty-state-esr-20261105/`. A suíte visual ampla do Studio segue parcial: 34/48 checks OK, 22 skipped e 14 falhas. A F5 continua parcial.

## Não versionado — Fase 5: Consumers reais dos componentes SDC implementados

- Integração de consumers reais no tema `aculta420_v2` para os componentes SDC que estavam pendentes:
  - `tabs`: template `templates/navigation/menu-local-tasks.html.twig` adota `aculta420_v2:tabs` para abas primárias e secundárias do Drupal, mantendo títulos acessíveis e navegação por links.
  - `progress`: template `templates/misc/progress-bar.html.twig` adota `aculta420_v2:progress` com rótulo, valor percentual e compatibilidade com o indicador nativo.
  - `form-field`: template `templates/form/form-element.html.twig` adota `aculta420_v2:form-field` para elementos de formulário Drupal, preservando identificadores, classes nativas, prefixos/sufixos, instruções e mensagens textuais de erro acessíveis.
  - `data-table`: template `templates/views/views-view-table.html.twig` adota `aculta420_v2:data-table` com região de rolagem acessível e `data-label` por célula para visualização mobile.
  - `empty-state`: template `templates/views/views-view.html.twig` adota `aculta420_v2:empty-state` no bloco `{% elseif empty %}`, padronizando a renderização de estados vazios de Views.
  - `author-card`: documentado e consolidado em `templates/aculta-user-author-card.html.twig`.
  - `dialog`: documentado e consolidado em `components/lms-tour/lms-tour.twig`.
- Componente `price`: segue fora do escopo, aguardando autorização explícita de Commerce/Loja.
- Status da Fase 5: implementada, validação visual no servidor pendente (outro agente).


## Não versionado — Fase 4: UI Styles implementada (validação de servidor pendente)

- Registro do catálogo oficial de UI Styles em `aculta420_v2.ui_styles.yml`, restrito ao administrador Drupal:
  - Botões: opções para as 4 variantes (.button--primary, .button--secondary, .button--outline, .button--ghost) com tokens semânticos `--button-*`.
  - Faixa de acento do cartão: verde, amarela e vermelha (.card-cultural--accent-{green,yellow,red}), mapeadas para o componente card-cultural, com regra de precedência da Opção A aprovada pelo responsável (UI Styles sobrepõe o accent vindo do backend).
  - Cores por purpose: 10 esquemas oficiais da Paleta ACULTA Skunk Family baseados em `PurposePaletteSchemes.php`.
- Inclusão dos tokens e classes correspondentes no mapa `PortalUiHooks::TOKENS`.
- Criação do gate automatizado `scripts/validate-aculta420-ui-styles.php` validando conformidade estrita das opções com os componentes CSS e com o mapa de classes.
- Teste unitário `PurposePaletteResolutionUnitTest.php` cobrindo a resolução de tokens e atributos HTML de propósito nos dois modos.
- Validação visual em Firefox ESR visível nos modos claro e escuro, 320 px e 1440 px, com zero rolagem horizontal e persistência em `$SUITE_OUT`.
- Status da F4: implementada, validação de servidor pendente.

## Não versionado — aplicação da escolha de esquema no runtime

- `SurfaceSettingsHooks::preprocessHtml` lê `aculta_purpose_palette`, valida o esquema em `PurposePaletteSchemes::SCHEMES` e aplica os tokens de purpose selecionados nos modos claro e escuro.
- A escolha permanece isolada da barra institucional, dos botões, do foco e dos neutros.

## 1.3.0 — Paleta ACULTA Skunk Family e cores por purpose (Fase 3)

- Migração completa dos botões do tema para o esquema oficial aprovado em `docs/botoes.md`:
  - Criação dos tokens semânticos `--button-*` em `src/styles/tokens.css` para os modos claro (`:root`) e escuro (`:root[data-theme="dark"]`).
  - Atualização de `components/button/button.css` para consumir unicamente tokens `--button-*` nas variantes `.button--primary`, `.button--secondary`, `.button--outline` e `.button--ghost`, cobrindo estados repouso, `:hover`, `:active`, `:focus-visible` (anel de 2px, offset de 2px) e desativado (`opacity: .65`, `cursor: not-allowed`).
  - Remoção de filtros de brilho (`filter: brightness(...)`) e eliminação total do acoplamento com `--color-brand-highlight` e `--purpose-*`.
  - Invariância estática dos tokens e estilos; captura visual estrita no Firefox ESR nos 10 purposes permanece pendente de runtime.
  - Expansão do gate `scripts/validate-aculta420-v2-foundations.php` com pares de contraste estruturados para texto primário/secundário (repouso, hover, active), borda do botão secundário (no botão e na tela >= 3:1) e anel de foco (>= 3:1).
- Implementa a Paleta ACULTA Skunk Family oficial por purpose em `src/styles/tokens.css`, cobrindo os 10 purposes com os esquemas aprovados (Skunk, Sativa, Ruderalis, Afghan, Acapulco Gold, Lemon Haze, Golden Goat, Red Kush, Panama Red e Red Congolese).
- Escopo estrito e não-negociável: isola a variação visual exclusivamente nos tokens semânticos `--purpose-accent`, `--purpose-on-accent`, `--purpose-sub-1..3` e `--purpose-surface-1..3`. Remove a sobrescrita perigosa de `--color-brand-highlight` por purpose, garantindo que botões, anéis de foco, abas, links, barra multidomínio e a conta na barra institucional permaneçam 100% consistentes e inalterados em qualquer purpose.
- Adiciona a seção nativa "Cores por purpose" em `theme-settings.php` no formulário administrativo do tema com as 10 variantes oficiais, organizada em caixas (cartões) em grid responsivo (2 colunas no desktop a partir de 60rem, 1 coluna no mobile).
- Visualização ao vivo da paleta integrada em cada caixa, exibindo os modos claro e escuro lado a lado com a faixa de destaque, nome do esquema, valores hex, chips de subelementos e contraste textual. Interatividade via `Drupal.behaviors` com `core/once` em `js/theme-settings.js` e fallback acessível sem JS.
- Criação de `src/Palette/PurposePaletteSchemes.php` como fonte única dos valores dos 10 esquemas oficiais e gate novo `scripts/validate-aculta420-palette-schemes.php`.
- Lê a lista de purposes ativos exclusivamente a partir da Core State API neutra `aculta_portal.active_purposes`. Se o estado estiver vazio, exibe mensagem neutra e oculta os controles de seleção.
- Schema e defaults em `aculta420_v2.settings` com mapeamento `aculta_purpose_palette` para todos os 10 purposes.
- Validação estrita de purposes aprovados no `SurfaceSettingsHooks::preprocessHtml` para o atributo `data-purpose`.
- Suporte completo aos modos claro e escuro, incluindo a exceção de alto contraste para Afghan em modo escuro (`#059e61` com texto preto) e superfícies escuras elevadas (`#282421`, `#39342f`, `#49433d`).
- Atualiza neutros claros da V2 conforme decisão do responsável (`--color-text-secondary: #4d6357;`, `--color-text-muted: #5a6b60;`, `--color-border: #6b7d70;`).
- Documentação atualizada em `docs/paletas-por-purpose.md`, `docs/configuracao-do-tema.md`, `docs/architecture.md` e `docs/roadmap.md`.

## Não versionado — conclusão da F1 de HTMX (ADR-050)

- Finalização formal da Fase 1 (F1a/F1b) do ADR-050 no roadmap do tema V2:
  busca do shell com prévia HTMX, login popup com fragmento e HX-Redirect,
  gavetas utilitárias de mensagens e avisos (com filtros de avisos integrados e
  a exceção aceita documentada) e cadastro popup no checkout de doação.
- Validação no Runtime com suíte de navegador aculta-htmx-f1 (43/43 checks) e
  gates de fundações, schemas e contratos em conformidade.
- Roadmap atualizado registrando F0 e F1 como CONCLUÍDAS.

## Não versionado — auditoria completa de SDCs e gate de 40 componentes

- O gate `scripts/validate-aculta420-v2-sdc.php` passou a cobrir integralmente
  todos os 40 SDCs existentes no tema (`lms-tour`, `revista-listing`,
  `revista-publication`, `revista-teaser`, `wiki-entry` adicionados à lista de
  descoberta, schemas estritos, assets e renderização isolada).
- Documentação do catálogo em `docs/components.md` atualizada com o SDC
  `lms-tour` (utilizado no percurso do estudante pelo módulo `aculta_lms`) e
  total de 40 componentes auditados sem entradas fantasmas.
- `docs/roadmap.md` atualizado com o status de cobertura dos 40 componentes.

## Não versionado — avatar do menu de conta usa o SDC

- `account-menu` passou a renderizar o SDC `avatar` no resumo e no painel de
  identidade, com `size: sm` (32 px), mídia fornecida pelo Portal e fallback
  neutro. O markup duplicado e o CSS `aculta-account-menu__avatar` foram
  removidos.
- Validado no Runtime anônimo (1 avatar de fallback) e autenticado (2 avatares
  com imagem), sem overflow; gate de render dos 35 SDCs, schemas e foundations
  PASS; suítes do shell PASS.

## Não versionado — dívida de estilo resolvida

- As 189 classes de `PortalUiHooks::TOKENS` sem CSS receberam estilo mínimo em
  `src/styles/portal-ui-map.css`, agrupado por componente e com tokens
  semânticos.
- Nomes Bootstrap removidos do mapa do V2: `bi` e `bi-*`, `breadcrumb`,
  `breadcrumb-item`, `btn`, `btn-link`, `form-control`, `btn-primary`,
  `btn-outline-primary`, `btn-outline-secondary` e `btn-sm`. O `data-portal-ui`
  permanece e o CSS do V2 continua por atributo; a V1 não foi alterada. Os
  seletores `.btn`, `.btn-link` e `.form-control` também saíram de
  `buttons.css`, `search.css`, `studio.css` e `studio-identity.css`, o que
  libera o gate `validate-aculta420-v2-sdc.php` (35 componentes, PASS).
- Novo gate `scripts/validate-aculta420-mapped-classes.php`: exige CSS ou
  exceção documentada para cada classe mapeada e recusa nomes Bootstrap.
  PASS (453 classes, 0 exceções).
- Suítes `aculta-htmx-f1` (43/43) e `aculta420-v2-footer` (6/6) PASS após o CSS
  global.

## Não versionado — data-purpose neutro no html (Fase 3)

- `SurfaceSettingsHooks::preprocessHtml` aplica `data-purpose` a partir de
  `aculta_purpose`, fornecido pelo Portal, sem ler hostname nem regra de
  domínio. O formato é validado antes do atributo.
- Verificado no Runtime em Main, Conta, Eventos e Wiki. O ajuste de tokens de
  destaque por purpose depende de `domain_presentation.accent` (D1).
- Catálogo de componentes auditado: 39 diretórios com entrada, schema e story;
  `breadcrumb`, `purpose-showcase` e `event-page-layout` documentados.
- `ui_styles` 1.22.0 habilitado declara suporte a `^10.3 || ^11 || ^12`; o tema
  não registra plugins próprios.

## Não versionado — microfase HTMX do shell (parcial)

- HTMX é documentado como padrão obrigatório para interações assíncronas novas
  do tema; Drupal AJAX (`core/drupal.ajax`, `#ajax`, `use-ajax`) é legado durante
  a migração. Login do shell, busca e gavetas de mensagens/avisos usam
  `core/drupal.htmx`; foco, título e anúncios após atualização são tratados por
  `studio-htmx`.
- A busca do shell deixou o Fetch: o gatilho abre o `<dialog>` por `hx-get` e o
  campo de prévia usa `hx-get` com debounce de 300 ms e `hx-sync`, mantendo o
  link e o formulário nativos como fallback. O template do diálogo passou a ter
  o `id="aculta-search-content"` exigido pelo alvo HTMX.
- O Portal responde ao cadastro popup com fragmento e `HX-Redirect` em
  requisições `HX-Request`. O convite de cadastro no checkout de doação foi
  autorizado e passou a usar o diálogo HTMX do shell, com o `href` como
  fallback; o restante do checkout segue na F5.
- As gavetas de mensagens/avisos mantêm a exceção aceita pelo responsável
  em 2026-11-05: abrem por `<button>` e dependem de JavaScript, sem link nativo
  para a página completa.
- Gates de SDC, foundations, shell contract, browser validators e render Drupal
  isolado (35 SDCs) passaram; o validador de render também exige o contrato
  HTMX do login. A suíte `tests/browser/aculta-htmx-f1/` passou 43/43 checks no
  Runtime de desenvolvimento: seis larguras em claro/escuro para busca, login e
  gaveta de avisos autenticada, prévia por HTMX, foco visível, zoom real de
  200%, teclado (Escape devolve o foco ao gatilho) e fallback sem JavaScript.
  O consumer V2 do cadastro popup é o checkout de doação (F5), aguardando
  autorização.

## Não versionado — auditoria de templates G3

- Removido `node--teaser.html.twig`, override genérico que apresentava bundles
  distintos como cartão cultural e podia omitir campos definidos no display,
  como arquivo institucional. Bundles sem adapter específico retornam ao
  template Core; adapters próprios e render arrays/access/cache permanecem.
- `docs/architecture.md` registra o mapa por bundle/view mode. Visual no Runtime
  com V2 ativa permanece pendente; Loja espera autorização específica.

## Não versionado — faixa de acento do card (DRAFT)

- `card-cultural` aceita `accent` neutro (`green`, `yellow`, `red`) e aplica os
  tokens contrastados por modo. Stories DRAFT mostram as cores sem mapear
  domínios ou tipos; o Portal ainda não fornece `domain_presentation.accent`.
- Gate Drupal verifica prop, classe e token; foundations verifica os seis
  contrastes contra a superfície de cartão (mínimo 3:1). Nenhum consumer de
  conteúdo foi alterado. F2 aguarda D1 e a confirmação da correspondência de Wiki.

## Não versionado — componente Empty state

- Criado o SDC `empty-state`, compatível com o contrato neutro já consumido pelo
  Studio: mensagem e ações vêm dos render arrays do módulo. A variante compacta
  controla somente o espaçamento; o componente não determina acesso nem vazio.
- Stories default/compact, catálogo, especificação do Studio e gate Drupal de
  render/assets incluídos. A integração em página Studio servida com a V2 ainda
  não foi exercitada no Runtime. Drupal validou schema estrito e render de 35
  SDCs; Firefox visível passou em 12 estados, foco por Tab e zoom real 200%.

## Não versionado — componente Data table

- Criado o SDC `data-table` com tabela semântica, variante de cartões móveis e
  rolagem horizontal explícita conforme ADR-040. O comportamento JS transforma
  o wrapper em região nomeada/focável somente durante overflow, acompanhando
  resize e mudanças renderizadas pelo Drupal.
- Stories cobrem as variantes. O gate Drupal V2 validou schema, assets e render
  de 34 SDCs; browser validators também passaram. O validador estático
  `validate-aculta420-sdc-schemas.php` cobre a V1, não a V2. Firefox visível
  passou em 12 estados (seis larguras e dois modos), zoom 200% a 320
  CSS px e fallback sem JavaScript passaram; consumer Drupal de Studio permanece
  pendente.

## Não versionado — componente Progress

- Criado o SDC experimental `progress` com elemento nativo `<progress>`, rótulo
  acessível e stories para estado determinado e indeterminado. O percentual só
  aparece quando o valor é conhecido.
- Gate SDC cobre os dois estados e a associação do rótulo. Firefox visível
  passou em 12 estados nas seis larguras e dois modos, com zoom 200%; a captura
  corrigiu o estilo indeterminado que inicialmente parecia completo. Integração
  a dados reais e consumer publicado permanecem pendentes.

## Não versionado — componente Form field

- Criado o SDC experimental `form-field` com slots de rótulo, controle,
  instrução e erro. O controle e a validação continuam sob responsabilidade
  do Drupal/Portal; duas stories demonstram associações acessíveis.
- Gate SDC estendido para renderizar e conferir controle, descrições e erro.
  Firefox passou 12/12 estados em fixture sintética nas seis larguras, claro e
  escuro, zoom 200% e foco por Tab. Integração em formulário consumidor segue
  pendente.

## Não versionado — componente Dialog

- Criado o SDC `dialog` com `<dialog>` nativo, variante fullscreen no mobile,
  fallback por navegação sem JavaScript e conteúdo/ações fornecidos pelo módulo
  proprietário. O CSS é anexado pelo SDC; a abertura reutiliza o comportamento
  compartilhado `utility-dialogs`, que mantém o foco cíclico por Tab/Shift+Tab.
  Story, documentação e gate de render incluídos.
- Schema (55/764), foundations (2.468) e render Drupal isolado (31 SDCs) PASS;
  Firefox visível passou 32/32 checks em fixture sintética, incluindo seis
  larguras, claro/escuro, zoom real 200%, teclado, fechamento e fallback sem JS.
  Integração a consumer publicado/autenticado do Runtime permanece pendente.

## Não versionado — componente Avatar

- Criado SDC `avatar` com tamanhos responsivos, slot de mídia de responsabilidade
  do Portal e fallback `user-circle` acessível. O componente nunca deriva letras
  ou iniciais do nome. Stories de mídia sintética e fallback documentam os dois
  estados; integração com os consumers existentes fica para uma entrega própria.

## Não versionado — auditoria do Author box

- Mapeada a função prevista de Author box ao slot `authors` de
  `revista-publication` e ao SDC `author-card`, conforme o contrato Portal
  `AUTHOR-CARDS.md`. O gate de render Drupal cobre o cartão com identidade
  autorizada, avatar e bio; captura no fluxo da Revista ainda pendente.

## Não versionado — componente Tabs

- Criado SDC de navegação entre páginas, com slot para a lista de links do
  Drupal, alvos de 44 px, scroll horizontal e estado ativo `aria-current`.
  Não adiciona JavaScript nem troca de painéis. Gates SDC e capturas responsivas
  passaram; integração aos render arrays das suítes segue para G3.

## Não versionado — dashboard e Minha Conta na rota definida pela ADR-048

- O preprocess mapeia a rota `aculta_studio.dashboard` para `aside`, inclusive
  quando ela é servida na raiz do purpose `account`.
- O Portal separa o dashboard em `/` de Minha Conta em `/conta`; o path interno
  do dashboard é bloqueado diretamente e a tela de Conta reutiliza a composição
  privada existente. Validação visual autenticada permanece pendente.

## Não versionado — accordion para FAQ e layout LMS

- Criado o SDC accordion com story e interação HTML nativa (details), foco
  visível e sem JavaScript. O template do FAQ usa o SDC quando disponível e
  mantém fallback sem cache persistente atualizado.
- Criado o SDC lms-lesson-page e sua story. Na rota de aula o tema o usa como
  contêiner do render array original, sem mover acesso, cache, atividades ou
  conteúdo do módulo LMS.

## Não versionado — acordeão nativo para FAQ

- Criado o SDC accordion com story e interação HTML nativa (details), foco
  visível e sem JavaScript. O template do FAQ usa o SDC quando disponível e
  mantém fallback sem cache persistente atualizado.

## Não versionado — compatibilidade com cache de descoberta aquecido

- O template mantém a página renderizável com `<main>` de compatibilidade quando
  o cache Drupal ainda não descobriu o SDC layout-page. O preprocess aceita a
  antiga lista compilada de argumentos e usa `stack` até o container conhecer
  os serviços de rota. A variante nova depende do rebuild normal autorizado.
- A disponibilidade de layout-page, accordion e lms-lesson-page é consultada no
  plugin manager; o arquivo Twig sozinho não indica que o plugin está registrado.

## Não versionado — mapeamento P2 de layout

- `PagePresentationHooks` seleciona `aside` para wiki/fórum e `full` para
  homepage, revista e rotas públicas de catálogo/mapa de eventos, com fallback
  stack. Sem regra por hostname/purpose. Loja permanece fora, aguardando
  autorização explícita de tarefa; confirmação visual também pendente.

## Não versionado — layout-page P1

- Criado SDC layout-page com variantes stack, aside e full, três stories
  e slots semânticos. Refatorado page.html.twig mantendo as oito regiões e
  removendo o grid lateral antigo. O template seleciona stack até o mapeamento
  P2; capturas, landmarks, zoom e teclado seguem pendentes por ausência de
  DISPLAY.

## Não versionado — revisão estrutural do shell (C4)

- `page.html.twig` adapta a micropágina de Links ao SDC próprio e apresenta o
  `social_links_page_footer` do Portal na variação social do `site-footer`. O
  SDC mantém dados, links e identidade como render arrays fornecidos pelo módulo.
  Render SDC e gates estáticos passaram; a revisão visual fica pendente porque
  `DISPLAY` não está disponível.

## Não versionado — paridade de mensagens e avisos (C3)

- Documentada a implementação já existente no shell V2 e no Portal: filtros de
  origem/leitura, contador zero oculto, estado de erro com texto, rodapé fixo e
  cancelamento de respostas antigas. A revisão visual autenticada e teclado
  ficam pendentes por ausência de `DISPLAY` nesta sessão.

## Não versionado — completa menu de conta e lista de sites

- O `account-menu` apresenta `aculta_domain_sites` do Portal com links normais
  e `aria-current="page"` no site atual. O bloco de sessão autenticada mostra
  o avatar e `aculta_account_name`; visitantes recebem o link Entrar dentro do
  disclosure. Os itens de conta originais mantêm access e cache do Drupal.
- Alvo do controle da barra alinhado em 35 px e ícone em 30 px, conforme
  instrução do responsável. Stories anônima/autenticada e documentação SDC
  atualizadas. Schemas (55 componentes/764 checks), design foundations (2.468),
  render Drupal SDC (23 componentes), shell (13 checks), browser validators
  (21 checks), PHP lint e `git diff --check` passaram. Capturas e interação
  autenticada ficam pendentes: `DISPLAY` não está disponível nesta sessão.

## Não versionado — unifica preprocess_html; render conferido

- O Core permite uma implementação `preprocess_html` por tema. Reunidos em
  `SurfaceSettingsHooks` o `data-theme` e as classes das opções de superfície;
  `ColorModeHooks` foi removido. A implementação usa `ContainerInjectionInterface`
  para resolver dependências do provider no mapa de hooks existente.
- PHP lint, gates de foundations (55 pares), design foundations (2.468 checks)
  e schemas (55 componentes/764 checks) passaram. O HTTP do Runtime retornou
  200; o HTML servido traz `data-theme="light"` e as cinco classes de superfície
  esperadas no `<body>`. A tabela de hooks ativa contém somente
  `SurfaceSettingsHooks::preprocessHtml`. `ThemeSettingsProvider` está disponível;
  como o provider não tem valor persistido para `aculta_radius_button`, o hook
  aplicou os defaults documentados. `updatedb:status` sem atualizações e
  `config:status` sem diferenças. Nenhum `cex` foi feito.

## 1.2.0-dev.17 — corrige a Institution Bar com Gin e ícone administrativo (2026-11-04)

- A Institution Bar mede a coluna lateral Gin e ocupa apenas o espaço geral,
  sem deslocar nem cobrir a navegação administrativa. O conteúdo mantém margens
  fluidas e reorganiza as utilidades quando a coluna restante fica estreita.
- Os ícones de busca, carrinho, mensagens e avisos passam a 32 px, alinhados ao
  tamanho do avatar; o espaço entre controles foi reduzido.
- Removido de `navigation.block_layout` o plugin legado `navigation_shortcuts`,
  indisponível quando o módulo Core `shortcut` está desativado. O bloco quebrado
  deixou de aparecer no menu Gin.
- Firefox ESR visível autenticado com a conta administrativa de teste: a matriz
  cobre sidebar Gin expandida/recolhida, Institution Bar, mensagem de bloco
  quebrado, alinhamento, painel de conta e ícones em 11 larguras entre 320 e
  1440 CSS px, nos modos claro/escuro: 44/44 checks de geometria PASS. Capturas em
  `$SUITE_OUT/aculta420-v2-admin-sidebar-20261104/`.
- O scrollWidth da página excede a viewport quando a sidebar Gin está recolhida
  em telas estreitas (385 px para viewport 320; 420 px para viewport 390). A
  inspeção aponta ações do Top Bar administrativo fora da Institution Bar; a
  rolagem da página como um todo fica DEFERRED para uma correção própria do
  toolbar, sem ampliar este ajuste do shell.

## 1.2.0-dev.16 — conecta categorias da landing do Fórum ao SDC (2026-11-04)

- A View `aculta_forum_categories:block_landing` agora usa `forum-category`,
  com wrapper próprio e seletores alinhados ao ID do bloco real do Runtime.
  Antes, a landing imprimia campos sem o cartão SDC; depois, os cartões e
  descrições passaram a usar a mesma apresentação da página de categorias.
- Firefox ESR visível, purpose `forum`, visitante, landing, categorias e tópico
  real, claro/escuro, 320/360/390/599/600/768/1024/1280/1440 CSS px: 76/76
  checks; Tab/Shift+Tab e zoom real 200%/400% com viewport CSS de 320 px sem
  overflow. Capturas em `$SUITE_OUT/aculta420-v2-forum-landing-20261104/`.
- Drush descobriu, validou e renderizou 25/25 SDCs. Runtime pré-sync:
  `updatedb:status` sem pendências e `config:status` sem diferenças; `drush cr`
  passou. O conteúdo foi somente lido.

## 1.2.0-dev.15 — migra apresentações do Fórum (2026-11-04)

- Portados o teaser e a apresentação completa de tópico para o SDC `forum-topic`,
  o cartão `forum-category` e o template de comentários via `discussion-comment`,
  além dos estilos da View pública, filtros de busca, vazios, controles de
  discussão e formulário de comentário. Render arrays de autoria, favoritos,
  contagens e atividade seguem sob controle do Drupal/Portal.
- Adapters de tópico, categoria e Views preservam os render arrays Drupal.
- Firefox ESR visível, Runtime Drupal 11.5.0-beta1, purpose `forum`, sessão
  anônima: landing, categorias e tópico em claro/escuro, 320/360/390/599/600/
  768/1024/1280/1440 CSS px; 58/58 verificações, sem overflow e com um H1.
  Tab/Shift+Tab passaram. Zoom real do Firefox em 200% e 400%, viewport efetiva
  de 320 CSS px, sem rolagem horizontal. Evidências em
  `$SUITE_OUT/aculta420-v2-forum-final-20261104/`.
- Gate Drush descobriu e renderizou 25/25 SDCs, incluindo três componentes do
  Fórum. Foundations: 51 pares de contraste PASS. O template de comentário
  renderiza no gate isolado, mas as páginas públicas consultadas não continham
  comentários do bundle `aculta_discussion_post`; esse estado autenticado segue
  DEFERRED. O Fórum continua anônimo; nenhuma mutação foi feita.

## Em validação — Wiki420

- Portados para SDC os templates de teaser e artigo completo de Wiki, com
  abas de ação, resumo, corpo, imagem, categorias, referências, discussão e
  links de operação renderizados pelo Drupal.
- A família preserva a composição V1 de artigos e cartões com tokens
  semânticos e CSS mobile-first. O artigo é dono do H1; home e listagens usam
  a grade de cartões adaptada ao wrapper real `aculta-wiki-list`.
- Drush descobriu e renderizou 22/22 SDCs. Firefox ESR visível no Runtime,
  home/listagem/verbete, claro e escuro, 320/390/767/768/1440 CSS px: 189/189
  checks; Tab/Shift+Tab e ausência de overflow passaram. Zoom físico e estados
  autenticados seguem pendentes. `/wiki/busca` responde 404 no Runtime atual;
  a rota não foi recriada pelo tema. O CSS de busca/categorias herdado da V1
  ainda aguarda um consumer real no Runtime.

## Em validação — migração visual da Revista

- Adicionados os SDCs `author-card`, `revista-listing`, `revista-teaser` e
  `revista-publication`, com adapters para a View `revista_sessoes`, os view
  modes `teaser`/`full` e o hook visual de autoria do `aculta_user`.
- A apresentação cobre a grade de 1/2/3 colunas, destaque da primeira
  publicação, metadados/compartilhamento, corpo editorial, imagens com modelos
  pequena/padrão/larga/destaque e alinhamento, autoria e discussão. Render arrays
  Drupal são mantidos como slots.
- A listagem passa a fornecer o H1 editorial “Revista 420”; o título genérico do
  Drupal fica oculto somente quando o SDC `revista-listing` está presente,
  removendo o H1 duplicado que a revisão Firefox detectou.
- PHP lint do gate, parse de todos os schemas/stories e `git diff --check`
  passaram. Validação de renderização Drupal e evidência Firefox Runtime ficam
  pendentes; a versão será marcada após esses gates.

## 1.2.0-dev.14 — aproxima o diálogo de acesso da V1

- A superfície recupera o verde exato da V1 (`#6b9a2c`), com texto escuro
  para manter contraste de 5,45:1. O botão Google recupera a sombra discreta e
  o fundo creme em hover e foco por teclado; usa o mesmo SVG de marca da V1.
- A composição, o formulário AJAX, os links e o item Minha conta no painel
  autenticado permanecem no contrato atual. No Runtime, Firefox ESR visível
  confirmou o formulário e Google em claro/escuro, Escape e ausência de overflow
  nos viewports efetivos de 500 e 1430 CSS px. 320/390 px e zoom ficaram
  DEFERRED porque o Firefox/X11 não reduziu a janela abaixo de 500 CSS px.

## 1.2.0-dev.13 — porta o breadcrumb editorial da V1

- A trilha de navegação agora é um SDC e substitui o template nativo com
  separadores, cor editorial, estado atual e foco visível da V1.
- O template mantém a responsabilidade Drupal de montar os itens e o Portal
  continua fornecendo o título atual. O vermelho editorial foi aprofundado para
  atingir contraste AA: 5,70:1 no canvas claro; no escuro, 6,79:1 na superfície.

## 1.2.0-dev.12 — reconcilia painel de login com a identidade V1

- O diálogo usa o verde institucional legado pela paleta semântica V2 e texto
  escuro para manter contraste AA em claro e escuro.
- A caixa Google, campos claros, ação amarela e links creme preservam a
  composição da V1. O gate de fundações mede esses pares em ambos os modos.
- Firefox ESR visível confirmou o modal e Google em 320, 390 e 1440 CSS px,
  além de zoom real de 200% com viewport de 320 CSS px. Tab avança do usuário
  para senha e Escape fecha o diálogo.

## 1.2.0-dev.11 — aplica o estilo ao controle HTML do Drupal

- O botão de envio do formulário Core é um input com `data-drupal-selector`,
  não um elemento `button`; o seletor do modal agora pinta o controle amarelo.
- O foco do formulário usa um token dedicado com contraste sobre as superfícies
  claras fixas dos campos e do provedor.

## 1.2.0-dev.9 — corrige slots Twig do menu de conta (2026-11-04)

- O template `account-menu` agora consome `avatar` e `menu_items` como slots
  SDC, sem redefinir blocos que colidiam com o `embed` de `multidomain-bar`.
  Fallback neutro de avatar e links recebidos foram preservados.
- Gate SDC e smoke funcional autenticado da rota de materiais passaram; turma
  e mídia temporárias foram removidas. Sem alteração visual/CSS.

## 1.2.0-dev.8 — fallback de entrada sem duplicar links

- O bloco de conta Core vazio/inacessível para visitante deixa de ocupar o slot.
  A Institution Bar então renderiza o link de entrada do Portal; autenticados
  continuam usando o render array protegido do menu Drupal.

## 1.2.0-dev.7 — mantém a entrada para visitantes

- Quando o bloco de conta do Drupal não fornece links para visitante, a barra
  usa o link `/entrar/janela` como fallback. O handler existente aprimora-o
  para o diálogo AJAX; sem JavaScript continua sendo um link navegável.

## 1.2.0-dev.6 — corrige o slot comum do menu

- O SDC `account-menu` declara `menu_items` uma única vez para visitante e
  usuário autenticado. Gate de renderização detectou a duplicidade Twig.

## 1.2.0-dev.5 — declara slots aninhados do menu (2026-11-04)

- Declara os slots de avatar e itens encaminhados pelo `multidomain-bar` ao
  SDC `account-menu`, permitindo a compilação da árvore Twig aninhada.
- `drush cr` revelou o problema de descoberta que o gate estático não cobria;
  a validação de renderização será repetida após a correção.

## 1.2.0-dev.4 — botão de conta somente com avatar (2026-11-04)

- A Institution Bar não recebe nem mostra apelido. O botão autenticado contém
  somente o avatar e “Minha conta” visualmente oculto como nome acessível.
- O resumo mantém o alvo de toque de 44 px. O Portal fornece apenas o avatar;
  o painel continua contendo os links Minha conta e Sair.
- A entrada `1.2.0-dev.3` foi substituída após a correção final do escopo.



## 1.2.0-dev.3 — shell institucional e integração visual (2026-11-04)

- A composição do Domain Header agora reúne marca e menu principal no SDC
  `navigation-bar`, preservando os render arrays e o peso do menu. Título,
  breadcrumb e ajuda são renderizados em suas regiões semânticas; a Institution
  Bar continua sendo a faixa fixa superior.
- A vitrine de portais foi isolada no SDC `purpose-showcase`, abaixo do header.
  Logo horizontal e variantes responsivas do Wiki420 vieram dos assets da V1;
  o tema escolhe o asset pela chave neutra fornecida por `domain_presentation`.
- Três blocos de eventos, fórum e agenda foram movidos de `header` para
  `sidebar_first`, recuperando a composição de duas colunas da referência V1.
  Uma entrada obsoleta de `bootstrap5` foi removida do `core.extension` de
  sincronização; nenhuma dependência ou instalação Bootstrap foi adicionada.
- Gin Core Navigation fixa a sidebar em `top: 0` com especificidade baseada em
  ID. O override do shell mede a altura real da Institution Bar e inicia a
  sidebar abaixo dela. Turnstile conserva o desafio dentro do formulário sem
  ampliar a largura da página em viewports estreitos.
- Firefox ESR visível: navegação 100/100 checks nos dez purposes, em
  320/767/768/1024/1099/1100/1440 px, com teclado e fallback sem JavaScript.
  Camadas Gin, Gin Core Navigation e Toolbar Core: 15/15 checks em
  320/390/768/1024/1280 px. Conta: 18/18 estados de reflow em claro/escuro nas
  larguras 320–1440; foco, Shift+Tab, zoom nativo 200/400, sem JavaScript e
  movimento reduzido passaram. HTTP 200; artefatos ficam em `$SUITE_OUT`.
- Runtime Drupal 11.5: nenhum update de banco, configuração ativa igual a
  `config/sync` e `drush cr` concluído. Shell e fundações continuam em revisão;
  toolbar autenticado, leitor de tela e propósitos/rotas fora da matriz focal
  permanecem DEFERRED.

## 1.2.0-dev.2 — navegação SDC no shell (2026-11-04)

- O bloco Drupal `system_menu_block:main` permanece na mesma posição da região
  `header`; o preprocess o envolve no render element nativo do SDC
  `navigation-bar`, preservando o render array original como slot e mantendo
  acesso, peso e cacheabilidade.
- O menu continua visível sem JavaScript; JavaScript aprimora o drawer mobile,
  com foco ao abrir, fechamento por Escape e retorno de foco. A marca permanece
  opcional no componente para evitar duplicação do Domain Header.
- Os landmarks do SDC e do menu Core têm nomes distintos (`Primary navigation`
  e `Main navigation`); links e título continuam sob o sistema de menus Drupal.
- A Institution Bar permanece no topo acima das camadas administrativas. A
  sidebar Gin começa após a altura medida da barra; a bandeja vertical Core
  começa após a barra e os controles do toolbar.
- Firefox ESR visível: baseline `33e4dddbf5`; a matriz funcional cobre dez
  purposes, 320/767/768/1440 px, Enter/Escape/foco e fallback sem JS. A matriz
  visual passou nas dez raízes de purpose em claro/escuro e 320–1440 px, sem
  overflow; zoom 200/400, foco de teclado, sem JS e movimento reduzido passaram.
  O runner injeta o CSS baseline no DOM atual, portanto não representa o
  histórico Twig anterior. O teste de camadas usa as folhas Gin/Core e fixtures
  DOM anônimas (10/10 checks em 320/390/768/1024/1280 px): a barra ocupa
  0–73 px, Gin começa em 73 px, controles Core em 73 px e bandeja Core em
  112 px. Sessão administrativa real e leitor de tela ficam DEFERRED.

## 1.2.0-dev.1 — tokens e fundações com identidade da V1 (2026-11-04)

- Consolidados tipografia Inter/Oswald, escala de espaçamento herdada, foco
  visível tokenizado, movimento reduzido e regras de reflow mobile-first.
- A biblioteca Drupal carrega as mesmas famílias tipográficas externas usadas
  pela V1; nenhum Node, Vite ou Bootstrap foi adicionado ao tema.
- O novo gate `validate-aculta420-v2-foundations.php` verifica tokens e pares
  de contraste do modo escuro. Os 33 pares de texto passam 4,5:1; os 4 pares
  de foco e 4 pares de borda passam 3:1. Piores resultados: texto muted em
  superfície elevada 6,78:1; link editorial em superfície elevada 6,07:1;
  borda em superfície elevada 3,21:1.
- Firefox ESR visível: baseline `676ff17449` comparado ao estado `1.2.0-dev.1`
  nos dez purposes documentados, nas raízes públicas (Conta redireciona à tela
  de entrada). Em 320/360/390/599/600/768/1024/1280/1440 CSS px, os modos
  claro/escuro passaram 180/180 estados sem overflow; todos responderam HTTP
  200. Tab e Shift+Tab: 10/10 focos visíveis. Zoom nativo a 200% e 400%, com
  viewport efetiva de 320 px: 10/10 em cada nível sem overflow. Sem JavaScript:
  90/90 larguras/purposes sem overflow. Movimento reduzido: 180/180
  combinações de largura/modo, transição calculada em 0,01 ms. Sessões anônimas;
  logins autenticados e leitores de tela não foram cobertos. Evidências e URLs
  ficam em `$SUITE_OUT/aculta420-v2-foundations-full/`; purposes e hosts vêm da
  matriz em `docs/architecture/multidomain.md`.
- O gate legado `scripts/validate-aculta420-foundation.php` continua exigindo a
  V1 como tema público e Bootstrap5 como base. No Runtime ativo em V2 ele falha
  com `ACULTA420 is the default public theme.`; o gate específico V2 passou.
- A declaração Drupal `^11.5 || ^12` permanece sem alegação de suporte
  validado no Core 12, conforme ADR-045.

## 1.1.0-dev.5 — menu de conta e login modal da barra (2026-11-04)

- O menu Drupal de conta voltou à barra institucional fixa. Os links nativos
  “Minha conta” e “Sair” permanecem sob controle de acesso do sistema de menu;
  o Portal fornece a URL de logout com token de sessão.
- Para visitantes, “Entrar” continua apontando ao popup do Portal. V2 carrega
  o formulário e envia o login por Fetch ao endpoint `drupal_ajax`, aplicando
  respostas Drupal de substituição/redirect dentro de `<dialog>` nativo. A
  busca usa modal nativo e preserva a prévia do Portal. Os dois gatilhos da
  barra não dependem de `core/drupal.dialog.ajax` nem de jQuery. Uma View
  existente ainda anexa `core/drupal.ajax` (e sua dependência jQuery) à home;
  essa biblioteca não é introduzida pela barra.
- A barra lê o layout de purpose do Portal, realça a conta no purpose account
  e respeita a supressão da faixa utilitária no purpose links.
- Validação SDC 15/15, PHP lint/PHPCS, sintaxe JavaScript e `drush cr` passaram.
  Smoke HTTP confirmou home, endpoint de login e endpoint de busca em 200.
  O envio de credenciais não foi testado. `updatedb:status` e `config:status`
  estão limpos.

## 1.1.0-dev.4 — ícones vetoriais e integração CDN/cache (2026-11-04)

- Adicionada a biblioteca Phosphor Icons Core 2.1.1 (regular e duotone, MIT)
  e o SDC `icon`, com nomes validados e SVG inline via Twig `source()` para
  herdar `currentColor` sem dependência de CORS ou sprite externo.
- Badge, button e card-cultural aceitam ícones SDC; os metadados de data e
  local usam ícones Phosphor.
- Busca, carrinho, mensagens, avisos, fechar diálogos, navegação e mensagens de
  status também usam o SDC `icon`; removidos os SVGs de interface desenhados
  diretamente nos templates.
- Documentada a estratégia de assets Drupal/CDN: URLs relativas ao Drupal,
  bibliotecas agregáveis e cache-tag invalidation pelo ambiente. Vite continua
  fora da fundação conforme a decisão anterior de usar Drupal libraries.
- Leaflet próprio recebe ícones inline renderizados pelo tema em templates
  inertes e os monta em `L.divIcon`; IDs e classes de categoria são validados.
- Validação: 15/15 SDCs discover, validate and render; icon output includes
  inline SVG, and a template audit found no inline interface SVGs outside the
  icon SDC. Runtime cache rebuild and Eventos `/mapa` HTTP smoke succeeded.
  Unit Maps: 4 tests / 26 assertions PASS. Visual/assistive review and
  environment CDN deployment remain follow-up.

## 1.1.0-dev.3 — 2026-11-04

- Added the global institutional bar SDC fixed at the legacy top position: the
  ACULTA logo links to the main domain, followed by icon-only search, cart,
  messages, and alerts. Optional portal links render in a separate secondary
  row; the four utilities have no visible text labels.
  Domain resolution, utility URLs/counts, and cache dependencies remain owned
  by ACULTA Portal.
- Migrated the ACULTA420 logo variants and favicon from V1. Added an
  `apple-touch-icon.png` derived from the V1 favicon and a web app manifest
  using square brand icons. Color mode remains controlled by the existing
  explicit selector.
- Foundation schema: 55 SDCs / 764 checks PASS; Design Foundations: 2468
  checks PASS; V2 SDC discovery/schema/render 14/14 PASS. Runtime cache rebuild
  and HTTP 200 confirmed. Visual review remains pending.

## 1.1.0-dev.2 — 2026-11-04

- Integrated the `map-popup` SDC with ACULTA Maps theme hooks. The map module
  supplies escaped, provider-neutral render data and the theme selects the SDC.
- Leaflet and Google InfoWindows clone the same inert server-rendered template;
  popup HTML is not transferred through `drupalSettings`.
- Added the optional `route_url` popup prop. Validation and visual review remain
  pending.

## 1.1.0-dev.1 — 2026-11-04

- Added ten schema-enforced Single Directory Components for buttons, badges,
  cultural cards, audio, hero banners, navigation, pagination, filter chips,
  status messages, and map popups.
- Added Drupal template adapters for pager, status messages, and node teasers.
- Added explicit light/dark token sets controlled by the existing ACULTA Portal
  appearance preference. System color-scheme detection is disabled; legacy
  `auto` resolves to light in V2.
- Added a progressive mobile navigation drawer using Vanilla JavaScript,
  `Drupal.behaviors`, and `core/once`.
- Added a Drush SDC validation gate and architecture documentation.
- Validation: Core strict SDC schemas and isolated renders, all Twig parses,
  node teaser render, YAML, JavaScript syntax, PHP lint/PHPCS, CSS parse and
  mobile-first media audit, explicit mode mapping, and development Runtime
  smoke. Full consumer visual and assistive technology review remains pending.

State: Dev. No RC, production deployment, or release tag is declared.

## [1.4.1] - 2026-11-08
- Adicionado componente SDC `discussion-reply-composer` para renderização acessível e integrada de respostas inline no fórum e discussões compartilhadas.
- Atualizado `forum-quote.js` para focar suavemente no editor ou textarea após o swap HTMX, respeitando `prefers-reduced-motion`.

## Não versionado — FST-08 estatísticas do Fórum (2026-11-10)

- Oito SDCs de estatísticas: cartão de indicador, série temporal acessível, filtro de período sem JavaScript, ranking de categorias, resumo de atividade, saúde das respostas, moderação e estado vazio.
- Todos com schema estrito, story, tokens semânticos e CSS mobile-first; `validate-aculta420-sdc-schemas.php`: PASS (80 componentes, 1707 checks).
- Consumidor: painel `aculta_statistics` da área Fórum e resumo do painel administrativo do Fórum.
- Sem validação de navegador nesta entrega; captura visual pendente.
## Não versionado — UI Icons Menu, Picker e Patterns (2026-11-10)

- Configura UI Icons 2.1 no Sync: Fields, Menu, Patterns e Picker; o SDC
  `button` já publica props de ícone via UI Patterns.
- Menus principal e de conta usam Picker e restringem escolha a Phosphor Regular
  e Duotone. Nenhum ícone é atribuído automaticamente; rótulos e links atuais
  permanecem sob propriedade editorial.
- UI Patterns usa Picker como seletor. Popover permanece fora do Sync para não
  manter seletor paralelo. Runtime/importação, permissões e foco visual pendem
  de cutover autorizado.

## Não versionado — UI Skins no Config Sync e remoção da chave antiga (2026-11-10)

- Habilita `ui_skins` no Config Sync e amplia o gate para falhar se o módulo
  não estiver incluído, além de validar scopes e contraste.
- No Runtime, remove apenas `aculta_purpose_palette` depois de confirmar que
  nenhum código ativo a lê. Não fica cópia, dual-read ou fallback antigo.
- UI Skins 1.2.1 foi instalado no Runtime, sem importar mapa ou scopes. O
  download da tradução `pt-br` falhou por `translations://` não configurado.
  Piloto Links, render e cache ficam pendentes até o cutover autorizado.


## Não versionado — UI Patterns style na listagem pública da Revista (2026-11-11)

- `aculta_news:block_1` passa a compor o SDC `revista-listing` sobre quatro nodes teaser, preservando acesso, filtros, ordenação e cache da View. O SDC aceita wrappers Views e nodes diretos retornados pelo style UI Patterns.
- Removida dessa View a classe genérica `aculta-editorial-list`; o script de instalação deixa de recriá-la para `aculta_news`. Browser Firefox ESR: A/B 10/10 em cinco larguras, claro/escuro; zoom/cache-hit seguem pendentes.

## Não versionado — Home editorial em UI Patterns Views (2026-11-11)

- `home_editorial_highlights:block_1` substitui o style VVJB pelo SDC `layout-grid`. Os três destaques curados continuam sob Views e seus campos/layout continuam renderizados pelo Layout Builder.
- Runtime anônimo manteve HTTP 200, IDs 14–16 e acesso/query/filtros/ordenação/pager/cache. Firefox ESR: A/B 10/10 em 320/390/768/1024/1440, claro/escuro, foco visível, contraste mínimo 8.80:1 e zero overflow.
- O cache de página permanece private/no-cache/UNCACHEABLE; cache-hit, zoom 200% e homologação ampliada pendem. VVJB não possui View consumidora; sua extensão não foi desinstalada.

## Não versionado — galeria editorial em UI Patterns (2026-11-11)

- As Views `aculta_album_fotos:block_1`, `aculta_forum_gallery_slides:block_1` e `galeria_produto:default` compõem o SDC `layout-grid` com UI Patterns, removendo os visuais VVJS das features ACULTA.
- PhotoSwipe, filtros/ordem/acesso/cache e render arrays Commerce permanecem; Fórum sem galeria publicada e aceite visual de produto pendente. O único consumidor VVJS restante é exemplo upstream classificado `BLOCKED_UPSTREAM`.
## Não versionado — Documentos públicos em UI Patterns (2026-11-11)

- A View `aculta_documents:block_1` usa `layout-grid` e o teaser SDC
  `document-card`, mantendo rows de node, Field API e regras nativas da View.
- Runtime anônimo preservou três resultados, H1/main e cache contextual. Os
  fixtures publicados têm campos de arquivo sem descrição, portanto a presença
  visual de links de arquivo não foi comprovada. Captura, perfis e cache-hit
  continuam pendentes.

## Não versionado — Catálogo da Loja em UI Patterns (2026-11-11)

- `aculta_shop_catalog` usa UI Patterns `layout-grid` nas saídas block/page; o
  adapter do view mode `catalog` compõe o SDC `product-card` com preço,
  variações e submit de compra pelos render arrays Commerce.
- Runtime anônimo: HTTP 200 e o mesmo produto/submit antes e depois. Firefox
  headless passou 10/10 em 320/390/768/1024/1440, claro/escuro, sem overflow,
  foco visível e contraste do título ≥12,4:1. Cache segue `UNCACHEABLE`.
- Zoom 200%, demais perfis, cache-hit e revisão visual humana pendem.


## Não versionado — Wiki em UI Patterns Views (2026-11-11)

- `wiki_entries` migrou o style de listagem para `layout-grid`; page e blocos compartilham a grade. O teaser `wiki-entry` e a consulta nativa da View foram mantidos.
- `layout-grid` agora declara `max_columns` no schema e limita 2/3/4 colunas; a Wiki usa duas colunas a partir de 768px. Firefox ESR: 215/215 em três páginas, cinco larguras e dois modos. Zoom, cache-hit e perfis seguem pendentes.

## Não versionado — categorias Wiki em UI Patterns Views (2026-11-11)

- `wiki_categories:block_1` usa o SDC existente `layout-grid` via UI Patterns; renderização de termos e links continuam sob Taxonomy/Views. Reflow validado de 1 para 2 colunas com 15 termos preservados.
- Firefox ESR passou 247/247 verificações em cinco larguras, três páginas e temas claro/escuro.
## Não versionado — violação CSP de estilos inline (2026-11-11)

- UI Skins recebe nonce por resposta no seu `<style>` gerado. HTMX usa estilos
  dos indicadores na folha CSS externa do tema. O placeholder do modal Drupal
  é estático e ocultado por CSS, sem `style` inline criado pelo Core.
- Sem `unsafe-inline` novo. Playwright observou zero eventos `style-src` em
  rotas públicas válidas de Portal, Eventos, Loja e Conta. O componente admin
  anônimo respondeu 403; a cobertura autenticada desta rodada fica pendente.
- Stylelint `base.css`, fundações (83 pares), PHPUnit focal (27 testes), PHP
  lint, `drush cr` e `git diff --check` passaram.

