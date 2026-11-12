# Catálogo de componentes do tema V2

Componentes SDC com schemas estritos (`additionalProperties: false`). Os nomes de prop e slot abaixo são os do `*.component.yml`. "Estado" indica o que foi entregue e verificado. Auditoria do catálogo em 2026-11-06: os 41 diretórios de `components/` têm entrada aqui, schema e story; não há entrada sem componente.

Prefixo de uso: `aculta420_v2:<nome>`. Em Twig, use `include` com `with_context = false`, ou `embed` quando o componente tem slots.

### heading — Título semântico

- **Papel:** renderiza H1–H6 com a escala Oswald da referência "Tipografia do Portal ACULTA420 V2", superfície semântica e destaque tricolor (H1/H2) ou barra lateral colorida (H3–H6).
- **Props:** `text`, `level` (1–6, padrão 2), `style` (`auto`, `plain`, `bar`, `stripe`; `auto` escolhe tricolor em H1/H2 e barra em H3–H6), `accent` (`green`, `yellow`, `red`, `dark-green`), `surface` (`none`, `canvas`, `raised`, `institution`, `highlight`) e `align` (`start`, `center`), além de `attributes`.
- **Slots:** `content` (markup já renderizado; tem precedência sobre `text`).
- **Stories:** `heading.default.story.yml`, `heading.h1.story.yml`, `heading.h3-bar.story.yml`, `heading.h4-yellow.story.yml`, `heading.h5-red.story.yml`, `heading.h6-dark-green.story.yml`, `heading.plain.story.yml`, `heading.surface-institution.story.yml` e `heading.surface-highlight.story.yml`.
- **Tokens:** escala, pesos, alturas de linha e espaçamentos em `--heading-*`; cores e superfícies pelos tokens semânticos (`--color-text-primary`, `--color-surface-*`, `--surface-accent-*`).
- **Regra:** não decide o nível semântico do documento nem duplica H1; o chamador informa `level`. O H1 da rota é renderizado pelo override `templates/page-title.html.twig`. A escala mínima atende 320/390 px e `overflow-wrap: break-word` evita quebrar palavras no meio.
- **Estado:** schema estrito e render isolado no gate V2; browser Firefox em 1280/1920/390 px, claro e escuro, com contraste medido e axe sem bloqueio (2026-11-12). Estilos de acento e superfície expostos em `docs/ui-styles.md`.

### account-shell — Shell da Conta

- **Papel:** estrutura da Minha Conta com menu, acordeão e conteúdo, preservando os atributos de comportamento do Portal.
- **Props:** `title`, `section_title`, `menu_items`, `accordion_items` e `logout`.
- **Slots:** `content`.
- **Story:** `account-shell.default.story.yml`.
- **Regra:** o H1 "Minha conta" é entregue pelo SDC `heading` (nível 1, faixa tricolor); o tema não lê usuário nem decide acesso.
- **Estado:** SM do H1 atualizado em 2026-11-12; validação autenticada continua pendente.

### document-card

Cartão editorial de documento institucional com props estritas `title` e
`url`; slots `category`, `summary` e `attachment` recebem render arrays dos
campos do node. Story: `document-card.default.story.yml`. Sem JavaScript; CSS
local. O adapter preserva renderização, acesso e cache da Field API e não lê
entidade nem usuário diretamente.

### product-card

Cartão de produto da vitrine Commerce, prop opcional `attributes` e slot
`content`. O adapter do view mode `catalog` passa, sem filtragem, todos os
campos e o formulário de compra do Commerce como render array; o Twig remove
somente `variation_attributes`, conforme o template upstream padrão. Story:
`product-card.default.story.yml`. Sem JavaScript; CSS local.

### layout-section

Seção empilhada com slot `header` (título/antetítulo/ação) e slot `content`.
Prop `tone` (`default` ou `raised`; `raised` usa `--color-surface-raised`).
O SDC também aceita `section_variant` (`default`, `city`, `transparency`) e
`section_color` (`default`, `red`, `green`, `yellow`, `green-dark`) para os
blocos editoriais do Portal.
Story: `layout-section.default.story.yml`. Sem JavaScript; CSS local. O
`ui_patterns_layouts` o descobre como layout de seção do Layout Builder
(regiões `header` e `content`).

### layout-two-columns

Conteúdo principal (`primary`) e coluna complementar (`secondary`); prop
`ratio` (`equal` ou `wide`, padrão `equal`). Uma coluna no mobile, duas a
partir de 60rem. Story: `layout-two-columns.default.story.yml`. Sem JavaScript.

### layout-grid

Grade automática (`auto-fit` + `minmax`) no slot `content`; prop
`min_item_width` (`12rem`, `16rem` ou `20rem`, padrão `16rem`) e `max_columns`
(`0` automático sem limite, `2`, `3` ou `4`). Sem estilo inline: largura mínima
e limite viram classes. Story: `layout-grid.default.story.yml`.

### layout-narrow

Coluna de leitura de até 68 caracteres no slot `content`. Story:
`layout-narrow.default.story.yml`.

### layout-full

