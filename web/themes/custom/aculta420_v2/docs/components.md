# Catálogo de componentes do tema V2

Componentes SDC com schemas estritos (`additionalProperties: false`). Os nomes de prop e slot abaixo são os do `*.component.yml`. "Estado" indica o que foi entregue e verificado. Inventário do checkout atualizado em 2026-11-14: 146 manifests SDC e 230 stories; schemas e renderização isolada são conferidos pelos gates de `docs/gates.md`. A existência do manifest/story não prova, por si só, que um consumidor de produção esteja configurado.

Prefixo de uso: `aculta420_v2:<nome>`. Em Twig, use `include` com `with_context = false`, ou `embed` quando o componente tem slots.

> **Mudança de consumers em 2026-11-13:** foram removidos os templates de
> apresentação de node, Views e vários blocos que eram citados como consumers
> neste catálogo. As descrições de uso abaixo são históricas até os displays e
> Views serem configurados com UI Patterns; existência/story no catálogo não
> comprova renderização ativa. Consulte o relatório do corte para os nomes e
> estado atual.

## UI Patterns Library e Biblioteca de Layouts

A UI Patterns Library descobre os componentes diretamente dos arquivos
`*.component.yml` e suas stories; não há uma lista paralela para manter. Neste
checkout há 146 manifests SDC e 230 stories. O Drupal precisa reconstruir seus
caches de descoberta para refletir alterações no Runtime. Todos os SDCs têm ao
menos uma story. A configuração habilita
`ui_patterns_library`; descoberta no Runtime não foi confirmada nesta revisão.

O grupo `Layouts` também alimenta os plugins de `ui_patterns_layouts`, usados
pelo Layout Builder. Os sete layouts declarados são `event-page-layout`, `layout-page`, `layout-section`, `layout-two-columns`, `layout-grid`, `layout-narrow` e `layout-full`. O rodapé institucional (`site-footer`) também é um item desta biblioteca, com regiões `brand`, `nav`, `content` e `legal`; ele está posicionado no layout de blocos do display `footer` do bloco `aculta_institution`. O gate de Runtime cobre hoje seis layouts
compartilhados; `event-page-layout` tem regiões próprias do detalhe editorial
de eventos e sua descoberta no seletor ainda precisa ser verificada no Runtime.

As alterações desta sessão aparecem nas stories dos SDCs existentes: catálogo
de eventos (`layout-grid`), contexto da Conta Studio (`layout-page`),
Dashboard/Conta Studio (`account-experience-dashboard`,
`account-experience-card`, `studio-calendar`, `studio-connections-block` e
`studio-menu`) e a nova composição LMS `/ganjalab` (`lms-learning-dashboard`,
`lms-learning-course-card`, `lms-certificate-card`, `heading`, `progress`,
`empty-state` e `button`). Os SDCs próprios já são descobertos pelo grupo
ACULTA; os layouts compartilhados são descobertos pelo grupo Layouts.

### studio-menu — Menu lateral do Studio Dashboard

Composto pelos slots `sidebar_items` e `dialog_items`, com render arrays do SDC
`studio-menu-item`. `AccountExperienceProvider` mantém a checagem de acesso e
cria os links autorizados; a story reutiliza essa mesma composição.

### studio-menu-item — Item do menu do Studio

Link com rótulo, destino, estado atual e ícone opcional. O provider do Studio
permanece responsável por filtrar destinos antes de criar o render array.
Story: `studio-menu-item.default.story.yml`.

### discussion-reaction-picker — Seletor de reações

Fragmento HTMX com busca local e categorias recebidas pelo slot `categories`.
O módulo Fórum prepara a árvore de SDCs depois que a rota valida o acesso.

### discussion-reaction-category — Categoria de reações

Agrupa opções SDC no slot `items`; mantém a chave usada pelo filtro local.

### discussion-reaction-option — Opção de reação

Formulário com fallback POST e atualização HTMX. O módulo owner fornece
endpoint, token CSRF, reação permitida e estado selecionado.

### article-authors — Autoria editorial

Agrupa os slots `title` e `authors` preparados pelo módulo `aculta_magazine`.
O SDC não consulta autor, publicação ou permissão. CSS local anexado com o
componente; story: `article-authors.default.story.yml`.

### editorial-category — Categoria editorial

O formatter `aculta_editorial_category_color` compõe este SDC para a categoria
da Revista. O link de referência renderizado pelo Drupal permanece no slot
`label`; a prop `color` aceita somente `red`, `green`, `yellow` e `green-dark`.
O CSS de contraste das quatro opções acompanha o componente.

### studio-connections-page — Conexões da Conta

O indicador de carregamento HTMX do filtro e da busca é estilizado dentro do
CSS deste SDC; a regra não depende mais de `base.css` global.

### related-content — Conteúdo relacionado

Envolve o `item_list` montado pelo bloco `aculta_index`; slots estritos sem
props. O módulo preserva descoberta, permissão e cache das entidades
relacionadas. O CSS da lista é local e só carrega quando o bloco é renderizado.
Story: `related-content.default.story.yml`.

### sales-panel — Relatórios da loja

Casca compartilhada dos relatórios de vendas de produto e doações. Os controllers
entregam as seções no slot `content`; consultas, permissões, filtros, tabelas,
paginação e cache continuam no módulo `aculta_portal`. A folha local é anexada
somente quando um relatório compõe o SDC.

