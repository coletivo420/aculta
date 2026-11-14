# Relatório de corte do tema para composição integral com UI Suite

**Data da revisão:** 2026-11-14
**Escopo:** estado do checkout após o commit `6537d1083a1ee100c457c48219e04a62a70403f4`; sem importação estrutural nem homologação visual pós-commit no Runtime.
**Decisão relacionada:** ADR-054, seção “Decisão vigente — corte radical da apresentação legada”.

## 1. Objetivo operacional

“Tema 100% UI Suite” deve significar que páginas, displays, Views, layouts e blocos são compostos pelos plugins UI Suite disponíveis, usando SDCs do tema como implementação dos componentes. Não significa remover os SDCs do tema: SDC é precisamente o provider de componentes que UI Patterns descobre e expõe.

O alvo é retirar a composição paralela em templates Twig, preprocess/hooks, CSS de domínio e configuração de apresentação duplicada. Manter a fundação mínima Drupal, a estrutura do shell, os tokens/skins e os componentes SDC que serão montados editorialmente. Não apagar conteúdo nem comportamento dos módulos owners.

## 1.1 Estado do corte no checkout atual

O lote publicado em `6537d1083a1ee100c457c48219e04a62a70403f4` removeu dois presenters: `templates/content/node--wiki-entry--full.html.twig` e `templates/layout/page--shell-v2.html.twig`, além da folha e configuração do shell paralelo. O display `node.wiki_entry.full` agora seleciona `aculta420_v2:wiki-entry` pelo ComponentLayout UI Patterns e mapeia os campos para suas regiões/slots. `page.html.twig` é o shell único e compõe `layout-page`.

A revisão R3 identificou perda funcional em remoções mais amplas de Fórum, Revista, catálogo de produto, teasers e formulários; esses templates foram mantidos até existir substituição equivalente. Portanto a afirmação anterior de que 35 templates foram retirados e `templates/` caiu de 55 para 20 **não descreve o checkout atual** e fica substituída por este registro.

Na revisão inicial havia **31 templates Twig em `web/themes/custom/aculta420_v2/templates/`**, além dos templates internos dos SDCs. Após os cortes dos teasers da Revista, Wiki e Documentos, do catálogo da Loja e da apresentação full da Revista, restam 26. O corte ainda não converteu todos os consumidores nem homologou a saída renderizada no Runtime. Não há alegação de PASS funcional/visual pós-commit.

### Atualização de implementação — 2026-11-14

O presenter `node--revista--teaser.html.twig` foi removido. `PagePresentationHooks::preprocessNode()` agora passa os fields formatados e o accent global ao SDC `revista-teaser` em render array, conserva dependências de cache do node/config e suprime o heading genérico do template Core para evitar título duplicado. O inventário de consumidores foi regenerado (461 registros mais cabeçalho). A validação de PHP e diff passou; saída no Runtime e aceitação visual seguem sem homologação.

O Config Sync de `node.revista.full` foi migrado para ComponentLayout UI Patterns com `revista-publication`. Os campos seguem em field blocks com os formatters atuais; os render arrays cacheáveis `post_meta` e `article_authors`, construídos pelo módulo em `node_view`, são encaminhados pelo tema aos slots `meta` e `authors` quando o ComponentLayout está ativo. `PagePresentationHooks::composeRevistaFull()` mantém a composição SDC em render array no caminho de compatibilidade. Essa configuração não foi importada; a renderização final segue pendente no Runtime.

O presenter Twig de `node.revista.full` foi removido. `PagePresentationHooks::composeRevistaFull()` encaminha os render arrays `post_meta` e `article_authors` aos slots do ComponentLayout quando ativo; enquanto a configuração antiga estiver ativa, compõe o mesmo SDC como render array com os fields formatados, mantendo acesso e cache. O Core fornece o wrapper do node e os contextual links. Config Sync, visual e cache seguem sem homologação no Runtime.

O presenter `node--wiki-entry--teaser.html.twig` também foi removido: `PagePresentationHooks::preprocessNode()` compõe o SDC existente `wiki-entry` com os campos formatados da Field API, link canônico do node, atributos e metadados de cache preservados. O título continua sendo um link com `rel="bookmark"`. A composição ainda não foi homologada no Runtime.

O presenter `node--document--teaser.html.twig` foi removido e substituído por render array do SDC existente `document-card`. Título/URL canônicos vêm do node; categoria, resumo e arquivo permanecem render arrays Field API com controle de acesso e cache. O resultado ainda não foi homologado no Runtime.