Conteúdo em largura total no slot `content`, sem limite de largura. Story:
`layout-full.default.story.yml`.

Os cinco layouts usam `group: Layouts` e são registrados automaticamente pelo
`ui_patterns_layouts` (categoria `Layouts`, regiões iguais aos slots). O gate
`validate-aculta420-layouts.php` verifica descoberta e renderização real.

### layout-page

Estrutura o landmark main nas variantes stack, aside e full; stories:
layout-page.default (aside), layout-page.aside-no-title, layout-page.stack,
layout-page.stack-no-title, layout-page.full e layout-page.full-no-title.
Slots: title, content
e aside. O conteúdo principal precede o complementar no DOM; a partir de 60rem,
aside com conteúdo forma duas colunas, com a área complementar à esquerda
apenas no visual (CSS Grid). Sem aside, a página permanece em uma coluna.
O fallback do shell espelha a geometria; validar foco, landmarks e zoom no
Runtime. O preprocess escolhe a variante por rota/conteúdo, sem resolver
identidade visual de domínio no SDC. Sem JavaScript.

### avatar

Consumer atual: `account-menu` usa o componente no resumo e no painel de
identidade (32 px, `size: sm`), com a mídia entregue pelo Portal e o fallback
neutro sem foto. `author-card` mantém o slot de autoria.

### avatar — contrato

Apresenta a mídia fornecida pelo módulo proprietário no slot `media` e mantém o
texto alternativo do render array. Sem mídia, mostra o ícone neutro `user-circle`
com nome acessível; não gera iniciais nem consulta usuário. Props: `size`
(`xs`, `sm`, `md`, `lg`, `xl`) e `label` (usado no fallback). Stories:
`avatar.default.story.yml` e `avatar.fallback.story.yml`. Sem JavaScript.

### tabs

Navegação horizontal rolável entre páginas, não troca de painel. Prop: `label`;
slot `items` recebe a lista do Drupal e preserva URLs, access/cache e
`aria-current="page"`. Story: `tabs.default.story.yml`. Links mantêm navegação
com e sem JavaScript; alvos de 44 px e sem role `tablist`.

### dialog

Janela modal HTML nativa com título, botão de fechar, conteúdo e ações em slots.
Props: `id`, `title`, `trigger_label`, `trigger_url`, `close_label` e `variant`
(`default` ou `fullscreen`). Story: `dialog.default.story.yml`. Reutiliza
`utility-drawers` e `js/utility-dialogs.js` para abrir/fechar; o gatilho é um
link para uma rota completa quando JavaScript está indisponível. Slots:
`content` e `actions`. A variante fullscreen ocupa a tela no mobile e volta à
largura modal a partir de 40rem.
Conteúdo, permissões, URLs e ações permanecem sob responsabilidade do módulo
proprietário; o componente não consulta entidades nem dados de domínio. Escape
e o botão fecham; Tab/Shift+Tab mantêm o foco dentro do diálogo e o fechamento
devolve foco ao gatilho. O gate Drupal renderiza o componente e comprova a
descoberta automática de `dialog.css` e a dependência JS. Firefox visível passou
em fixture sintética (32 checks); consumer Drupal publicado/autenticado permanece
sem validação.

### auth-dialog

Diálogo/Modal de autenticação e login completo da UI Library com as listras de acento tricolores (verde, amarelo e vermelho: `--accent-green-stripe`, `--accent-yellow-stripe`, `--accent-red-stripe`).
Props: `id`, `title` (padrão: "Entrar na ACULTA"), `subtitle` ("Acesse sua conta e continue de onde parou."), `header_variant` (`neutral` ou `institutional`), `show_accent_stripes` (booleano), `close_label`, `google_auth_url`, `password_reset_url` e `register_url`. Slot: `content` (recebe os campos do formulário Form API e o botão de envio).
Stories: `auth-dialog.default.story.yml` (Superfície Neutra) e `auth-dialog.institutional.story.yml` (Faixa Institucional Verde).
Inclui botão Google com ícone de marca, divisor centralizado "ou entre com sua conta", rodapé com link para cadastro e selo de autenticação segura. Suporta nativamente modo claro e escuro por tokens semânticos e `prefers-reduced-motion`.

### accordion

Resposta expansível baseada em details/summary, sem JavaScript e operável por
teclado nativamente. Props: title e expanded; slot: content. Story:
accordion.default.story.yml. Usado pelo template node--aculta-faq--full; quando
o cache SDC Drupal ainda não conhece o componente, o template mantém uma saída
equivalente com HTML nativo.

### form-field

Envolve o rótulo, o controle Drupal, a instrução e o erro textual em uma
composição visual única. Props: `id` (identificador do wrapper) e `invalid`.
Slots: `label`, `control`, `description` e `error`. Stories:
`form-field.default.story.yml` e `form-field.error.story.yml`. O consumidor
preserva a associação `for`/`id` do controle e associa os IDs
`<id>-description` e `<id>-error` por `aria-describedby`; o controle também
recebe `aria-invalid="true"` quando aplicável. O componente não cria nem altera
controles Drupal e não valida dados.