### search-preview — Prévia de busca

Região acessível que recebe dica ou resultados pelo slot `content` e atributos
por `attributes`. Wiki e Messenger compõem o SDC nos campos de prévia; as
respostas assíncronas continuam geradas pelos módulos. CSS local com story
`search-preview.default.story.yml`.

### account-courses — Cursos da Conta

Lista cursos preparados por `AccountCoursePresenter`; o controller conserva
consulta, estado vazio, cache e ações autorizadas. O CSS local do SDC assume a
grade e os cartões, inclusive metadados. Story: `account-courses.default.story.yml`.

### portal-requirements — Relatório de requisitos

Envolve as seções de requisitos, estado das integrações e tabelas do controller
`PortalRequirementsController`. Cache e conteúdo permanecem no Portal; o asset
do SDC assume o layout anteriormente mantido no mapa global. Story:
`portal-requirements.default.story.yml`.

### document-download — Download de documento

Apresenta o link e os metadados de formato/tamanho que o formatter
`DownloadWithMetaFormatter` prepara para cada arquivo. URL, nome, rótulo
acessível e dados do arquivo continuam no módulo. Story:
`document-download.default.story.yml`.

### donation-presentation — Apresentação de doação

Casca de apresentação para resumo do checkout, confirmação, valor e convite de
conta. O módulo `aculta_donation` mantém cálculo, conteúdo, fluxo e cache; seus
render arrays são fornecidos pelo slot `content`. CSS fica no componente. Story:
`donation-presentation.default.story.yml`.

### saved-content-page — Página de salvos e recomendações

Recebe cabeçalho, abas, lista, estado vazio e paginação no slot `content`. O
controller do Studio mantém consultas, autorização, cache e montagem dos cartões;
o CSS local cobre somente a página `/salvos`. Story:
`saved-content-page.default.story.yml`.

### green-stash-page — Página Green Stash

Recebe a composição de navegação, busca, formulários e listagens do Commerce. O
tema envolve as rotas Green Stash com este SDC para anexar a folha visual apenas nesse fluxo;
consultas, operações, acesso e cache permanecem nos módulos. Story:
`green-stash-page.default.story.yml`.

### studio-account-section-layout — Layout de seção da Conta

Envolve o render array que combina navegação contextual e o formulário/conteúdo
da seção. O módulo preserva `id`, atributos HTMX, autorização, cache e conteúdo;
o CSS local apresenta o layout. Story:
`studio-account-section-layout.default.story.yml`.

### studio-revista-form — Formulário editorial do Studio

Envolve o formulário Drupal de edição de revista somente quando ele é renderizado
em rota `aculta_studio.*`. O Form API e suas operações permanecem no módulo;
`studio-form` apresenta seus controles e este SDC mantém só a borda superior
editorial. Story:
`studio-revista-form.default.story.yml`.

### studio-profile-editor — Editor privado do Perfil

Mapeia o componente neutro `profile-editor` para SDC e mantém seus slots de
identidade, biografia e redes sociais preparados pelos módulos. IDs, foco,
attachments e metadados de cache são repassados ao SDC. A grade da composição é
local; os links de `studio-profile-actions` têm alvo de toque no CSS local. O
contexto de formulário continua compartilhado com o Studio. Story:
`studio-profile-editor.default.story.yml`.

### studio-course-authoring-page — Criação de curso no Studio

Envolve a composição da rota `aculta_lms.course_create` para carregar o CSS da
apresentação onde o formulário de criação realmente é servido. O SDC trata o
cabeçalho e o retorno ao painel; o controller e o Form API do Group mantêm
conteúdo, autorização, operações e cache. Story:
`studio-course-authoring-page.default.story.yml`.

### studio-shell — Casca do Studio

Envolve o render array integral de cada rota `aculta_studio.*` após a composição
dos SDCs filhos. Seu CSS contém tipografia e layout do Studio; o Form API usa
`studio-form`, com skin carregada somente quando há um form em `studio-main`.
A rota, permissões e cache
permanecem nos módulos. Story: `studio-shell.default.story.yml`.
O antigo estilo `studio-saved-summary` não pertence mais ao shell; `/salvos`
tem apresentação própria em `saved-content-page`.

### studio-form — Skin de formulário do Studio e LMS

Envolve o render array de Form API somente sob a região `studio-main` das rotas
do Studio e da criação de curso LMS. Campos, handlers, attachments e cache do
formulário original são preservados no slot; o CSS da skin é asset local. Story:
`studio-form.default.story.yml`.

### studio-section-header — Cabeçalho de seção do Studio

Envolve o render array do título e das ações na seção Marley. O conteúdo
autorizado permanece no controller; o asset local cuida do alinhamento e dos
resets de lista apenas quando esse cabeçalho é renderizado. Story:
`studio-section-header.default.story.yml`.

### discussion-topic-composer — Compositor de tópico do Fórum

Recebe o Form API do nó de tópico pelo slot `form`; controller, validação, submit
e permissões permanecem no Fórum. A story executável `DiscussionTopicComposerStory`
monta um formulário de demonstração. Ela não prova composição deste SDC na rota
de produção. O antigo Twig `node-add-form--aculta-forum-topic.html.twig` foi
removido porque o hook não existe; Core registra `node_edit_form`. O SDC continua
na UI Patterns Library.

### forum-list-page — Lista pública do Fórum

