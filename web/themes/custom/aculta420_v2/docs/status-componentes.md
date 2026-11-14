# Status dos componentes (G1)

> **Inventário histórico, em reconciliação (ADR-056):** as seções abaixo
> registram implementações verificadas em datas anteriores, mas não afirmam que
> todos os consumers continuam ativos nem que todos migraram para fallbacks.
> Confira cada consumer em código e Config Sync. O estado vigente e a sequência
> do cruzamento ficam no início de `roadmap.md`.

Status por componente extraído do roadmap em 2026-11-05. O estado geral das fases fica em `roadmap.md`.

## Reconciliação com o código atual — 2026-11-14

Esta checagem atualiza o que os registros históricos provam hoje. Os caminhos de
templates citados abaixo não são tratados como consumers ativos quando foram
removidos; SDCs publicados permanecem na UI Patterns Library mesmo sem consumer
de produção confirmado. A busca confirma código/configuração do checkout, não o
uso persistido ou a renderização atual no Runtime.

| Item histórico | Evidência no checkout atual | Situação e consequência para o plano |
| --- | --- | --- |
| `avatar` no menu da Conta | `components/account-menu/account-menu.twig`; também `forum-author-line`, `forum-quote`, `account-photo-editor` e Studio. | Consumer real no tema; manter. A foto é preparada pelo chamador e o SDC não consulta usuário. |
| Author box / `author-card` | `PagePresentationHooks::preprocessAuthorCard()` emite render array `aculta420_v2:author-card`; `revista-publication` tem slot `authors`; o adapter mantém fallback neutro do módulo. Core injeta `#slots` como overrides dos blocks `avatar`/`actions`; o validator confere identidade, bio e avatar por slot. | Consumer real por hook e composição; manter. O gate não cobre todos os contextos nem substitui homologação Runtime. `reactions_received` e `reactions_given` agora têm saída identificada nas variantes de Fórum/perfil. |
| `tabs` em tarefas locais Drupal | `templates/navigation/menu-local-tasks.html.twig` não existe no checkout. O SDC é usado na página Overview da UI Patterns; `LearningDashboardController` o emite diretamente; `PagePresentationHooks::preprocessPortalComponent()` mapeia `context-tabs` para `tabs`. | A afirmação histórica sobre o template não descreve o código atual. Há consumers SDC atuais; não restaurar o template antigo por presunção. Homologar os fluxos que passam pelo preprocess e pelo LMS. |
| `dialog` | Consumido por `lms-tour`, `forum-topic-page` e `aculta_user` (`AccountBioCard`). | Consumers reais no tema/módulo; manter. A validação de fluxo autenticado/runtime ainda é separada. |
| `form-field` via `form-element` | `templates/form/form-element.html.twig` não existe; nenhuma referência de produção atual ao SDC foi localizada. Há stories próprias e uso em story de composição. | SDC catalogado sem consumer de produção confirmado. Preservar por estar na Library; manter a integração Drupal como pendente, sem alegar migração ativa. |
| `progress` via `progress-bar` | `templates/misc/progress-bar.html.twig` não existe. `lms-learning-course-card.twig` compõe o SDC `progress` para curso com aulas. | Consumer LMS real via SDC; não há evidência de adapter genérico para todas as progress bars do Drupal. |
| `data-table` via `views-view-table` | `templates/views/views-view-table.html.twig` não existe; nenhuma referência de produção atual ao SDC foi localizada fora do catálogo/stories. | SDC publicado com stories, sem consumer de produção confirmado. Preservar o item da Library e não marcar a migração de Views como ativa. |
| `empty-state` via `views-view` | `templates/views/views-view.html.twig` não existe. Há composição em `lms-learning-dashboard`, mapeamento do componente neutro `empty-state` pelo preprocess do Portal e render array direto no formatter H5P (`aculta_lms_h5p`). | Consumers atuais permanecem como SDC direto/adapter de componente; o antigo adapter genérico de Views não está no tema. |
| `profile-details` | O preprocess do Portal mapeia `profile-bio`/`profile-social` para `profile-details`, adaptando slots e IDs; Core injeta os valores nos blocks `bio`, `social_links` e `actions`. | Consumer real no Studio; manter os render arrays do Portal. Validar conteúdo/cache no Runtime; captura de 2026-11-06 é evidência visual histórica. |
| `revista-teaser` | `PagePresentationHooks::composeRevistaTeaser()` emite render array com quatro slots; Core injeta valores nos blocks correspondentes. | Consumer real de nodes da Revista; manter. Validar estados com/sem cada conteúdo, cache e saída no Runtime; captura de 2026-11-12 é evidência visual histórica. |
| `revista-listing` | `views-view--revista-sessoes--page-revista.html.twig` usa `{% embed %}` para preencher `items`; a story da Library passa o mesmo slot por `#type: component`. | Consumer real da View; manter. `embed` e story usam o contrato Core de slots. Confirmar style ativo no Runtime e preservar pager, anexos, filtros e links contextuais. |
| `forum-comment` | Os dois templates de comentário usam `{% embed %}` para preencher blocks; Core e UI Patterns Library suportam esses slots via `#slots`. | Consumers atuais são válidos; manter adapters enquanto transportarem estruturas/metadados do Comment API. Não migrar apenas por compatibilidade de slot; avaliar separadamente a substituição dos overrides e preservar cache/acesso. |
| `price` | Manifest/story não encontrados; há uso de Price no módulo Commerce, mas sem contrato visual genérico definido para o tema. | Continua fora do escopo aprovado; exige tarefa com consumer e contrato definidos antes de criar SDC. |