### progress

Indicador HTML nativo `<progress>` com rótulo acessível e percentual visível
quando o valor é conhecido. Props: `id`, `label`, `value` (null para estado
indeterminado) e `max`. Stories: `progress.default.story.yml` e
`progress.indeterminate.story.yml`. O controle conserva a semântica nativa e
não busca dados nem estima progresso; valores acima de `max` são limitados ao
máximo para a apresentação.

### data-table

Apresenta uma tabela HTML semântica, com variante de cartões em telas abaixo de
40rem ou rolagem horizontal local. Props: `label` e `mobile_variant`
(`cards` ou `scroll`, padrão `scroll`). Slot: `table`. Stories:
`data-table.default.story.yml` e `data-table.scroll.story.yml`. Na variante
`cards`, o consumer fornece `role="table/rowgroup/row/cell"`, `scope` e
`headers` nos cabeçalhos/células, além de `data-label` em cada célula para os
rótulos inline. A variante `scroll` segue ADR-040; o JS adiciona role `region`,
nome e foco ao wrapper apenas quando há overflow, removendo-os e limpando
observers no detach Drupal. O componente não cria linhas nem altera
acesso/cache dos render arrays.

### empty-state

Apresenta uma mensagem e, opcionalmente, ações fornecidas pelo consumer quando
uma lista autorizada não tem resultados. Prop: `compact` (boolean, padrão
`false`). Slots: `message` e `actions`. Stories: `empty-state.default.story.yml`
e `empty-state.compact.story.yml`. O componente não consulta dados, decide se
a lista está vazia nem cria links; texto, permissões, URLs e cache permanecem
no módulo proprietário. Usa tokens semânticos V2 e não depende de JavaScript.

### event-page-layout

Regiões semânticas para o detalhe público de evento em ordem mobile-first.
Props: nenhuma (`additionalProperties: false`). Slots: `title`, `highlight`,
`essentials`, `content` e `sidebar`. Story: `event-page-layout.default.story.yml`.
Sem JavaScript. O componente não consulta entidade, mídia, datas ou acesso; o
consumidor entrega os render arrays prontos e o `aculta_events` mantém tipo,
permissões e cache.

### lms-lesson-page

Contêiner visual para o render array entregue pelo controlador do LMS na rota
de aula. Não recebe entidade, aluno ou dados de acesso como prop. Slot único:
content. Story: lms-lesson-page.default.story.yml. Sem JavaScript; tabela rola
dentro do próprio bloco em telas estreitas. O template usa fallback ao render
original caso a descoberta Drupal ainda não conheça o SDC.

### lms-tour

Apresentação acessível em diálogo nativo HTML para o percurso guiado do
estudante no LMS. Embedda o SDC dialog com indicador de progresso e slots para
conteúdo e ações. Props: id, title, trigger_label, trigger_url, current_step
(padrão welcome), total_steps (padrão 4). Slots: content e actions. Story:
lms-tour.default.story.yml. O componente não consulta entidades nem decide
etapas; controle de fluxo e permissões permanecem no módulo proprietário
(aculta_lms).


## Shell e navegação

### multidomain-bar — Barra institucional
- **Papel:** faixa institucional fixa com logotipo, utilidades (busca, carrinho, mensagens, avisos, conta) e vitrine de portais na home principal.
- **Props:** `brand_home_url`, `logo_base_url`, `logo_alt`, `is_account_domain`, `show_utility_bar`, `account_authenticated`, `cart_*`, `messages_*`, `alerts_*`, `links`.
- **Slots:** `search`, `account_menu`.
- **JS:** `multidomain-bar.js` (login e busca por HTMX, com fragmento fornecido pelo Portal; gatilhos nativos preservados como fallback e `htmx:abort` ao fechar).
- **Estado:** entregue. Verificada em 320, 390, 768, 959, 960 e 1440 px, claro/escuro, zoom 200%, anônimo e autenticado (gavetas e conta de teste); leitor de tela permanece pendente.

### navigation-bar — Navegação principal
- **Papel:** envolve o menu principal Drupal. Visível sem JavaScript; com JavaScript, vira drawer no mobile.
- **Props:** `site_name`, `site_logo_url`, `menu_id` (padrão `aculta-primary-navigation`).
- **Slots:** `menu_items`, `utility_items`.
- **JS:** `navigation-bar.js` (abre, fecha com Escape, devolve o foco).
- **Estado:** entregue (Fase 2, `1.2.0-dev.2`).

### account-menu — Menu de conta e sites