É dona da grade responsiva e organiza slots para cabeçalho, categorias, busca,
navegação, atalhos, tópicos, estado vazio, pager e painel lateral. O template da
View limita-se a fornecer os dados do Fórum e compor os SDCs filhos. Stories:
`forum-list-page.default.story.yml` e `forum-list-page.empty.story.yml`.

### forum-category-page — Página de categoria do Fórum

É dona do layout responsivo e expõe slots separados para cabeçalho, navegação,
tópicos, estado vazio, paginação e painel lateral. O template da View do Fórum
fica como adapter dos dados e compõe os SDCs existentes nesses slots. Story:
`forum-category-page.default.story.yml` e `forum-category-page.empty.story.yml`.

### forum-search-page — Busca do Fórum

Envolve a saída padrão da View de busca e carrega o CSS de filtros e estado
vazio. A sugestão Twig preserva exposed form, resultados, attachments, pager,
footer e feed icons; query, filtros, acesso e cache continuam com Views/Search
API. Story: `forum-search-page.default.story.yml`.

Os estilos de apresentação da navegação JS de progresso, recuo hierárquico,
foco e resposta agora acompanham `forum-post-card`, incluído nos templates de
comentário compartilhados por Fórum, Revista, Wiki e LMS. A caixa editorial da
discussão Wiki acompanha o SDC `wiki-entry`. Os render arrays Drupal continuam
preservados.

Os SDCs `lms-learning-journey` e `lms-learning-room` usam o indicador global de
foco de `base.css`; seus CSS locais ficam com os estados e layouts próprios do
LMS.

### studio-listing-table — Tabela de listagem do Studio

Anexa o estilo à região responsiva e às tabelas produzidas pelo construtor de
listagens do Studio, sem adicionar um wrapper DOM próprio. Consulta, acesso,
campos, operações e paginação ficam no módulo proprietário; a folha local cuida
da leitura visual da tabela. Story:
`studio-listing-table.default.story.yml`.


### calendar-interactive — Calendário interativo

Envolve os render arrays da grade DHTMLX, dos controles acessíveis e da agenda
alternativa. O controller mantém a expansão de ocorrências, cache, `drupalSettings`
e attachment da biblioteca funcional; o SDC é dono do layout e seu CSS usa os
atributos `data-portal-ui` preservados para o JavaScript.

### image-audit-report — Relatório de imagens

Envolve o aviso e as tabelas somente de leitura do controller administrativo
de imagens. O módulo mantém permissões, cálculo e cache; o CSS do espaçamento é
local e só carrega na rota que apresenta o SDC.

### secret-reveal — Revelação de credencial

Envolve o valor escapado e a orientação do modal de segredos. A consulta,
registro da revelação, autorização e `max-age: 0` permanecem no módulo de
segredos; a folha do componente só carrega quando essa saída é renderizada.

### consent-banner — Preferência de métricas

Banner anônimo opcional, inserido em `page_bottom` quando a configuração está
ativa. O módulo `aculta_statistics` controla a decisão/cookie e o JavaScript;
marcação e estilos são do SDC e só entram na página nesse caso.

### revista-publication — Publicação completa

A folha do SDC declara `revista-publication.css`, incluindo o layout da
metainformação e dos links de compartilhamento que o módulo entrega no slot
`meta`. O CSS editorial acompanha o SDC no view mode full, sem biblioteca
global de autoria.

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
- **CSS:** navegação responsiva, conteúdo, foco, tabelas e ações da visão geral carregam pelo asset do SDC; o editor de foto tem SDC e asset próprios, enquanto gatilho e ações continuam na biblioteca contextual da Conta.
- **Regra:** o H1 "Minha conta" é entregue pelo SDC `heading` (nível 1, faixa tricolor); o tema não lê usuário nem decide acesso.
- **Estado:** SM do H1 atualizado em 2026-11-12; validação autenticada continua pendente.

### account-photo-editor — Editor de foto da Conta

- **Papel:** envolve a prévia e o Form API original do editor, mantendo o ponto de extensão `aculta_portal_photo_editor_alter`.
- **Props/slots:** nenhum prop; slot `content` recebe o render array já preparado pelo módulo.
- **Story:** `account-photo-editor.default.story.yml`, com avatar e botão demonstrativos.
- **CSS:** asset local do SDC contém apenas o posicionamento do editor; o formulário real preserva cache, acesso e attachments.
- **Estado:** migrado via preprocess; validação autenticada no Runtime pendente.

### account-terms-modal — Modal de aceite dos termos

- **Papel:** apresenta o formulário de aceite fornecido pelo `aculta_portal` dentro do diálogo existente.
- **Props/slots:** `title` e slot `content` com o Form API original.
- **Story:** `account-terms-modal.default.story.yml`.
- **CSS:** a lista de documentos usa o asset local do SDC; o wrapper preserva `id`, `hidden` e `data-dialog-title` usados pelo JavaScript do Portal.
- **Estado:** schema estrito e render isolado cobertos pelo gate V2; validação no diálogo em runtime pendente.

### account-profile-card — Cartão privado de perfil

Envolve as composições de biografia e redes da Conta por seus tokens de
apresentação, preservando os render arrays de `aculta_user`. Os estilos desses
cartões agora carregam com o SDC nas rotas do dashboard e do Perfil Studio.
Story: `account-profile-card.default.story.yml`.

