# Roadmap do tema ACULTA420 V2

## ADR-056 — revisão documental e recomendações UI Patterns 2 (2026-11-13)

Esta é a fila vigente; entradas datadas abaixo preservam o histórico das rodadas
anteriores e não substituem a conferência do código atual.

### Já confirmado no checkout

- Um shell em `templates/layout/page.html.twig`; `aculta420_v2.info.yml`
  declara cinco regiões (`institution`, `header`, `content`, `sidebar_first`,
  `footer`). O seletor classic/v2 foi removido do formulário/configuração e a
  chave `aculta_shell_variant` não existe no Config Sync nem no Runtime ativo.
  O hook no-op temporário de compatibilidade foi removido depois do rebuild do
  cache Drupal em 2026-11-14.
- 145 manifests SDC e 229 stories após remover os três componentes do seletor. Todos têm story: `hint` exemplifica os tons
  normal/muted; `sales-panel` e `secret-reveal` usam dados demonstrativos
  fictícios.
- Config Sync habilita UI Patterns, Blocks, Field, Field Formatters, Layouts,
  Library e Views; e UI Styles para Block, Layout Builder, Page, UI Patterns,
  Views e Entity Status. Isso não comprova descoberta ou funcionamento no
  Runtime.
- O Composer lock usa UI Patterns `2.0.x-dev` no commit que também é a tag
  estável 2.0.23. O histórico mostra que a constraint dev foi adotada para
  compatibilidade antecipada com Drupal 12; o patch ACULTA que troca quatro
  chamadas de `drupal_static()` continua aplicado. A release estável 2.0.23
  declara suporte a Drupal 12. Avaliar constraint estável `^2.0` mantendo o
  patch, sem atualizar dependências nesta etapa. A Library 2.0 ainda usa
  templates `hook_theme` e upstream sinaliza migração para SDC/display building
  em 2.1/2.2; manter gate dos quatro overrides.
- A UI Patterns Library upstream monta a página de provider percorrendo as
  definições agrupadas do component plugin manager. Não localizei filtro
  oficial para ocultar SDCs individuais. O CSV de consumidores foi regenerado
  para o checkout atual; os campos `runtime_status` ainda incluem snapshots
  antigos. Não remover SDCs por busca literal: a ausência de referência não
  prova ausência de uso editorial ou dinâmico. Os 35 sem referência literal
  foram classificados pela intenção declarada nos manifests em
  `docs/architecture/ACULTA420-V2-AUDITORIA-SDCS-SEM-REFERENCIA-2026-11-14.md`;
  todos permanecem publicados. Confirmar seleções editoriais persistidas no
  Runtime após liberar preflight. Matriz:
  `docs/architecture/inventarios/ui-suite-consumidores-iniciais.csv`.
- Decisão do responsável (2026-11-13): remover por completo o módulo customizado
  `aculta_ui_library`, inclusive o redirect legado, e manter somente a UI Patterns
  Library oficial. Estado conferido em 2026-11-14: o módulo está ausente de
  `core.extension` no Config Sync e no Runtime; o Runtime habilita
  `ui_patterns_library`; o último cache anterior à purga descobria 148 SDCs `aculta420_v2`. O checkout agora tem 145 e requer rebuild.
  Não há código executável do módulo no checkout; os dois documentos restantes
  neste diretório foram removidos. Persiste apenas a chave órfã de schema no
  Runtime, cuja limpeza requer backup e prova de restauração.
  A leitura Runtime de 2026-11-14 confirmou zero updates e zero deploy hooks e
  nove configurações divergentes (`aculta420_v2.settings`, o display footer da
  instituição, `node.revista.full`, cinco Views e `revista_sessoes`). O risco
  cumulativo do Runtime Sync continua RX e seu baseline permanece em
  `e37f3d8d45ecba9719d4b97dde67effc2236b13f`; não houve importação estrutural.
  O checkout compartilhado com Apache é o modo esperado.
- Restam 17 templates Twig Drupal no tema. O fallback de fields para
  `aculta_forum_categories:page_categories` foi removido após leitura somente
  no Runtime confirmar que sua row ativa usa UI Patterns + `forum-category`;
  o template de agrupamento continua necessário porque o estilo ativo ainda
  agrupa por categoria-pai. A renderização final ainda precisa de homologação.
  O shell paralelo foi removido e `node.wiki_entry.full` usa ComponentLayout
  UI Patterns com o SDC `wiki-entry`.
  O Config Sync também prepara `node.revista.full` com o SDC `revista-publication`;
  o preprocess preserva a composição SDC quando o ComponentLayout ainda não está ativo no Runtime. Os demais
  permanecem até o consumidor ter substituição equivalente, inclusive
  Views e formulários do Fórum. A revisão independente desse lote não encontrou
  bloqueadores no mapeamento Wiki/shell.
- O catálogo de Eventos compõe `card-cultural` pela row de Views; não depende do
  antigo template de card. A verificação HTTP recente respondeu 200; a
  homologação visual completa continua pendente.

### Trabalho pendente, em ordem

1. **P0 — reconciliar documentação (primeira passada feita):** README, instruções
   do tema, `architecture.md`, `components.md`, `gates.md`, `ui-styles.md`,
   `layouts-plan.md`, status e roadmaps já foram comparados ao checkout. Foram
   corrigidas métricas, shell, UI Styles, Eventos e FAQ. Falta reler os planos
   por domínio e concluir o mapa de consumidores dos SDCs/templates, corrigindo
   cada status que ainda não tenha fonte de código/configuração.
2. **P1 — contratos e stories:** cruzar cada SDC com código/configuração e
   inventariar todos os itens publicados/selecionáveis nas UI Libraries. Preservar
   SDCs presentes nessas bibliotecas e garantir que todo item nelas seja SDC.
   Não cortar SDCs pela falta de referência literal: a Library atual lista as
   definições do provider. Stories para `hint`, `sales-panel` e `secret-reveal`
   foram adicionadas. Todos os slots agora têm título e descrição, e as props
   de topo têm título; o gate exige esses metadados. Auditoria atual dos 145
   manifests: 525 props de topo, das quais 305 ainda não têm descrição em 67
   SDCs. Considerando todos os nós de schema tipados sob essas props, incluindo
   itens de arrays e schemas aninhados, são 689 nós, com 415 sem descrição em
   70 SDCs. Totais históricos de 526/333 e 654/429 não se reproduzem neste
   checkout e foram substituídos. Nesta rodada, oito manifests básicos
   (`audio-player`, `data-table`, `filter-chips`, `form-field`, `hero-banner` e
   três componentes de estatísticas) foram conferidos contra seus Twig e tiveram
   descrições adicionadas às props e estruturas aninhadas. Os 18 manifests
   anteriores do domínio Fórum também foram conferidos contra Twig e tiveram
   esses campos documentados. A checagem do provider instalado encontrou
   A validação atual do provider encontrou 22 props `unknown` em 13 SDCs,
   incluindo `mail-envelope.colors`; duas props obrigatórias já estavam no
   inventário anterior. O objeto de cores do envelope é fornecido pelo adapter
   de e-mail, sem Source configurável no provider. A auditoria classificou os
   produtores encontrados e registrou as fontes na ADR-056. Os presenters de Eventos e a
   barra de reações já chamam SDCs diretamente, preservando cálculo, acesso e
   cache nos módulos. O seletor HTMX e seus SDCs (`discussion-reaction-picker`,
   `discussion-reaction-category`, `discussion-reaction-option`) foram removidos.
   A rota e o builder de fragmento também saíram. `DiscussionReactionBuilder`
   entrega `discussion-reaction-bar`, cuja paleta interna preserva a interação.
   As barras de Reações e Reações Recebidas mantêm seus contratos. O resultado
   no Runtime ainda precisa de homologação. Seguem
   pendentes as descrições específicas das props e a confirmação de uso
   editorial persistido no Runtime. Avançar na auditoria semântica restante:
   nomes, tipagem, slots/props,
   variants/defaults, includes e escaping. Renomear apenas conceitos realmente
   reutilizáveis com plano de compatibilidade das referências persistidas.