- **Papel:** disclosure `<details>` na barra institucional, com a lista de sites publicados e uma seção de sessão. Visitante tem o link "Entrar"; autenticado tem avatar/nome e os itens de conta.
- **Props:** `authenticated`, `summary_label`, `account_name`, `sites` (`purpose`, `title`, URL absoluta e `current`). O site atual recebe `aria-current="page"`; a navegação entre sites usa links normais.
- **Slots:** `avatar` (render array de `aculta_user`; sem slot, ícone neutro), `menu_items` (render array Drupal com access/cache preservados).
- **Stories:** `account-menu.anonymous.story.yml`, `account-menu.authenticated.story.yml`.
- **Montagem:** `PagePresentationHooks::buildAccountMenuComponent`; normaliza apenas os campos contratuais. Render array varia por `user`.
- **Regras:** nome de exibição só no bloco de sessão, nunca no resumo da barra; sem inicial/letra no avatar. Alvo do controle 35 px e ícone 30 px.
- **Estado:** implementação C2 no tema e contratos Portal D1/D2 disponíveis. Capturas autenticadas e validação dos três estados de avatar permanecem pendentes.
- **Status no schema:** `experimental`.

### site-footer — Rodapé
- **Papel:** apresenta os blocos da região Drupal `footer` ou os canais sociais preparados pelo Portal.
- **Props:** `variant` (`institutional` ou `social-links`).
- **Slot:** `content` (render array Drupal, com acesso e cache preservados).
- **Stories:** `site-footer.default.story.yml`, `site-footer.social-links.story.yml`.
- **Estado:** variação social integrada por C4; validação visual pendente.

### alerts-trigger — Avisos
- **Papel:** botão com contagem e diálogo de avisos carregado sob demanda.
- **Props:** `href`, `all_href`, `count`, `count_kind` (`active` ou `attention`).
- **Estado:** entregue.

### messenger-trigger — Mensagens
- **Papel:** botão com contagem de mensagens não lidas e diálogo de conversas.
- **Props:** `href`, `all_href`, `count`.
- **Estado:** entregue.

### cart-offcanvas — Carrinho
- **Papel:** gatilho e painel lateral do carrinho, com itens agrupados por site e link para o checkout no purpose principal.
- **Props:** `count`, `empty`, `url`, `total`, `sites` (com `items`), `checkout_url`, `banner`.
- **Estado:** entregue. O checkout e o pagamento pertencem ao purpose principal (PAYMENT-DOMAIN-POLICY).

### pagination — Paginação
- **Papel:** navegação entre páginas de resultados.
- **Props:** `current_page`, `total_pages`, `links`.
- **Estado:** entregue.

### breadcrumb — Navegação estrutural

Trilha de navegação com o nome acessível configurável. Prop: `label` (padrão
`Navegação estrutural`). Slot: `items`, com os itens já preparados pelo template
do Drupal; o componente preserva URLs e o estado atual marcado pelo Core. Story:
`breadcrumb.default.story.yml`. Sem JavaScript; sem link inventado e sem leitura
de entidade, menu ou domínio.

### purpose-showcase — Vitrine de portais

Links entre os portais ativos da rede ACULTA. Props: `heading` (padrão
`Ecossistema ACULTA`) e `links` (lista de `title` e `url`). Story:
`purpose-showcase.default.story.yml`. Sem JavaScript e sem hostname fixo: as
URLs vêm do contrato neutro do Portal; o componente só apresenta a lista
autorizada.

### filter-chips — Filtros
- **Papel:** faixa rolável de filtros, com link e estado atual explícitos.
- **Props:** `items`.
- **Estado:** entregue.

## Conteúdo e apresentação

### hero-banner — Abertura de página
- **Papel:** título, chamada opcional e mídia de fundo.
- **Props:** `title`, `tagline`, `cta_text`, `cta_url`.
- **Slots:** `background_media`.
- **Estado:** entregue.

### card-cultural — Cartão cultural
- **Papel:** cartão para eventos e pontos culturais.
- **Props:** `title`, `url`, `category`, `date`, `location`, `summary`, `variant` (`default` ou `compact`), `accent` (`green`, `yellow`, `red` ou ausente), `cancelled` e `cancel_label`. Stories DRAFT de acento: `card-cultural.accent-draft.story.yml`, `card-cultural.accent-yellow-draft.story.yml` e `card-cultural.accent-red-draft.story.yml`; story de cancelamento: `card-cultural.event-cancelled.story.yml`. O tema recebe dados resolvidos pelo Portal; stories não são selecionadas pelo tema.
- **Slots:** `image`, `actions`.
- **Estado:** apresentação consumida pelo catálogo público de Eventos via override fino no tema; dados, ACL, filtro e cache continuam no módulo owner. Faixa de acento permanece separada e DRAFT até D1 (`domain_presentation.accent`). Render do SDC no tema ativo e browser visual aguardam cutover/runtime.

### forum-topic — Tópico do Fórum
- **Papel:** teaser e conteúdo completo do tópico, preservando autoria, categorias, favoritos e discussão nos render arrays Drupal.
- **Props:** `variant` (`teaser` ou `full`), `has_title`, `has_meta`, `has_bookmark`.
- **Slots:** `title`, `meta`, `bookmark`, `content`.
- **Consumers:** view mode de node `aculta_forum_topic`, View pública de tópicos e discussão compartilhada.
- **Estado:** adapter, CSS e testes visuais anônimos no Runtime concluídos em `1.2.0-dev.15`; estados de comentário/autenticado pendentes.