### account-experience-card — Cartão de experiência da Conta

- **Papel:** experiência disponível no painel Minha Conta, em cartão ou faixa.
- **Props:** `id`, `kind`, `icon`, `title`, `description`, `tag`, `url` e `action_label`.
- **Slots:** nenhum.
- **Story:** `account-experience-card.default.story.yml`.
- **Regra:** o módulo entrega rótulos e destinos já autorizados; o SDC só apresenta os dados e não decide acesso.
- **Estado:** renderizado diretamente pelo controller do Studio como componente; CSS carregado junto ao SDC.

### account-experience-dashboard — Painel de experiências da Conta

- **Props:** `show_identity`, `identity_name`, `identity_email`, `profile_url`, `experiences_title`, `experiences_description`, `account_url` e rótulos do atalho da conta.
- **Slots:** menu, avatar, link público, calendário, conexões e render arrays dos cartões de experiência.
- **Estado:** o provider do Studio compõe o SDC diretamente; identidade, autorização, cache e render arrays continuam nos módulos proprietários. CSS e JS do carrossel, com `core/drupal` e `core/once`, são assets do componente.

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

Os layouts usam `group: Layouts` e são registrados automaticamente pelo
`ui_patterns_layouts` (categoria `Layouts`, regiões iguais aos slots). O gate
`validate-aculta420-layouts.php` verifica descoberta e renderização real dos
seis layouts compartilhados.

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
accordion.default.story.yml. `PagePresentationHooks::composeFaqFull()` compõe
este SDC no view mode `full` do bundle `aculta_faq`, usando o título do node e o
render array do campo body. O override Twig específico foi removido; o template
Core mantém o wrapper do node, contextual links e title prefix/suffix. Se o SDC
não estiver registrado, o preprocess usa o elemento Drupal `details` com o
mesmo título e render array de resposta.

### form-field

Envolve o rótulo, o controle Drupal, a instrução e o erro textual em uma
composição visual única. Props: `id` (identificador do wrapper) e `invalid`.
Slots: `label`, `control`, `description` e `error`. Stories:
`form-field.default.story.yml` e `form-field.error.story.yml`. O consumidor
preserva a associação `for`/`id` do controle e associa os IDs
`<id>-description` e `<id>-error` por `aria-describedby`; o controle também
recebe `aria-invalid="true"` quando aplicável. O componente não cria nem altera
controles Drupal e não valida dados.

### support-form — Formulário de apoio

Asset local para o formulário do Form API na rota `/apoio`; o SDC imprime o slot
sem reescrever o `<form>`, seus handlers, cache ou mensagens. Props: nenhuma.
Slot: `content`. Story: `support-form.default.story.yml`.

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
O CSS local apresenta render arrays de apoiadores e citações identificados por
`EventEditorialPresenter` via `data-portal-ui`. Sem JavaScript. No checkout
atual não foi encontrado consumidor PHP/Twig nem referência no Config Sync para
`event-page-layout`; a story é documentação, não prova de uso. A implementação
fica candidata a P2/P3 até confirmar a composição real do detalhe do Evento.

### lms-lesson-page

Contêiner visual para o render array autorizado entregue pelo módulo LMS na rota
de prévia da aula. O controlador compõe `aculta420_v2:lms-lesson-page` e passa
todo o conteúdo no slot `content`; o SDC não recebe entidade, estudante ou dados
de acesso como prop. Story: lms-lesson-page.default.story.yml. Sem JavaScript;
tabelas rolam dentro do bloco em telas estreitas. O template mantém fallback ao
render original caso a descoberta Drupal ainda não conheça o SDC.

### lms-learning-dashboard

Página nova da rota `/ganjalab`, composta diretamente pelo
`LearnerDashboardComposer` como SDC. Props recebem apenas rótulos localizados;
os slots recebem os cartões `lms-learning-course-card`, o CTA do catálogo e os
certificados. LMS continua dono das matrículas, destinos, progresso, acesso e
cache. Não usa mais os wrappers genéricos `context-header`, `card`,
`content-grid` nem o mapa de tokens legado da home. O template inclui os SDCs
`heading` e `empty-state`. Story: `lms-learning-dashboard.default.story.yml`.

### lms-learning-course-card

Cartão LMS próprio com progresso (SDC `progress`), estado de avaliação e ações
canônicas de jornada/retomada. Props são projeções autorizadas do curso; links
chegam nos slots como SDC `button`. Story: `lms-learning-course-card.default.story.yml`.

### lms-certificate-card

Certificado da conta com estado ativo/revogado, data localizada e ação de
verificação pública. LMS prepara os dados e URL; o SDC controla a apresentação.
Story: `lms-certificate-card.default.story.yml`.

### lms-learning-journey

Casca SDC consumida pelo nome neutro `lms-learning-journey` solicitado pelo
`CourseJourneyPresentationHooks`; o slot `content` recebe o render array do
`CourseJourneyComposer`, com resumo, progresso e lista autorizada. Cache e dados
continuam no módulo. CSS contextual próprio e story
`lms-learning-journey.default.story.yml`.

### lms-learning-room

Casca SDC para a sala de aula e formulário Drupal, consumida pelo nome neutro
`lms-learning-room` no preprocess da rota de envio. O layout preserva o render
array da atividade e o formulário no slot `content`; CSS responsivo só é
carregado quando a sala é renderizada. Story:
`lms-learning-room.default.story.yml`.