O template do view mode `catalog` de Commerce também foi removido. `PagePresentationHooks::preprocessCommerceProduct()` passa o render array `product` ao slot do SDC `product-card`, retirando somente `variation_attributes`, como fazia o Twig anterior; o template Core mantém o `<article>` do produto e `title_prefix/title_suffix`. Preço, variações, formulário, acesso e cache continuam nos arrays originais. A revisão R2 não encontrou bloqueadores; Runtime e visual ainda precisam de homologação.

## 2. Fotografia encontrada no commit atual

- **141 manifests SDC e 223 stories** no checkout. Os gates de schema e contrato da UI Patterns Library passaram para esse conjunto; isso prova metadados/stories no código, não uso editorial nem renderização de todas as combinações.
- **31 templates Twig** na fotografia inicial; após os cortes dos teasers da Revista, Wiki e Documentos, do catálogo da Loja e da apresentação full da Revista restam 26 sob `web/themes/custom/aculta420_v2/templates/`. Os 141 SDCs têm seus próprios templates, que são implementação dos componentes e não devem ser contados como presenters Drupal legados.
- O diretório contém shell, templates de conteúdo/Views, formulários/fragmentos, quatro templates da UI Patterns Library, e-mail e adapters de módulos. A classificação individual vigente continua no roadmap do tema.
- `node.wiki_entry.full` usa agora Layout Builder + `ui_patterns:aculta420_v2:wiki_entry`; oito regiões/slots recebem summary, body, categories, image, references, related, discussion e actions. A revisão independente confirmou o mapeamento no Config Sync e o contrato do SDC.
- `views.view.revista_sessoes` foi configurada no Config Sync com UI Patterns Views style `aculta420_v2:revista-listing` no display base; os seis displays de página herdam a seleção. O row nativo `entity:node`/teaser, query, filtros, acesso, pager e cache permanecem. O template da Página Revista preserva title/contextual links e os demais elementos da View; ele imprime o style SDC sem embrulhar novamente e usa o embed anterior como fallback. Configuração ainda não importada nem homologada no Runtime.
- O único shell é `templates/layout/page.html.twig`, com cinco regiões declaradas em `aculta420_v2.info.yml`; o SDC `layout-page` fornece o landmark `main`.
- O inventário de integrações/consumidores fica em `docs/architecture/inventarios/ui-suite-consumidores-iniciais.csv`; foi regenerado para os arquivos do checkout atual. Não confundir os campos de Runtime capturados em snapshots de 2026-11-10/11 com estado atual.
- A matriz foi regenerada neste lote após remover o presenter full da Revista: 458 linhas (457 consumidores e cabeçalho), com 26 overrides Twig do tema. A busca encontrou referências literais de produção a 107 SDCs; 34 não têm referência literal no código ou configuração varridos. Isso não demonstra que sejam órfãos: a UI Patterns Library cataloga todos, e usos editoriais/dinâmicos não são enumeráveis pela busca. Campos `runtime_status` da matriz são snapshots antigos ou “not observed”, não estado atual.
- `runtime-sync doctor --format=json`, executado após `7e2833abd3bb75c10aa66a126be6edc12144fea3`, retornou **BLOCKED/RX**: 0 updates e 0 deploy hooks pendentes; quatro configurações em drift (`aculta420_v2.settings`, `core.entity_view_display.block_content.aculta_institution.footer`, `core.entity_view_display.node.revista.full` e `views.view.revista_sessoes`); schema ausente para 11 módulos, incluindo `aculta_ui_library`; checkout compartilhado com Apache e arquivo local `.twig-cs-fixer.cache`; cache/baseline ainda em `e37f3d8d45ecba9719d4b97dde67effc2236b13f`. A View da Revista aguarda importação e homologação. Não executar sincronização, importação, desinstalação ou rebuild com esse preflight.
- “UI Suite habilitado” não significa “todas as páginas compostas pelo UI Suite”. O plano continua por domínio e exige inventário, migração e homologação por consumidor.

## 3. O que deve sair do caminho de renderização

### 3.1 Templates específicos de conteúdo

Converter para entity displays/view modes e UI Patterns Field/Field Formatters, com rows SDC quando suportado. Depois remover estes overrides:

- `templates/content/node--aculta-faq--full.html.twig` — compor campos e `accordion`/HTML details no display.
- `templates/content/node--aculta-forum-topic.html.twig` — montar o display com os SDCs de tópico e comentário existentes; preservar render arrays dos comentários e ações.
- `templates/content/node-add-form--aculta-forum-topic.html.twig` — remover somente o adapter visual; manter Form API, validação, handlers e acesso do módulo.
- `document-card` já é integrado diretamente pelo preprocess com todos os fields renderizados; seu antigo presenter Twig foi removido.
- `node.revista.full` agora compõe o SDC `revista-publication` pelo Layout Builder UI Patterns ou pelo preprocess de compatibilidade; o template Drupal foi removido. O teaser também usa o SDC `revista-teaser` em render array.
- `templates/content/node--wiki-entry--full.html.twig` — usar display/formatter do `wiki-entry` e Layout Builder/UI Patterns. O teaser foi removido e é composto por render array SDC pelo preprocess do tema.
- `product-card` agora é integrado pelo preprocess no view mode `catalog`; o presenter Twig foi removido, mantendo o formulário, variações, preço, cache e acesso do Commerce.

Remover presenters quando código de display, preprocess ou UI Suite compuser o SDC equivalente preservando render arrays. Se a apresentação depender de configuração ainda não importada, manter caminho de compatibilidade SDC no código e registrar Runtime/browser como pendente; não manter Twig paralelo apenas para cobrir a transição.

### 3.2 Templates de Views e linhas

Usar `ui_patterns_views` para display/style/row segundo as capacidades efetivas do plugin instalado. Manter Views como donas de consulta, acesso, filtros, exposed form, ordenação, pager e argumentos contextuais.

- `templates/views/views-view--aculta-forum-public-topics.html.twig`
- `templates/views/views-view--aculta-forum-public-topics--page-category-term.html.twig`
- `templates/views/views-view--aculta-forum-search--page-search.html.twig`
- `templates/views/views-view--revista-sessoes--page-revista.html.twig`
- `templates/views/views-view-fields--aculta-forum-categories--page-categories.html.twig`
- `templates/views/views-view-fields--aculta-forum-public-topics.html.twig`
- `templates/views/views-view-unformatted--aculta-forum-categories--page-categories.html.twig`

Em Fórum, a evidência existente registra um adapter `forum-topic-row` usado porque o source `view_field` não expõe props suficientes para pinned/badges. Isso não é motivo para apagar dados/estado: ou mapear sources compatíveis na versão real, ou preservar adapter funcional mínimo até UI Patterns poder expressar o contrato. Não reintroduzir markup duplicado dentro de um segundo template de View.

### 3.3 Templates de bloco, páginas e integrações ACULTA

| Templates | Ação-alvo |
|---|---|
| `aculta-event-catalogue-card.html.twig`, `aculta-event-page.html.twig`, `aculta-events-upcoming-block.html.twig` | Passar card/detalhe/bloco para SDCs e compor por Views/Blocks/Layout Builder. O módulo `aculta_events` continua dono de eventos, operações e acesso. |
| `aculta-links-landing.html.twig`, `aculta-links-page.html.twig` | Compor landing e página com Layout Builder/UI Patterns; reutilizar `social-links-page`. Remover templates de landing/adaptação quando módulos e blocos fornecerem os render arrays. |
| `aculta-section.html.twig` | Migrar configuração para o layout/SDC `layout-section` e retirar o adapter quando nenhum bloco o solicitar. |
| `aculta-user-author-card.html.twig` | Passar o render array cacheável do Portal diretamente a `author-card`/formatter apropriado; manter dados e decisão de avatar no módulo owner. |
| `aculta-portal-shell.html.twig` | Decompor em layouts e slots UI Patterns; retirar o wrapper se só duplicar o shell/layout do site. Preservar IDs/attributes necessários a comportamento externo. |
| `aculta-portal-account-terms-modal.html.twig`, `aculta-portal-photo-editor.html.twig` | Reutilizar SDCs para apresentação; o JS, formulários, IDs, permissões e handlers continuam no módulo. O componente não substitui a lógica do modal/editor. |
| `aculta-portal-button.html.twig`, `aculta-donation-back.html.twig`, `aculta-donation-donate.html.twig`, `aculta-donation-quick.html.twig` | Usar `button` existente/configuração de bloco. Dono do módulo mantém URLs, valores e regras de doação. |
| `portal-cart-panel.html.twig`, `portal-cart-status.html.twig`, `portal-payment-notice.html.twig`, `portal-search-trigger.html.twig` | Compor `cart-offcanvas`, `status-messages`, `payment-notice` e `search-preview` por SDC/slots; manter HTMX, Commerce e contrato do Portal. |
| `aculta-maps-popup-content.html.twig` | Retirar somente se UI Patterns/component puder fornecer fragmento ao mecanismo Leaflet sem perda de attachments/cache. O wrapper `<template>` e o contrato de clonagem podem permanecer como adapter técnico. |
| `aculta-maps-marker-icon-template.html.twig` | Não é página nem composição editorial; manter enquanto Leaflet clonar o template de ícone. Se possível, mover a geração para o componente/integração de Maps sem alterar o contrato JS. |
| `comment--comments.html.twig`, `comment--field-aculta-discussion.html.twig` | Configurar renderização do entity display/formatter e SDC de comentário. Preservar permissões, campos, links de operação e renderização de comentários do Core/Portal. |
| `discussion-reaction-bar.html.twig`, `discussion-reaction-picker.html.twig`, `discussion-reply-composer.html.twig` | Converter apresentação para os SDCs de reação/composer já existentes; remover adapters e garantir que Form API/HTMX mantenha fallback e autorização. |
| `portal-component.html.twig` (se presente como tema hook) | Redundância provável com `portal-element`/preprocess e render arrays já compostos. Confirmar todos os invocadores do Portal antes de retirar hook/template. |