### forum-category — Categoria do Fórum
- **Papel:** cartão de categoria para a View pública e o bloco de categorias da landing.
- **Props:** nenhuma.
- **Slots:** `parent`, `title`, `description`, `count`.
- **Estado:** adapters de Views para a página de categorias e a landing usam o mesmo SDC; Firefox ESR cobriu ambos os consumers em nove larguras e dois modos.

### discussion-comment — Comentário de discussão
- **Papel:** apresentação de autoria pública, data, conteúdo e links de comentário.
- **Props:** `has_author`, `has_submitted`.
- **Slots:** `author`, `submitted`, `body`, `footer`.
- **Consumer:** template de comentário `aculta_discussion_post`.
- **Estado:** template V1 e CSS portados; não há comentário desse bundle nas páginas do Runtime verificadas.

### map-popup — Balão de mapa
- **Papel:** cartão compacto para um marcador de mapa.
- **Props:** `title`, `url`, `category`, `address`, `route_url`.
- **Slots:** `thumbnail`.
- **Estado:** entregue. Os tiles de mapa não mudam sem revisão de privacidade (ver roadmap).
- **Ponte com `aculta_maps`:** `templates/aculta-maps-popup-content.html.twig` inclui o SDC com as props do hook `aculta_maps_popup_content`; sem o tema, o módulo usa o fallback neutro. O conteúdo fica num `<template>` inerte clonado pelo Leaflet; nada de HTML ou SVG vai por `drupalSettings`.
- **Stories:** `default` (todas as props) e `minimal` (só título).

### audio-player — Player de áudio
- **Papel:** player com controles nativos acessíveis.
- **Props:** `src`, `title`, `artist`.
- **Estado:** entregue.

### badge — Etiqueta
- **Papel:** rótulo compacto de categoria ou estado.
- **Props:** `label`, `variant` (`primary`, `highlight` ou `neutral`), `icon_name`.
- **Estado:** entregue.

### button — Botão
- **Papel:** ação ou link com estados acessíveis.
- **Props:** `text`, `url`, `variant` (`primary`, `secondary`, `outline` ou `ghost`), `is_submit`, `disabled`, `icon_before`, `icon_after`.
- **Estado:** entregue.

### status-messages — Mensagens de estado
- **Papel:** mensagem anunciada a tecnologias assistivas, incluindo estados do carrinho.
- **Props:** `type` (`status`, `warning` ou `error`), `messages`, `role` opcional (`status`/`alert`), `dismissible` e `close_label`.
- **Integração:** `portal-cart-status.html.twig` converte sucesso do Portal para `status`, preserva erro/role, link e atributos consumidos pelo JS; o fallback do módulo continua sem estilos.
- **Estado:** entregue.

### social-links-page — Micropágina de links
- **Papel:** apresenta título, descrição, identidade pública autorizada e links preparados pelo módulo `aculta_linkweed` (o antigo `aculta_links` foi removido em 2026-11-05).
- **Props:** `title`, `description`, `has_links`, `theme_preset` (`classic`, `soft` ou `outline`).
- **Slots:** `owner_identity`, `links` (render arrays do Portal).
- **Story:** `social-links-page.default.story.yml`.
- **Regra:** o tema só apresenta os dados do módulo; não consulta dono, entidades ou destinos. Desde 2026-11-12 o H1 é renderizado pelo SDC `heading` (nível 1, faixa tricolor), mantendo o `id` usado por `aria-labelledby`.
- **Estado:** adapter e componente implementados em C4; H1 pelo SDC em 2026-11-12; validação visual pendente.

### icon — Ícone
- **Papel:** ícone Phosphor vetorial, injetado inline e oculto para leitores de tela (`aria-hidden`).
- **Props:** `name`, `variant` (`duotone` ou `regular`), `size` (`sm`, `md`, `lg`, `xl` ou `bar`), `color`.
- **Estado:** `bar` mede 30px para os controles da barra institucional (ADR-047, item 10).
- **Estado:** entregue. Ícones ausentes do vendor são ignorados (`ignore_missing`).

### author-card — Cartão de autoria
- **Papel:** apresenta identidade pública e contextual preparada pelo `aculta_user`, inclusive autoria editorial.
- **Props:** `variant`, `size`, `display_name`, `profile_url`, `source_label`, `has_avatar`, `has_actions`, `member_since`, `designation`, `bio`, `social_links`.
- **Slots:** `avatar`, `actions`.
- **Story:** `author-card.default.story.yml`.
- **Regra:** não carrega usuário/Profile, não decide visibilidade e não inventa iniciais; os slots conservam os render arrays cacheáveis do módulo proprietário.
- **Estado:** implementação presente, com gate de render SDC. Cumpre a função de Author box no slot `authors` de `revista-publication`, usando identidade, avatar e biografia fornecidos pelo Portal; não existe necessidade documentada para um segundo SDC homônimo. O consumer `profile-identity` do Studio também usa a variante `profile_full`; comparação antes/depois em `/meu-perfil` passou no Firefox ESR (89/89, 22 ignoradas), claro/escuro, 320/1440 px, foco por teclado e zero overflow. Capturas em `$SUITE_OUT/layouts-purpose-studio-profile-identity-paired-20261106/{before,after}/`. A rota pública de perfil continua sem validação de Runtime.