### lms-course-curriculum

Composição da tela de currículo do educador. Props vazias; slots `header`,
`summary`, `actions`, `lessons_heading`, `lessons` e `lesson_order_form` recebem
subárvores de renderização montadas e autorizadas pelo módulo `aculta_lms`.
O módulo mantém consultas, rotas, permissões, cache e o JavaScript de ordenação;
este SDC é dono da estrutura visual e do CSS responsivo. Story:
`lms-course-curriculum.default.story.yml`. Sem JavaScript próprio.
Os estados de publicação recebem classes locais do SDC e tokens semânticos de
status. As classes do currículo são emitidas diretamente nos render arrays pelo
módulo; não passam pela tradução genérica `data-portal-ui`.

### lms-course-materials

Seção de documentos privados do curso. Props vazias; slots `title`,
`description`, `add`, `items`, `empty` e `unavailable` recebem texto/link/lista
já filtrados pelo módulo `aculta_lms`. O módulo mantém acesso e cache dos
documentos; o SDC apresenta a lista. Na estrutura do curso, o hook de apresentação
do tema encaixa o render array de recursos no slot `resources` do currículo,
mantendo a regra de acesso/cache no módulo e a apresentação que já vinha do
render array. Story:
`lms-course-materials.default.story.yml`. Sem JavaScript.

O formatter H5P usa `layout-section` para envolver documentos PDF relacionados
à atividade; os render arrays e metadados de cache do viewer são preservados.

### courses-catalog

Página pública de cursos com slots `intro`, `catalog_title`, `catalog` e
`collaboration`. O slot `catalog` recebe a View nativa; acesso, pager, consulta
e cache permanecem no Drupal. Story: `courses-catalog.default.story.yml`.
Sem JavaScript.

### lms-h5p-course

Cartão de curso com slots `title`, `course_link` e `activities`. A composição
usa `surface-panel`; o módulo H5P mantém acesso ao Group, URL e renderização do
campo de atividades. Story: `lms-h5p-course.default.story.yml`. Sem JavaScript.

### Moderação de fórum LMS

As rotas de visão geral por curso e fila de comentários compõem o SDC
`layout-section`; o módulo preserva consultas, acesso e cache. O wrapper da fila
não adiciona título artificial. As duas rotas deixam de depender de seletores
globais dedicados. Validação visual autenticada ainda pendente.

O SDC `layout-section` também mantém o reset da lista de discussões H5P no CSS
local, anexado junto do componente; `portal-ui-map.css` não carrega estilos LMS.

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
- **Slots:** `search`, `portal_menu`.
- **JS:** `multidomain-bar.js` (login e busca por HTMX, com fragmento fornecido pelo Portal; gatilhos nativos preservados como fallback e `htmx:abort` ao fechar).
- **CSS:** estilos de layout dos fragmentos de login e cadastro HTMX acompanham o asset do SDC, não o mapa global.
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
- **Slots:** `content` (render array Drupal, com acesso e cache preservados); `brand`, `nav` e `legal` (opcionais) montam a estrutura da referência: listra tricolor, linha de marca e navegação, divisor e linha legal.
- **Stories:** `site-footer.default.story.yml`, `site-footer.social-links.story.yml`.
- **Estado:** estrutura da referência e variação social em CSS; o `region--footer` legado foi removido. Os slots `brand`, `nav` e `legal` ainda não são preenchidos pelas páginas: a região continua entregando só `content`. Validação visual nos modos claro e escuro pendente.

### alerts-trigger — Avisos
- **Papel:** botão com contagem e diálogo de avisos carregado sob demanda.
- **Props:** `href`, `all_href`, `count`, `count_kind` (`active` ou `attention`).
- **Estado:** entregue.

### messenger-trigger — Mensagens
- **Papel:** botão com contagem de mensagens não lidas e diálogo de conversas.
- **Props:** `href`, `all_href`, `count`.
- **CSS:** `messenger-trigger.css` contém gaveta, conversas, avisos agrupados e estados HTMX; o asset é anexado junto do SDC, que só é incluído quando a barra tem URL de mensagens.
- **Estado:** entregue; validar visualmente em conta autenticada continua pendente.

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
- **Estado:** a row de `eventos_publicos` compõe `card-cultural` como SDC; a View usa UI Patterns Views com `layout-grid`. O plugin `PublicCatalogueCard` prepara props mínimas; dados, ACL, filtro e metadados de cache continuam no módulo owner. HTTP 200 foi confirmado após correção do template ausente; aceite visual completo ainda pendente. Faixa de acento permanece separada e DRAFT até D1 (`domain_presentation.accent`). Browser visual aguarda validação autenticada/runtime.

### forum-topic — Tópico do Fórum
- **Papel:** teaser e conteúdo completo do tópico, preservando autoria, categorias, favoritos e discussão nos render arrays Drupal.
- **Props:** `variant` (`teaser` ou `full`), `has_title`, `has_meta`, `has_bookmark`.
- **Slots:** `title`, `meta`, `bookmark`, `content`.
- **Consumers:** view mode de node `aculta_forum_topic`, View pública de tópicos e discussão compartilhada.
- **Estado:** todos os view modes continuam usando o SDC; `full` é composto pelo hook `PagePresentationHooks::composeForumTopic()` com o SDC `forum-topic-page`, e os demais usam a variante teaser. Render isolado passou no checkout; homologação Runtime e visual da retirada do template permanecem pendentes.