Arquivos relevantes: `src/Hook/PagePresentationHooks.php`,
`components/account-menu/account-menu.twig`,
`components/lms-learning-course-card/lms-learning-course-card.twig`,
`components/lms-learning-dashboard/lms-learning-dashboard.twig`,
`components/lms-tour/lms-tour.twig` e `components/forum-topic-page/forum-topic-page.twig`.

## Avatar — G1 implementado e integrado ao menu de conta

Criado o SDC `avatar`, com slot de mídia. Sem mídia ele deixa o quadro vazio;
não consulta entidade/usuário, não gera iniciais e não tem fallback próprio. O
módulo proprietário/`AvatarResolver` entrega mídia de acervo a consumidores
reais sem foto pessoal. As stories mostram mídia de exemplo. O gate de schema
passou e o validator de renderização passou para 90 SDCs após alinhar sua
asserção ao contrato do componente. Firefox ESR visível capturou estados em
320, 390, 768, 959, 960 e 1440 px nos modos claro/escuro; zoom 200% em 320 CSS
px também passou sem overflow. Teclado não se aplica ao componente estático.
`account-menu` adota o componente no resumo e no painel de identidade, com
`size: sm` e mídia entregue pelo Portal; a validação de anônimo mede a mídia
preparada pelo Portal, não um fallback do SDC. `author-card` mantém o slot de
autoria atual, validado também no render isolado.

## Author box — G1 coberto por author-card; consumer integrado

O contrato de autoria do `aculta_user` documenta a caixa de autoria como slot
`authors` de `revista-publication`; o SDC compartilhado `author-card` recebe
identidade, avatar e biografia autorizados. `PagePresentationHooks` encaminha
render arrays `avatar`/`actions`, e o fallback semântico do módulo permanece
para outros temas. Core injeta slots nos blocks SDC; o validator atual confirma
identidade, bio e avatar. As capturas Firefox abaixo são evidência histórica de
layout/teclado/modos; conteúdo publicado/autenticado permanece para homologação.

## Tabs — SDC atual; adapter antigo removido

Criado SDC de navegação por links normais, compatível com a lista de links
renderizada pelo Drupal. O item atual usa `aria-current="page"`; a lista rola
horizontalmente no mobile e não depende de JavaScript. Não usa semântica de
troca de painel (`role="tablist"`), pois Studio e suites navegam entre rotas.
O registro histórico de integração por `menu-local-tasks.html.twig` não
corresponde ao checkout atual: esse template foi removido. O SDC continua
publicado na UI Patterns Library e possui consumers atuais na página Overview
da Library, em `LearningDashboardController` e no mapeamento do componente
neutro `context-tabs` por `PagePresentationHooks::preprocessPortalComponent()`.
As capturas Firefox ESR registram uma versão anterior e não comprovam esses
fluxos atuais; a homologação por consumer segue pendente.

## Dialog — G1 implementado e integrado aos fluxos de modal/tour