### profile-details — Detalhes de perfil
- **Papel:** apresenta biografia ou redes sociais já preparadas pelo `aculta_user` no Studio.
- **Props:** `title`, `title_id` e `heading_level` (2 ou 3), para associar `aria-labelledby` ao título sem perder a hierarquia semântica.
- **Slots:** `bio`, `social_links` e `actions`; todos preservam render arrays do módulo proprietário.
- **Story:** `profile-details.default.story.yml`.
- **Regra:** o tema não carrega usuário/Profile, não transforma render arrays em texto ou links e não decide autorização/visibilidade.
- **Estado:** `profile-bio` e `profile-social` mapeados pelo preprocess de apresentação. Schema e render Drupal passaram; captura pareada autenticada em `/meu-perfil` passou 97/97 verificações (22 ignoradas, zero falhas), em claro/escuro a 320/1440 px, com títulos acessíveis, foco por teclado e zero overflow. Evidências em `$SUITE_OUT/layouts-purpose-studio-profile-details-paired-20261106/{before,after}/`.

### revista-teaser — Cartão editorial
- **Papel:** substitui a apresentação V1 do teaser de publicação na listagem da Revista.
- **Props:** `title`, `url`, `has_category`, `has_subtitle`, `has_summary`, `has_cover`, `read_label`.
- **Slots:** `image`, `category`, `subtitle`, `summary`.
- **Story:** `revista-teaser.default.story.yml`.
- **Regra:** recebe render arrays do node view, não resolve conteúdo nem cache; a capa ausente usa apenas uma superfície decorativa.
- **Estado:** implementação pronta; aguardando gates SDC e verificação de Runtime.

### revista-listing — Listagem editorial
- **Papel:** apresenta o H1 editorial e a grade da View `revista_sessoes:page_revista`.
- **Props:** `eyebrow`, `title`, `title_level` (`h1` ou `h2`, padrão `h2`), `has_items`.
- **Slots:** `items` (saída renderizada da View).
- **Story:** `revista-listing.default.story.yml`.
- **Regra:** não reordena o DOM nem consulta conteúdo; substitui o título genérico só na presença da listagem e mantém uma, duas e três colunas conforme o espaço. O H1 da página pertence ao título da rota; a listagem usa `h2` por padrão para não duplicar o título principal. Desde 2026-11-12 o título é renderizado pelo SDC `heading` (nível conforme `title_level`, faixa tricolor).
- **Estado:** implementação pronta; gates SDC e browser Firefox (1280/1920/390 px, claro/escuro) passaram em 2026-11-12.

### revista-publication — Publicação completa
- **Papel:** compõe metadados, capa, chamada, resumo, corpo, conteúdo editorial adicional, autoria e discussão.
- **Props:** `has_meta`, `has_subtitle`, `has_summary`, `has_cover`, `has_footer`, `has_authors`, `has_discussion`.
- **Slots:** `meta`, `cover`, `subtitle`, `summary`, `body`, `footer`, `authors`, `discussion`.
- **Story:** `revista-publication.default.story.yml`.
- **Regra:** os dados e controles de acesso permanecem nos render arrays de `aculta_magazine` e `aculta_user`; o componente controla apenas a ordem e a apresentação.
- **Estado:** implementação pronta; aguardando gates SDC e verificação de Runtime.

### wiki-entry — Verbete da Wiki
- **Papel:** apresenta verbetes completos e cartões de listagem, portando a hierarquia editorial da V1 para Wiki420.
- **Props:** `variant`, `title_level` (`h1` ou `h2`, padrão `h2`; o H1 pertence ao título da rota), `has_summary`, `has_image`, `has_categories`, demais flags de apresentação.
- **Props:** `variant`, `url`, `has_summary`, `has_image`, `has_categories`, `has_related`, `has_references`, `has_discussion`.
- **Slots:** `title`, `summary`, `image`, `categories`, `body`, `related`, `references`, `discussion`, `actions`.
- **Stories:** `wiki-entry.default.story.yml` e `wiki-entry.teaser.story.yml`.
- **Regra:** conserva os render arrays e controles de acesso do Drupal; não consulta conteúdo nem decide acesso. O título da página completa é dono do H1; o título genérico fica oculto apenas quando o adapter está presente. Desde 2026-11-12 o título do artigo é renderizado pelo SDC `heading` (nível conforme `title_level`, faixa tricolor).
- **Estado:** renderização e comparação Firefox do Runtime passaram para home, listagem e artigo (320–1440 px, claro/escuro); título pelo SDC em 2026-11-12; zoom físico, autenticação e leitor de tela ainda pendentes. A antiga rota de busca devolve 404 e não foi recriada pelo tema.


## Lacunas do catálogo

Componentes previstos no ADR-046: `avatar`, `tabs`, `dialog`, `form-field`, `progress`, `data-table` e `empty-state` têm SDC próprio; a função de `author-box` é coberta pelo slot de autoria de `revista-publication` e pelo SDC compartilhado `author-card`. `price` aguarda autorização por tarefa: é apresentação ligada a Commerce/Loja e não há contrato visual ou consumer neutro definido. `breadcrumb` e `accordion` já existem. Ordem e uso estão no [roadmap.md](roadmap.md).