### 3.4 Templates do Drupal e da UI Patterns Library

Não são cortes prioritários de domínio. Manter `layout/html.html.twig` e um shell de página padrão; reduzir para a estrutura/regions de que o UI Suite precisa. O shell define landmarks, `page_top/page_bottom`, atributos, instituição, navegação, regiões e inserção de render arrays — função que UI Patterns Library não substitui.

Manter inicialmente `layout/page.html.twig`, `layout/page--shell-v2.html.twig`, `page-title.html.twig`, `navigation/breadcrumb.html.twig`, `navigation/pager.html.twig`, `misc/status-messages.html.twig` e `input--submit.html.twig`. Cada um pode ser simplificado ou substituído por componente/configuração depois de comprovar suporte e não duplicação. Remover o shell dual classic/V2 assim que a opção paralela deixar de existir; o alvo é um shell só.

Manter os quatro templates próprios da UI Patterns Library — overview, single e stories (compact/full) — enquanto forem necessários para o catálogo. Eles não são templates legados de página ACULTA. Manter `aculta-mailer-wrap.html.twig` para e-mail: e-mail não é saída de página e não é automaticamente consumidor de Layout Builder.

### 3.5 Ficheiros JS/CSS de página e administração

Remover CSS de domínio e scripts auxiliares quando o renderizador que os anexava for substituído e não houver consumidor externo:

- CSS de página/área para reavaliar e provavelmente remover: `account-dialogs.css`, `address-fields.css`, `event-organization.css`, `events.css`, `linkweed-admin.css`, `login-page.css`, `portal-account.css`, `search.css`, `secrets-admin.css`, `statistics.css`, `wiki.css`.
- `shell-v2.css`: consolidar no único shell/layout final e remover a escolha de shell.
- `layout.css`, `buttons.css`: revisar seletor por seletor; manter somente regras de fundação que não forem próprias de SDC.
- `ui-library-admin.css`, `ui-library-tabs.js`, `ui-library-bulk-preview.js`, `component-library-mode.css`, `component-library-mode.js`: preservar enquanto a UI Patterns Library depender deles; são infraestrutura do catálogo oficial/stories, não de páginas públicas. Não pertencem às prévias removidas de blocos ACULTA.
- `studio-htmx.js`, `htmx-dialogs.js`, `utility-dialogs.js`, `messenger-drawers.js`, `counters.js`, `alerts-dismiss.js`, `account-carousel.js`: mover para bibliotecas dos SDCs/módulos consumidores ou retirar se a interação for eliminada. Preservar HTMX, uma única inicialização, fallback e ownership do JS.
- `unpublished-preview.css`: manter somente se o hook de entidade ainda anexar a marcação de preview. O hook também preserva estado editorial, logo não apagar junto com templates de páginas.

`tokens.css`, `base.css`, o mapa `aculta420_v2.ui_skins.css_variables.yml` e a configuração UI Icons não são legado automaticamente: são fundação de skins, modo de cor, tipografia/contraste e ícones que os SDCs UI Patterns podem consumir. Reduzir tokens àqueles usados após o corte; não remover a integração de UI Skins/UI Icons sem decisão própria.

## 4. Hooks, classes e lógica que montam páginas

### Remover após migrar consumidores