3. **P2 — cruzar integrações:** matriz estática regenerada em 2026-11-14 com
   451 linhas (cabeçalho e 450 consumers): 145 SDCs, 159 displays Views, 52
   instâncias de bloco, 22 displays com Layout Builder e 17 templates Twig Drupal
   do tema. Foram achadas
   referências literais a 110 SDCs; os 35 sem referência literal não são
   candidatos automáticos a exclusão, pois a Library os publica e usos
   editoriais/dinâmicos não aparecem nessa busca. A leitura do provider sem
   cache encontrou 145 definições SDC; a classificação de intenção dos 35 está
   em `docs/architecture/ACULTA420-V2-AUDITORIA-SDCS-SEM-REFERENCIA-2026-11-14.md`.
   Ainda falta reconciliar seleções editoriais persistidas no Runtime e revisar
   consumidores por item.
   Leituras Drush somente no Runtime em 2026-11-14 confirmaram estado ativo
   diferente do Sync: `aculta_forum_categories.page_categories` usa row UI
   Patterns + `forum-category`, mas estilo `default` agrupado por `parent_name`;
   `aculta_forum_public_topics.default` usa row `fields`; `revista_sessoes`
   mantém estilo `default` com rows `entity:node` teaser; Cursos foi configurado
   no Sync para UI Patterns Views + `layout-grid` nas Views `courses` e
   `courses_catalog`, preservando a row `entity:group` e o view mode `default`.
   A leitura Runtime anterior a essa alteração encontrou `grid_responsive` em
   `courses` e estilo `default` em `courses_catalog`; a importação ainda não foi
   executada. Perfis usa row `fields`. Os cortes devem seguir o estado ativo até
   alinhar/homologar a configuração. Para Revista, o
   template de página preserva title/contextual links, filtros, empty, pager,
   attachments, more, footer e feed, e apresenta `revista-listing` como fallback
   quando a style UI Patterns ainda não está ativa. A API do Component Display
   Extender não oferece Source para `title_prefix/title_suffix`/contextual
   links. Runtime, cache e os seis paths ainda precisam de homologação após
   liberar o preflight.
4. **P3 — reduzir os 17 templates com substituição comprovada:** a triagem por
   fonte está abaixo. Para cada arquivo, classificar se é markup de domínio
   removível, adapter Drupal→SDC/UI Patterns ou infraestrutura do hook Core.
   Remover templates de domínio quando o display/UI Patterns assumir o contrato;
   manter somente adapters inevitáveis e shell estrutural. Antes de trocar por
   fallback Core, comparar o markup e os consumidores do projeto, inclusive
   dialogs, atributos, regiões, prefix/suffix, contextual links e placeholders.
5. **P4 — homologar:** gates focais e comparação real por consumidor, incluindo
   cache-hit, acesso público/autenticado, tradução, claro/escuro, teclado e
   larguras aplicáveis. Runtime Sync/rebuild somente após preflight liberado.

### Retirada de `aculta_ui_library`

Concluída no código e no estado ativo: o Config Sync e o Runtime não habilitam
`aculta_ui_library`; a extensão não tem código executável, rota ou gate próprio
no checkout. Os documentos históricos residuais foram removidos. A UI Patterns
Library permanece habilitada. O último cache Runtime anterior à purga descobria 148 SDCs; o checkout atual tem 145 e requer rebuild. A única pendência
é uma chave órfã em `system.schema` no Runtime. Tratar essa chave como limpeza
focal de banco: gerar plano, backup privado e prova de restauração em
`aculta_runtime_copia`; então removê-la pela API Drupal e validar. Não fazer
importação geral de configuração para resolver esse resíduo.

### Templates restantes no tema para P3

Conferidos por `find web/themes/custom/aculta420_v2/templates -name '*.twig'`
em 2026-11-14 (17 arquivos após remover uma sugestão sem hook consumidor, o
fallback de fields substituído por row UI Patterns e o adapter de busca convertido
para UI Patterns Views):

`aculta-mailer-wrap.html.twig`, `comment--comments.html.twig`,
`comment--field-aculta-discussion.html.twig`,
`input--submit.html.twig`,
`layout/html.html.twig`, `layout/page.html.twig`,
`misc/status-messages.html.twig`, `navigation/breadcrumb.html.twig`,
`navigation/pager.html.twig`, `page-title.html.twig`,
`ui-patterns-overview-page.html.twig`, `ui-patterns-stories-compact.html.twig`,
`ui-patterns-stories-full.html.twig`,
`views/views-view--aculta-forum-public-topics--page-category-term.html.twig`,
`views/views-view--aculta-forum-public-topics.html.twig`,
`views/views-view--revista-sessoes--page-revista.html.twig`,
`views/views-view-unformatted--aculta-forum-categories--page-categories.html.twig`.

O FAQ e o override `ui-patterns-single-page.html.twig` saíram desta fila em
2026-11-14. A single page usa novamente o template upstream instalado; o loader
de prévias agora extrai `.ui_patterns_component__stories`. Não contar templates dos módulos como
overrides do tema; os presenters Twig module-owned relacionados são
`aculta-portal-photo-editor.html.twig`, `portal-component.html.twig`,
`aculta-mailer-wrap.html.twig`, `aculta-maps-popup-template.html.twig` e
`aculta-maps-popup-content.html.twig`.
Cada um exige revisão de fronteira com seu módulo owner antes de remoção.

### Classificação dos presenters pelo código