## Como documentar um componente novo

Cada entrada tem: papel, props, slots, JavaScript (se houver), estado e a regra que ele não viola (por exemplo, "não lê entidade"). Atualize este arquivo no mesmo commit do componente.

### forum-category-header — Cabeçalho de Categoria do Fórum
- **Papel:** apresenta o cabeçalho de categoria com eyebrow institucional "GROWROOM420 / COMUNIDADE", título da categoria, descrição explicativa e contadores de estatísticas com faixa superior vermelha editorial.
- **Props:** `title` (string, obrigatória), `title_level` (`h1` ou `h2`, padrão `h1`), `description` (string, opcional), `icon` (string, opcional), `stats` (array de `{label, value}`, opcional).
- **Slots:** nenhum.
- **Story:** `forum-category-header.default.story.yml`.
- **Regra:** não lê entidade ou regra de domínio; valores nulos/ausentes de estatísticas são renderizados como placeholder acessível `—<span class="visually-hidden">Dado indisponível</span>`. O título usa o SDC `heading` (nível 1 no padrão). Nas rotas `view.aculta_forum_public_topics.*`, `PagePresentationHooks` não renderiza o título da rota para manter um único H1.
- **Estado:** entregue na Entrega 1 do redesenho visual Growroom420; H1 pelo SDC `heading` e H2/H3 da listagem ajustados em 2026-11-12; schema estrito validado.

### forum-tabs — Abas do Fórum
- **Papel:** barra de navegação horizontal com indicador ativo verde, contadores de itens não lidos e slot de filtros.
- **Props:** `label` (string, obrigatória), `items` (array de `{label, href, active, count}`, obrigatório).
- **Slots:** `filters` (selects e filtros nativos).
- **Story:** `forum-tabs.default.story.yml`.
- **Regra:** rolagem horizontal contida internamente a `.forum-tabs__bar` para preservar viewport de 320 px sem overflow. Sem role artificial de tablist em links de navegação.
- **Estado:** entregue na Entrega 1 do redesenho visual Growroom420; schema estrito validado.

### forum-topic-row — Linha de Tópico do Fórum
- **Papel:** linha da listagem de discussões em tabela com pílulas de status, autor, categoria e contadores em colunas no desktop ou empilhados no mobile.
- **Props:** `pinned` (bool, obrigatório), `badges` (array de `{label, tone}`, opcional), `replies` (opcional), `views` (opcional), `last_activity` (opcional).
- **Slots:** `title`, `meta`, `preview`, `thumbnail`.
- **Story:** `forum-topic-row.default.story.yml`.
- **Regra:** destaque fixado recebe fundo `--color-surface-highlight` e borda lateral verde; dados ausentes (como visualizações) viram placeholder neutro `—`.
- **Estado:** entregue na Entrega 1 do redesenho visual Growroom420; schema estrito validado.

### forum-reply-mirror — Cartão do Post do Fórum Espelhado
- **Papel:** cartão de post do fórum no layout espelhado, para comentários pares: autor, faixa e reações à direita; ações à esquerda.
- **Props:** `author_name` (string, obrigatório), `timestamp`, `author_posts`, `author_reactions` (opcionais).
- **Slots:** `avatar`, `body`, `actions`, `reactions_slot`.
- **Story:** `forum-reply-mirror.default.story.yml`.
- **Regra:** desktop (a partir de 48rem) inverte colunas e faixa; mobile (abaixo de 48rem) muda só a faixa de lado. Faixa de destaque vermelha (`--button-danger-bg`) e linha acima das ações verde (`--color-brand-primary`). Os comentários pares de `field_aculta_discussion` usam as mesmas regras em `src/styles/forum.css`.
- **Estado:** novo; story, README e documentação incluídos.

### forum-opening-post — Postagem de Abertura do Tópico
- **Papel:** primeiro post do tópico na página completa, com o mesmo destaque da linha de tópico fixado.
- **Props:** `author_name` (string, obrigatório), `timestamp` (string, opcional).
- **Slots:** `body`.
- **Story:** `forum-opening-post.default.story.yml`.
- **Regra:** destaque usa barra lateral `--color-brand-primary` e fundo `--color-surface-highlight`; o corpo vem do Drupal, sem leitura de entidade no componente.
- **Uso:** `node--aculta-forum-topic` (visualização completa), nos purposes Fórum e Fórum LMS.
- **Estado:** novo; story e documentação incluídas.

### forum-topic-card — Cartão de Tópico do Fórum
- **Papel:** formato de cartão com miniatura (imagem do tópico ou ícone neutro da categoria), metadados e contadores, implementando o padrão Discourse Topic Cards.
- **Props:** `pinned` (bool, obrigatório), `thumbnail_src` (opcional), `thumbnail_alt` (opcional), `category_icon` (opcional), `badges` (opcional), `replies` (opcional), `views` (opcional), `last_activity` (opcional).
- **Slots:** `title`, `meta`, `preview`.
- **Story:** `forum-topic-card.default.story.yml`.
- **Regra:** nunca utiliza letra ou inicial como avatar/miniatura (apenas imagem ou ícone neutro Phosphor).
- **Estado:** entregue na Entrega 1 do redesenho visual Growroom420; schema estrito validado.