### forum-topic-page — Página de tópico do Fórum
- **Papel:** compõe o tópico completo, painel lateral de contexto, diálogo HTMX de resposta e botão Comentar.
- **Props:** `category_label`, `author_name`, `created`.
- **Slots:** `topic` (SDC `forum-topic` com os render arrays do node).
- **Story:** `forum-topic-page.default.story.yml`.
- **Regra:** não recebe entidades nem decide acesso. O módulo Fórum prepara URLs autorizadas, reações, biblioteca HTMX e metadados; o Core mantém wrapper do node, title prefix/suffix e contextual links.
- **Estado:** schema, story, renderização isolada e teste focal passam; validação visual e Runtime pendentes devido ao preflight bloqueado.

### forum-category — Categoria do Fórum
- **Papel:** cartão de categoria para a View pública e o bloco de categorias da landing.
- **Props:** `title_level` (`h2` ou `h3`, padrão `h2`).
- **Slots:** `parent`, `title`, `description`, `count`.
- **Regra:** desde 2026-11-12 o título é renderizado pelo SDC `heading` no nível de `title_level`; o CSS tipográfico próprio foi removido.
- **Estado:** adapters de Views para a página de categorias e a landing usam o mesmo SDC; Firefox ESR cobriu ambos os consumers em nove larguras e dois modos; título pelo SDC `heading` em 2026-11-12.

### map-popup — Balão de mapa
- **Papel:** cartão compacto para um marcador de mapa.
- **Props:** `title`, `url`, `category`, `address`, `route_url`.
- **Slots:** `thumbnail`.
- **Estado:** entregue. Os tiles de mapa não mudam sem revisão de privacidade (ver roadmap).
- **Ponte com `aculta_maps`:** o preprocess do tema mapeia as props do hook `aculta_maps_popup_content` ao SDC; o fallback neutro do módulo permanece para outros temas. O conteúdo fica num `<template>` inerte clonado pelo Leaflet; nada de HTML ou SVG vai por `drupalSettings`.
- **Stories:** `default` (todas as props) e `minimal` (só título).

### map-canvas — Superfície de mapa

Casca visual do render array do `MapRenderer`; no diretório `/mapa` envolve a
página da lista e, em outras páginas, envolve o mapa individual. O slot
`content` preserva os attachments de Leaflet, templates e cache. O CSS local
cuida do mapa, atribuição, aviso de privacidade e links de rota sem anexar essas
regras globalmente. Story: `map-canvas.default.story.yml`.

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

### donation-quick — Valores rápidos de doação
- **Papel:** lista valores e destinos preparados pelo módulo de doação, compondo cada ação com o SDC `button`.
- **Props:** nenhuma.
- **Slot:** `items`, itens `<li>` com botões renderizados.
- **Story:** `donation-quick.default.story.yml`, com valores fictícios e opção aberta.
- **Regra:** decisões, valores, destinos e ordem continuam no `aculta_donation`; o SDC só define a lista semântica.
- **Estado:** emissor dos três itens foi migrado para UI Suite; fallback do módulo permanece para outros temas.

### status-messages — Mensagens de estado
- **Papel:** mensagem anunciada a tecnologias assistivas, incluindo estados do carrinho.
- **Props:** `type` (`status`, `info`, `warning` ou `error`), `messages` ou slot `content` para render arrays com links/ações, `role` opcional (`status`/`alert`), `dismissible` e `close_label`.
- **Integração:** `portal-cart-status.html.twig` converte sucesso do Portal para `status`, preserva erro/role, link e atributos consumidos pelo JS.
- **Estado:** avisos próprios de Portal, Events, Maps, segredos, doação, H5P e UI Library compõem este SDC ou assets locais de módulo. A compatibilidade `.messages` permanece para módulos contrib que ainda emitem esse contrato.

### social-links-page — Micropágina de links
- **Papel:** apresenta título, descrição, identidade pública autorizada e links preparados pelo módulo `aculta_linkweed` (o antigo `aculta_links` foi removido em 2026-11-05).
- **Props:** `title`, `description`, `has_links`, `theme_preset` (`classic`, `soft` ou `outline`).
- **Slots:** `owner_identity`, `links` (render arrays do Portal).
- **Story:** `social-links-page.default.story.yml`.
- **Regra:** o tema só apresenta os dados do módulo; não consulta dono, entidades ou destinos. Desde 2026-11-12 o H1 é renderizado pelo SDC `heading` (nível 1, faixa tricolor), mantendo o `id` usado por `aria-labelledby`.
- **CSS:** listas e canais usam os atributos neutros `data-portal-ui` diretamente; o estilo acompanha este SDC e o SDC `site-footer`, sem classes de ponte no mapa global.
- **Estado:** integração direta a partir do contrato neutro do LinkWeed; o módulo conserva o fallback para outros temas. Validação visual pendente.

