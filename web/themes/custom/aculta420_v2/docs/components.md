# Catálogo de componentes do tema V2

Componentes SDC com schemas estritos (`additionalProperties: false`). Os nomes de prop e slot abaixo são os do `*.component.yml`. "Estado" indica o que foi entregue e verificado; a auditoria integral do catálogo (Fase 5) ainda não foi feita.

Prefixo de uso: `aculta420_v2:<nome>`. Em Twig, use `include` com `with_context = false`, ou `embed` quando o componente tem slots.

### layout-page

Estrutura o landmark main nas variantes stack, aside e full; stories:
layout-page.default (aside), layout-page.stack e layout-page.full. Slots: title, content
e aside. O conteúdo principal precede o complementar no DOM; aside só forma
duas colunas a partir de 60rem quando tem conteúdo. Não escolhe tipo nem
domínio, responsabilidade reservada ao preprocess da fase P2. Sem JavaScript.

### avatar

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

### lms-lesson-page

Contêiner visual para o render array entregue pelo controlador do LMS na rota
de aula. Não recebe entidade, aluno ou dados de acesso como prop. Slot único:
content. Story: lms-lesson-page.default.story.yml. Sem JavaScript; tabela rola
dentro do próprio bloco em telas estreitas. O template usa fallback ao render
original caso a descoberta Drupal ainda não conheça o SDC.

## Shell e navegação

### multidomain-bar — Barra institucional
- **Papel:** faixa institucional fixa com logotipo, utilidades (busca, carrinho, mensagens, avisos, conta) e vitrine de portais na home principal.
- **Props:** `brand_home_url`, `logo_base_url`, `logo_alt`, `is_account_domain`, `show_utility_bar`, `account_authenticated`, `cart_*`, `messages_*`, `alerts_*`, `links`.
- **Slots:** `search`, `account_menu`.
- **JS:** `multidomain-bar.js` (altura, login e busca por Fetch em `<dialog>`).
- **Estado:** entregue. Verificada no ambiente anônimo; estado autenticado e leitor de tela pendentes.

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
- **Props:** `title`, `url`, `category`, `date`, `location`, `summary`, `variant` (`default` ou `compact`).
- **Slots:** `image`, `actions`.
- **Estado:** entregue. A loja deve usar este componente no modo de catálogo (Fase 4).

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
- **Papel:** mensagem anunciada a tecnologias assistivas.
- **Props:** `type` (`status`, `warning` ou `error`), `messages`.
- **Estado:** entregue.

### social-links-page — Micropágina de links
- **Papel:** apresenta título, descrição, identidade pública autorizada e links preparados pelo submódulo `aculta_links`.
- **Props:** `title`, `description`, `has_links`, `theme_preset` (`classic`, `soft` ou `outline`).
- **Slots:** `owner_identity`, `links` (render arrays do Portal).
- **Story:** `social-links-page.default.story.yml`.
- **Regra:** o tema só apresenta os dados do módulo; não consulta dono, entidades ou destinos.
- **Estado:** adapter e componente implementados em C4; validação visual pendente.

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
- **Estado:** implementação presente, com gate de render SDC. Cumpre a função de Author box no slot `authors` de `revista-publication`, usando identidade, avatar e biografia fornecidos pelo Portal; não existe necessidade documentada para um segundo SDC homônimo. Fixture composta renderizada pelos dois SDCs passou em Firefox, modos claro/escuro, 320–1440 px, zoom 200% e foco por teclado; conteúdo publicado/autenticado no Runtime permanece sem validação.

### revista-teaser — Cartão editorial
- **Papel:** substitui a apresentação V1 do teaser de publicação na listagem da Revista.
- **Props:** `title`, `url`, `has_category`, `has_subtitle`, `has_summary`, `has_cover`, `read_label`.
- **Slots:** `image`, `category`, `subtitle`, `summary`.
- **Story:** `revista-teaser.default.story.yml`.
- **Regra:** recebe render arrays do node view, não resolve conteúdo nem cache; a capa ausente usa apenas uma superfície decorativa.
- **Estado:** implementação pronta; aguardando gates SDC e verificação de Runtime.

### revista-listing — Listagem editorial
- **Papel:** apresenta o H1 editorial e a grade da View `revista_sessoes:page_revista`.
- **Props:** `eyebrow`, `title`, `has_items`.
- **Slots:** `items` (saída renderizada da View).
- **Story:** `revista-listing.default.story.yml`.
- **Regra:** não reordena o DOM nem consulta conteúdo; substitui o título genérico só na presença da listagem e mantém uma, duas e três colunas conforme o espaço.
- **Estado:** implementação pronta; aguardando gates SDC e verificação de Runtime.

### revista-publication — Publicação completa
- **Papel:** compõe metadados, capa, chamada, resumo, corpo, conteúdo editorial adicional, autoria e discussão.
- **Props:** `has_meta`, `has_subtitle`, `has_summary`, `has_cover`, `has_footer`, `has_authors`, `has_discussion`.
- **Slots:** `meta`, `cover`, `subtitle`, `summary`, `body`, `footer`, `authors`, `discussion`.
- **Story:** `revista-publication.default.story.yml`.
- **Regra:** os dados e controles de acesso permanecem nos render arrays de `aculta_magazine` e `aculta_user`; o componente controla apenas a ordem e a apresentação.
- **Estado:** implementação pronta; aguardando gates SDC e verificação de Runtime.

### wiki-entry — Verbete da Wiki
- **Papel:** apresenta verbetes completos e cartões de listagem, portando a hierarquia editorial da V1 para Wiki420.
- **Props:** `variant`, `url`, `has_summary`, `has_image`, `has_categories`, `has_related`, `has_references`, `has_discussion`.
- **Slots:** `title`, `summary`, `image`, `categories`, `body`, `related`, `references`, `discussion`, `actions`.
- **Stories:** `wiki-entry.default.story.yml` e `wiki-entry.teaser.story.yml`.
- **Regra:** conserva os render arrays e controles de acesso do Drupal; não consulta conteúdo nem decide acesso. O título da página completa é dono do H1; o título genérico fica oculto apenas quando o adapter está presente.
- **Estado:** renderização e comparação Firefox do Runtime passaram para home, listagem e artigo (320–1440 px, claro/escuro); zoom físico, autenticação e leitor de tela ainda pendentes. A antiga rota de busca devolve 404 e não foi recriada pelo tema.


## Lacunas do catálogo

Componentes previstos no ADR-046: `avatar`, `tabs`, `dialog`, `form-field`, `progress`, `data-table` e `empty-state` têm SDC próprio; a função de `author-box` é coberta pelo slot de autoria de `revista-publication` e pelo SDC compartilhado `author-card`. `price` aguarda autorização por tarefa: é apresentação ligada a Commerce/Loja e não há contrato visual ou consumer neutro definido. `breadcrumb` e `accordion` já existem. Ordem e uso estão no [roadmap.md](roadmap.md).

## Como documentar um componente novo

Cada entrada tem: papel, props, slots, JavaScript (se houver), estado e a regra que ele não viola (por exemplo, "não lê entidade"). Atualize este arquivo no mesmo commit do componente.