### forum-post-card — Cartão de Post do Fórum
- **Papel:** postagem no tópico com faixa colorida no topo (stripe), identificação de autor, papel, contagem de posts, timestamp, corpo e reações.
- **Props:** `stripe` (enum `brand|highlight|member`), `author_name` (string, obrigatória), `author_role` (opcional), `author_posts` (opcional), `timestamp` (opcional), `reactions` (opcional), `reply_url` (opcional).
- **Slots:** `avatar`, `body`, `actions`, `reply`.
- **Story:** `forum-post-card.default.story.yml`.
- **Regra:** o papel do autor e a variante de faixa vêm do Portal; o tema não consulta papel de usuário nem entidade. Dados ausentes viram placeholder `—`.
- **Estado:** entregue na Entrega 1 do redesenho visual Growroom420; schema estrito validado.

### forum-sidebar-panels — Painéis Laterais do Fórum
- **Papel:** painéis informativos verticais para o fórum com faixas alternadas nas cores vermelha editorial e amarela de destaque (`stats`, `featured`, `moderators`, `rules` e `related`).
- **Props:** `stats` (array de `{label, value}`), `featured` (array de `{title, url, thumbnail, replies}`), `moderators` (array de `{name, role}`), `rules` (array de strings), `related` (array de `{name, url, count}`).
- **Slots:** nenhum.
- **Story:** `forum-sidebar-panels.default.story.yml`.
- **Regra:** campos sem valor ou pendentes no Portal (como moderadores e estatísticas ausentes) são renderizados como placeholder `—<span class="visually-hidden">Dado indisponível</span>` e pílula "Em breve".
- **Estado:** entregue na Entrega 2 do redesenho visual Growroom420; schema estrito validado.

### forum-composer-guidelines — Diretrizes do Compositor do Fórum
- **Papel:** painel de boas práticas e normas de publicação para o formulário `/criar-topico`, com duas colunas no desktop e uma coluna no mobile, faixa amarela de destaque e opção de recolhimento via `<details>` nativo.
- **Props:** `collapsed` (bool), `tips` (array de strings), `community_rules` (array de `{title, text}`).
- **Slots:** nenhum.
- **Story:** `forum-composer-guidelines.default.story.yml`.
- **Regra:** sem dependência de JavaScript obrigatório; opera com `<details>` nativo do HTML.
- **Estado:** entregue na Entrega 2 do redesenho visual Growroom420; schema estrito validado.


## Fórum Growroom420 (entregas 1 e 2)

- `forum-category-header`: prop `actions` (markup de link de ação principal). Story: `forum-category`.
- `forum-tabs`, `forum-topic-row`, `forum-post-card`, `forum-sidebar-panels`, `forum-composer-guidelines`: usados na lista, na página de tópico e em `/criar-topico`.
- `forum-topic-card`: schema e story existentes; sem uso na lista nesta entrega (modo cards pendente, seção 3.4).

### discussion-reply-composer — Compositor de Resposta da Discussão

- **Local:** `components/discussion-reply-composer/`
- **Schema:** estrito, com props `target_id`, `status`, `mode` (`responder`, `citar`, `editar`), `parent_id`, `parent_author`, `parent_subject`, `host_title` e slots `messages`, `context`, `form`.
- **Story:** `discussion-reply-composer.default.story.yml`.
- **Consumer:** template de tema `discussion-reply-composer.html.twig` e módulo `aculta_forum`.

## Estatísticas do Fórum (FST-08)

Componentes neutros de apresentação consumidos por `aculta_statistics`; não consultam banco, acesso ou privacidade.

- `statistics-metric-card`: props `label`, `value`, `unit`, `hint`, `tone` (`neutral|positive|warning`). Story: `statistics-metric-card.default.story.yml`.
- `statistics-trend-chart`: props `title`, `summary`, `caption`, `bars` (`{label, value, percent}`). Barras com valores textuais acessíveis. Story: `statistics-trend-chart.default.story.yml`.
- `statistics-period-filter`: formulário GET sem JavaScript; props `action`, `label`, `options`, `selected`, `submit_label`, `hidden`. Story: `statistics-period-filter.default.story.yml`.
- `statistics-category-ranking`: tabela semântica; props `caption`, `rows`, `empty`. Story: `statistics-category-ranking.default.story.yml`.
- `statistics-activity-summary`, `statistics-response-health`, `statistics-moderation-summary`: listas de definição; props `title`, `rows` (`{label, value, description}`), `note`. Stories homônimas.
- `statistics-empty-state`: props `message`, `hint`. Story: `statistics-empty-state.default.story.yml`.

Estado: implementados para a FST-08; schema validado. Renderização no Runtime e captura visual: pendente.