- `PagePresentationHooks`: principal concentrador de shell, sugestões, composição por rota, wrapping em SDC, title/region adapter, Maps/Green Stash, Studio/conta e seleção de bibliotecas. É o maior alvo. Dividir os comportamentos: os de composição visual saem do tema para config UI Suite; os de dados/permissão/cache voltam para o módulo owner. Evitar manter um hook genérico que continue montando as páginas depois da migração.
- `ForumListHooks`: transferir os dados necessários à View/row para a View ou `aculta_forum`; remover preprocess específico de View que não tenha papel após configuração UI Patterns.
- `PortalUiHooks` e `aculta420_v2.theme` genérico: substituir mapa de classes globais por SDC/CSS local, UI Styles ou tokens já publicados. Conferir contratos `data-portal-ui` dos módulos antes de retirar, para não deixar tokens sem apresentação.
- `UiPatternsStoryHooks`, `src/Story/*.php`: reduzir stories PHP e usar stories SDC YAML como fonte quando possível. Manter somente stories realmente dinâmicas que precisem providers Drupal; dados fictícios ficam em story e nunca viram preview operacional.
- `ComponentLibraryRoutes`: preservar identificação da UI Patterns Library enquanto ela influenciar shell/asset; substituir teste de rota com integração suportada ou manter serviço mínimo, não regras de largura/estilo de página escondidas no hook.
- `WikiBrandMediaProps`: migrar a preparação de props ao módulo/formatter que possui a mídia. Preservar acesso do arquivo e cache.

### Preservar ou realocar, não simplesmente apagar

- `SurfaceSettingsHooks`: define `data-theme`, skin e atributos usados por UI Skins/tokens. Preservar enquanto o Portal/UI Skins não assumir integralmente esse contrato; remover só código redundante de classe/variante.
- `UnpublishedPreviewHooks`: marca estado de revisão editorial; não é composição de página. Manter no módulo editor/Portal ou como integração mínima se continuar dono do preview.
- `MailEnvelopeHooks`: preserva apresentação de e-mail, mover para o módulo de mail se o tema deixar de fornecer templates de e-mail.
- Helpers de status messages que garantem compatibilidade com mensagens emitidas por módulos contrib devem ficar no owner funcional, não em uma camada de tema que retempla globalmente todos os formulários.

## 5. Componentes SDC: o que retirar, decompor e preservar

Os diretórios SDC não são “fora do UI Suite”: eles são o material que UI Patterns cataloga. A regra não é remover os 135 SDCs em lote. Classificar por granularidade:

### Preservar como building blocks UI Patterns

Manter os componentes atômicos/de superfície e os que representam conteúdo funcional reutilizável, desde que o responsável continue pretendendo montá-los: `button`, `heading`, `icon`, `avatar`, `badge`, `form-field`, `empty-state`, `pagination`, `tabs`, `status-messages`, `surface`, `surface-accent`, `surface-panel`, `card-cultural`, `document-card`, `document-download`, `product-card`, `revista-album-card`, `revista-publication`, `revista-teaser`, `wiki-entry`, `forum-category`, `forum-topic-row`, `forum-opening-post`, `forum-post-card`, `discussion-comment`, compositores/reação, calendário, mapas, eventos e componentes reutilizáveis de conta/LMS/Studio.

Isso não significa aprovar o desenho atual de cada componente: markup e CSS de um SDC podem ser refeitos. A decisão de manter o contrato SDC não obriga manter visual ou props antigos.

### Decompor quando SDC significa página/shell fixo

Esses são candidatos fortes a deixar de ser uma composição opaca e virar layout UI Suite + componentes filhos:

- `account-shell`, `account-experience-dashboard`, `account-courses`, `public-profile-page`, `saved-content-page`;
- `event-page-layout`, `forum-category-page`, `forum-list-page`, `forum-search-page`, `green-stash-page`, `social-links-page`;
- `lms-learning-dashboard`, `lms-learning-journey`, `lms-learning-room`, `lms-lesson-page`, `studio-shell`, `studio-course-authoring-page`, `studio-connections-page`;
- `layout-page` e qualquer SDC que tente substituir o shell geral, em vez de ser layout editorial inserível em display.

Usar slots/regions de layouts UI Patterns para expor essas áreas. Onde módulo atualmente entrega uma composição pronta (ex.: dashboard), migrar a responsabilidade de composição para Layout Builder ou montagem explícita por blocos UI Patterns; módulo continua fornecendo dados/render arrays e acesso.

### Remover após provar ausência de consumidor

Componentes sem referência explícita em PHP/Twig/config não são mortos automaticamente porque a UI Patterns Library os descobre como catálogo. Confirmar intenção editorial. Só excluir componentes claramente abandonados/de demonstração — por exemplo, itens `image-audit-report`, `audio-player`, `filter-chips`, `button-scheme`, `profile-editor-dialog`, `map-widget`, `lms-tour` ou componentes de estatística sem consumer — depois de confirmar que não serão usados na biblioteca que o responsável está construindo.

## 6. Configuração para compor pelo UI Suite