### icon — Ícone
- **Papel:** ícone SVG decorativo, injetado inline e oculto para leitores de tela (`aria-hidden`).
- **Props:** `name`, `variant` (`thin`, `light`, `regular`, `bold`, `fill`, `duotone`), `size` (`sm`, `md`, `lg`, `xl` ou `bar`), `color`.
- **Famílias:** ícones Phosphor oficiais continuam disponíveis em `regular` e `duotone`. O nome `aculta-brocolis` seleciona o desenho original ACULTA Brócolis nas seis variações. Para Phosphor, outros pesos solicitados são normalizados para `regular`.
- **UI Icons:** pacote `aculta_brocolis` com `aculta-brocolis` (regular), `aculta-brocolis-thin`, `aculta-brocolis-light`, `aculta-brocolis-bold`, `aculta-brocolis-fill` e `aculta-brocolis-duotone`; habilitado também nos seletores dos menus `main` e `account`.
- **Stories:** `icon.brocolis-*.story.yml` mostram os seis pesos na biblioteca visual.
- **Estado:** `bar` mede 30px para os controles da barra institucional (ADR-047, item 10).
- **Estado:** entregue. Ícones ausentes do acervo são ignorados (`ignore_missing`).

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

### public-profile-page — Página de perfil público

Host de asset sem wrapper DOM adicional para a saída autorizada do perfil
público. A rota `aculta_user.perfil_publico` move o render array raiz para seu
slot; identidade, relacionamentos, cache e política de privacidade continuam no
Portal. Props: nenhuma. Slot: `content`. Story:
`public-profile-page.default.story.yml`.

### revista-teaser — Cartão editorial
- **Papel:** substitui a apresentação V1 do teaser de publicação na listagem da Revista.
- **Props:** `title`, `url`, `has_category`, `has_subtitle`, `has_summary`, `has_cover`, `read_label`.
- **Slots:** `image`, `category`, `subtitle`, `summary`.
- **Story:** `revista-teaser.default.story.yml`.
- **Regra:** recebe render arrays do node view, não resolve conteúdo nem cache; a capa ausente usa apenas uma superfície decorativa. Desde 2026-11-12 o título do cartão é um H5 do SDC `heading`, com link herdando a cor e o sublinhado do título; o teaser em destaque usa a escala H4 por token.
- **Estado:** implementação pronta; título pelo SDC `heading` e browser Firefox (1280/1920/390 px, claro/escuro) em 2026-11-12.

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
- **Regra:** conserva os render arrays e controles de acesso do Drupal; não consulta conteúdo nem decide acesso. O título da página completa é dono do H1; o título genérico fica oculto apenas quando o adapter está presente. Desde 2026-11-12 o título do artigo e o título do cartão (teaser) são renderizados pelo SDC `heading` (artigo no nível de `title_level`, faixa tricolor; cartão em H5).
- **Estado:** renderização e comparação Firefox do Runtime passaram para home, listagem e artigo (320–1440 px, claro/escuro); títulos pelo SDC em 2026-11-12; zoom físico, autenticação e leitor de tela ainda pendentes. A antiga rota de busca devolve 404 e não foi recriada pelo tema.


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
- **Slots:** `title`, `meta`, `preview`, `thumbnail`, `pinned_content`, `replies_content`, `activity_content`.
- **Story:** `forum-topic-row.default.story.yml`.
- **Regra:** destaque fixado recebe fundo `--color-surface-highlight` e borda lateral verde; dados ausentes (como visualizações) viram placeholder neutro `—`.
- **Estado:** entregue na Entrega 1 do redesenho visual Growroom420; schema estrito validado. A View pública de tópicos passa a compor esta row por UI Patterns Views; os slots de contadores/atividade preservam os formatters da View.

### forum-reply-mirror — Cartão do Post do Fórum Espelhado
- **Papel:** cartão de post do fórum no layout espelhado, para comentários pares: autor, faixa e reações à direita; ações à esquerda.
- **Props:** `author_name` (string, obrigatório), `timestamp`, `author_posts`, `author_reactions` (opcionais).
- **Slots:** `avatar`, `body`, `actions`, `reactions_slot`.
- **Story:** `forum-reply-mirror.default.story.yml`.
- **Regra:** desktop (a partir de 48rem) inverte colunas e faixa; mobile (abaixo de 48rem) muda só a faixa de lado. Faixa de destaque vermelha (`--button-danger-bg`) e linha acima das ações verde (`--color-brand-primary`). Os comentários pares de `field_aculta_discussion` usam as mesmas regras em `forum-post-card.css`.
- **Estado:** novo; story, README e documentação incluídos.

### forum-opening-post — Postagem de Abertura do Tópico
- **Papel:** primeiro post do tópico na página completa, com o mesmo destaque da linha de tópico fixado.
- **Props:** `author_name` (string, obrigatório), `author_posts`, `author_reactions`, `timestamp`, `permalink_url`, `delete_url`, `edit_url`, `quote_url`, `reply_url`, `density`. O chamador fornece apenas os destinos permitidos pela política de acesso e moderação; ícones são resolvidos pelos SDCs das ações.
- **Slots:** `body`.
- **Story:** `forum-opening-post.default.story.yml`.
- **Regra:** destaque usa barra lateral `--color-brand-primary` e fundo `--color-surface-highlight`; o corpo vem do Drupal, sem leitura de entidade no componente.
- **Uso:** view mode full de `aculta_forum_topic`, composto por `PagePresentationHooks::composeForumTopic()` nos purposes Fórum e Fórum LMS.
- **Estado:** novo; story e documentação incluídas.