| Grupo/arquivos | Evidência encontrada | Direção de planejamento |
| --- | --- | --- |
| `layout/html.html.twig`, `layout/page.html.twig` | Infraestrutura de documento/página Drupal. O override HTML mantém `#drupal-modal` (consumido pelos comandos Dialog/AJAX do Core e pelo UI Icons Picker), favicon, Apple touch icon, manifest e configuração HTMX. O fallback `core/modules/system/templates/html.html.twig` não contém o contêiner `#drupal-modal` nem esses quatro metadados. `page.html.twig` compõe `layout-page`, mas também seleciona conteúdo/variantes para LMS, Studio e rodapé social. | Não remover `html.html.twig` por comparação superficial com o Core. Para retirá-lo, primeiro migrar os metadados para attachments do Drupal e fornecer o alvo `#drupal-modal` por uma integração renderizável, preservando a ordem dos placeholders; validar diálogos AJAX, Entity Embed e UI Icons. Para `page.html.twig`, mover decisões de domínio para preprocess/render arrays; manter a ponte de regiões somente enquanto nenhum plugin/layout UI Suite substituir essa composição sem perder os contratos do page hook. |
| `ui-patterns-overview-page.html.twig`, `ui-patterns-stories-compact.html.twig`, `ui-patterns-stories-full.html.twig` | Overrides remanescentes da Library; implementam catálogo e prévias progressivas/HTMX, exibem metadados e hidratam props/slots de stories. `ui-patterns-single-page.html.twig` foi removido em 2026-11-14 e voltou ao template upstream. | Manter overview/stories enquanto o upstream não oferecer preview sob demanda: a implementação oficial renderiza as stories no overview, incluindo as 229 stories do checkout atual. A documentação oficial permite overrides de template e informa migração futura para SDC/display building em 2.1/2.2. Migrar só quando a API suportar lazy loading e mantiver todas as stories disponíveis; preservar todos os SDCs e stories catalogados. |
| Override do tema `aculta-maps-marker-icon-template.html.twig` | Removido em 2026-11-14. O `MapRenderer` produz o wrapper inerte do marcador num `inline_template` com autoescape e inclui o SDC `icon`; remove a dependência do registro antigo de template Twig sem introduzir novo componente ou cache de descoberta. | Corte concluído; validar rendering e clone do ícone em requisição nova no Runtime. |
| Overrides do tema `page-title.html.twig`, `misc/status-messages.html.twig`, `navigation/breadcrumb.html.twig`, `navigation/pager.html.twig`; template owner `aculta-maps-popup-content.html.twig` | Composição leve de SDCs (`map-popup`, `heading`, `status-messages`, `breadcrumb`, `pagination`) com dados/atributos Drupal. O popup é template do módulo Maps, não override do tema; o modal de termos já compõe SDC direto pelo tema. | Para os overrides do tema, tentar render array SDC/configuração ou integração UI Patterns. Manter presenter fino apenas se o hook Drupal/Core for inevitável; revisar prop type `links` e atributos. Migrar o template owner apenas respeitando a fronteira do módulo Maps. |
| `aculta-mailer-wrap.html.twig` | Hook `aculta_mailer_wrap`, enviado pelo `AcultaMailerMail`; o template mantém documento HTML, metadados, CSS inline de claro/escuro e saída simples. A tabela visual, logo, corpo e rodapé opcional passaram ao SDC `mail-envelope` incluído no template. | Composição visual agora está no SDC publicado pela UI Patterns Library; manter a ponte do documento de e-mail por depender do pipeline de mail. Verificar geração real HTML/texto, URL do logo e cores após cache no Runtime. |
| `comment--comments.html.twig`, `comment--field-aculta-discussion.html.twig` | São sugestões ativas: `CommentViewBuilder` define `#theme` como `comment__{field_name}__{entity_bundle}`. `comments` atende `aculta_lms_answer.comments`/`aculta_lms_answer_comment`; `field_aculta_discussion` está configurado em `aculta_forum_topic`, `revista`, `wiki_entry` e `aculta_lms_topic`. Ambos agora compõem `forum-comment`, que delega ao SDC `forum-post-card`; os adaptadores continuam diferentes apenas no contexto visual do comentário-pai; preservam atributos, prefix/suffix, permalink, pai, links de ação e destino do compositor. | Não classificar como órfãos nem apagar por duplicação textual. Os adaptadores agora centralizam o wrapper e mantêm pager, threading, acesso, cache, ações e os quatro domínios. Para removê-los do tema, avaliar UI Patterns Field/Field Formatter contra a API do formatter `comment_default`, preservando atributos, prefix/suffix e destino do compositor. Uma troca de presenter atinge comentário público/autenticado em vários domínios (R2); só retirar ambos após prova de substituição e revisão independente. |
| Template owner `aculta-portal-photo-editor.html.twig` | Já compõe `aculta420_v2:account-photo-editor` pelo hook `preprocess_aculta_portal_photo_editor`, com o render array de Form API no slot `content`; o template owner imprime esse componente e mantém wrapper neutro como fallback. | Composição SDC concluída no código; não entra na contagem dos templates do tema. Homologar formulário, acesso, cache e fallback no Runtime. |
| Template owner `portal-component.html.twig` | Ponte neutra de fallback fornecida pelo Portal; `PagePresentationHooks::preprocessPortalComponent()` mapeia contratos semânticos conhecidos para SDCs do tema. | Manter enquanto houver emissores do contrato neutro e fallback não mapeado; migrar emissores apenas quando puder preservar a independência do módulo em relação ao tema. Não entra na contagem dos templates do tema. |
| `content/node--aculta-forum-topic.html.twig` (removido em 2026-11-14); `content/node-add-form--aculta-forum-topic.html.twig` (removido em 2026-11-14) | `PagePresentationHooks::composeForumTopic()` monta `forum-opening-post`, `forum-topic` e `forum-topic-page` como render arrays. O wrapper Core mantém atributos, prefixo/sufixo, título de teaser e contextual links. O arquivo `node-add-form--aculta-forum-topic.html.twig` não tinha hook consumidor: Core registra `node_edit_form` (`NodeThemeHooks::theme()`), e nenhum código do projeto registra `node_add_form`; a rota usa Form API/`TopicComposerFormHooks`. O SDC `discussion-topic-composer` continua na Library e tem story executável, mas isso não torna aquele filename um template ativo. | O corte do node foi feito e testado anteriormente. A sugestão órfã de formulário foi removida sem alterar Form API ou handlers. Manter ambos os SDCs (`discussion-topic-composer` e `forum-composer-guidelines`) no catálogo; a conexão de produção dessas composições é uma tarefa separada, sem inferi-la pela story. Runtime/browser do tópico e do formulário ainda precisa de homologação quando o preflight liberar. |
| Views do Fórum público | `aculta_forum_public_topics` usa row UI Patterns `forum-topic-row`; `sticky` chega como boolean pela Source `view_field_value`, e o presenter `views-view-fields` foi removido. A busca usa o display extender UI Patterns com `forum-search-page`; `view_title_markup` preserva o título escapado e `view_contextual_links` mantém o placeholder administrativo do Core, junto às Sources upstream de resultados, filtros, empty, pager e áreas. Config Sync e YAML de instalação ficam sincronizados; os displays efetivos do Runtime ainda precisam importar e homologar. | Permanecem os wrappers das páginas de lista e categoria porque transportam dados próprios do Fórum que ainda precisam de Sources dedicadas; o template de agrupamento de categorias fica enquanto UI Patterns Views não suportar grouping. |
| `aculta_portal` Revista full | `PagePresentationHooks::composeRevistaFull()` preserva fields renderizados e compõe o SDC `revista-publication` como render array quando o ComponentLayout UI Patterns ainda não está ativo; quando ativo, encaminha `post_meta` e `article_authors` aos slots existentes. O Twig específico foi removido; o wrapper Core mantém artigo, contextual links e title prefix/suffix. | Corte no código concluído; importação da configuração e homologação de layout, acesso e cache no Runtime continuam pendentes. |
| `content/node--revista--teaser.html.twig` | Removido em 2026-11-14. `PagePresentationHooks::preprocessNode()` compõe o SDC `revista-teaser` com os render arrays dos fields do view mode e mantém a configuração de accent como dependência de cache. | Corte concluído no código do checkout; Runtime/renderização após cache não homologados. |
| `aculta_portal` Loja | O template Commerce do catálogo foi removido; `PagePresentationHooks::preprocessCommerceProduct()` passa o render array de `catalog` ao SDC `product-card` e o template Core mantém o wrapper `article`. | UI Patterns `layout-grid` continua compondo as linhas Commerce; validar render, preço, acesso e cache quando o Runtime for liberado. |
| `content/node--aculta-faq--full.html.twig` | Removido em 2026-11-14. `PagePresentationHooks::composeFaqFull()` compõe `accordion` com título do node e render array de `body` no view mode `full`; se a descoberta do SDC falhar, usa o elemento Core `details`. O template Core mantém wrapper e contextual links. | Corte de código concluído; verificar composição real, acesso e cache no Runtime após desbloquear o preflight. |
| `input--submit.html.twig` | O submit genérico converte controles Drupal para o SDC `button`, preservando atributos. O picker de reações agora usa três SDCs em slots e o hook/template foi removido; o endpoint owner mantém access check e CSRF. | Manter o adapter Core do submit enquanto não houver substituto que preserve atributos. Homologar o picker SDC, search e resposta HTMX no Runtime quando o preflight permitir. |
| `views/views-view--revista-sessoes--page-revista.html.twig` | Os seis displays herdam UI Patterns Views style `revista-listing` pelo Config Sync; rows continuam node teaser. O template específico da Página Revista preserva title/contextual links, filtros, empty, pager, attachments, more, footer e feed, além de fallback anterior ao import. | Configuração preparada; o template imprime a saída SDC do style plugin quando ativo, sem duplo wrapper. Após liberar sync, verificar os seis paths, acesso/cache, empty, pager, anexos e contextual links. Só depois avaliar retirada; o Component Display Extender não fornece Source para o contexto administrativo do Views que o Twig atual preserva. |