1. **Displays (95):** classificar os 73 sem Layout Builder entre: displays funcionais/nativos (admin, Commerce order, e-mail, campos simples), displays que devem adotar Layout Builder/ComponentLayout, e displays que serão SDC via UI Patterns field formatter. Não forçar Layout Builder em field-only/Commerce/admin se prejudicar formulário ou acesso. A configuração UI Suite/SDC deve ser escolhida por display, sem apagar valores atuais por lote cego.
2. **Blocos (35):** inventariar plugin, placement, região, visibilidade, `site_key`, domínio, acesso e cache. Blocos reais permanecem blocos; editar a região/ordem/visibilidade e selecionar componente UI Patterns/Styles, sem reimplementar plugin de negócio como SDC. As prévias administrativas próprias foram removidas; composição e gestão seguem nas ferramentas Drupal/UI Suite.
3. **Views:** converter cada View pública de apresentação para UI Patterns row/style/display conforme source disponível. Não alterar query handlers; se UI Patterns não consegue representar uma row ainda, documentar a limitação específica e usar o adapter mais fino possível.
4. **Campos/formatters:** usar UI Patterns Field/Field Formatters somente após confirmar API e módulos efetivamente habilitados no Runtime. Preservar cardinalidade, revisão, tradução e acesso a entidade referenciada.
5. **UI Styles:** reduzir classes de apresentação genéricas para opções editoriais intencionais. Remover classes que duplicam variantes dos SDCs ou só existem para templates a excluir; preservar schema e seleções existentes até migração de Config Sync.

## 7. Corte por domínio

| `site_key` | Superfícies a compor no UI Suite | Saídas temáticas a remover |
|---|---|---|
| `main` | landing, atividades, páginas institucionais, navegação e blocos editoriais | wrappers de home, CSS de página/portal e composição de hook |
| `account` | login/conta, perfil, painel, dashboard e buscas de conta | `account-shell`, page adapters e CSS de conta após portar controles a módulos/SDCs |
| `support` | doação, campanhas, painel de vendas e mensagens | templates de botões/doação e folhas específicas; manter panes/checkout no Commerce owner |
| `magazine` | home, revista, publicação, autores, álbum/galeria e Views | node/view templates de Revista e CSS de página |
| `wiki` | landing, categorias, busca, artigos e relacionados | templates de node/View e `wiki.css`, substituídos por display/layout e assets dos componentes |
| `shop` | catálogo, produto, galeria e notices | template Commerce de produto e estilos globais do catálogo, preservando formulários/variações/preço |
| `courses` | catálogo, páginas de curso/aula, experiência do aluno e Studio | page wrappers, shell especializado e assets duplicados; manter LMS/Form API |
| `forum` | landing/categorias, busca, tópicos, comentários, composições e galerias | templates de Views/node/comment e hooks de lista depois de mapeamento equivalente |
| `links` | landing e social links page como composição editorial | templates de landing e page adapter próprios |
| `eventos` | landing, catálogo, evento, próximos eventos, organização | templates de card/page/blocks e estilos globais após anexar assets por componente |

## 8. Alvo mínimo do tema após o corte

- `aculta420_v2.info.yml`: tema sem base theme continua possível, com cinco regiões necessárias e apenas bibliotecas de fundação inevitáveis.
- Um template `html` e um template de `page` para shell/regions/landmarks; sem bifurcação classic/v2 ou composição de conteúdo por route.
- `tokens.css` reduzido a tokens consumidos; `base.css` com reset, tipografia/foco/motion/reflow mínimos.
- UI Skins define a identidade por skin; UI Icons fornece os packs desejados.
- Diretórios `components/` contêm somente SDCs que o responsável quer construir e expor no UI Patterns. Nada de page-specific SDC opaco se seu conteúdo precisa ser editável por slots/Layout Builder.
- `src` sem page builder; apenas adapters mínimos e contratos transversais ainda não oferecidos pelo Core/UI Suite.
- CSS/JS de comportamentos em bibliotecas SDC/módulos, não agregados globalmente.
- UI Patterns Library e ACULTA UI Library continuam como ferramentas distintas.

## 9. Sequência de execução recomendada

1. Congelar o inventário dos consumers da versão real: Config Sync + configurações ativas/overrides por domain + UI Suite filesystem/runtime. Resolver divergências antes de apagar templates.
2. Converter domínio a domínio em commits pequenos, começando pelas páginas com displays e SDCs já disponíveis; a escolha do primeiro domínio não precisa ser gate para os demais.
3. Para cada saída, configurar UI Suite, retirar template/hook/CSS/JS que só existia para aquela saída e rodar gates funcionais apropriados. Reverter com Git/config versionada se o render quebrar; não manter visual legado só para rollback.
4. Migrar os displays/Views/blocks por ID e registrar status: `UI_PATTERNS`, `SDC_ADAPTER`, `NATIVE_FUNCTIONAL` ou `REMOVE_CONFIRMED`. A exceção `SDC_ADAPTER` exige uma limitação concreta do plugin, não preferência por Twig.
5. Repetir para dez `site_key`; revisar saída anônima/autenticada, acesso/caches, formulários e operações; revisar visual novo sem comparar obrigatoriamente com o antigo.
6. Remover hooks/artefatos compartilhados só após seus consumidores desaparecerem. Em seguida reduzir as bibliotecas globais e atualizar esta matriz com IDs/arquivos efetivamente removidos.