### forum-topic-card — Cartão de Tópico do Fórum
- **Papel:** formato de cartão com miniatura (imagem do tópico ou ícone neutro da categoria), metadados e contadores, implementando o padrão Discourse Topic Cards.
- **Props:** `pinned` (bool, obrigatório), `thumbnail_src` (opcional), `thumbnail_alt` (opcional), `category_icon` (opcional), `badges` (opcional), `replies` (opcional), `views` (opcional), `last_activity` (opcional).
- **Slots:** `title`, `meta`, `preview`.
- **Story:** `forum-topic-card.default.story.yml`.
- **Regra:** nunca utiliza letra ou inicial como avatar/miniatura (apenas imagem ou ícone neutro Phosphor). Desde 2026-11-12 o título é um H6 do SDC `heading` (conteúdo no slot `content`).
- **Estado:** entregue na Entrega 1 do redesenho visual Growroom420; título pelo SDC `heading` em 2026-11-12; schema estrito validado.

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
- **Papel:** painel de boas práticas e normas de publicação, com duas colunas no desktop e uma coluna no mobile, faixa amarela de destaque e opção de recolhimento via `<details>` nativo. Story disponível; consumidor de produção não confirmado.
- **Props:** `collapsed` (bool), `tips` (array de strings), `community_rules` (array de `{title, text}`).
- **Slots:** nenhum.
- **Story:** `forum-composer-guidelines.default.story.yml`.
- **Regra:** sem dependência de JavaScript obrigatório; opera com `<details>` nativo do HTML.
- **Estado:** entregue na Entrega 2 do redesenho visual Growroom420; schema estrito validado.


## Fórum Growroom420 (entregas 1 e 2)

- `forum-category-header`: prop `actions` (markup de link de ação principal). Story: `forum-category`.
- `forum-tabs`, `forum-topic-row`, `forum-post-card` e `forum-sidebar-panels`: usados nas listagens e páginas atuais do Fórum. `forum-composer-guidelines` permanece catalogado com story, sem consumidor de produção confirmado.
- `forum-topic-card`: schema e story existentes; sem uso na lista nesta entrega (modo cards pendente, seção 3.4).

### discussion-reply-composer — Compositor de Resposta da Discussão

- **Local:** `components/discussion-reply-composer/`
- **Schema:** estrito, com props `target_id`, `status`, `mode` (`responder`, `citar`, `editar`), `parent_id`, `parent_author`, `parent_subject`, `host_title` e slots `messages`, `context`, `form`.
- **Story:** `discussion-reply-composer.default.story.yml`.
- **Consumer:** preprocess do tema para o hook `discussion_reply_composer`; módulo `aculta_forum` fornece props e Form API em render arrays.

## Estatísticas do Fórum (FST-08)

Componentes neutros de apresentação consumidos por `aculta_statistics`; não consultam banco, acesso ou privacidade.

- `statistics-metric-card`: props `label`, `value`, `unit`, `hint`, `tone` (`neutral|positive|warning`). Story: `statistics-metric-card.default.story.yml`.
- `statistics-trend-chart`: props `title`, `summary`, `caption`, `bars` (`{label, value, percent}`). Barras com valores textuais acessíveis. Story: `statistics-trend-chart.default.story.yml`.
- `statistics-period-filter`: formulário GET sem JavaScript; props `action`, `label`, `options`, `selected`, `submit_label`, `hidden`. Story: `statistics-period-filter.default.story.yml`.
- `statistics-category-ranking`: tabela semântica; props `caption`, `rows`, `empty`. Story: `statistics-category-ranking.default.story.yml`.
- `statistics-activity-summary`, `statistics-response-health`, `statistics-moderation-summary`: listas de definição; props `title`, `rows` (`{label, value, description}`), `note`. Stories homônimas.
- `statistics-empty-state`: props `message`, `hint`. Story: `statistics-empty-state.default.story.yml`.

Estado: implementados para a FST-08; schema validado. Renderização no Runtime e captura visual: pendente.

Os estilos compartilhados das três listas ficam em `statistics-activity-summary.css`.
`statistics-response-health` e `statistics-moderation-summary` não declaram CSS
próprio; ambos reutilizam as mesmas classes e a biblioteca `statistics` mantém
uma única folha para esse contrato visual.

## Atualização de superfícies - 2026-11-12

Os três SDCs surface, surface-panel e surface-accent incluem tone green-soft
como padrão omitido (schema e Twig). Canvas, raised, subtle e institution
continuam válidos e com o mesmo significado; nenhuma prop anterior foi
removida. No claro, o uso principal é verde externo com cartão raised branco
interno. A UI Library precisa de validação real nos dois modos.

## Layout-section: tom verde opcional - 2026-11-12

`layout-section` oferece os tons `default`, `raised` e
`green-soft`. A opção `default` permanece estável para não alterar
os 22 displays Layout Builder com props/slots vazios. A nova story
`layout-section.green-soft.story.yml` exemplifica a seção verde com
`surface` raised neutra no interior. Requer aceite real da UI Library
e do plugin de UI Styles da seção.
# Painéis de relatório

Os relatórios de vendas de produto e de doações compõem `sales-panel`, que é dono apenas da casca visual e do espaçamento. Seções, tabelas, paginação, filtros e consultas permanecem nos controllers e builders do módulo `aculta_portal`; ambos os controllers preservam a variação de cache por `url.query_args` e `max-age: 0`.


O SDC `site-footer` é o único responsável pela apresentação da região Drupal
`footer`; `layout.css` não replica mais a grade e a navegação dessa região.