Esta classificação é somente uma triagem por fonte encontrada. Ela não prova que
um template tenha consumidor ativo em cada domínio/Runtime. O objetivo é testar a
remoção de cada um; a exceção é um presenter mínimo quando não houver API de
display/substituição adequada. A migração de `comment`, `photo-editor` e
templates owner de reação/email/mapas depende da matriz completa de chamadas,
displays, respostas HTMX, acesso e cache da fase P2. `portal-cart-panel` não foi
encontrado no checkout e foi retirado da classificação vigente.

A decisão de manter temporariamente os templates de overview/stories segue a
[documentação oficial de UI Patterns Library 2](https://project.pages.drupalcode.org/ui_patterns/2-authors/1-stories-and-library/): ela documenta a personalização por overrides e prevê migrar essas páginas para SDC/display building em 2.1 ou 2.2.

## Continuação do corte CSS SDC-first (2026-11-13)

- Revisão da classificação PurgeCSS: os 22 seletores sem evidência foram
  procurados em todas as fontes Twig/PHP/YAML/JavaScript do tema e módulos
  customizados, sem consumidores. Removidos 24 nós de regra em
  `donation-presentation`, `sales-panel` e `studio-connections-page` (3.295
  bytes no diff incluindo espaço em branco). Os 144 rejeitados restantes foram
  inspecionados por categoria e mantidos por serem dinâmicos, presentes no
  Runtime, cobertos por padrão Drupal/UI ou referenciados no código. Inventário
  atual: 144 folhas, 411.996 bytes, 2.357 regras e 2.811 seletores. Auditoria
  PurgeCSS: 390.250 bytes retidos e 21.746 removidos. Aceite visual após corte
  não executado; sincronização Runtime bloqueada por risco RX/drift.

- UI Patterns Library / Biblioteca de Layouts: naquele snapshot, o catálogo
  registrou 127 manifests e 188 stories descobertos no código. As mudanças desta sessão
  usam SDCs existentes e suas stories, incluindo eventos, Conta Studio e LMS.
  Os sete SDCs no grupo `Layouts` estão documentados; seis são cobertos pelo
  gate atual e `event-page-layout` ainda precisa de conferência no seletor do
  Layout Builder. A descoberta no Runtime depende de rebuild de cache.

- A composição do Dashboard Studio não usa mais theme hooks/templates intermediários: provider, compositores e controller emitem os SDCs `account-experience-dashboard`, `account-experience-card`, `studio-calendar` e `studio-connections-block` diretamente. Acesso e cache permanecem nos render arrays. Render Drupal e aceite visual pendentes.
- Studio Conta: menu e calendário seguem como SDCs; o contexto da rota agora é passado explicitamente ao `layout-page`, removendo regras `:has()` nos ancestrais e os overrides de preview da UI Patterns Library. Validação visual do dashboard e das stories no Runtime pendente.
- O catálogo público de eventos usa UI Patterns Views com o SDC `layout-grid` (`view_rows`, mínimo de 16rem e máximo de três colunas). O arquivo de instalação da View está alinhado ao config sync; o template unformatted e a grade `:has()` foram removidos. Renderização no Runtime e aceite visual responsivo permanecem pendentes.
- O cartão cultural deixou de emitir tokens de estilo mapeados: seus estados e elementos são estilizados pelo próprio SDC `card-cultural`. Acessibilidade visual dos estados e links aguarda aceite responsivo no Runtime.
- `/ganjalab` foi recriada como página SDC completa. `LearnerDashboardComposer`
  agora transforma somente o resumo autorizado em props/slots dos SDCs
  `lms-learning-dashboard`, `lms-learning-course-card` e `lms-certificate-card`;
  os wrappers genéricos de `context-header`, `card`, `content-grid` e o token
  `studio-fidelity-scope` saíram. O dashboard compõe ainda `heading`,
  `progress`, `empty-state` e `button`. Consultas, matrícula, autorização,
  destinos e cache continuam nos serviços LMS. O preprocess não envolve mais
  a página pela segunda vez. O antigo registro deste roadmap sobre o wrapper e
  cartões UI Patterns fica substituído por esta composição.
- O `AccountShellBuilder` ainda lista Aprendizagem no hub da Conta, mas ignora
  a rota LMS ao compor a shell de `/ganjalab`. O sidebar de agenda configurado
  para outras páginas também não é anexado à home LMS; ela usa layout full.
- Os erros 500 dos hosts Wiki420 e Growroom eram erros Twig de descoberta de
  SDC. Após `drush cr`, os 11 hosts revisados responderam sem 500 nas raízes:
  9×200, Conta 302, Fórum 404 previsto pelo mapa atual. `/ganjalab` e o workspace
  Educador dão 403 anônimo conforme as guardas de acesso. Render Drupal direto
  da nova página LMS confirmou H1, empty-state e CTA de catálogo, sem marcador
  legado. Browser autenticado confirma a página isolada em 390/1280 px, um H1,
  sem rolagem horizontal, shell da Conta ou bloco de agenda, além de tokens
  escuros. Foram usados estado vazio e cursos matriculados sem aulas publicadas;
  a jornada/sala seguem sem uma conta de revisão com aulas acessíveis.

- Eliminei todo o bloco de aliases `--aculta-studio-fidelity-*` de
  `studio-shell.css`: as referências válidas agora usam diretamente tokens e
  valores equivalentes; aliases sem consumidor foram descartados. Stylelint,
  CSS scope (168), mapped classes (376), CSS audit/verify e diff check passaram.
  Redução adicional de 1.367 bytes; inventário: 142 folhas/417.284 bytes/
  2.384 regras/2.836 seletores. A captura autenticada continua pendente.
- Os SDCs `lms-learning-dashboard`, `lms-learning-journey` e
  `lms-learning-room` agora herdam tipografia, cor-base e quebra de texto de
  `base.css`, removendo declarações locais duplicadas. Mantive tamanhos e pesos
  específicos de títulos, além do layout e dos estados de progresso/navegação.
  Validação autenticada da home, jornada e sala LMS ainda é necessária.
- Na UI Patterns, removi as regras globais duplicadas de resumo de detalhes,
  agora cobertas pelo seletor já escopado à biblioteca, e duas declarações
  repetidas da moldura de cartões. O grid, as prévias HTMX e os controles
  administrativos permanecem na biblioteca contextual. CSS scope (176),
  Stylelint e CSS audit/verify: PASS. Inventário atual: 142 folhas/416.436
  bytes/2.381 regras/2.833 seletores; 228 classes dinâmicas preservadas.
- O painel de Marley agora é o SDC `surface-panel`, com atributos de
  comportamento `data-aculta-studio` próprios. O token obsoleto `studio-panel`
  foi removido do mapa V2; o Portal de Pessoas e demais emissores sem SDC não
  foram alterados nesta migração.

- O espaçamento usado só pela identidade da Conta saiu das variáveis de
  fidelidade do `studio-shell` e foi resolvido por `spacing-lg` no CSS do SDC
  `account-experience-dashboard`. Removi também o seletor sem consumidor
  `.studio__panel` e seu alias de classe do mapa. O título `studio-heading` de
  Marley agora é estilizado pelo SDC `studio-section-header`; o marcador
  `data-portal-ui` continua no DOM para o fluxo de foco HTMX. Mapeadas: 376
  classes. Inventário após atualização: 142 folhas/418.651 bytes/2.385 regras/
  2.837 seletores; classes dinâmicas: 228 em 125 SDCs.

- Os SDCs `studio-connections-page` e `studio-connections-block` já carregam
  automaticamente os CSS homônimos; removi `attach_library` para bibliotecas do
  tema que não existiam. O attach explícito do Leaflet em `map-widget` permanece
  porque pertence ao módulo `aculta_maps` e declara comportamento funcional.
  CSS scope (162), mapped classes (377), schemas (125/2.333), render SDC (82),
  Stylelint, ESLint, CSS verify e `git diff --check`: PASS. Inventário: 142
  folhas/418.820 bytes; homologação visual autenticada do Studio, Conta e LMS
  continua pendente.

- Próxima rodada recomendada: auditar CSS residual em `studio-shell`/folhas de
  rota e as regras de UI Patterns/UI Suite, seguindo cada seletor até o emissor
  real. Mover apenas regras de apresentação com um SDC proprietário; manter no
  módulo as bibliotecas funcionais (Leaflet, behaviors, diálogos) e verificar
  páginas LMS reais em desktop/móvel antes de remover fallback ou token dinâmico.

- Removi o estilo legado isolado `studio-saved-summary` do `studio-shell` e
  retirei seu token de classe do mapa. O atributo semântico do provedor segue
  intacto; nenhum controller atual renderiza o build desse provedor. O SDC
  `/salvos` continua com estilo próprio. Gates: Stylelint, classes mapeadas
  (378), CSS scope (134), schemas (120/2.286), render SDC (77), CSS verify
  (120 SDCs) e `git diff --check`: PASS. Validação visual autenticada do Studio
  e LMS continua pendente.
- Os SDCs LMS de jornada e sala agora usam o indicador global de foco de
  `base.css`; removi suas declarações locais duplicadas e preservei a
  apresentação e os estados específicos de cada fluxo.
- A UI Patterns Library também herda o indicador global de foco nos resumos de
  detalhes; removi dois outlines locais duplicados. Inventário atual: 139
  arquivos/427.548 bytes/2.422 regras/2.888 seletores. Stylelint, CSS scope,
  CSS audit/verify e `git diff --check`: PASS.
- O formulário de edição de revista deixou de repetir a skin geral do
  `studio-shell`; o SDC `studio-revista-form` preserva apenas a borda superior
  editorial. Sua story agora inclui o shell como na rota real. Redução líquida:
  2.790 bytes; inventário em 424.758 bytes, 2.410 regras e 2.876 seletores.
  Lint, schema, render SDC, CSS verify e YAML da story: PASS.
- O `studio-shell` perdeu seis variáveis de fidelidade sem referência em
  nenhum consumidor do repositório. Redução adicional: 336 bytes; inventário
  atual em 424.422 bytes/2.410 regras/2.876 seletores. Restam estilos gerais de
  formulário e listagem no shell para revisão por rota/SDC.
- Removi do shell os resets de tipografia/cor já herdados de `body` e a fonte
  redundante do placeholder. Mantive a hierarquia de títulos e a skin de campos.
  Corte: 806 bytes; inventário em 423.616 bytes/2.408 regras/2.874 seletores.
- O shell não redefine mais a fonte dos headings semânticos; `base.css` já
  fornece a tipografia e não existe uso local de `.h1`–`.h6`. Inventário:
  423.502 bytes, 2.407 regras e 2.873 seletores. Ainda faltam validação visual
  autenticada e a revisão de outros estilos gerais de formulário/listagem.
- Extraí o cabeçalho e os resets das ações Marley para `studio-section-header`;
  o preprocess o envolve somente nas rotas do Studio em que há esse token. Os
  dados/autorização permanecem no módulo. Render SDC (78), schemas (121/2.294),
  CSS scope (137), mapped classes (378) e CSS verify passaram. Inventário:
  140 arquivos/423.573 bytes. Captura autenticada da seção pendente.
- O estilo de alvos de toque de `studio-profile-actions` agora pertence ao CSS
  de `studio-profile-editor`; o shell não carrega mais esse seletor específico.
  Volume do inventário permaneceu em 423.573 bytes; lint, scope, render SDC,
  classes mapeadas e CSS verify passaram.
- A skin de Form API foi extraída de `studio-shell` para `studio-form` e agora
  carrega somente em formulários sob `studio-main`, também na criação de curso
  LMS. A edição de revista reutiliza o SDC sem dupla composição. Inventário:
  141 arquivos/421.126 bytes; redução líquida de 2.447 bytes. Schema (122/2.304),
  render (79), scope (142), mapped classes, Stylelint e CSS verify passaram.
  Validação visual autenticada dos formulários pendente.

- Checkout de doações passou a compor `donation-presentation` nas áreas de
  resumo, confirmação, valor e convite. Os cálculos, os dados dos render arrays,
  fluxo e cache permanecem no módulo `aculta_donation`; os templates Twig
  neutros de resumo foram removidos. A folha é local ao SDC.
- As filas administrativas de avisos e reivindicações de posse agora anexam
  bibliotecas CSS dos módulos apenas nas páginas que as compõem. Os tokens
  permanecem como ponte de classe; as regras `admin-item`, `admin-list`, avisos
  e filtros saíram do mapa global.
- A página `/salvos` passou a compor `saved-content-page`. Seu CSS saiu de
  `studio.css` e virou asset local do SDC; o controller continua dono da consulta,
  cache, paginação e render arrays. Renderização isolada: 65 componentes.
- Green Stash passou a compor `green-stash-page` nas rotas de conteúdo e criação
  de produto. As regras saíram de `studio.css`, que era carregado apenas por
  rotas `aculta_studio.*`; agora são CSS local do SDC e os tokens base são do
  tema. Commerce mantém conteúdo, permissões, operações e cache.
- O shell das subpáginas autenticadas da Conta passou a usar
  `studio-account-section-layout`. O preprocess do tema envolve o render array
  do Studio sem alterar seu `id`, tokens HTMX, cache ou conteúdo; o CSS saiu de
  `studio.css` e acompanha o SDC.
- As tabelas responsivas do construtor de listagens Marley agora são envolvidas
  por `studio-listing-table`; os estilos da tabela e do wrapper saíram de
  `studio.css`. O preprocess envolve todas as tabelas presentes em cada página.
- O formulário de edição de revista nas rotas do Studio agora é envolvido por
  `studio-revista-form`; os estilos Marley saíram de `studio.css` e só carregam
  quando o formulário compõe o SDC. Form API e operações editoriais não mudaram.
- O componente privado `profile-editor` agora é mapeado para o SDC
  `studio-profile-editor`; sua grade é CSS local, enquanto o contexto visual e
  as regras de formulário permanecem compartilhados com o Studio. O mapper
  preserva id, foco, attachments e metadados de cache e repassa os slots sem
  reconstruir conteúdo ou dados.
- A criação de curso do Educador (`aculta_lms.course_create`) agora compõe
  `studio-course-authoring-page`; cabeçalho e retorno saíram de `studio.css` e
  o CSS do SDC carrega na rota LMS. Controller e Form API do Group permanecem
  donos de autorização, operações, conteúdo e cache.
- O CSS de navegação, conteúdo, tabelas e estado de segurança do `account-shell`
  saiu de `portal-account.css` e passou ao asset do SDC. Permanecem na biblioteca
  contextual apenas estilos de diálogo e cartões reutilizados fora do shell.
- A criação de tópico já usa `discussion-topic-composer`; removi um cartão
  legado redundante em torno do Form API e passei os estilos do formulário para
  o asset do SDC.
- A View pública do Fórum agora compõe `forum-list-page`; os estilos próprios
  de cabeçalho da View, lista, categorias e painéis laterais saíram de
  `forum.css` e acompanham o SDC.
- Os cartões de biografia e redes do dashboard e do Perfil Studio agora são
  envolvidos por `account-profile-card`; o CSS correspondente saiu de
  `portal-account.css` e carrega por cartão.

- Minha Conta passou a compor `account-courses`: grade e cartões foram para o
  CSS do SDC, e as regras antigas de cabeçalho `h2`/botão sem consumidor saíram.
  O presenter e o controller conservam conteúdo, ações e cache. O SDC foi
  adicionado ao gate de render isolado.
- Removi de `content.css` os estilos da classe `aculta-editorial-list`, já
  ausente da View e do instalador. Estado: 125 folhas/441.783 bytes/2.446
  regras/2.979 seletores; o CSS global segue em 68.960 bytes.
- `search.css` agora é carregado apenas na rota `aculta_portal.search`; o campo
  de prévia está no SDC `search-preview` e a classe do popup acompanha
  `multidomain-bar`. `global-styling` caiu para 12 entradas/66.137 bytes.
- O relatório de requisitos do Portal passou ao SDC `portal-requirements`; os
  estilos de seção saíram do mapa global e a referência à biblioteca inexistente
  do módulo foi removida. Gate: 62 SDCs renderizados.
- O formatter de arquivos compõe `document-download`; CSS de CEP, Mercado Pago e
  Galeria foi para bibliotecas dos módulos. Título do Studio e link público da
  micropágina passaram aos escopos proprietários; removido um token sem emissor.
- A prévia de busca da Wiki e do Messenger agora compõe `search-preview`; regras
  de lista, rodapé e controles foram extraídas para o asset do componente.
- Estado atual: 127 folhas/442.004 bytes/2.447 regras/2.975 seletores;
  `global-styling` 65.176 bytes; 391 classes mapeadas; CSS verify preserva 223
  classes dinâmicas em 107 componentes. Schema (107) e render isolado (63)
  passaram; CSS scope tem 127 checks. Homologação visual autenticada pendente.
- Após esses cortes, o inventário registra 132 folhas/440.413 bytes, 2.448
  regras e 2.970 seletores; `global-styling` está em 64.258 bytes. Classes
  mapeadas: 380; 228 classes dinâmicas em 112 SDCs permanecem preservadas.

Próximos passos daquela rodada CSS (a fila UI Patterns vigente está no início deste roadmap):

1. Revisar as regras gerais de formulários e listagens restantes de `studio.css`,
   separar escopos por rota ou SDC e manter a composição acessível dos formulários.
2. Homologar visualmente as rotas autenticadas do LMS, Minha Conta, checkout de
   doação, avisos e reivindicações, além da busca, antes de declarar o corte.

- Novo corte LMS: currículo usa classes semânticas diretamente nos render
  arrays do SDC; tokens vazios de currículo, prévia, dashboard, jornada e sala
  foram removidos do mapa genérico. Stories agora usam o contrato atual.
- Regras do carrinho, dos filtros/rodapés de diálogos e da seção dos relatórios
  foram transferidas da folha multidomain/global para os SDCs proprietários.
- Mapas do renderer agora recebem CSS encapsulado em `map-canvas`; na rota
  `/mapa`, a lista e o aviso também entram no slot para preservar seu layout sem
  seletores globais. A folha funcional Leaflet permanece no módulo.
- Regras de cartões, apoiadores e agenda de eventos foram para bibliotecas
  contextuais `events`/`event-organization`; `events` também carrega em nodes
  `aculta_event`.
- Layout dos fragmentos HTMX de login e cadastro acompanha o asset do SDC
  `multidomain-bar`.
- Sugestões de título, decisão/status de proposta e retorno do workspace da Wiki
  passaram para `wiki.css`, carregado pelo `site_key=wiki`.
- Social links deixou de emitir classes de ponte do mapa; o SDC da micropágina,
  o rodapé e a biblioteca da configuração administrativa assumem os estilos.
- Itens salvos/recomendações e tokens de resumo/autoria passaram a usar a folha
  contextual do Studio; removidas classes de ponte redundantes dos render arrays.
- O corte seguinte retirou do global as cópias de estilo das abas, campos de
  segredo e tabela já cobertos pela biblioteca `aculta_secrets_manager/secrets-tabs`;
  `data-portal-ui` segue disponível ao JS. Estado atual: 122 folhas/442.316
  bytes/2.451 regras/3.002 seletores; `global-styling` em 71.593 bytes e mapped
  classes em 416.
- O recorte do avatar público também passou ao CSS contextual `public-profile`;
  o estado atual tem 442.321 bytes e `global-styling` 71.506 bytes. CSS verify
  preserva 222 classes dinâmicas; homologação visual autenticada ainda pendente.
- A cor dos pedidos comerciais passou ao `portal-account.css`; os estilos
  Google seguem globais por atenderem login e checkout. O inventário atual tem
  442.359 bytes e o CSS global 71.479 bytes; Stylelint, scope, mapa de classes e
  CSS verify passaram.
- O status acessível do Studio/Marley usa agora a regra contextual `.studio__live`
  em `studio.css`; a classe de ponte global saiu. Mapped classes caiu a 415 e
  `global-styling` a 71.404 bytes. O aviso direto do mapa, o estado do widget de
  Events, a prévia da UI Library e a validação do editor H5P migraram para o SDC
  ou CSS do módulo. Os tokens `notice-*` do Portal passaram a compor o SDC
  `status-messages`; a compatibilidade `.messages` segue necessária para módulos
  contrib com emissores diretos.
- A página interativa do calendário passou a compor `calendar-interactive`; o
  módulo conserva motor, dados e attachment DHTMLX e o SDC assume o CSS de
  layout. `global-styling` caiu a 71.031 bytes; catálogo: 103 componentes,
  410 classes mapeadas e 99 SDCs com CSS verificados.
- O conteúdo do SDC `status-messages` aceita agora slot rico, e avisos/status do
  Portal, Events, segredos e doação trocaram os tokens `.messages` pelo SDC. A
  variante `info` também é componente. Removida a regra global `messages--info`
  e classes de ponte `notice-*`/`email-notice`; o global mantém `messages`,
  `--status`, `--warning` e `--error` para consumidores diretos em contrib.
- Estado atual: 123 folhas/442.009 bytes/2.449 regras/2.998 seletores;
  `global-styling` 70.861 bytes. Mapped classes: 404; CSS verify preserva 223
  classes dinâmicas em 103 componentes.
- O mapa de apresentação também perdeu pontes redundantes de `display:block`
  em wrappers naturalmente block; o avatar mantém display/recorte em
  `public-profile.css`. Estado: 123 folhas/441.786 bytes/2.449 regras/2.989
  seletores, `global-styling` 70.620 bytes e 399 mapped classes. CSS verify,
  schema, scope e Stylelint passaram.

- Os controllers dos relatórios de produto e doações agora usam o SDC
  `sales-panel`; consultas, filtros, permissões, tabelas e cache contextual
  permanecem no módulo proprietário.
- A cópia global dos estilos dos links de login foi removida; os SDCs
  `auth-dialog` e `multidomain-bar` já cobrem o atributo do formulário.
- Avisos de segurança/e-mail e documentos dos termos foram para a biblioteca
  contextual `portal-account`; saíram também os seletores Bootstrap da barra e
  o token genérico `nav-link`.
- Estilos da rota `user.login` migraram para a biblioteca `login-page`, anexada
  condicionalmente pelo tema.
- Formulário `/apoio` agora usa `support-page.css` anexado na rota; removida sua
  referência a uma biblioteca de módulo inexistente.
- O relatório administrativo de imagens compõe `image-audit-report` por slot;
  permissão, relatório e cache permanecem no controller.
- O banner de consentimento foi conectado ao SDC `consent-banner` em
  `page_bottom`, condicionado à configuração e visitante anônimo; o JS usa um
  marcador próprio do componente. Kernel: PASS (4 testes/10 asserções). No
  Runtime, a configuração continua desabilitada, portanto o banner não é
  renderizado para anônimos.
- A saída de segredos usa o SDC `secret-reveal`; tamanhos de modal da Conta e
  Studio saíram do global para `account-dialogs.css`, anexado nesses escopos.
- `portal-ui-extras.css` foi removido. O CSS restante de Número/S/N agora é
  anexado pelo widget compartilhado; Runtime `/apoio` 200, CSS presente na rota
  e ausente na home. `/entrar` no domínio Conta 200 com `login-page.css`, ausente
  na home pública.
- Estado após os primeiros cortes: `global-styling` com 13 entradas/75.740 bytes;
  CSS scope 119 checks, mapped classes 442, 54 SDCs renderizados e CSS verify em
  115 folhas/92 SDCs com CSS; docs tokens 508/427/160. Na rodada mais recente,
  o inventário passou a 116 folhas/442.035 bytes, CSS verify cobre 93 SDCs e o
  render isolado 55 SDCs. Falta homologação visual
  autenticada dos relatórios: a conta comum disponível não tem as permissões
  das rotas administrativas; não houve alteração de papéis.
- `event-organization.css` foi reduzido em 1.537 bytes e escopado pelos
  wrappers do widget e do perfil. A biblioteca agora é anexada somente nas 8
  rotas de widget, perfil, tabela administrativa e modal; `/eventos` Runtime
  não a carrega. `events.css` continua nas rotas de eventos.
- `studio.css` perdeu 153 linhas (5.029 bytes) de regras `/culturas` não
  entregues nessa rota. Os tokens de UI de guia/filtros mantêm `data-portal-ui`,
  mas deixaram de emitir classes legadas sem consumidor. No Runtime, `/culturas`
  mantém `display:grid` e gap de 16 px por `events.css`; `/conta` continua com
  o CSS Studio carregado e o layout de conta em grid.
- O layout da página, painel “Sobre este tópico”, estilos do cabeçalho/categoria e do diálogo HTMX foram para `forum-topic.css`; links de operação e alternância dos cartões de comentário foram para `forum-post-card.css`. Os templates reais usam esses SDCs em páginas de tópico e comentários de Fórum/LMS. O experimental `discussion-comment` não entrou no fluxo, preservando as ações Drupal. `forum.css` caiu de 28.199 para 21.368 bytes (-6.831 bytes) desde a auditoria anterior.
- O template `aculta-studio-account-card` agora delega a marcação ao SDC `account-experience-card` com props estritas; cartões/faixas saíram de `studio.css` para o asset do componente. `studio.css` perdeu 3.379 bytes; o novo asset do SDC soma 2.582 bytes e só é carregado quando a experiência é renderizada (saldo total de −797 bytes). A lista mantém seus `<li>` para o carrossel.
- `.layout-page--stack` e o container fallback `.page__main--stack` foram removidos do CSS global; os dois templates daquele snapshot compunham diretamente `layout-page`. O shell paralelo foi removido em 2026-11-13; ver o status vigente no início deste roadmap. Homologação visual no Runtime permanece pendente.
- O template da página Minha Conta agora compõe o SDC `account-experience-dashboard`; menu, avatar, calendário, conexões, experiências e links entram como slots/render arrays. Todo o CSS do dashboard e carrossel saiu de `studio.css` (−7.192 bytes) para o asset do SDC (+7.091 bytes), saldo líquido de −101 bytes; `studio-account-section` permanece em `studio.css` por ser markup produzido pelo módulo.
- Implementação anterior do dashboard LMS: wrapper SDC aplicado no preprocess e cartões em UI Patterns. **Substituída em 2026-11-13** por composição direta da página e de cartões SDC no `LearnerDashboardComposer`; ver o item de recriação acima.
- A jornada do Group e a sala de atividade usam `lms-learning-journey`/`lms-learning-room`. A ponte Portal neutra mantém os composers e render arrays; o preprocess da rota da sala envolve o formulário, e o componente de jornada é pedido pelo hook de apresentação do módulo. O bloco LMS saiu de `studio.css` (−7.161 bytes) para dois assets SDC (+7.469 bytes), saldo +308 bytes, com CSS carregado junto ao componente usado.
- Estado no fechamento daquela rodada: 120 folhas, 442.527 bytes, 2.453 regras e 3.028 seletores. PurgeCSS mantém 222 classes dinâmicas de 101 componentes; o relatório em modo audit aponta 39.378 bytes removíveis, sem aplicação automática.
- Validações desta retomada: Stylelint; CSS verify (120 folhas, 97 SDCs com CSS, 222 classes dinâmicas); schema SDC (101 componentes/2.122 checks); render isolado (59 SDCs); CSS scope (119), mapped classes (442) e `git diff --check`: PASS. Validação visual autenticada ainda pendente.

## Auditoria, PurgeCSS e limpeza de CSS legado (2026-11-12)

- Continuação SDC-first: currículo, materiais e áreas do LMS/H5P migrados para
  SDCs; discussões e documentos usam `layout-section`. Autoria editorial e
  conteúdo relacionado recebem SDC próprio. Estilos de Messenger e Revista
  agora são assets contextuais; CSS de eventos sai do global. Gates de código
  PASS; homologação visual autenticada e Kernel sem `SIMPLETEST_DB`: DEFERRED.
- Estado do inventário: 107 arquivos, 452.068 bytes CSS, 2.487 regras, 3.075
  seletores. `global-styling`: 85.431 bytes locais em 14 entradas. PurgeCSS
  preserva todos os 222 seletores dinâmicos; 20 candidatos sem evidência estão
  no menu autenticado do Studio e permanecem até captura desse fluxo.

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
- Concluído em 2026-11-12: migração dos utilitários `aculta-u-*` do mapa de
  tokens para classes semânticas por domínio; `utilities.css` removido de
  `global-styling`. As equivalências Bootstrap do mapa (`.text-muted`,
  `.small`, `.table`, `.nav`, `.list-unstyled`, `.pager-*`) também saíram.
  Contraste do rodapé e da trilha corrigido e medido (22/22 axe PASS).
- Homologado em 2026-11-12: telas autenticadas da conta e `/marley` (28/28),
  teclado (20/20, 30 paradas de Tab), diálogos e gaveta de Mensagens (12/12),
  matriz com viewport real e capturas determinísticas (100/100; 40/40 PNGs
  idênticos em duas execuções).
- Pendente: papéis de contribuidor Wiki (`/we-sativa`) e educador
  (`/ganjalab`), que recebem 403 com as contas de teste; verificação manual
  com Orca (instalado, `speech-dispatcher` inativo); composição SDC para
  marcação de módulos que hoje usa `data-portal-ui` (incremental, sem camada
  de compatibilidade). A auditoria do mapa (529 tokens) não encontrou entrada
  comprovadamente morta.

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
# Continuação — corte CSS e remoção de legados (2026-11-12)

- `sales-panel` foi extraído de `portal-ui-extras.css` para um SDC carregado
  pelos relatórios de produto e doações. Os controllers mantêm filtros,
  consultas, permissão via rota, paginação, tabelas e cache contextual.
- Checagens executadas: 51 SDCs renderizados; CSS scope 72; CSS verify (108
  arquivos, 89 SDCs com CSS, 222 classes dinâmicas preservadas); mapped classes
  448; Stylelint; teste unitário de doações 2/2; `git diff --check`.
- Pendências: teste unitário dedicado do controller de vendas, Kernel tests com
  banco de teste e validação visual autenticada dos relatórios e mudanças deste
  lote. Manter o lote como não versionado até homologar essas rotas.

### UI Patterns Views no Fórum e recuperação do 500 (2026-11-14)

A View de busca pública agora usa `forum-search-page` pelo display extender UI Patterns Views; Sources do título e links contextuais mantêm o contrato Core, e o restante da página mapeia resultados, filtros, vazio, pager e áreas. A row de tópicos entrega `sticky` via Source `view_field_value`; os dois presenters Twig redundantes foram removidos. Config Sync e install estão alinhados. A matriz registra a busca como `UI_SUITE_NATIVE`; o Runtime ainda precisa importar essa configuração e validar resultados, acesso, cache e navegador.

O Runtime retornava 500 nos domínios devido ao container compilado sem a injeção das dependências de `TopicOpeningPostHooks` e `PublicForumListViewHooks`. Com a correção de serviços já publicada, `drush cr` sob lock concluiu. Revista, principal e os outros hosts consultados retornaram HTTP 200 (conta, 302 esperado); o watchdog não registrou novos erros após o rebuild.

### Continuação do corte CSS — Fórum, Wiki e LMS (2026-11-13)

O CSS da lista pública passou ao SDC `forum-list-page`; categoria e busca
passaram respectivamente a `forum-category-page` e `forum-search-page`.
Estilos comuns de thread e controles JS acompanham `forum-post-card` e
`wiki-entry`, incluindo as discussões em Fórum, Revista, Wiki e LMS. Avatares
passaram a `forum-topic-row`. A folha `forum.css` e sua biblioteca foram
removidas. Schema/render SDC, Stylelint e gates CSS passaram; o cache Runtime
foi reconstruído em 2026-11-14, e a inspeção browser/runtime das Views ainda
depende do alinhamento das configurações divergentes.

### Corte de seletores do Studio (2026-11-13)

Removidos 24 seletores (3.981 bytes) sem consumer: `form.studio-profile-form`
não é emitido pelos Form API atuais e seletores `body.path-perfil` não
correspondem às rotas `/meu-perfil/...`. O formulário de Revista fica sob o
SDC `studio-revista-form`. As regras genéricas `.studio__main form` permanecem.
`studio.css` reduziu de 15.244 para 11.263
bytes; auditoria CSS, Stylelint e gates passaram. Validação visual autenticada
continua pendente.

### CSS do Studio no SDC de casca (2026-11-13)

Rotas `aculta_studio.*` envolvem seu render array final em `studio-shell` após
compor os SDCs internos. `studio.css` agora é asset local do SDC, dispensando a
biblioteca CSS autônoma. Conteúdo, Forms API, cache e attachments permanecem no
slot. O inventário manteve 428.282 bytes; schema/render SDC, Stylelint, auditoria
e CSS scope passaram. Verificação visual autenticada pendente.

- O SDC `studio-listing-table` deixou de imprimir uma `<div>` sem função visual;
  sua folha continua carregada no wrapper responsivo emitido pelo módulo. A
  classe Bootstrap `table-responsive` também saiu do token `studio-listing-wrap`;
  o CSS usa apenas `studio__listing-wrap`. Consulta, operações e paginação não
  mudaram. Inspeção autenticada de Marley ainda pendente.
- `content.css`: removida a regra `.aculta-panel__body:last-child` sem efeito
  (margem padrão de `div` já é zero), as declarações de painel já cobertas por
  `portal-ui-map.css` e uma regra duplicada de `.aculta-category` foi fundida.
  Permanecem a borda editorial e a interação de hover/foco do painel e as cores
  de categorias editoriais. Stylelint, CSS scope (142), CSS audit/verify e
  `git diff --check`: PASS. Inventário atualizado: 141 arquivos/420.921 bytes,
  2.405 regras e 2.871 seletores.
- Os estilos da região `.page__footer-region` saíram de `layout.css`: o
  template `page.html.twig` já envolve essa região
  no SDC `site-footer`, que fornece a grade responsiva, títulos e navegação.
  O fallback do shell de conteúdo e o `grid-area` do footer continuam intactos.
- `portal-account.css`: removidos o alias `.table-responsive` (seus únicos
  emissores são as páginas de contribuições de Eventos, fora do site account),
  limites de imagem já cobertos por `base.css` e limites repetidos pelo SDC
  `account-shell`. O scroll de `<pre>` e o limite do conteúdo continuam. Stylelint,
  CSS scope (142), CSS audit/verify e `git diff --check`: PASS; inventário atual
  141 arquivos/419.394 bytes/2.395 regras/2.859 seletores.
- `portal-ui-map.css` perdeu os estilos `.messages*` que não tinham mais
  consumidor: o override `templates/misc/status-messages.html.twig` compõe o SDC
  `status-messages` e não emite essas classes. Emissores de Form API usam o
  render element `status_messages`, coberto pelo override. Stylelint, CSS scope
  (142), mapped classes (377), CSS audit/verify e diff check: PASS. Inventário:
  141 arquivos/418.714 bytes/2.391 regras/2.852 seletores.
- No `portal-ui-map.css`, `.aculta-pager` saiu do agrupamento que dava `display:grid`:
  a mesma classe já recebe `display:flex` no seletor logo abaixo, então a primeira
  declaração era sempre sobrescrita. Estilos de mensagens e filtros permanecem.
  Stylelint, CSS scope (142), mapped classes (377), CSS audit/verify e diff check:
  PASS. Inventário: 141 arquivos/418.643 bytes/2.391 regras/2.851 seletores.
- O estilo de tipo, título, resumo, metadados e cancelamento do evento saiu de
  `events.css` e acompanha o SDC `card-cultural`, único emissor desses elementos.
  As classes de apresentação legadas do mapa seguem cobertas na folha local do
  SDC. `events.css` mantém a grade do catálogo, toolbar e outras áreas Events.
  Stylelint, schemas/includes (122/2.304), CSS scope (142), mapped classes (377),
  render SDC (79), audit/verify e diff check: PASS. Inventário: 141 arquivos,
  418.361 bytes, 2.388 regras e 2.848 seletores. Browser visual pendente.
- O formulário `/apoio` agora carrega `support-form.css` pelo SDC
  `support-form`, envolvido pela apresentação da rota. A biblioteca `support-page`
  foi removida; o Form API original continua no slot. Gate de escopo atualizado,
  stylelint, schemas/includes (123/2.312), CSS scope (143), mapped classes (377),
  render SDC (80), CSS audit/verify e diff check: PASS. Inventário sem aumento:
  141 arquivos/418.361 bytes/2.388 regras/2.848 seletores. Verificação visual
  da rota `/apoio` permanece pendente.
- O perfil público deixou de anexar a biblioteca `public-profile` por rota. O
  render array autorizado agora carrega a antiga folha local como asset do SDC
  `public-profile-page`, sem markup extra. Gate CSS scope atualizado; schemas
  (124/2.320), CSS scope (143), mapped classes (377), render SDC (81), Stylelint,
  audit/verify e diff check: PASS. Browser autenticado/runtime de `/u/<apelido>`
  continua pendente.
- Regras de conteúdo, leitura de `pre`, foco de navegação e ações do dashboard
  saíram de `portal-account.css` e passaram ao SDC `account-shell`, seus únicos
  consumidores. O formulário de foto e as regras de container do purpose
  continuam na folha contextual. Stylelint, schema/render SDC (124/81), CSS
  scope (143), mapped classes (377), CSS audit/verify e diff check: PASS.
  Inventário: 141 arquivos/418.334 bytes/2.387 regras/2.847 seletores; os
  seletores repetidos entre arquivos caíram de 7 para 5. Validação autenticada
  da Conta pendente.
- A lista de documentos do modal de termos passou de `portal-account.css` para
  o asset do SDC `account-terms-modal`, usado pelo template de tema que mantém
  o ID, `hidden` e `data-dialog-title` esperados pelo JS do Portal. Validação
  isolada e de escopo pendente.
- `lms-lesson-page.css` deixou de repetir o anel local de `:focus-visible`;
  `base.css` já aplica o indicador acessível global com tokens semânticos.
  A rolagem da tabela Form API da aula e a apresentação específica do preview
  continuam no SDC LMS.
- A lista de recursos da estrutura do curso passou ao slot `resources` do SDC
  `lms-course-curriculum`; o tema faz a composição final após os preprocesses
  do módulo. A listagem pública extra field continua separada. Acesso, cache e
  peso permanecem no builder do LMS; sem mudança visual intencional.
- As regras de apoiadores e citações editoriais de evento saíram da folha de
  rota `events.css` e agora acompanham o SDC `event-page-layout`; os atributos
  funcionais `data-portal-ui` e o conteúdo do presenter permanecem inalterados.
- O token `studio-account-home` deixou de repetir as classes já declaradas pelo
  SDC `account-experience-dashboard`; permanece no DOM como marcador funcional.
- O JS do carrossel saiu do attach-library inexistente no template de Conta e
  passou a asset do SDC `account-experience-dashboard`, com dependências
  Drupal behavior/once declaradas no componente.