### Bloqueio atual para Runtime

A auditoria presente é do repositório. Há mudanças locais em andamento em arquivos do Fórum, UI Library, inventário e validador, além de remoção local não commitada de `discussion-comment`; preservei-as sem edição. Portanto, a contagem inicial de arquivos é uma fotografia e as áreas alteradas precisam ser reconciliadas com o estado final antes de executar exclusões. A configuração exportada permite inventário estático, mas não comprova que plugins, displays, overrides de Domain Config, permissões e conteúdo ativo coincidam. O Runtime Sync observado anteriormente está bloqueado por worktree suja, checkout servido compartilhado e delta cumulativo R4; nenhuma alteração estrutural do plano deve ser sincronizada nesse estado. Isso não bloqueia preparar e revisar código/config em Git; bloqueia afirmar migração efetiva no Runtime.

## 10. Critério para chamar o tema de “100% UI Suite”

- Toda composição editorial de página, display, bloco e View está declarada no UI Suite/Config Sync ou usa componente SDC por plugin documentado.
- Zero templates específicos de domínio/página que remontam conteúdo; os templates que restarem são estrutura Drupal, biblioteca, e-mail ou adapter funcional comprovado.
- Zero hooks do tema montando página por rota/domínio; `PagePresentationHooks` removido ou reduzido a contrato transversal mínimo demonstrado.
- CSS/JS anexados por SDC/integração que os consome, sem folha global de domínio sem consumer.
- Todo SDC mantido é intencional para UI Patterns; páginas complexas não escondem composição editorial dentro de componentes monolíticos.
- Todos os dez domínios abrem e entregam rotas/conteúdo/formulários com acesso/cache intactos. Paridade com o visual antigo não é exigida.

## 11. Snapshot anterior da composição UI Suite (2026-11-13)

Esta seção preserva a fotografia feita antes do commit `6537d1083a1ee100c457c48219e04a62a70403f4`. Contagens de SDCs, status de `node.wiki_entry.full`, caminhos removidos e estado do Runtime abaixo são históricos; para o estado atual, prevalece a seção 2 revisada em 2026-11-14 e o roadmap do tema.

### Configuração declarada

- Composer lock: `ui_patterns` `dev-2.0.x`, `ui_patterns_layouts` `2.0.23`, `ui_styles` `1.22.0`, `ui_icons` `2.1.0`, `ui_skins` `1.2.1`. Pacotes e diretórios agregadores existem no checkout; os submódulos UI Patterns/Styles ficam dentro dos diretórios agregadores, não em `web/modules/contrib/<submódulo>`.
- `core.extension.yml` habilita os módulos de UI Patterns para blocks, fields, field formatters, layouts, library e Views; Layout Builder e as integrações correspondentes de UI Styles também estão declaradas.
- `components/`: 135 diretórios SDC visíveis no momento da checagem. A UI Patterns Library descobre os SDCs pelo provider do tema. O gate de schema passou; o gate de render isolado cobre 83 componentes, não todos os 135.
- Entity displays: 95 no Sync; 22 habilitam Layout Builder. Os 22 usam `ui_patterns:*` como `layout_id` do ComponentLayout. Há **zero field formatter `ui_patterns`** em `content:` dos entity displays. Nos layouts atuais, os campos são componentes nativos de Layout Builder; isto fornece uma seção UI Patterns, mas não mapeia cada campo para props/slots de um SDC editorial.
- Views: 66 Views/159 displays. **13 Views** têm pelo menos uma composição UI Patterns: 12 seleções de style, 4 de row e 3 de display (19 pontos no total, com sobreposição entre pontos da mesma View). As Views configuradas são `aculta_album_fotos`, `aculta_albuns`, `aculta_documents`, `aculta_forum_categories`, `aculta_forum_gallery_slides`, `aculta_news`, `aculta_shop_catalog`, `eventos_publicos`, `galeria_produto`, `home_editorial_highlights`, `media_galleries`, `wiki_categories` e `wiki_entries`.
- Blocks: 35 placements associados ao tema ACULTA420, todos nas cinco regiões finais; zero fora delas. No universo de 52 block placements do Config Sync, não há instância configurada como bloco SDC/UI Patterns. `aculta_ui_library` não cataloga nem pré-visualiza mais blocos; a composição/gestão permanece nas ferramentas Drupal/UI Suite.

