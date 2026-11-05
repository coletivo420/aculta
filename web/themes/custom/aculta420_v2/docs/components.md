# Catálogo de componentes do tema V2

Vinte e um componentes SDC, todos com schema estrito (`additionalProperties: false`). Os nomes de prop e slot abaixo são os do `*.component.yml`. "Estado" indica o que foi entregue e verificado; a auditoria integral do catálogo (Fase 5) ainda não foi feita.

Prefixo de uso: `aculta420_v2:<nome>`. Em Twig, use `include` com `with_context = false`, ou `embed` quando o componente tem slots.

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

### account-menu — Menu de conta
- **Papel:** menu de conta da barra institucional (ADR-047). Visitante vê "Entrar". Autenticado vê um disclosure `<details>` só com o avatar e, dentro do painel, as ações de conta.
- **Props:** `authenticated` (boolean), `summary_label` (texto oculto visualmente, dá nome acessível ao botão; padrão "Minha conta").
- **Slots:** `avatar` (render array de `aculta_user`; sem slot, mostra o ícone neutro), `menu_items` (visitante: link "Entrar"; autenticado: menu Drupal de conta).
- **Montagem:** `PagePresentationHooks::buildAccountMenuComponent`. O componente é um render array com `#cache` de contexto `user`.
- **Regras:** o nome do usuário não aparece na barra, nem apelido. Não há inicial nem letra no avatar.
- **Estado:** versionado de `1.2.0-dev.3` a `1.2.0-dev.11`. Pendentes: validação visual autenticada (foto, acervo e sem foto), leitor de tela e gates de renderização no Homelab.
- **Status no schema:** `experimental`.

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

### icon — Ícone
- **Papel:** ícone Phosphor vetorial, injetado inline e oculto para leitores de tela (`aria-hidden`).
- **Props:** `name`, `variant` (`duotone` ou `regular`), `size` (`sm`, `md`, `lg`, `xl`), `color`.
- **Estado:** entregue. Ícones ausentes do vendor são ignorados (`ignore_missing`).

### author-card — Cartão de autoria
- **Papel:** apresenta identidade pública e contextual preparada pelo `aculta_user`, inclusive autoria editorial.
- **Props:** `variant`, `size`, `display_name`, `profile_url`, `source_label`, `has_avatar`, `has_actions`, `member_since`, `designation`, `bio`, `social_links`.
- **Slots:** `avatar`, `actions`.
- **Story:** `author-card.default.story.yml`.
- **Regra:** não carrega usuário/Profile, não decide visibilidade e não inventa iniciais; os slots conservam os render arrays cacheáveis do módulo proprietário.
- **Estado:** implementação pronta; aguardando gates SDC e verificação de Runtime. Usado pelo adapter `templates/aculta-user-author-card.html.twig` e pela autoria dos artigos da Revista.

### revista-teaser — Cartão editorial
- **Papel:** substitui a apresentação V1 do teaser de publicação na listagem da Revista.
- **Props:** `title`, `url`, `has_category`, `has_subtitle`, `has_summary`, `has_cover`, `read_label`.
- **Slots:** `image`, `category`, `subtitle`, `summary`.
- **Story:** `revista-teaser.default.story.yml`.
- **Regra:** recebe render arrays do node view, não resolve conteúdo nem cache; a capa ausente usa apenas uma superfície decorativa.
- **Estado:** implementação pronta; aguardando gates SDC e verificação de Runtime.

### revista-listing — Listagem editorial
- **Papel:** apresenta o cabeçalho e a grade da View `revista_sessoes:page_revista`.
- **Props:** `eyebrow`, `title`, `has_items`.
- **Slots:** `items` (saída renderizada da View).
- **Story:** `revista-listing.default.story.yml`.
- **Regra:** não reordena o DOM nem consulta conteúdo; a grade mantém uma, duas e três colunas conforme o espaço.
- **Estado:** implementação pronta; aguardando gates SDC e verificação de Runtime.

### revista-publication — Publicação completa
- **Papel:** compõe metadados, capa, chamada, resumo, corpo, conteúdo editorial adicional, autoria e discussão.
- **Props:** `has_meta`, `has_subtitle`, `has_summary`, `has_cover`, `has_footer`, `has_authors`, `has_discussion`.
- **Slots:** `meta`, `cover`, `subtitle`, `summary`, `body`, `footer`, `authors`, `discussion`.
- **Story:** `revista-publication.default.story.yml`.
- **Regra:** os dados e controles de acesso permanecem nos render arrays de `aculta_magazine` e `aculta_user`; o componente controla apenas a ordem e a apresentação.
- **Estado:** implementação pronta; aguardando gates SDC e verificação de Runtime.


## Lacunas do catálogo

Componentes previstos no ADR-046 e ainda não criados: `avatar` (o `account-menu` usa só o slot), `author-box`, `breadcrumb`, `tabs`, `accordion`, `dialog`, `form-field`, `progress`, `data-table`, `empty-state` e `price`. Ordem e uso estão no [roadmap.md](roadmap.md).

## Como documentar um componente novo

Cada entrada tem: papel, props, slots, JavaScript (se houver), estado e a regra que ele não viola (por exemplo, "não lê entidade"). Atualize este arquivo no mesmo commit do componente.