Criado o SDC `dialog` com `<dialog>` nativo, gatilho em forma de link para
fallback sem JavaScript, título associado por `aria-labelledby`, ação de fechar
e slots para conteúdo e ações. A variante `fullscreen` ocupa a tela no mobile e
volta à largura modal a partir de 40rem. O comportamento reutiliza
`utility-dialogs.js`, com foco cíclico por Tab/Shift+Tab; o SDC usa a descoberta
automática de `dialog.css`. Consumer implementado em `components/lms-tour/lms-tour.twig`
com apresentação acessível para o percurso do estudante no LMS. Schema 55/764,
design foundations, render Drupal isolado (40 SDCs), PHP lint/PHPCS, YAML e diff
passaram. Validação visual no Runtime com outro agente.

## Form field — SDC publicado; adapter genérico não confirmado

Criado o SDC `form-field` para compor o rótulo, o controle Drupal, a instrução
e a mensagem textual de erro, sem assumir a validação nem o tipo do controle.
O registro histórico de integração por `form-element.html.twig` está obsoleto:
o arquivo não existe no checkout e nenhuma referência de produção ao SDC foi
localizada. `form-field` permanece publicado na UI Patterns Library, com suas
stories e usos em stories compostas. Preservar o SDC; tratar uma integração
genérica de formulários Drupal como trabalho pendente, sem afirmar que a
migração está ativa.

## Progress — composição atual no cartão de curso LMS

Criado o SDC `progress` baseado no elemento nativo, com rótulo e valor
percentual legíveis quando determinado; `value: null` preserva o estado
indeterminado sem inventar percentual e o CSS deixa preenchimento parcial. O
O registro histórico de integração por `progress-bar.html.twig` está obsoleto:
o arquivo não existe no checkout. O consumer atual confirmado é
`lms-learning-course-card`, que compõe `progress` para cursos com aulas. Isso
não demonstra substituição global de barras de progresso Drupal. O gate Drupal
verifica os estados determinado e indeterminado e o rótulo do SDC.

## Data table — SDC publicado; adapter de Views não confirmado

Criado o SDC `data-table` com variante `cards` responsiva para o layout do
Studio e variante `scroll` padrão conforme ADR-040. A tabela e os render arrays
continuam no módulo consumidor; a variante de cartões exige cabeçalhos/células
com roles e `data-label`. O registro histórico de integração por
`views-view-table.html.twig` está obsoleto: o arquivo não existe e nenhuma
referência de produção ao SDC foi localizada fora do catálogo/stories.
Preservar `data-table` na Library; não marcar a migração genérica de tabelas de
Views como ativa.

## Empty state — SDC atual; adapter genérico de Views removido

Criado o SDC `empty-state` com slots para mensagem e ações e variante de
espaçamento compacto. Mantém o contrato neutro consumido pelo Studio e pelas
listagens do Portal: o módulo continua decidindo o estado vazio, preparando
conteúdo localizado e links permitidos, com cache e permissões preservados.
O registro histórico de integração por `views-view.html.twig` está obsoleto: o
arquivo não existe. Os consumers atuais incluem `lms-learning-dashboard`, o
mapeamento do componente neutro `empty-state` por
`PagePresentationHooks::preprocessPortalComponent()` e o formatter H5P de
`aculta_lms_h5p`. Não há evidência de um adapter genérico para estados vazios de
Views. Preservar o componente e não declarar essa migração como concluída.

## Price — G1 aguardando autorização

O ADR-046 lista `price`, mas as referências lidas não definem props,
apresentação ou consumer semântico. A implementação é uma tarefa ligada a
Commerce/Loja, que o plano atual exclui sem autorização explícita por tarefa.
Não foi criado componente nem assumido formato, moeda, impostos ou desconto.
Retomar após autorização e definição do consumer/contrato; outras fases seguem.

## Profile details — adoção de bio e redes do Studio

O preprocess do tema adapta `profile-bio` e `profile-social` para `profile-details`
sem consultar usuários nem achatar render arrays; `title_id` e `heading_level`
associam o título por `aria-labelledby`. **Atualização da auditoria 2026-11-14:**
Drupal Core injeta `#slots` nos blocks SDC e os guards condicionam wrappers
opcionais. O gate renderiza o catálogo, mas não confere cada saída deste
consumer. A captura de `/meu-perfil` no Firefox ESR (97/97 checks, 22 ignorados)
é evidência visual histórica; confirmar conteúdo e cache no Runtime. Capturas em
`$SUITE_OUT/layouts-purpose-studio-profile-details-paired-20261106/{before,after}/`.