### Consumers de página sem UI Patterns configurado

Entre as Views com saída pública ou bloco público sem row/style/display UI Patterns, a checagem encontrou:

- Fórum: `aculta_forum_public_topics` (bloco e quatro páginas), `aculta_forum_search` (página) e `aculta_forum_unread_topics` (página autenticada). Os templates de apresentação do tema para tópicos e busca foram removidos; os três agora dependem do fallback Views/row atual até migração.
- Revista: `revista_sessoes` tem seis displays de página sem UI Patterns; seu template de página foi removido. `aculta_news` e as Views de álbum/galeria usam UI Patterns.
- Cursos: `courses` e `courses_catalog` têm página/bloco sem plugin UI Patterns.
- Main/Eventos: `aculta_activities` tem blocos sem plugin UI Patterns. `eventos_locais` é uma View administrativa; `eventos_publicos` usa UI Patterns style.
- Conta/perfis: `perfis_publicos` tem página `/membros` sem plugin UI Patterns.
- Exemplos VVJ (`vvja_example`, `vvjf_example`, `vvjh_example`, `vvjl_example`, `vvjr_example`, `vvjs_example`, `vvjt_example`) continuam usando plugins próprios de views_vv*; não são composição UI Patterns.

Há ainda Views administrativas, Commerce operacional, Webform, Media Library, tabelas e controles nativos. Esses itens não devem ser contados automaticamente como páginas editoriais pendentes; a regra de aceite precisa classificá-los como exceção funcional ou migração específica.

### Renderização e configuração ativa do Runtime

Leitura Drush somente-leitura no site de desenvolvimento confirmou que os styles ativos de `eventos_publicos`, `wiki_categories` e `wiki_entries` usam `aculta420_v2:layout-grid`; o display ativo do rodapé usa ComponentLayout `aculta420_v2:site-footer`. Os valores relevantes de UI Patterns permanecem ativos apesar do drift de outras propriedades/configurações.

`runtime-sync doctor --format=json` retornou `BLOCKED/R4`: 6 configurações divergem do Sync, há 0 updates e 0 deploy hooks pendentes, e o checkout está sujo e compartilhado com o Apache. A lista de drift inclui `aculta_forum.settings`, `block.block.aculta420_v2_aculta_breadcrumbs`, `core.entity_view_display.block_content.aculta_institution.footer`, `views.view.eventos_publicos`, `views.view.wiki_categories` e `views.view.wiki_entries`. Portanto a checagem confirma seleções ativas pontuais, mas não autoriza sincronizar nem prova que o cache HTTP esteja reconstruído.

### Gates executados

- `scripts/validate-ui-patterns-views-cutover.php`: **PASS (95 checks)** para as Views cobertas pelo gate.
- `scripts/validate-aculta420-sdc-schemas.php`: **PASS (135 componentes, 136 includes, 2374 checks)**.
- `scripts/validate-aculta420-v2-sdc.php`: **PASS (83 componentes descobertos/renderizados isoladamente)**.
- `scripts/audit-aculta420-v2-inventory.php`: **PASS como inventário estático**; 35 blocos-tema nas cinco regiões, 95 displays, 22 com Layout Builder.
- `scripts/validate-aculta420-layout-builder-batch.php`: **FAIL (4 assertions)**. Um failure é `node.wiki_entry.full`: o adapter Twig que fornecia `aculta-wiki-article` foi removido, mas o display ainda não compõe o SDC `wiki-entry` por UI Patterns. Três assertions do rodapé esperam genericamente `layout-section`; Config Sync e Runtime usam corretamente o layout específico `site-footer`, então este trecho do gate está desatualizado e não distingue layouts ComponentLayout.
- Browser visual/HTTP pós-corte e cache rebuild: **DEFERRED**; não executados nesta verificação.

### Resultado

O site tem adoção concreta de UI Patterns em ComponentLayout e em 13 Views, mas não há composição universal. O corte de Twig expôs, em vez de converter, páginas públicas que ainda dependiam de templates SDC. O bloqueio mais claro para chamar Wiki de convertida é `node.wiki_entry.full`; Fórum, Revista, Cursos, Conta e atividades também precisam de configuração de row/style/display antes de serem declarados UI Suite. Os 53 Views restantes incluem vários consumidores nativos/admin que precisam de classificação antes de qualquer migração em lote.
