## Revisão visual das listas públicas de atividade (2026-10-21)

- Firefox ESR/X11 anônimo no Runtime de teste: `/discussoes/novas`,
  `/discussoes/sem-resposta` e `/discussoes/mais-comentadas` responderam HTTP
  200, com H1 único, região principal e estado vazio esperado (12/12 verificações
  estruturais e de viewport em 390/768/1024 CSS px).
- Foram capturadas as três páginas em claro e escuro em cada viewport; o tema
  escuro foi forçado localmente no DOM. Inspeção manual de capturas representativas
  a 390 px/claro e 1024 px/escuro não encontrou corte ou sobreposição no conteúdo;
  o aviso de consentimento permaneceu aberto.
- Capturas privadas `0600` e resultados em
  `/home/aculta/private/aculta/visual-tests/forum-lists-dev132-20261021/`.
  Navegação GET-only; nenhuma escolha de consentimento, submissão ou escrita no
  Runtime. As listas estavam vazias, portanto linhas com tópicos não foram
  avaliadas.

## `0.1.0-dev.131` — valida salto acessível ao conteúdo no Firefox

- `forum-index-visual.mjs` usa Tab e Enter via WebDriver no teste anônimo das
  páginas públicas: o primeiro Tab deve revelar o link de salto com foco visível
  e Enter deve focar `main#main-content`.
- `node --check` PASS. Firefox ESR/X11 no Runtime de teste: 30/30 verificações
  no conjunto (15/15 na raiz e 15/15 em `/categorias`), incluindo 18
  verificações de viewport/tema e quatro de teclado; capturas nos diretórios
  privados `forum-rc-dev131-keyboard-home-20261021` e
  `forum-rc-dev131-keyboard-categories-20261021`.
- Captura específica do link de salto focado a 390 px também fica em cada
  diretório; o modo escuro é forçado somente no DOM do navegador.
- Navegação só com GET, Tab e Enter; nenhum formulário foi submetido e nenhuma
  configuração foi alterada.

## `0.1.0-dev.130` — comprova imagem inline após mover comentário

- Kernel `ForumCommentMoverKernelTest` salva uma imagem inline referenciada
  pelo CKEditor no `comment_body`, move o comentário e confirma que o hook
  `editor_file_reference` conserva o uso por `editor` no mesmo CID.
- Suite Kernel: 19 testes / 386 assertions PASS em MariaDB `aculta_simpletest`;
  19 depreciações de Core/contrib. Nenhum dado do Runtime foi alterado.
- Revisão visual GET-only da raiz e categorias em Firefox/X11: 26/26
  verificações PASS nos viewports 390/768/1024 px, claro/escuro. Capturas
  privadas e limites da inspeção estão em `docs/ROADMAP.md`.

## `0.1.0-dev.129` — documenta contrato de anexos nos comentários

- `ForumTopicDisplayConfigTest` verifica que a resposta pública usa o formato
  `usuario_aculta`, que permite embeds do Media `document` e upload inline via
  CKEditor no diretório `inline-images`.
- O limite do upload CKEditor é comparado com `aculta_image.UploadPolicy`; a
  integração usa o processamento existente de imagem, sem campo ou política
  paralela no Fórum.
- Upload direto de documentos por participantes segue pendente da decisão de
  escopo; não houve mudança de permissões nem upload no Runtime.

## `0.1.0-dev.128` — PHPCS dos docblocks de atividade — 2026-10-21

- Quebra docblocks longos do produtor de eventos em linhas compatíveis com
  Drupal/DrupalPractice. Nenhum comportamento ou contrato mudou.
- README, roadmap, matriz de testes e índice de status foram alinhados à dev.128.
- A documentação da fase LMS registra o fluxo H5P–Fórum validado no Runtime de
  teste pela F15 de `aculta_lms_h5p`; busca e notificações privadas LMS seguem
  pendentes. Nenhuma alteração de Runtime nesta atualização documental.

## `0.1.0-dev.127` — produtor de eventos de atividade para estatísticas — 2026-10-21

- Correção de teste (`ForumSearchConfigTest`): o `config/install` da busca do Fórum (`search_api.index.aculta_forum.yml` e `views.view.aculta_forum_search.yml`) passa a ser idêntico ao `config/sync` exportado do Runtime. Diferenças eram ordem de chaves e duas entradas `remember_roles` com valor `'0'` (equivalentes a ausentes, Drupal não as exporta). Nenhuma regra de acesso mudou.
- `ForumActivityEvent` (`aculta_forum.topic_created` e `aculta_forum.comment_created`) é disparado na criação de tópico (`aculta_forum_topic`) e de comentário em tópico do Fórum. Edição, movimentação e exclusão não emitem evento; comentários de publicações, LMS e Wiki são ignorados.
- O evento carrega só identificador e instante. Nenhum autor, texto ou e-mail.
- `aculta_statistics` assina os nomes e grava `forum.topics.created` e `forum.comments.created` (ADR-0004).
- Testes: `ForumActivityEventHooksTest` (4 casos) passa. Suíte Unit do Fórum: 94/94.
- Pendente: validar os eventos no Runtime com um tópico e um comentário reais; decidir se o painel do Fórum passa a ler o agregado.

## `0.1.0-dev.126` — verificar permalink canônico após mover comentário — 2026-10-20

- O Kernel verifica que, depois de mover uma árvore, o permalink gerado pelo
  Comment continua usando a rota canônica Core `entity.comment.canonical` e o
  fragmento `#comment-{cid}`. O comentário mantém seu CID, e a rota Core usa o
  hospedeiro atual para calcular a página e encaminhar ao tópico de destino.
- Links antigos diretamente para o tópico de origem com fragmento não podem
  ser redirecionados no servidor: fragmentos não são enviados na requisição.
  Essa limitação permanece conhecida; o permalink canônico do comentário é
  estável após a movimentação.
- `ForumCommentMoverKernelTest`: 18 testes/368 assertions PASS em MariaDB
  `aculta_simpletest`; 19 depreciações Core/contrib. `ForumTopicDisplayConfigTest`:
  19/283 PASS. Suas comparações de configuração agora ignoram apenas a ordem
  de chaves em mapas YAML e preservam a ordem significativa de listas.
  PHPCS e `git diff --check` PASS. RUNTIME STATUS: DEFERRED (não houve
  alteração nem navegação no Runtime). O gate Drupal 11+ executa o status-index
  com PASS, mas falha na regra da Wiki que impede expor autoria pública; essa
  configuração fora do escopo não foi alterada.

## `0.1.0-dev.125` — homologar mover comentário no painel canônico — 2026-10-20

- A rota `/painel-administrativo/forum/comentarios/{comment}/mover` mostra o
  formulário com o título “Mover comentário do fórum”. O navegador X11 autenticado
  confirmou layout em 390 e 768 px, confirmação CSRF e submissão real pela Form API.
- Runtime de teste: árvore ramificada de quatro Comments movida entre dois
  tópicos temporários. CIDs, autoria, conteúdo, estados pendente/rejeitado,
  relações `pid`/`thread` e denúncia Flag/fila ACULTA User foram preservados.
  A limpeza removeu fixtures e configuração temporária: zero tópicos/Comments,
  zero flaggings de denúncia, 12 categorias, três avisos e fila Messenger
  preservados; `comment_move_enabled` voltou a ausente/desabilitado.
- Evidências X11 privadas `0600` em
  `/home/aculta/private/aculta-forum-comment-move-dev125-20261020/`.
- Forum Unit `ForumTopicDisplayConfigTest`: 19 testes/283 assertions PASS;
  Kernel `ForumCommentMoverKernelTest`: 18 testes/366 assertions PASS (19
  depreciações de Core/contrib); PHPCS, `node --check`, Drupal 11+ (996), mapa
  (154), fronteira (1598), slugs (381 rotas; 20 violações contrib baselined) e
  `git diff --check` PASS. CLI disponível: PHP 8.4.26, abaixo do alvo 8.5.
- RUNTIME STATUS: PASS no fluxo e viewports exercitados; aprovação, concorrência
  HTTP, anexos e aviso aos seguidores permanecem pendentes na matriz geral.

## `0.1.0-dev.124` — reabrir a rota canônica de mover comentários — 2026-10-20

- O formulário `ForumCommentMoveForm` fica acessível em
  `/painel-administrativo/forum/comentarios/{comment}/mover`, na área
  administrativa Comentários, por decisão do responsável. A rota mantém a
  conversão de Comment e exige `move aculta forum comments`; o caminho antigo
  singular e o caminho antigo sob Conteúdo continuam redirecionando ao painel.
- `comment_move_enabled` permanece desabilitado por padrão. Nenhuma operação de
  movimentação foi executada no Runtime nesta mudança.
- `ForumTopicDisplayConfigTest`: 19 testes/283 assertions PASS; PHPCS e
  `git diff --check` PASS. Runtime de teste: cache reconstruído e RouteProvider
  confirma a rota/handler/conversor; config ausente e nenhum conteúdo alterado.
  Sem Comment de Fórum ativo, renderização autenticada do formulário permanece
  DEFERRED (RUNTIME STATUS: PARTIAL).
- Drupal.org documenta `_form` como handler Form API e conversão/upcasting de
  parâmetros de entidade em rotas; ver fontes oficiais citadas no relatório.

## `0.1.0-dev.123` — homologar movimento de tópicos em lote

- Firefox/X11 autenticado no Runtime de teste confirmou seleção VBO de dois
  tópicos, configuração e confirmação explícitas, execução completa do Batch
  API e movimentação das entidades para a categoria escolhida. Identidade,
  autoria, publicação, Domain e discussão foram preservados; as revisões
  avançaram. Fixtures exatas foram removidas ao final e o fórum voltou ao
  baseline vazio, sem alteração de categorias ou da fila Messenger.
- O texto sobre os quatro contextos de discussão fica em
  `docs/ADMIN-TOOLS.md`; o painel Growroom420 não o renderiza. O smoke visual
  dev.122 verifica explicitamente essa ausência.

## `0.1.0-dev.122` — abas administrativas do Fórum

- `/painel-administrativo/forum` é a aba Início, intitulada Painel Growroom420,
  com o resumo público. A explicação dos limites dos quatro contextos fica só
  na documentação, não no painel. Os links repetidos de Ferramentas do Fórum
  foram removidos do conteúdo; Tópicos, Comentários e Configurações ficam nas
  abas administrativas nativas do Drupal e no menu lateral.
- Reativados os Page displays das Views de tópicos e comentários dentro da
  seção própria. Comentários publicados, aguardando moderação e rejeitados são
  subabas. Listagens mantêm filtros fixos por bundle, campo de comentário,
  tópico e Domain ativo do Growroom.
- `/painel-administrativo/forum/configuracoes` volta a abrir `ForumSettingsForm`.
  Caminhos históricos sob Conteúdo seguem redirecionando para a entrada central.
- Cache reconstruído no Runtime confirmado de teste; Firefox/X11 autenticado
  confirmou páginas canônicas HTTP 200, abas administrativas de primeiro e
  segundo nível e redirects legados. Smoke GET-only 25/25 depois da retirada do
  texto contextual; viewports 390/768/1024 sem overflow. Entity API confirmou
  zero tópicos e comentários. RUNTIME STATUS: PASS no escopo administrativo;
  produção não acessada.

## `0.1.0-dev.121` — preencher o painel central do Growroom420

- `/painel-administrativo/forum` agora mostra contagens de categorias públicas e
  tópicos publicados, limitadas ao bundle `aculta_forum_topic` e ao Domain
  canônico `forum`. As EntityQuery usam `accessCheck(TRUE)` e o resumo varia por
  grants, permissões, idioma e Domain, invalida pelas tags das listas e expira
  após 60 segundos.
- A página explica a separação das discussões de Growroom420, Revista, LMS e
  Wiki e oferece links gerados pelo `DomainPurposeManager` para o Growroom e
  suas categorias. Ferramentas administrativas continuam redirecionando ao
  painel e não são agregadas nele.
- Unit direcionado `ForumAdminOverviewControllerTest`: 2 testes/29 assertions
  PASS. Validação Runtime e smoke X11 desta versão serão registrados após
  executar o cache rebuild e inspecionar o conteúdo renderizado.

## `0.1.0-dev.120` — centralizar os caminhos administrativos do Fórum

- `/painel-administrativo/forum` permanece uma página própria e mínima. Os
  caminhos específicos de conteúdo, filas de comentários, denúncias,
  configuração e movimentação redirecionam localmente para ela em GET/HEAD/POST;
  nenhuma ferramenta é copiada para o painel. `/painel-administrativo/conteudo`
  continua sem tópicos do Fórum.
- Removidos os page displays administrativos das Views e seus atalhos de menu.
  Rotas genéricas Core de Taxonomy/tipos de conteúdo e configuração compartilhada
  de participação permanecem em seus sistemas.
- Forum + User Unit: 155 testes/831 assertions PASS; Kernel mover 3/84 PASS
  (5 depreciações de Domain contrib); PHPCS direcionado e gates Drupal 11 (988),
  mapa (154) e slugs (381) PASS.
- Runtime de teste: importação parcial apenas dessas duas Views, `drush cr` PASS,
  displays `default` e RouteProvider resolve GET/POST nos caminhos centrais. Anônimo recebe 403 nos oito caminhos. ULI pelo purpose `conta` autenticou uid 1: a página central respondeu 200 e os sete redirects GET responderam 302 para ela. Logout encerrou a sessão e a página voltou a 403 anônimo.
  Backup anterior privado; nenhum Node ou Comment criado/alterado.
- Firefox ESR/X11 autenticado: 20/20 verificações GET-only; 12 caminhos estáticos
  terminaram no painel central e os viewports CSS 390/768/1024 não tiveram
  overflow. Evidências privadas em
  `/home/aculta/private/aculta/visual-tests/forum-admin-central-dev120-20261020/`.
  Rotas dinâmicas de movimentação não foram chamadas sem entidades de teste.
  RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.119` — corrigir validação da seleção da Action VBO

- `MoveForumTopicsAction` valida a seleção com os dados persistidos em
  `FormState`; o novo plugin criado pelo `ConfigureAction` do VBO não recebe
  `setContext()` durante a validação.
- Kernel `ForumTopicMoverKernelTest`: 3 testes/84 assertions PASS, incluindo
  instância sem contexto de plugin; 5 depreciações vieram do Domain contrib.
  Nenhum conteúdo do Runtime foi movimentado.

## `0.1.0-dev.118` — mover tópicos em lote com VBO

- Adicionada a dependência estável `drupal/views_bulk_operations:^4.4` e uma
  Action própria no display administrativo de tópicos. A pessoa seleciona até
  50 itens, escolhe uma categoria, revisa a lista e confirma; o processamento
  usa Batch API com lotes de 10 e resultado por tópico.
- Cada item volta a validar categoria, permissão `move aculta forum topics`,
  Domain e imutabilidade de `field_autor_legado` por `ForumTopicMover`. Itens
  que mudaram ou não podem ser movidos geram aviso individual; não há edição em
  lote de outros campos nem remoção/recriação de Node.
- Kernel `ForumTopicMoverKernelTest` cobre dois Nodes pela Action e recusa de
  tópico histórico; testes da View validam a configuração fixa. No Runtime de
  teste, VBO foi habilitado, somente a View de tópicos foi importada parcialmente
  e o cache reconstruído; RouteProvider, handler e Action foram descobertos. Em
  Firefox/X11 autenticado, a View listou as duas fixtures, a seleção real foi
  registrada e a Action habilitada (5/7 verificações). A configuração VBO abriu,
  mas o smoke não reconheceu o resumo/seletores e não submeteu confirmação ou
  Batch API. As fixtures foram removidas e a verificação final confirmou zero
  tópicos e Comments. GET administrativo
  anônimo retorna 403. A tradução pt-br não pôde ser baixada. RUNTIME STATUS:
  PARTIAL.

## `0.1.0-dev.117` — painel central do Fórum geral

- `/painel-administrativo/forum` passa a ser o painel de entrada do Growroom420,
  como página canônica mínima. Não agrega nem replica ferramentas, nem vira alias
  da lista de tópicos; a navegação administrativa mantém cada ferramenta em sua
  rota própria. Os caminhos administrativos legados redirecionam para essa
  entrada central.
- A listagem fica em `/painel-administrativo/forum/conteudo`; comentários e
  moderação continuam isolados na seção própria. `/painel-administrativo/conteudo`
  permanece sem conteúdo do Fórum.
- GET/HEAD dos antigos paths sob `/painel-administrativo/conteudo/forum`
  redirecionam localmente ao painel. Telas Core de categorias e bundle recebem
  destino de retorno; formulários de mover tópico/comentário redirecionam ao painel.
- O controller não consulta nem renderiza Menu Link Tree; portanto a página
  central não reúne telas ou links que pertencem às rotas administrativas próprias.
- `ADMIN-TOOLS.md` esclarece a direção canônica: `/painel-administrativo/forum`
  renderiza sua própria página; caminhos antigos/dispersos sob Conteúdo apontam
  para ela, e a rota central não incorpora nem redireciona para as ferramentas.
- Unit da suíte `aculta_forum`: 90 testes/601 assertions PASS; PHPCS PASS.
  Runtime `toca`, PHP CLI 8.4.26: `drush cr` PASS; Firefox/X11 autenticado
  34/34 para painel, conteúdo, filas,
  revista, viewport 390/768 px e sete paths antigos. A listagem de tópicos abre
  somente em `/forum/conteudo`.
- Smoke Form API de mover tópico: 7/7 PASS em duas execuções; fixtures unpublished
  NIDs 464 e 465 criaram revisões preservando UUID/autor/estado e foram removidas. Pré/pós: 0 tópicos,
  0 comentários compartilhados, 0 legado, 12 categorias e fila Messenger 0.
  Evidências privadas em `/home/aculta/private/aculta/visual-tests/`.
- A primeira tentativa de redirect genérico capturou somente caminhos de um
  segmento; os seis padrões legados descendentes foram explicitados e o smoke
  final passou. O gate de fronteira módulo/tema reporta uma violação em
  `aculta_mailer/src/Form/MailBrandForm.php`, fora do escopo e do commit do
  Fórum. RUNTIME STATUS: PASS no escopo. Produção não foi acessada.

## `0.1.0-dev.116` — configurar opções próprias do Fórum

- Adicionado `ForumSettingsForm` com `ConfigFormBase` do Core e `#config_target`
  para `aculta_forum.settings:comment_move_enabled`; a opção controla apenas a
  disponibilidade da ferramenta de mover comentários do Growroom420.
- Nova rota `/painel-administrativo/forum/configuracoes` e link na seção própria.
  Exige `administer aculta forum settings` (`restrict access`); não concedida a
  `content_editor`. Habilitar a opção não concede `move aculta forum comments`.
- O valor de configuração de instalação e sync continua `false`. O formulário
  só habilita a operação mediante permissão separada; tópicos/Comments históricos
  continuam inelegíveis e o escopo permanece no contexto público.
- PHPUnit `ForumTopicDisplayConfigTest`: 19 testes/246 assertions PASS; PHPCS
  Drupal/DrupalPractice PASS. PHP CLI 8.4.26 está abaixo da baseline 8.5.
  Firefox ESR/X11 autenticado: 31/31; rota 200,
  checkbox desmarcado, submissão Form API de `false` bem-sucedida e anônimo 403.
- `drush cr --uri=https://aculta.toca.net.br` PASS para registrar a rota. Antes
  do smoke, `aculta_forum.settings` não existia; o script gravou somente
  `comment_move_enabled: false`, verificou a renderização após o POST e removeu
  esse único item para restaurar a ausência. EntityQuery após cleanup: 0 tópicos
  públicos e 0 Comments compartilhados. Runtime PASS para rota, access e gravação
  do estado seguro; não foi testado habilitar o movimento.

## `0.1.0-dev.115` — seção administrativa própria do Growroom420

- A aba de tópicos que antes ficava em Conteúdo passa a ser a página principal
  da seção própria `/painel-administrativo/forum`; os tópicos deixam de aparecer
  em `/painel-administrativo/conteudo`. As filas de
  comentários publicados, rejeitados e aguardando moderação e as ferramentas de
  mover tópico/comentário passam para a mesma árvore.
- Entrada própria no menu lateral, com links para categorias Taxonomy,
  configuração do bundle do tópico, denúncias e regras de participação
  compartilhadas. Access continua sob as permissões e handlers dos respectivos
  sistemas; não foi criado CRUD/configuração paralela.
- Configuração ativa atualizada somente nos cinco paths das duas Views; `drush
  cr` PASS. Rotas novas são registradas, URLs dos cinco links do menu geram
  destinos sob a seção, e comentários aparecem como tarefas locais nos níveis
  esperados. `access_manager` permite os nove destinos administrativos ao UID 1;
  requests anônimos retornam 403 e os paths antigos sob Conteúdo retornam 404.
  Firefox ESR/X11 autenticado GET-only: 27/27 verificações PASS; a página de
  tópicos e quatro páginas de Comments renderizam sem erro, sete caminhos antigos
  retornam 404, e Conteúdo não oferece a aba antiga. Em 390 px persistem 3 px de
  overflow só na tabela responsiva vazia; em 768 px não há overflow. Capturas
  privadas em `/home/piradopirata/private/aculta/visual-tests/forum-admin-dev115-20261020/`.
  Runtime PARTIAL: filas vazias não exercitam operações com comentários reais.
- Runtime permaneceu com 0 tópicos públicos e 0 Comments compartilhados; nenhuma
  entidade de conteúdo/categoria foi alterada. Configuração anterior era
  recuperável pelos cinco paths: `admin/content/forum`, `admin/content/forum/comentarios`,
  `admin/content/forum/comentarios/publicados`,
  `admin/content/forum/comentarios/rejeitados` e
  `admin/content/forum/comentarios/aguardando-moderacao`. Reverter código/menu,
  restaurar esses valores e reconstruir cache; sem import/export global.

## `0.1.0-dev.114` — validar transições LMS e reconstruir o cache

- Smoke autenticado no Runtime de teste submeteu confirmações Form API de
  aprovação e rejeição com tokens reais: ambas retornaram HTTP 200 e o estado
  persistido moveu cada Comment somente para sua fila correspondente. A fila
  negou acesso anônimo; nenhuma notificação pública foi criada para as ações LMS.
- Recibo privado registra 12/12 verificações e limpeza. Pós-condição confirmada
  novamente: 1 curso preexistente, 0 tópicos `aculta_lms_topic`, 0 Comments
  `aculta_discussion_post` e nenhum Group da fixture. Sem alteração de conteúdo
  preexistente.
- `drush cr --uri=https://aculta.toca.net.br` PASS. O serviço
  `aculta_forum_lms.moderation_controller` já resolvia antes do rebuild e
  continua resolvendo depois; as rotas de overview/fila estão registradas e a
  fila retorna 403 para anônimo, como esperado. A quebra do container atribuída
  ao controller não foi reproduzida nesta checagem; os erros recentes do log
  consultado eram de outras operações. Nenhuma alteração de código do
  controller nesta subfase. PHP CLI 8.4.26 permanece abaixo da baseline 8.5.
  RUNTIME STATUS: PASS para reconstrução do container e transições LMS cobertas;
  homologação geral continua pendente.
- Nova organização administrativa aprovada para planejamento: transferir a aba
  de Fórum de Conteúdo para `/painel-administrativo/forum`; ver Roadmap,
  `CONTEXTS.md` e mapa de caminhos. Esta decisão documental não altera rotas
  ativas.

## `0.1.0-dev.113` — validar linha e confirmações de moderação LMS

- Smoke autenticado no Runtime de teste usou Group, Node e Comment efêmeros
  próprios. A View mostra o Comment somente na fila pendente e emite as ações
  contextuais `approve`/`reject`; ambas as confirmações Form API abriram por GET.
  Nenhuma decisão foi submetida. Acesso anônimo à fila e ao tópico privado foi
  negado.
- Fixture, membership automática do Group e sessão foram removidas; consultas
  finais confirmaram 1 curso preexistente, 0 tópicos LMS e 0 Comments. Recibo
  privado: `/home/aculta/private/aculta/forum-lms-dev113/smoke-result.json`.
  RUNTIME STATUS: PARTIAL; transições HTTP e regressão de notificações/Game
  seguem pendentes.

## `0.1.0-dev.112` — provar montagem da fila pelo controller

- O Kernel instancia o `LmsForumModerationController` com as dependências reais
  da fixture e chama `comments()` contra a View de moderação instalada. As
  asserções verificam o display pendente, o argumento Group da rota e a cache
  tag contextual, prevenindo regressão de montagem/container.
- Kernel do arquivo: 1 teste/110 assertions PASS; suíte Kernel completa do
  adapter: 2 testes/127 assertions PASS, 40 depreciações Core/contrib/LMS. PHP
  CLI 8.4.26 está abaixo da baseline 8.5. Runtime sem alteração nesta subfase;
  smoke autenticado vazio está registrado na dev.111. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.111` — testar conversores reais da rota de moderação — 2026-10-20

- Kernel configura `entity:group` e `entity:comment` na rota, executa os
  conversores do Core a partir dos IDs e então invoca `CustomAccessCheck` com o
  Group/Comment carregado, decisão e conta. Verifica Educador permitido, aluno
  negado e Group autorizado divergente sem autorização.
- Adapter Unit: 18/190; Kernel: 2/122 PASS, 40 depreciações Core/contrib/LMS.
  Runtime não alterado. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.110` — validar resolução de access na rota de moderação LMS — 2026-10-20

- Kernel agora invoca o `CustomAccessCheck` do Core com parâmetros upcastados
  de Group/Comment, decisão escalar e conta. Educador autorizado passa; estudante
  e Group válido divergente não passam. Isso cobre a integração de rota além do
  hook, manager e submit do Form API.
- Adapter Unit: 18/190; Kernel: 2/118 PASS, 40 depreciações Core/contrib/LMS.
  Sem mudanças de Runtime ou conteúdo. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.109` — ações de moderação contextual LMS — 2026-10-20

- O adapter `aculta_forum_lms` adiciona operações de aprovar/rejeitar nas filas
  de comentários por Group, com confirmação Form API e revalidação no submit.
  Nenhuma alteração em Comment fora dos tópicos LMS é permitida por essas ações.
- Adapter Unit: 18/190; Kernel: 2/112 PASS, 40 depreciações Core/contrib/LMS.
- A View recebeu a coluna por importação parcial de um único item e o cache foi
  reconstruído no Runtime de teste. Sem comentário LMS ou smoke autenticado;
  RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.108` — criar filas de comentários por Group no LMS — 2026-10-20

- O painel `/painel-administrativo/lms/forum` lista apenas cursos e turmas
  moderáveis pela conta atual; três rotas de status revalidam o Educador via
  Group. Uma View Embed interna fixa Group, bundle, campo Comment e status, sem
  página/bloco global. Kernel verifica curso/turma, published/pending/rejected,
  Group não atribuído e Educador sem membership direto na turma.
- Runtime/HTTP ainda não revalidado. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.107` — permitir aprovação de comentários por Educadores LMS — 2026-10-20

- O adapter `aculta_forum_lms` agora autoriza `view`/`approve` contextual de
  Comments no LMS por `grade students`, sem conceder `update`, `delete` ou
  `administer comments`. Testes Unit 16/165 e Kernel 1/62 PASS; 37
  depreciações Core/contrib/LMS no Kernel.
- Filas e rotas Views do LMS ainda não existem; integração do hook em Runtime
  e acesso com conteúdo real seguem pendentes. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.106` — aplicar permissão contextual do adapter LMS — 2026-10-20

- `core.extension` rastreia o adapter LMS e `gnode`; importação parcial
  revisada aplica Group permissions e herança `grade students` somente nos
  cinco objetos necessários. HTTP público preservado e acervo de tópicos e
  Comments continua vazio. Adapter Unit 14/147 e Kernel LMS 2/74 PASS; UI e
  filas ainda pendentes.
  RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.105` — corrigir ativação do adapter LMS — 2026-10-20

- O adapter LMS passa a resolver explicitamente o serviço contextual do Fórum
  pelo seu ID registrado, corrigindo a compilação do container na ativação.
- Unit adapter: 13 testes / 143 assertions PASS. Kernel adapter: 2 testes / 74
  assertions PASS, com 38 depreciações Core/contrib/LMS. Runtime de teste:
  adapter habilitado; smoke HTTP 200; nenhuma discussão criada. Config Sync não
  reconciliada e autorização contextual Runtime pendente. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.104` — endurecer prova de Educador em turma LMS — 2026-10-20

- O Kernel LMS agora testa curso e turma de propriedade independente. Um
  Educador membro do curso, sem membership direto na turma, pode acessar o
  tópico contextual via permissões herdadas; conta externa com papel global
  `lms_teacher` continua sem acesso. A moderação contextual permanece exclusiva
  do Educador.
- Adapter `aculta_forum_lms` dev.12: Kernel 2 testes / 74 assertions PASS; 38
  depreciações de Core/contrib/LMS. Unit adapter 12/141 PASS. Nenhuma alteração
  no Runtime; ativação completa aguarda a integração de filas e UI do LMS.
  RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.94` — configurar o bundle privado LMS — 2026-10-20

- O adapter recebe install config para `aculta_lms_topic`, `body`, Comment
  compartilhado `field_aculta_discussion`, displays e relações Group Node
  restritas a `lms_course`/`lms_class`. Não há armazenamento novo de respostas.
- Os IDs de relationship type gerados conforme Group 3.3.5 têm até 32
  caracteres. Config Unit: 2 testes / 29 assertions PASS; suíte adapter
  completa: 8 testes / 48 assertions.
- Permissões dos Group roles, `lms_classes.settings`, configuração raiz de
  ativação, Kernel e Runtime foram adiados até validar a matriz de acesso.
  Nenhum dado/configuração do Runtime foi alterado. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.93` — negar acesso direto a tópico LMS sem Group — 2026-10-20

- O hook OOP `entity_access` impede que um Node `aculta_lms_topic` sem Group
  único, ou ligado a uma turma sem exatamente um curso pai, herde o acesso
  público padrão de Nodes publicados. Relações válidas retornam neutro e
  deixam o Group Core/contrib autorizar conforme permissões e membership.
- A proteção não cria rotas, conteúdo ou configuração Runtime. Adapter Unit total:
  6 testes / 19 assertions PASS (3 testes / 11 assertions novos). Runtime DEFERRED.

## `0.1.0-dev.92` — fundação do adapter privado LMS — 2026-10-20

- Criado `aculta_forum_lms` como adapter opcional, dependente de Forum, LMS e
  Group Node, sem dependência reversa ou habilitação no Runtime.
- O provider tagueado reconhece apenas Nodes persistidos `aculta_lms_topic`,
  exige vínculo Group único a curso/turma suportados e combina access do Node,
  Group e membership real. Vínculo ausente/ambíguo ou cadeia de turma/curso
  inválida nega acesso.
- Moderação exige `grade students` no curso contextual, inclusive para turma.
  Hook OOP permite ao Educador autorizado operar o marcador compartilhado sem
  conceder permissão global de administração de comentários.
- Faltam configuração instalável de bundle/campo/relationship types, prova
  Kernel da matriz de acesso e integração de filas, busca, avisos e UI. O módulo
  permanece desabilitado e a homologação Runtime fica DEFERRED. Nenhum dado do
  Runtime foi alterado.

## `0.1.0-dev.91` — teste visual X11 da busca pública — 2026-10-20

- Adicionada suíte Firefox X11/WebDriver BiDi somente leitura para `/pesquisar`.
  Ela valida formulário, estado vazio, ausência de CAPTCHA, enquadramento e
  contraste em 390/768/1024 CSS px; não envia formulários nem cria conteúdo.
- Runtime de teste: 20/20 verificações PASS; botão da busca atingiu contraste
  12,40:1 no claro e 12,23:1 no escuro. A página não ampliou o viewport; o
  offcanvas fechado do carrinho está fora da tela e não gera overflow do
  documento. Capturas privadas em
  `/home/aculta-system-data/private/aculta-forum-search-dev91/visual/captures/`.
- A busca usa o Runtime vazio: 0 tópicos e 0 comentários antes/depois.
  Comparação completa com categorias/tópicos e referência Ubuntu Discourse
  permanece planejada para quando houver uma página com conteúdo permitido.
  RUNTIME STATUS: PASS para busca vazia e apresentação visual.

## `0.1.0-dev.90` — validar texto, categoria e relevância da busca — 2026-10-20

- `ForumCategorySearchKernelTest` agora indexa título e corpo com Search API DB;
  prova que busca textual e filtro de categoria podem ser combinados e que o
  boost configurado para título pontua acima de uma correspondência apenas no
  corpo. Também confirma que o padrão de relevância deixa uma correspondência
  no título antes da correspondência somente no corpo.
- Suíte Kernel completa: 26 testes / 513 assertions PASS no MariaDB dedicado;
  19 depreciações de Core/contrib. A suíte Unit não foi alterada nesta entrega.
  Nenhum conteúdo Runtime foi criado ou alterado; RUNTIME STATUS: DEFERRED para
  resultados reais publicados, pois o Fórum deve permanecer vazio.

## `0.1.0-dev.85` — negar formulário de movimento para Growroom histórico — 2026-10-20

- Kernel prova que a imutabilidade do tópico histórico é aplicada também no
  formulário: mesmo com permissão administrativa, o serviço nega movimento, o
  Form API lança acesso negado e a categoria original permanece.
- `ForumTopicMoverKernelTest`: 3 testes / 71 assertions PASS no MariaDB
  dedicado; 5 depreciações de Core/Domain contrib. Lint, PHPCS, Drupal 11+
  (975), fronteira (1451), mapa (152) e `git diff --check` PASS. A suíte Kernel
  completa já havia passado na dev.84 antes deste novo caso; Runtime não
  alterado; RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.84` — negar formulário sem acesso ao tópico — 2026-10-20

- Kernel invoca o Form API de mover com uma conta que tem a permissão de mover,
  mas não pode acessar o tópico fonte. O formulário lança acesso negado antes
  de expor destinos; chamada direta também preserva o host. A suíte Kernel
  completa passou: 24 testes / 476 assertions no MariaDB dedicado, 19
  depreciações Core/contrib. Forum Unit: 82 testes / 500 assertions PASS.
- PHP lint, PHPCS, Drupal 11+ (975), fronteira (1451), mapa (152) e diff-check
  PASS. PHP CLI 8.4.26 abaixo da baseline 8.5. Runtime DEFERRED.
- Regressão dos consumidores próximos: `aculta_game` Unit 10 testes / 46
  assertions PASS; `aculta_user` Unit 55 / 189 PASS, com uma depreciação do
  PHPUnit. Nenhum arquivo desses módulos foi alterado.

## `0.1.0-dev.83` — exigir acesso ao tópico de origem para mover comentários — 2026-10-20

- Kernel cobre conta com permissão operacional de movimento, mas sem `access
  content`: a fonte e destinos são inacessíveis, e chamada direta não altera
  o host. `ForumCommentMoverKernelTest`: 18 testes / 365 assertions PASS no
  MariaDB dedicado; 19 depreciações Core/contrib. PHP CLI 8.4.26 abaixo da
  baseline 8.5; lint, PHPCS, Drupal 11+ (975), fronteira (1450), mapa (152) e
  `git diff --check` PASS.
- Regressão completa de `aculta_forum`: Unit 82 testes / 500 assertions PASS;
  Kernel 24 testes / 475 assertions PASS no MariaDB dedicado. As 19
  depreciações reportadas são de Core/contrib instalados.
- Sem alteração no Runtime; RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.82` — exigir permissão dedicada para mover comentários — 2026-10-20

- Kernel MariaDB cobre a negação quando a conta pode ler conteúdo/comentários,
  mas não tem `move aculta forum comments`: a operação e a lista de destinos
  ficam indisponíveis e o host do comentário permanece inalterado.
- `ForumCommentMoverKernelTest`: 17 testes / 348 assertions PASS no MariaDB
  dedicado; 19 depreciações preexistentes de Core/contrib. PHP CLI 8.4.26
  abaixo da baseline 8.5; lint, PHPCS, Drupal 11+ (975), fronteira (1450),
  mapa (152) e `git diff --check` PASS. Runtime não alterado;
  RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.81` — negar destino fechado ou de outro purpose — 2026-10-20

- O Kernel comprova que a tentativa de mover Comment publicado para tópico
  fechado ou Node do purpose `main` é negada, mantendo o Comment no host
  original. Incluído o Domain `aculta_org` na fixture para testar a fronteira
  real entre purposes.
- `ForumCommentMoverKernelTest`: 16 testes / 331 assertions PASS no MariaDB
  dedicado; 19 depreciações Core/contrib. PHP CLI 8.4.26 abaixo da baseline
  8.5; PHP lint, PHPCS, gates Portal 975, fronteira 1448, mapa 152 e
  `git diff --check` PASS. Sem mudança de Runtime; ferramenta continua
  fail-closed. RUNTIME STATUS: DEFERRED.
- Revalidação administrativa X11 autenticada: 19/19 verificações PASS; as
  quatro filas de comentários retornaram 200, `/painel-administrativo/conteudo`
  mostrou a aba Fórum, Revista permaneceu consolidada e a rota antiga retornou
  404. Nenhum 500. Em 768 px sem overflow; em 390 px o excesso de 3 px segue
  restrito à tabela administrativa. Roteiro só fez GET, sem conteúdo/config
  alterados; capturas privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-admin-recheck-dev81-20261020/`.
- Auditoria dos hooks do Messenger registrou um ponto que requer decisão antes
  da homologação de Comment publicado: movimentos não criam novo aviso, mas os
  avisos agregados já existentes continuam no tópico de origem. O storage não
  guarda eventos individuais suficientes para reverter/migrar contadores com
  segurança; ver `MOVE-TOOLS.md`. Nenhuma alteração de Messenger foi feita.

## `0.1.0-dev.80` — preservar traduções ao mover comentário — 2026-10-20

- O Kernel cria uma tradução francesa em Comment ACULTA e move a entidade pelo
  serviço real. A mesma entidade mantém idioma original/francês, assunto, corpo
  e publicação da tradução no destino. A cobertura usa a Entity API instalada;
  nenhuma lógica especial de idioma foi adicionada ao mover.
- `ForumCommentMoverKernelTest`: 15 testes / 315 assertions PASS no MariaDB
  dedicado; 19 depreciações Core/contrib. PHP CLI 8.4.26 abaixo da baseline
  8.5. PHP lint, PHPCS, gates Portal 975, fronteira 1448, mapa 152 e
  `git diff --check` PASS. Sem alteração do Runtime; RUNTIME STATUS: DEFERRED.
- Correção documental da evidência do mover: `MOVE-TOOLS.md` e a matriz agora
  distinguem coberturas Kernel já executadas (flagging, Search API, file usage,
  History e traduções) das integrações Runtime ainda pendentes (`aculta_image`,
  avisos com seguidores e concorrência HTTP).

## `0.1.0-dev.79` — validar transferência de estado não lido — 2026-10-20

- O Kernel MariaDB agora instala History no cenário do mover e comprova a
  semântica de leitura contextual: após mover um Comment publicado, a resposta
  nova deixa de contar no tópico de origem e passa a contar no destino. Os dois
  recibos pessoais de leitura por Node permanecem idênticos; nenhum histórico de
  participante é regravado ou transferido.
- `ForumCommentMoverKernelTest`: 14 testes / 292 assertions PASS. Foram
  observadas 19 depreciações de Core/contrib; PHP CLI 8.4.26 está abaixo da
  baseline 8.5. PHPCS, gates Portal 975, fronteira 1447, mapa 152 e
  `git diff --check` PASS. Sem mudança de comportamento no Runtime; mover segue
  desabilitado por padrão. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.78` — preservar denúncias ao mover subárvore — 2026-10-20

- O roteiro autenticado X11 cria uma denúncia real de Comment com a Flag
  `sinalizar_comentario`, verifica a identidade/owner da Flagging e confirma que
  `aculta_user.report_queue` continua exibindo a mesma denúncia após mover a
  árvore ramificada. O smoke passou 10/10 em 390×844, 768×1024 e 1024×768 CSS
  px; CIDs, autores, estados pendente/rejeitado, `pid`, `thread` e tópico destino
  também foram preservados.
- O teste detectou que a configuração ativa da Flag ainda apontava para o
  bundle antigo `comment_forum`; após backup e comparação, somente
  `flag.flag.sinalizar_comentario` foi alinhada à configuração versionada
  `aculta_discussion_post`. A importação parcial não alterou outras configurações.
- Cleanup removeu a Flagging QA, Comments e tópicos temporários; confirmou zero
  conteúdo/denúncias do Fórum, fila Messenger 0, 12 categorias, 3 avisos e
  `aculta_forum.settings` ausente. Capturas privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-comment-flags-dev78-20261020/`.
- Sem mudança PHP de Runtime. `drush cr` foi omitido do roteiro após reproduzir
  falha em `ConfigNamesMapper::getPath()` por mapper do `aculta_mailer` cuja rota
  `entity.aculta_mailer_template.edit_form` não estava registrada durante a
  edição paralela. Esse bloqueio externo ficou documentado; não houve alteração
  nos arquivos do Mailer. Gates Portal 975, fronteira 1447, mapa 152,
  `node --check` e `git diff --check` PASS. RUNTIME STATUS: PARTIAL.
- Revalidação posterior (2026-10-20), após commits independentes do Mailer:
  `drush cr` PASS; route provider confirmou a rota de edição do modelo e as
  páginas administrativas do Fórum. A raiz retorna HTTP 200; filas
  administrativas retornam 403 a anônimo. Runtime continua com 0 tópicos e 0
  comentários ACULTA e Flag configurada para `aculta_discussion_post`.

## `0.1.0-dev.77` — preservar pendência e rejeição em árvore movida — 2026-10-20

- Smoke X11 autenticado moveu uma árvore ramificada de quatro Comments,
  preservando a raiz pendente, uma resposta marcada como rejeitada, duas
  respostas pendentes, autores, textos, CIDs, `pid`, caminhos `thread` e
  destino. Todos continuaram não publicados; nenhum badge ou aviso foi gerado.
- 10/10 verificações PASS via Form API/CSRF, incluindo 390×844, 768×1024 e
  1024×768 CSS px sem overflow. Capturas privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-comment-mixed-dev77-20261020/`.
- Cleanup restaurou o baseline: Fórum/Comments vazios, fila Messenger 0, 12
  categorias, 3 avisos e opção de mover ausente. A prova usa `field_aculta_rejected`
  apenas em dados temporários e confirma sua preservação; estado publicado e
  aprovação via workflow ainda não foram exercitados. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.76` — isolar efeitos de teste do Messenger — 2026-10-20

- A revisão pós-dev.75 encontrou 24 eventos da fila Core
  `aculta_messenger_message_subscribe_event`; uma leitura via Drupal API
  confirmou que todos apontavam para Nodes já removidos. O worker oficial
  `drush queue:run` processou somente essa fila, dentro do limite de 24 itens.
  Recontagem: fila 0, Fórum/Comments 0, 12 categorias e 3 avisos preservados.
- O smoke Runtime agora exige fila Messenger vazia, marca somente os Nodes QA
  publicados como sincronizando durante o insert (evita evento de publicação e
  efeitos de conquistas) e verifica que a fila segue vazia após a limpeza. Isso
  não altera o comportamento do mover nem do Runtime fora do teste.
- O protocolo X11 foi repetido com árvore ramificada: 10/10 PASS e cleanup
  completo; capturas em
  `/home/piradopirata/private/aculta/visual-tests/forum-comment-branch-dev76-20261020/`.
  RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.75` — homologar movimentação de árvore ramificada — 2026-10-20

- O roteiro X11 de Runtime cria dois tópicos e quatro Comments pendentes: raiz,
  dois ramos diretos e uma resposta aninhada no primeiro ramo. O formulário
  autenticado moveu a árvore com CSRF; CIDs, autores, textos, status, relações
  `pid` e posições `thread` foram preservados. Origem ficou com zero Comments e
  destino com quatro antes da limpeza.
- Firefox ESR 153.4/geckodriver 0.37.1: 10/10 verificações PASS, inclusive
  formulário/fila em 390×844, 768×1024 e 1024×768 CSS px sem overflow
  horizontal. Capturas privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-comment-branch-dev75-20261020/`.
- Cleanup reverso removeu Comments e tópicos, apagou configuração temporária e
  reconfirmou zero tópicos/Comments, 12 categorias, 3 avisos e settings do
  mover ausentes. A função segue desativada por padrão. Limites: somente árvore
  pendente; estados mistos, History, flags, anexos `aculta_image`, notificações
  e concorrência HTTP pendentes. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.74` — homologar movimento de subárvore em dois níveis — 2026-10-20

- O roteiro X11 de Runtime agora cria uma raiz e duas respostas descendentes
  pendentes, executa o Form API com CSRF e verifica a transferência da árvore
  inteira. CIDs, autores, textos, estados, `pid`, prefixos `thread` e entidade
  de destino foram preservados; origem terminou com zero Comments e destino
  com três antes da limpeza.
- Firefox ESR 153.4/geckodriver 0.37.1: 10/10 verificações PASS. O formulário
  exibiu “3 comentários foram movidos.”; sem overflow em 390 e 768 CSS px.
  Capturas e resultados privados em
  `/home/piradopirata/private/aculta/visual-tests/forum-comment-subtree-dev74-20261020/`.
- Limpeza reversa removeu respostas, raiz e tópicos, apagou a configuração
  temporária e confirmou Runtime no baseline: 0 tópicos/Comments, 12 categorias,
  3 avisos, `aculta_forum.settings` ausente. A movimentação segue desativada por
  padrão. Limites: árvore linear de três itens, todos pendentes; estados mistos,
  ramificações, History, flags, anexos `aculta_image`, notificações e concorrência
  HTTP seguem pendentes. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.73` — homologar a movimentação de comentário — 2026-10-20

- O Runtime de teste tinha as duas instâncias `field_autor_legado` do Fórum
  ausentes, embora os armazenamentos já existissem e os bundles não tivessem
  conteúdo. Após registro privado do estado anterior e conferência de zero
  tópicos/comentários, foram importadas somente as duas configurações
  versionadas de instância. Nenhum armazenamento compartilhado foi removido.
- O roteiro X11 autenticado habilita temporariamente `comment_move_enabled`
  (ausente antes e removido no `finally`), cria dois tópicos públicos efêmeros e
  um comentário pendente, envia o formulário com CSRF e verifica movimento do
  mesmo CID ao destino, preservando autor, texto, estado e raiz da thread. As
  entidades temporárias são removidas pela Entity API; a contagem final voltou
  a zero. Os 12 termos e 3 avisos existentes foram preservados.
- A tela mostrou erro de plural (“1 comentários”); o formulário agora usa
  `formatPlural()`. Firefox ESR 153.4/geckodriver 0.37.1 no X11: 10/10
  verificações PASS em formulário de 390/768/1024 CSS px, incluindo ausência
  de overflow horizontal. Capturas privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-comment-move-dev73-20261020/`.
- PHPUnit Unit completo 82/500; Kernel `ForumCommentMoverKernelTest` 13/272
  (19 depreciações de Core/contrib); PHPCS Drupal/DrupalPractice e gates Portal
  975, fronteira 1419 e mapa 152 PASS. PHP CLI 8.4.26, abaixo da baseline 8.5.
  Limites: a rodada valida somente uma raiz pendente, sem descendentes; flags, History, anexos ACULTA Image,
  notificações com seguidores e concorrência HTTP seguem pendentes. A operação
  continua desativada por padrão. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.72` — corrigir documentação PHP do contrato de contexto — 2026-10-20

- Uma varredura PHPCS do módulo encontrou violações preexistentes em sete
  arquivos do contrato de contexto público. Foram ajustados namespace/use,
  docblocks de classes, métodos e propriedades; o teste de ambiguidade não usa
  mais variável ignorada. Comportamento de runtime e contratos não mudaram.
- PHPUnit Unit completo do Fórum: 82 testes/500 assertions PASS. PHPCS
  Drupal/DrupalPractice PASS nos sete arquivos; `php -l` e `git diff --check`
  PASS. O Runtime não foi alterado nesta fase. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.71` — validar a fila administrativa em telas estreitas — 2026-10-20

- A suíte administrativa X11 agora valida viewports CSS de 390 e 768 px,
  verifica a largura da fila de moderação e registra capturas adicionais.
- Firefox ESR 153.4/geckodriver 0.37.1, autenticado no MAIN de teste: 19/19
  verificações PASS. Em 768 px não há overflow; em 390 px a tabela responsiva
  estende-se 3 px e o excesso fica restrito aos elementos da tabela. O roteiro
  registra a condição observada. A página carregou corretamente e foi inspecionada
  em X11; não foi alterado CSS do tema administrativo nem código contrib.
- Capturas privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-admin-dev71-20261020/`.
  Somente navegação GET e login ULI temporário; nenhuma entidade foi criada ou
  alterada. Filas vazias não permitem avaliar as linhas de conteúdo em viewport
  estreito. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.70` — traduzir vazios da moderação do Fórum — 2026-10-20

- As filas administrativas de comentários passam a identificar as colunas de
  atualização e ações como “Atualizado” e “Operações”, e o resultado vazio como
  “Não há comentários disponíveis.”, em Config Sync e configuração de instalação.
- Unit verifica paridade install/sync e os três rótulos. No Runtime de teste,
  a configuração completa da View foi copiada antes da alteração em backup
  privado (`/home/aculta/private/aculta/forum-admin-labels-20261020`); os três
  valores foram alinhados ao Config Sync e `config:status` não reportou drift
  para a View. Firefox/X11 autenticado confirmou as quatro filas e rótulos em
  português em 15/15 verificações. Capturas privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-admin-dev70-20261020/`.
  Nenhum conteúdo foi criado; o fórum continuou limpo (0 tópicos e 0 comentários).
  Como as filas não têm dados, o isolamento entre contextos com linhas reais não
  foi revalidado. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.69` — smoke X11 de organização administrativa — 2026-10-20

- Criado roteiro automatizado de leitura autenticada no painel: separação entre
  conteúdo geral e Fórum, três filas de comentários, Revista consolidada e
  resposta 404 no caminho legado de comentários da Revista.
- Usa link temporário do administrador por Drush, somente navega por GET e salva
  capturas fora do checkout. Não altera conteúdo, configuração nem Runtime.
- Firefox ESR 153.4/geckodriver 0.37.1 no Runtime `toca`, sessão X11: 11/11
  checks PASS. Conteúdo geral e aba Fórum retornaram 200; as páginas agregada,
  publicadas, rejeitadas e aguardando moderação retornaram 200 e as três subabas
  apareceram sob Fórum. `/painel-administrativo/conteudo/revista` retornou 200;
  `/painel-administrativo/revista/comentarios` retornou 404.
- Capturas privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-admin-dev69-20261020/`.
  Roteiro só usou GET e login ULI temporário; Runtime permaneceu com 0 tópicos
  e 0 Comments do Fórum. Listas vazias não permitem provar exclusão cruzada de
  registros reais. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.68` — teclado e limites dos controles em X11 — 2026-10-20

- A suíte visual também envia Enter/ArrowRight pelo endpoint W3C WebDriver Actions
  para verificar anúncio em botão, atualização de `aria-valuetext` no slider,
  silêncio acessível na navegação contínua e estados de limite Início/Fim.
- Firefox ESR 153.4/geckodriver 0.37.1 no Runtime `toca`: Enter em Próxima
  anunciou “Mensagem 2 de 6”; ArrowRight avançou `aria-valuetext` sem anúncio
  contínuo; Fim/Início atualizaram o slider e os controles desabilitados. Suíte
  completa PASS: 25/25, com viewports CSS 320/360/390/500/900 px e capturas
  claro/escuro. Nenhum Node/Comment foi gravado. O overflow preexistente do
  offcanvas de carrinho continua igual ao baseline; ver dev.67. RUNTIME STATUS:
  PARTIAL.

## `0.1.0-dev.67` — suíte visual X11 reproduzível — 2026-10-20

- Adicionada suíte Firefox/geckodriver sem modo headless para repetir a revisão
  visual da barra de progresso em publicação de teste. Host é validado contra o
  ACULTA Deployer; largura, controles, marcador `.new`, anúncio, overflow e
  estados claro/escuro são verificados automaticamente.
- Seis mensagens sintéticas são inseridas somente no DOM. A suíte não autentica,
  não envia formulários e não grava conteúdo. Capturas e resultados exigem
  diretório absoluto privado fora do checkout.
- Firefox ESR 153.4, geckodriver 0.37.1, Node 22.23.3, sessão X11 `:0`,
  Runtime `toca` Drupal 11.4.10: 20/20 verificações PASS em viewports CSS exatos
  de 320/360/390/500/900 px; alvo móvel 44 px; ação History `.new`, anúncio,
  overflow adicionado pelos controles e capturas claro/escuro inspecionadas.
  Evidências privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-progress-dev67-20261020/`.
- A publicação usada já apresenta `document/body.scrollWidth=509` em viewports
  de 320–500 px por causa do `#aculta-cart-offcanvas` fixo fora da tela; a suíte
  registra o baseline e confirma que os controles não aumentam esse overflow.
  A causa pertence ao componente de carrinho e não foi alterada nesta entrega.
  Nenhum Node/Comment foi salvo. `node --check` e gates direcionados PASS.
  History real autenticado, leitor de tela e correção da rolagem preexistente
  continuam pendentes. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.66` — saltar à primeira resposta não lida — 2026-10-20

- A barra de progresso oferece “Não lidas” somente quando o History instalado
  marca um Comment renderizado com `.new`. MutationObserver acompanha a classe
  nativa; a ação navega à primeira mensagem marcada e usa o anúncio explícito
  acessível já estabilizado em dev.65. Sem marcador (incluindo anônimo), o
  botão fica oculto. Não calcula timestamp, contagem, acesso nem storage próprios.
- Firefox ESR 153.4/X11 e BiDi no Runtime de teste, página pública da Revista,
  seis mensagens e marcador `.new` sintéticos somente no DOM. A ação anunciou
  “Mensagem 4 de 6”; remover a classe ocultou o controle. Viewports
  320/360/390/500/1250 CSS px, sem overflow, claro/escuro inspecionados. Nenhum
  Comment foi persistido. `node --check` e gates Portal/tema/mapa PASS; integração
  autenticada real e leitor de tela pendentes. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.65` — preservar anúncio explícito durante rolagem suave — 2026-10-20

- A ação de botão mantém o anúncio acessível do destino solicitado durante a
  rolagem suave, mesmo quando o IntersectionObserver atualiza o progresso
  visual em mensagens posteriores. Após 1,2 s, a live region volta a `off` e
  sincroniza silenciosamente com a posição visível. Scroll manual e slider
  continuam silenciosos; `aria-valuetext` acompanha a posição efetiva.
- Firefox ESR 153.4/X11, viewport 390 × 844 CSS px: seis mensagens sintéticas
  somente no DOM. Enter em Próxima deixou “Mensagem 4 de 6” estável em
  `aria-live=polite` após 250 ms, enquanto a rolagem atualizou o estado visual
  para 6/6; ao fim do timeout, anúncio e estado visual sincronizaram em 6/6 com
  `aria-live=off`. Nenhum Comment foi salvo. `node --check` PASS; leitor de tela
  real pendente. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.64` — reduzir anúncios da navegação de progresso — 2026-10-20

- A posição visual acompanha a rolagem; a live region acompanha o mesmo texto
  em `aria-live=off`, sem anunciar cada mensagem. O slider mantém
  `aria-valuetext`; os botões publicam a posição para tecnologia assistiva
  somente após ação explícita. `role="status"` mantém o anúncio não intrusivo.
  ACULTA420 mantém o texto de anúncio fora do fluxo visual por atributo neutro,
  sem classes de design no Portal.
- WAI-ARIA/W3C documenta `role=status` como anúncio `polite`; MDN recomenda que
  live regions recebam atualizações breves e não frequentes. Fontes: [W3C
  ARIA22](https://www.w3.org/WAI/WCAG22/Techniques/aria/ARIA22.html), [MDN
  aria-live](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-live).
- Firefox ESR 153.4/Geckodriver no Runtime de teste: com seis Comments
  sintéticos somente no DOM, a rolagem manual atualiza status visual e texto
  acessível para 5/6 com `aria-live=off`; o slider atualiza ambos para 3/6,
  também em `off`; Próxima alterna para `polite` e anuncia 4/6. A região oculta
  mede 1×1 px e usa `clip-path`; nenhum Comment foi salvo. Não houve leitor de
  tela real. WebDriver BiDi aplicou viewport de 390 × 844 CSS px: barra
  `static`, largura de janela 390 px, documento/corpo 378 px, mensagem 5/6 e
  `aria-live=off` sem overflow horizontal.
- Forum Unit 82/497, JavaScript syntax, Drupal 11+ 975, fronteira 1419, mapa
  152, UI docs 280 tokens/97 atributos, SDC 535, Design Foundations 2097 e
  Foundation Runtime 366 PASS. `drush cr` PASS. PHP CLI 8.4.26 permanece abaixo
  da baseline 8.5. RUNTIME STATUS: PARTIAL (sem validação com leitor de tela).

## `0.1.0-dev.63` — navegação de progresso em discussões — 2026-10-20

- `DiscussionProgressHooks` anexa a biblioteca somente à renderização `full` de
  Nodes com o campo acessível `field_aculta_discussion`; os controles operam
  apenas sobre os Comments que já chegaram renderizados à página.
- A biblioteca permite ir ao início/fim, avançar/voltar e selecionar a posição
  com teclado. A posição acompanha a leitura por IntersectionObserver; sem
  JavaScript, os Comments continuam em fluxo linear e paginado pelo Core.
- A implementação é comum ao Growroom, publicações e futuros providers LMS/Wiki;
  não consulta entidades, não muda permissões nem expõe conteúdo fora da página.
- Pesquisa da API oficial Drupal 11: `hook_entity_view_alter` altera a render
  array consolidada; a biblioteca é anexada ao campo para respeitar o ciclo de
  render/cache. Fontes: API `hook_entity_view_alter` e `entity_crud`.
- Forum Unit 82/497, teste de hook 3/13, PHPCS, syntax check JS e gates
  Drupal11+ (975), fronteira (1419), mapa (152), atributos (280 tokens/96
  atributos), SDC (535), Design Foundations (2097), Foundation (365) e Shell
  (11) PASS. Runtime `toca`:
  cache rebuild e publicação pública HTTP 200; marcador aplicado e biblioteca
  agregado contém o behavior. Firefox ESR/X11 validou DOM sintético não
  persistido em 1250/500 px, quatro controles, avanço e ArrowRight no slider
  para mensagem 2/6 e ausência de overflow (document/body 500 px). Modo escuro
  forçado somente no DOM da sessão exibiu tokens legíveis. Fórum manteve-se sem
  fixtures. Claro/escuro, teclado do slider e IntersectionObserver PASS; 390 px,
  Comments reais e leitor de tela/AT seguem pendentes. Rolagem X11 detectou
  barra sob o cabeçalho; correção de offset do tema dev.111 revalidada a 500 px
  com a barra visível (topo 56 px) e status 4/6. Teste exato em 390 CSS px
  encontrou controles sticky cobrindo a mensagem; o tema dev.112 remove o
  sticky no mobile e foi revalidado em 390 × 844 CSS px: controles em fluxo,
  status 5/6 pela rolagem, sem sobreposição e sem overflow. Runtime segue
  PARTIAL porque Comments reais e leitor de tela/AT não foram homologados.
  Revalidação posterior dos movers: Forum Unit 82/497; Kernels de comentário e
  tópico 16/341 no MariaDB `aculta_simpletest`; Portal 975, fronteira 1419,
  mapa 152 e Design Foundations 2097 PASS. Runtime `toca`, URI
  `https://growroom420.aculta.toca.net.br`: rota registrada com permissão
  `move aculta forum comments`, opção ausente (fail-closed), rota anônima com
  ID inexistente 404, raiz 200 e 0 tópicos/Comments. Não houve login, alteração
  de configuração ou escrita no Runtime; homologação autenticada continua
  pendente. Os Kernels emitiram 19 depreciações de Core/contrib. PHP CLI
  8.4.26 permanece abaixo da baseline 8.5.

## `0.1.0-dev.62` — corrigir filas de moderação do Fórum — 2026-10-20

- A consulta da fila pendente/rejeitada falhava fechada em todas as execuções:
  no Core 11.4, a relação Node da View usa o alias
  `node_field_data_comment_field_data`, então procurar pelo nome-base
  `node_field_data` retornava vazio e o hook adicionava `1 = 0`.
- `ForumHistoricModerationQueueHooks` resolve o alias da relação configurada e
  confirma que a tabela é `node_field_data` antes de excluir autores históricos.
  A política fail-closed é preservada: relacionamento/tabelas ausentes
  continuam fechando a fila para proteger o acervo.
- Unit do hook: 5 testes/18 assertions; suíte Unit do Fórum: 79 testes/484
  assertions; Kernel de moderação `aculta_user`: 7 testes/84 assertions PASS.
  Runtime `toca`: a fila pendente trouxe somente o comentário
  do tópico Fórum e excluiu o da publicação. Fixtures 425/426 e 56/57 removidas
  via Entity API; estado final vazio para tópicos e Comments compartilhados.
- A conta QA disponível é isenta por `content_editor`; não foi usada para
  simular um primeiro comentário real. Essa política tem cobertura Kernel, mas
  o fluxo autenticado da rota e a inspeção visual permanecem pendentes.
  PHP CLI 8.4.26 (abaixo da baseline 8.5). RUNTIME STATUS: PARTIAL para a
  homologação integral; PASS para consulta e separação das filas.

### Complemento visual X11 da thread

- A comparação com um tópico Ubuntu Discourse foi feita em Firefox ESR/X11.
  ACULTA respondeu HTTP 200 e exibiu o comentário publicado; 1270 px e 500 px
  sem overflow (largura documental/corpo de 500 px). A thread ACULTA apresenta
  título, autoria/categoria do tópico e painéis de comentário com permalink.
  Discourse também mantém timeline/progresso do leitor e navegação persistente;
  esses detalhes ficam como foco de apresentação da dev.11.
- A thread usou resposta temporária publicada sob marcador de sincronização para
  excluir concessão de conquista; isso não validou moderação. O teste anônimo
  ficou pendente conforme regra e foi removido. Todos os fixtures foram
  removidos; Runtime final 0 tópicos/Comments, 24 History e 2 flaggings
  preservadas. Capturas mode 0600 em
  `/home/aculta/private/aculta/visual-tests/forum-thread-20261020/`.
- Thread extensa, ações autenticadas, leitor de tela e 390 px exatos seguem
  pendentes. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.60` — inspeção visual das listas de descoberta — 2026-10-20

- Firefox ESR visível em X11, visitante anônimo, Runtime `toca`:
  `/discussoes/novas`, `/discussoes/sem-resposta` e
  `/discussoes/mais-comentadas` retornaram 200. Três tópicos de QA com 0, 2 e
  3 comentários publicados comprovaram: ordenação por data decrescente em
  “novas” (3 → 2 → 0), somente o tópico sem respostas em sua lista e ordenação
  por contagem em “mais comentadas” (3 → 2 → 0).
- X11 a 1024 px mostrou título, categoria, autoria, contagem de respostas e
  última atividade em cada card. Janela estreita limitada a 500 px pelo
  gerenciador/Firefox: tabs quebraram em duas linhas e o conteúdo permaneceu
  dentro da área visível. Capturas privadas em
  `/home/aculta/private/aculta/visual-tests/forum-discovery-20261020/`.
- As três fixtures e seus cinco Comments foram removidos após conferir NID,
  UUID, título e vínculos; aliases e History específicos também foram limpos.
  Cache rebuild PASS; estado final: 0 tópicos, 0 Comments, 24 History globais
  (baseline) e 2 flaggings de avisos de site preservadas. As três rotas seguem
  HTTP 200 com lista vazia.
- Complemento visual em 2026-10-20: Firefox ESR no X11 Responsive Design Mode
  confirmou viewport real 390 × 670 px nas quatro rotas (raiz, novas, sem
  resposta e mais comentadas), todas HTTP 200, com `documentWidth` e
  `bodyWidth` de 390 px. A raiz e as três listas mostraram o tópico de fixture.
  Modo escuro foi aplicado somente no DOM local; tokens resultaram em fundo
  carvão e texto creme, sem salvar preferência Runtime. Comparação da categoria
  Support and Help do Ubuntu Discourse a 390 px observou header compacto,
  filtros que quebram em linhas e contagem ao lado do tópico; ACULTA mantém sua
  identidade, dobra tabs e conserva cartão legível sem overflow. O diálogo de
  cookies externo foi fechado somente no DOM local, sem aceitar consentimento.
  Fixture NID 418 e alias removidos; estado final 0 tópicos/Comments, 24 History
  e duas flaggings existentes preservadas. Capturas privadas em
  `/home/aculta/private/aculta/visual-tests/forum-mobile-20261020/`. Teclado em
  X11 também avançou desde o link de salto até links de categoria em 16 tabs;
  o foco permaneceu visível com contorno sólido de 2 px. Leitor de tela e
  comparação completa das páginas do Discourse seguem pendentes.
  RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.59` — homologação isolada da leitura em lote — 2026-10-20

- Runtime `toca`, Drupal 11.4.10, `aculta_runtime`: o serviço de leitura
  retornou exatamente um tópico elegível para a fixture QA e `markBatch()`
  gravou somente o par History uid 5/NID 410. Esse registro foi confirmado e
  removido pelo par exato; o Node e seu alias foram excluídos via Entity API.
  Cache rebuild PASS; estado final confirmado: 0 tópicos, 0 Comments
  compartilhados, 24 registros History (mesmo total anterior ao teste) e 2
  flaggings de avisos do site preservadas. Raiz e `/categorias` HTTP 200.
- A rota, confirmação e Core Batch completo não foram submetidos pelo
  navegador; a homologação desta ação permanece parcial. RUNTIME STATUS:
  PARTIAL.

## `0.1.0-dev.58` — ocultar ações Flag dos cartões teaser — 2026-10-20

- Flag registra os pseudocampos em todos os modos por padrão, então os links de
  seguir/salvar apareciam em cartões teaser das Views, apesar de estarem
  planejados somente no detalhe completo. O teaser agora oculta ambos os campos
  explicitamente; a página completa mantém as ações.
- Instalação e Config Sync foram alinhadas; o teste Unit compara os YAML e
  exige os dois pseudocampos ocultos no teaser. PHPUnit: 18 testes/224
  assertions PASS; PHPCS, Portal 11+ (975), fronteira (1415) e mapa (152)
  PASS. PHPUnit executou em PHP CLI 8.4.26, abaixo da baseline 8.5; permanece
  o aviso preexistente sobre `sites/simpletest/browser_output` não gravável.
- Runtime `toca`, Drupal 11.4.10, `aculta_runtime`: backup privado da
  configuração anterior, importação parcial somente do display teaser, cache
  rebuild e ausência de drift do alvo PASS. Render API confirmou teaser sem
  links e detalhe `full` com ambos. Firefox/X11 autenticado confirmou que os
  controles desapareceram da lista de não lidos e continuam no detalhe; o
  indicador de duas respostas novas permaneceu. A fixture NID 409, com dois
  Comments, foi excluída via Entity API; History e alias também foram limpos.
  Estado final: 0 tópicos, 0 Comments, 0 flags do Fórum e 0 registros History
  da fixture. Raiz e `/categorias` HTTP 200; rota de não lidos anônima 403;
  alias removido 404. RUNTIME STATUS: PASS para o escopo desta correção.

## `0.1.0-dev.57` — expor ações de seguir e salvar no tópico — 2026-10-20

- A View Display `full` inclui os pseudocampos de Flag `seguir_topico` e
  `salvar_topico` após a categoria e antes das respostas. Os links continuam
  sendo construídos pelo módulo Flag, com access, cache e estado por conta; não
  foram criados campos de storage nem endpoints próprios. O teaser público não
  recebe essas ações.
- `ForumTopicDisplayConfigTest` e instalação/Config Sync validam os dois
  pseudocampos e o placement. Unit: 18 testes/221 assertions PASS; PHPCS,
  Portal 11+ (975), fronteira (1415), mapa (152), SDC (535) e Design
  Foundations (2097) PASS. No Runtime, importação parcial somente do display
  após backup privado e cache rebuild PASS. Render da página completa para uid
  5 incluiu os dois links “Seguir tópico”/“Salvar tópico”; o Link Builder de
  Flag produziu âncoras autorizadas sob uid 5; render anônimo não expôs esses
  links. Firefox/X11 autenticado em 1024 px confirmou ambos os controles. Os
  cliques na conta de teste gravaram uma flag de cada tipo; os cliques seguintes
  removeram ambas (`1 → 0`). A fixture exata foi excluída após confirmar ausência
  de comentários e flags; Runtime terminou com 0 tópicos, 0 Comments e 0 flags
  de seguir/salvar. Firefox Responsive Design Mode sob uid 5, a 390 × 670 px,
  confirmou os dois botões lado a lado, sem transbordamento horizontal visível.
  Modo escuro forçado localmente no DOM mostrou os controles legíveis em 1024 px;
  a preferência/configuração do Runtime não foi alterada. Nenhuma flag de outro
  contexto foi alterada. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.56` — remover bundle residual do Forum contrib nas Flags — 2026-10-20

- `seguir_topico` e `salvar_topico` agora se aplicam somente ao bundle atual
  `aculta_forum_topic`. Removida a referência residual `forum` dos defaults e
  Config Sync depois da desinstalação oficial do módulo contrib. Nenhum dado de
  Flag foi removido.
- `ForumTopicDisplayConfigTest` exige a lista exata de bundles para impedir que
  a associação antiga volte. Estado inicial do Runtime: ambas as Flags tinham
  0 flaggings. Importação parcial atualizou somente as duas Flags após backup
  privado; cache rebuild e `config:status` dos alvos PASS. Contagem de flaggings
  permaneceu 0 para ambas, fórum com 0 tópicos/Comments, raiz e `/categorias`
  HTTP 200. Nenhum storage ou flagging foi apagado. RUNTIME STATUS: PASS.

## `0.1.0-dev.55` — propagar cache do texto citado — 2026-10-20

- A ação de citação lê a propriedade computada `processed` do item de texto e
  inclui seu `CacheableDependencyInterface` no formulário. Tags, contextos e
  max-age dos filtros aplicados acompanham o texto pré-preenchido.
- PHPUnit dos hooks: 6/18; suíte direcionada com Builder e config: 26/240 PASS;
  PHPCS, Portal 11+ (975) e fronteira módulo/tema (1415) PASS. Runtime `toca`:
  cache rebuild PASS e hook/Builder/Context Resolver resolvidos pelo container.
  Firefox/X11: listagem de tópicos em 1024, 768 e 390 px e detalhe em 390 px
  exibiram cartões legíveis, autoria, texto e categoria sem overflow horizontal.
  Para a inspeção, três tópicos QA temporários foram criados via Entity API e
  removidos após a captura; não tinham comentários. Verificação final no
  Runtime confirmou 0 tópicos, 0 Comments, fila de inscrição Messenger vazia e
  aliases removidos. O formulário e a ação Citar com sessão autenticada seguem
  pendentes. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.54` — agrupar subcategorias na navegação — 2026-10-20

- A View de `/categorias` agora ordena primeiro pelo nome do pai, depois pelo
  peso e pelo nome da categoria. Raízes ficam no início; subcategorias aparecem
  agrupadas pela categoria principal, reduzindo a dispersão observada na
  inspeção visual X11. O filtro e o vocabulário continuam fixos.
- Configuração de instalação e Config Sync estão alinhadas. PHPUnit
  `ForumTopicDisplayConfigTest`: 17/217 PASS; PHPCS, Portal 11+ (975), fronteira
  (1415), mapa (152), pacotes próprios (26), slugs (378), aculta_image (25) e
  `git diff --check` PASS.
- Runtime `toca`, Drupal 11.4.10, `aculta_runtime`: snapshot privado da View
  anterior em `/home/aculta/private/aculta/runtime-config-backups/forum-dev54-20261020/`
  (SHA-256 `44b2578fdd372b4815ad46af65252e3a36fa79b8b167ce33d16cb69e13816521`);
  importação parcial processou somente essa View, cache rebuild PASS, listagem
  da API ordenou 4 raízes e 8 filhos por pai, `/categorias` HTTP 200 e 0 Nodes
  `aculta_forum_topic`/0 Comments `aculta_discussion_post`. `config:status` ainda
  marca a View como `Different` porque a ordem de chaves de `display` e de
  `block_landing.display_options` difere; arrays normalizados do Runtime e Sync
  têm os mesmos valores, sem drift semântico nessa View.
- Firefox/X11: categorias conferidas em 1024 e 768 px, mais viewport 320 px no
  Responsive Design Mode; raízes antes dos filhos, grupo/ordem coerentes e sem
  overflow horizontal observado. Capturas privadas indexadas em
  `docs/TEST-MATRIX.md`. RUNTIME STATUS: PARTIAL; fluxo visual autenticado e
  páginas com tópicos permanecem sem fixture.

## `0.1.0-dev.53` — citar comentário no contexto — 2026-10-20

- A ação **Citar** usa a rota Core de resposta com o ID do comentário como pai;
  o formulário só recebe a citação quando a origem é o pai exato, publicado,
  legível no contexto atual e o usuário pode responder. Atende os providers
  públicos existentes (Growroom e Revista); LMS e Wiki permanecem fora até
  terem providers com autorização contextual.
- O builder usa o conteúdo já filtrado, remove marcação, limita a 1.200
  caracteres e escapa texto e autoria dentro de `blockquote` permitido pelo
  formato `usuario_aculta`. A resposta original não é alterada. Nenhuma
  dependência contrib foi instalada.
- Unit: 9 testes/18 assertions PASS, incluindo escape/limite, bloqueio de
  contexto ilegível, rota Core e preenchimento da resposta. PHPCS e `git
-  diff --check` PASS. Runtime em `toca`, Drupal 11.4.10, banco
  `aculta_runtime`: cache rebuild e resolução dos dois serviços PASS; Fórum e
  `/categorias` HTTP 200; contagens finais 0 tópicos e 0 Comments. O link e o
  formulário não foram testados com comentário real porque o Runtime precisa
  permanecer vazio. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.52` — categorias junto à lista recente — 2026-10-20

- Adicionado o display Views `block_landing` à View Taxonomy existente e bloco
  CTools na barra lateral, visível apenas pela raiz `/` e rota interna
  `/discussoes` do purpose `forum`. A View mantém fixos o vocabulário e o filtro
  de categorias principais; CTools controla somente quantidade e pager. A
  lista completa e sua hierarquia continuam em `/categorias`.
- Configurações parciais da View e do bloco importadas no Runtime de teste com
  backup privado; cache rebuild PASS. Firefox/X11 mostrou categorias à esquerda
  e atividade à direita em 1024 px, e a barra após a lista em 768/390 px. A raiz
  principal não recebe o bloco. Tópicos e Comments permaneceram em zero.
  PHPUnit Unit direcionado 17/215, Foundation 364, SDC 535, Design Foundations
  2097, Portal 975, fronteira 1411 e mapa 152 PASS. Modo escuro foi inspecionado
  em X11 a 1024/390 px por override DOM local. Tab verificou foco visível no
  atalho, marca e busca em 1024 px; leitores de tela, fluxo completo, tópico e
  composer seguem pendentes. RUNTIME STATUS: PARTIAL.

## Revisão visual X11 parcial — sem nova versão — 2026-10-20

- Comparadas a entrada ACULTA Forum e o Ubuntu Discourse em Firefox/X11; raiz e
  categorias ACULTA verificadas em 1024, 768 e 390 px. A grade reflowa e não
  apresentou overflow horizontal. As capturas foram arquivadas fora do Git em
  `/home/aculta/private/aculta/visual-tests/forum-dev11-20261020/`.
- A rodada inicial encontrou categorias separadas da atividade; dev.52 corrigiu
  a composição responsiva. Modo escuro, foco/teclado, tópicos com conteúdo,
  tópico e composer permanecem pendentes; não houve criação de fixtures no
  Runtime. Ver
  `docs/TEST-MATRIX.md`. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.51 — excluir o acervo Growroom histórico do fluxo de denúncias

- A permissão `report aculta forum content` é concedida a `email_confirmed`;
  `HistoricForumFlagAccessHooks` limita as ações a comentários hospedados em
  tópicos públicos atuais e tópicos desse bundle. As permissões genéricas
  `flag`/`unflag` foram retiradas, pois o Flag combina hook e permissões com OR.
- A fila do `aculta_user` inclui somente conteúdo do Fórum público atual e
  exclui autoria histórica. O hook `flagging_presave` bloqueia gravações diretas
  no acervo, inclusive por administradores; comentários de Revista/LMS/Wiki não
  entram na fila do Fórum.
- `ForumHistoricModerationQueueHooks` também exclui comentários e tópicos com
  autoria histórica das abas pendente/rejeitada da View administrativa,
  falhando fechado se os campos/relacionamento não estiverem disponíveis. As
  permissões existentes para mover tópicos e comentários foram preservadas.
- Forum Unit direcionado: 14 testes/58 assertions PASS; suítes completas Forum
  68/440 e User 55/189 PASS (uma depreciação PHPUnit preexistente no User).
  PHPCS Drupal, Portal 11+ (971), fronteira (1402), mapa (152), pacotes (25) e
  YAML/lint/diff-check/papéis/hierarquia PASS; evidências em `docs/TEST-MATRIX.md`.
  No Runtime de
  teste, Config Sync parcial atualizou somente `user.role.email_confirmed`, o
  cache rebuild descobriu a permissão/hook e Fórum/principal responderam HTTP
  200; smoke autenticado/Flag end-to-end segue pendente. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.50 — desinstalar Forum contrib no Runtime de teste — 2026-10-20

- Config Sync remove as entidades/configurações exclusivas do módulo contrib,
  ajusta o bloco para a View ACULTA e mantém `history` direto no Composer.
  Removido também o Pathauto duplicado de Magazine que dependia de `node.type.forum`.
- No Runtime `aculta_runtime`, o uninstall oficial removeu `forum`, seus bundles,
  tabelas e configurações. As seis instâncias vazias de `field_discussao` foram
  removidas pelo Field API após confirmar zero linhas e revisões. `composer remove`
  retirou somente `drupal/forum`; `drupal/history` foi mantido.
- Cache rebuild PASS; Fórum e Revista HTTP 200; Conta anônima HTTP 302 esperado.
  Conteúdo antigo continua zerado. Config Sync integral não foi importado devido
  a drift preexistente; importação dirigida e smoke autenticado seguem pendentes.
  RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.49 — remover fóruns restritos por Taxonomy — 2026-10-20

- A política de visibilidade por realm do Forum contrib foi removida do User,
  junto de permissões, subscriber 404 e campo de nível na taxonomia antiga.
  Growroom420 permanece público; discussões internas LMS usam Group.
- Atualizada a documentação central de papéis e avisos, preservando a decisão
  histórica como substituída. Config Sync ainda não importado no Runtime.
  User Unit 52/175, Portal 970, fronteira 1376 e mapa 151 PASS. Runtime `toca`:
  cache rebuild e raízes Fórum/Conta/Revista HTTP 200; bundles contribuído ainda
  instalados, as duas configs de `field_visibilidade` e permissões antigas
  continuam ativas até limpeza dirigida/uninstall. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.48 — migrar guards de conteúdo histórico para bundles ACULTA — 2026-10-20

- `aculta_user` não reconhece bundles Drupal Forum; congela apenas tópicos
  `aculta_forum_topic` com `field_autor_legado` e suas discussões Comment.
  Removidos defaults antigos `field_discussao`; `ContentPurposeResolver` e
  `AccountTermsHooks` deixaram de consumir estruturas contrib.
- User Unit 61/192; gates Portal 970, fronteira 1380 e mapa 151 PASS. Sem
  atualização persistente do Runtime. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.47 — remover preprocess da listagem Forum contrib — 2026-10-20

- O hook Messenger `preprocess_forums` foi removido: a listagem atual usa Views
  Core e não compartilha o render array do módulo contrib. Os avisos do Fórum
  ACULTA continuam nos serviços e eventos próprios.
- Verificação de referências, lint e PHPCS PASS; Runtime sem alteração.
  RUNTIME STATUS: DEFERRED.
- `MIGRATION-REMOVAL.md` corrigido para distinguir o inventário histórico de
  2026-10-19 da limpeza executada e das remoções de consumidor em dev.15/56.

## 0.1.0-dev.46 — retirar dependência editorial do Forum contrib — 2026-10-20

- `aculta_magazine` não depende mais de `forum:forum`: removidos lookup e
  renderização de publicação como abertura de tópico, além do campo antigo
  `field_discussao` de displays/Config Sync. Conversão de tipo preserva os
  Comments compartilhados da publicação.
- Magazine Unit 7/31 e gates Portal, fronteira e mapa PASS. Runtime de teste:
  cache rebuild, resolução do serviço e raiz Magazine HTTP 200; Forum contrib
  ainda habilitado. Import de configuração e smoke autenticado DEFERRED.
  RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.45 — limpeza do conteúdo antigo no Runtime de teste — 2026-10-20

- Por decisão F-D33, foram removidos pela Entity API os 30 Nodes `forum`, os
  oito termos do vocabulário `forums` e os 12 vínculos `field_discussao` nas
  publicações; não havia Comments `comment_forum`. Os 12 termos da árvore nova
  `aculta_forum_categories` e o conteúdo editorial foram preservados.
- Backup integral `aculta_runtime` em diretório privado, modo 0600,
  `gzip -t` PASS; SHA-256 e contagens pré/pós documentadas em
  `docs/MIGRATION-REMOVAL.md`. Verificações encontraram zero tópicos,
  comentários, termos, referências, revisões, aliases, flaggings e linhas nas
  tabelas do Forum contrib. Restore isolado não executado.
- O validador de uninstall não reporta blockers; o dry-run de dependências
  ainda aponta alterações em campo da Revista e papéis. Consumers/config/tema,
  uninstall oficial e remoção Composer seguem pendentes. RUNTIME STATUS:
  PARTIAL.

## Validação adicional — ciclo de moderação das contagens — 2026-10-20

- `ForumTopicMoverKernelTest` agora exercita o agregado oficial do Comment Core
  durante aprovação, despublicação e exclusão; comentários pendentes não entram
  no total público. Kernel MariaDB: 3 testes/69 assertions PASS, com cinco
  depreciações da dependência Domain. `PublicTopicCommentCountViewKernelTest`
  executa um Views Core real com três tópicos, garantindo ordenação por total
  publicado e exclusão de resposta pendente (1/11 PASS). Unit Forum: 54/382 PASS; PHPCS, gates
  Drupal 11+ (970 verificações), fronteira (1392 verificações) e `git diff
  --check` PASS. A View continua ordenando pelo agregado Core configurado.
- Nenhuma entidade ou configuração do Runtime foi alterada. A ordenação entre
  vários tópicos com respostas positivas ainda requer teste de Views e
  inspeção autenticada no navegador.

## 0.1.0-dev.44 — navegação direta por categoria — 2026-10-20

- A View `aculta_forum_categories` passa o TID como token excluído e renderiza
  cada nome como link à raiz pública com filtro `categoria`. Config/install,
  Config Sync e teste de equivalência permanecem alinhados.
- Pesquisa de módulos contrib registrada em `docs/CONTRIB-PORT-RESEARCH.md`:
  CTools e Flag são APIs existentes a reutilizar; Comment Mover, Quote,
  Achievements e referências históricas foram avaliados como inspiração/port
  condicionado. Sem novas dependências ou código externo copiado.
- O ACULTA420 implementa o SDC `content/forum-category` e os presenters de
  Views para exibir hierarquia e descrição em grade responsiva; sem consulta de
  entidade ou construção de URL no tema.
- Testes Unit do Fórum 54/382 PASS; PHPCS do teste, Drupal 11+ (970), fronteira
  (1388), mapa (154), SDC (531), design (2103), Foundation (362) e `git diff
  --check` PASS. HTTP `/categorias` respondeu 200 e exibiu 12 categorias.
- Import parcial com diff/backup aplicou somente `views.view.aculta_forum_categories`
  no Runtime de teste; nenhum conteúdo foi gravado. A primeira resposta revelou
  token literal `[tid]`; substituído pelo placeholder Views Core `{{ tid }}`.
  Confirmar render final dos links e filtro ainda pendente; inspeção visual
  DEFERRED. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.43 — modernizar metadados PHPUnit — 2026-10-20

- Os testes `DiscussionModerationHooksTest` e `ForumReadAccessTest` agora usam
  atributos PHPUnit para grupo/cobertura, eliminando metadados em docblocks.
- Suíte Unit completa do Fórum: 54 testes / 379 asserções PASS, sem
  depreciações PHPUnit. PHPCS PASS. Runtime: DEFERRED (somente testes).

## 0.1.0-dev.42 — navegação de descoberta na raiz do Growroom — 2026-10-20

- A raiz pública `/` agora mostra a navegação existente de Categorias, Novas,
  Sem resposta e Mais comentadas, além do link ativo para a própria raiz. A aba
  Não lidos continua sujeita ao access de cada tarefa local. Nenhum link aponta
  para `/discussoes`, que permanece fechado como caminho interno.
- A implementação reutiliza o `LocalTaskManager` Core e os títulos/rotas já
  declarados, preserva access/cache dos tabs e delega a apresentação ao tema.
- Unit 3/19 PASS; PHPCS, gate Drupal 11, fronteira módulo/tema, mapa e diff
  limpo PASS. Runtime: cache rebuild e HTTP 200 na raiz e nas quatro abas;
  `/discussoes` 404. HTML confirma aba raiz ativa com `aria-current="page"`.
  Firefox havia confirmado a composição visual das tabs antes do ajuste
  semântico. Nenhum conteúdo foi criado ou alterado.
- Pesquisa contrib complementada: `forum_access` 2.0.2/ACL e código GPL
  inspecionados; sem dependência instalada ou código copiado. Decisão e
  proveniência em `docs/PESQUISA.md`.

## 0.1.0-dev.41 — auditar categoria editorial legada — 2026-10-19

- Consultas Entity API somente leitura no Runtime `aculta_runtime` compararam
  `field_categoria_revista` nos termos antigos com `field_editorial_category`
  direto nas 12 publicações vinculadas (8 `article`, 4 `blog`): 12/12 coincidem.
  O mapeamento auxiliar não precisa ser migrado; categorias diretas e conteúdo
  editorial ficam preservados. A submissão/exibição real dos Comments ainda
  precisa de browser antes de remover nós/vínculos antigos.
- Nenhum dado, configuração ou extensão foi alterado. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.40 — analisar efeitos de uninstall do Forum contrib — 2026-10-19

- Inspecionados código, validator e teste funcional de `drupal/forum` 1.1.3.
  O uninstall requer zero Nodes `forum` e zero termos do vocabulário; chama
  `FieldPurger` em lote de até 10 para os field storages `taxonomy_forums`,
  `comment_forum` e `forum_container`, mas não apaga Nodes/termos/Comments.
  Registrados o impacto compartilhado de `taxonomy_forums` e a dependência
  editorial dos termos `forums`.
- Somente leitura/documentação: nenhuma entidade, config ou extensão foi
  alterada. Runtime de teste permanece com 30 Nodes `forum`, 8 termos e zero
  Comments `comment_forum`; uninstall e remoção Composer continuam DEFERRED.
- Fontes: `web/modules/contrib/forum/src/ForumUninstallValidator.php`,
  `forum.install`, `tests/src/Functional/ForumUninstallTest.php`; [registro do
  Core](https://www.drupal.org/node/2404451) e [API do validator](https://api.drupal.org/api/drupal/core%21modules%21forum%21src%21ForumUninstallValidator.php/function/ForumUninstallValidator%3A%3Avalidate/11.x).

## 0.1.0-dev.39 — remover apresentação do Forum contrib — 2026-10-19

- Removido de `aculta_user` `ForumTopicPresentationHooks`, que só servia Nodes
  `forum` e Comments `comment_forum`; a autoria e apresentação do novo bundle
  seguem no contexto ACULTA. Nenhum Node ou Comment foi alterado.
- Unit `aculta_user` 60/175 PASS; gates Portal (970), fronteira (1382) e mapa
  (154) PASS. Runtime `toca`: zero `comment_forum`, cache rebuild, classe ausente,
  Node antigo renderizado e HTTP 200 após redirect. Sem dados para testar
  contagem de Comments legados; PHP 8.4.26 abaixo de 8.5. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.38 — aposentar pastas editoriais do Forum antigo — 2026-10-19

- `aculta_magazine` 0.1.0-dev.14 agora mantém os updates 10001/10002 como no-op,
  remove `ForumSectionResolver` e deixa de instalar seu campo exclusivo na
  taxonomy `forums`. O update de aliases 10003 permanece para a próxima etapa;
  referências e Nodes antigos não foram alterados.
- Unit Magazine 8/32, PHPCS e gates Drupal 11+ (970), fronteira (1383), mapa
  (154) PASS. Runtime schema já era 10003; sem `updb`/config import. Cache
  rebuild, resolver ausente, lookup histórico presente, render de publicação e
  HTTP 200 após redirect PASS. PHP CLI 8.4.26 abaixo da baseline 8.5; comentários
  diretos reais/browser continuam pendentes. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.37 — preparar restore point da retirada — 2026-10-19

- Criado dump pré-limpeza do Runtime de teste `aculta_runtime`, privado fora do
  repositório, modo 0600; validação `gzip -t` PASS. URI explícita
  `https://revista420.aculta.toca.net.br`, checksum e limites registrados em
  `docs/MIGRATION-REMOVAL.md`. Não foi restaurado nem usado para alterar dados.
- O arquivo é um checkpoint de banco, não inclui arquivos públicos/privados.
  Nenhuma entidade ou configuração foi removida; uninstall permanece bloqueado
  até migrar consumers e fechar plano de recuperação de arquivos. RUNTIME STATUS:
  PARTIAL.

## 0.1.0-dev.36 — retirar hook residual do Magazine — 2026-10-19

- O `aculta_magazine` removeu o único hook restante ligado a Comments
  `comment_forum`; a suíte Unit confirmou 7 testes/30 assertions e o Runtime
  confirmou zero Comments antigos, render full de publicação e HTTP 200 após
  redirect. Os 12 vínculos e Nodes antigos permanecem intocados.
- Gatilhos de criação/sincronização já haviam sido removidos; agora não há
  hook de visualização/editorial executável nesse módulo que dependa do Forum
  contrib. Permanecem os serviços de leitura/conversão de vínculo até a migração
  dos dados. RUNTIME STATUS: PARTIAL; Comments diretos reais/browser pendentes.

## 0.1.0-dev.35 — remover consumidor editorial do Forum legado — 2026-10-19

- Retirado de `aculta_user` o fallback de resumo que consultava Comments do
  Drupal Forum. Os 12 Nodes de Revista ainda têm vínculos antigos, mas agora o
  conteúdo editorial usa Comment direto; a leitura histórica permanece apenas
  nos serviços de compatibilidade do `aculta_magazine` até a limpeza aprovada.
- Nenhum dado foi alterado no Runtime. `drush cr`, renderização full de uma
  publicação e GET à URL canônica passaram; o request `/node/90` redirecionou
  para a URL editorial e terminou em HTTP 200. Unit `aculta_user` 60/175,
  gate Drupal 11+ (970), mapa módulo/tema (154), diff-check e PHPCS PASS.
  Sem Comments reais para validar formulário/exibição de conteúdo; PHP 8.4.26
  abaixo da baseline 8.5. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.34 — revisão de fonte contrib e thread ramificada — 2026-10-19

- Inspecionado diretamente o tarball `comment_mover` 2.0.0-alpha1: a operação
  reatribui as entidades Comment existentes, mas a recursão de thread mantém a
  base original e a suíte publicada não cobre descendentes em múltiplos ramos.
  Nenhum código contrib foi copiado e o módulo não foi instalado.
- Kernel `ForumCommentMoverKernelTest` ganhou cenário com dois ramos e netos,
  verificando caminhos `thread`, parents e contagens após mover a árvore.
  MariaDB dedicado: 13 testes/272 assertions PASS; 19 depreciações de
  Core/Domain/Flag/Search API contrib.
- Consulta somente leitura ao Runtime `toca` confirmou 8 vínculos antigos em
  `article` e 4 em `blog`, todos com campo de discussão novo presente. Os Nodes,
  Comments, Composer, configuração ativa e conteúdo do Runtime não foram
  alterados. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.33 — mover comentário com subárvore — 2026-10-19

- Implementados serviço transacional, formulário de confirmação, rota e
  operação contextual para mover um comentário público e suas respostas
  descendentes entre tópicos abertos do Growroom. Revalida permissão, acesso,
  Domain e imutabilidade do acervo histórico na submissão.
- Preserva Comment IDs, UUIDs, autor, texto, datas e estado de moderação; atualiza
  parent/thread, recalcula estatísticas dos dois Nodes e invalida caches. Lock
  segue o padrão de chave Comment Core e relê o máximo da thread sob lock.
- Kernel MariaDB dedicado: 12 testes/246 assertions PASS (árvore em três níveis,
  estado pendente, legado bloqueado, estatísticas, flag disabled, rollback,
  formulário de resposta/remoção desatualizado e retenção do lock até o callback
  da transação raiz, verificada por uma segunda conexão MariaDB independente).
  Form API pelo `FormBuilder::submitForm()` confirma que a movimentação exige
  destino e confirmação explícita; com a caixa ausente, o envio é rejeitado e o
  tópico hospedeiro permanece. Persistem 19 depreciações de Core/Domain/Flag/
  Search API contrib. Runtime de teste `toca`: `drush status` confirmou Drupal
  11.4.10, URI main explícita e banco `aculta_runtime`; consulta somente leitura
  confirmou rota, permissão e serviço do mover, com `comment_move_enabled: null`
  (ausente e portanto fechado). GET ao formulário com Comment inexistente retornou
  404. Não há Comments publicados desse bundle no Runtime para testar rota com
  entidade real; nenhuma configuração do mover foi alterada. Rota com entidade
  existente, CSRF, concorrência entre processos e efeitos integrados permanecem
  DEFERRED; PHP 8.4.26 abaixo da baseline 8.5; RUNTIME STATUS: PARTIAL.
- Limpeza de homologação autorizada no Runtime: removidos pela Entity API os
  oito Nodes `[TESTE]` (IDs 383–390) do bundle novo, após confirmar títulos,
  bundle e owner uid 5, sem Comments, Flags ou referências editoriais. Os 12
  termos da árvore inicial foram preservados; resultado: 0 tópicos e 0 Comments
  novos. Os oito aliases Pathauto foram removidos e cada URL anterior retorna
  HTTP 404. O módulo Forum antigo, seus 30 Nodes, 8 termos `forums`, 12 vínculos
  da Revista e 0 Comments legados permaneceram intactos. Configuração não foi
  alterada. Inventário e evidência em `docs/TEST-FIXTURES.md`.
- Forum Unit: 51 testes/360 assertions PASS; PHPCS nos arquivos novos e gates
  Drupal 11+ (970), fronteira (1388), mapa (154), slugs (387) e hierarquia de
  papéis PASS. PHP CLI 8.4.26 (abaixo da baseline 8.5).
- Pesquisa contrib expandida: Forum Access (matriz de permissões), Comment Notify
  (preferências de acompanhamento), Achievements/Gamification (marcos e limites)
  e VBO (ações em lote) são referências; nenhum código/dependência foi copiado.
  Comment Moderation AI foi rejeitado como dependência por processamento externo
  e ausência de cobertura pela política de security advisories. Ver
  `docs/PESQUISA.md`.
- `ForumDiscussionWriteGuard` serializa comentário e movimento por tópico, com
  locks adquiridos em ordem estável, validação de pai/host após lock e liberação
  ao final da transação raiz. A fixture Kernel substitui `NullLockBackend` pelo
  `DatabaseLockBackend`; uma conexão MariaDB adicional não adquire a chave até a
  transação raiz encerrar e consegue adquiri-la após o commit. Isso prova o lock
  persistente entre conexões, mas não simula dois processos/requisições HTTP.
  Efeitos externos seguem pendentes. O mover reserva primeiro o lock de thread
  Core, respeitando `Comment::preSave()` para evitar inversão.
- Revisão dos hooks laterais: `aculta_game` Unit `ForumParticipationPolicyTest`
  (8/35) confirma que update de comentário já publicado não repete o gatilho de
  conquista. Messenger `ForumAvisoHooksTest` (2/6) confirma que mover comentário
  publicado não gera novo aviso e que aprovação ainda registra; entrega e fila
  real permanecem sem teste integrado. Runtime `toca`: cache rebuild e descoberta
  do serviço OOP PASS; nenhuma ação ou aviso foi submetido.
- Kernel também instala Flag 5.1.1 e Search API 1.41.0: flagging de usuário em
  Comment permanece ligada ao mesmo CID após a movimentação; um Comment indexado
  é marcado para reindexação após o update, mantendo o mesmo item ID. O índice
  atual `aculta_conteudo` só indexa Nodes dos bundles institucionais definidos e
  não inclui Forum nem Comments; o novo teste cria um índice Kernel isolado e
  não altera essa política de busca.
- Campo File privado no Comment também foi exercitado: o CID manteve a referência
  do anexo e seu `file_usage` após a mudança de tópico. O fixture usa arquivo de
  texto; ingestão e acesso de mídia ACULTA permanecem responsabilidade do
  `aculta_image` e não foram validados por esse teste.

## Pesquisa adicional de módulos contrib — 2026-10-19 (documentação)

- `PESQUISA.md` registra APIs e decisões para os contrib instalados Flag,
  CTools, History, Search API, Group e Message stack, com versões do lockfile.
- `comment_mover` foi avaliado como referência para ferramentas de movimento:
  não está instalado; a release disponível é alpha para Drupal 9/10. O tarball
  foi inspecionado: ele reatribui Comments in-place, mas a recursão não passa a
  thread recém-calculada ao mover descendentes profundos e não valida o contrato
  de acesso/estatísticas da ACULTA. Reutilizar só requisitos de
  seleção/subárvore/split; código e dependência não foram incorporados.
- `MOVE-TOOLS.md` registra origem, compatibilidade e limites. O bloqueio inicial
  foi substituído pelo protótipo independente em dev.33; validações de integrações
  ainda mantêm a ação fora do Runtime. Sem código contrib copiado ou dependência
  nova.
- A auditoria ampliada dos módulos contrib instalados e candidatos está em
  `MOVE-TOOLS.md`. CTools, Flag, History, Search API e Group permanecem donos de
  suas funções; padrões de Advanced Forum, Comment Mover e Achievements foram
  avaliados sem copiar código nem criar storage concorrente.

## 0.1.0-dev.32 — autoria da última atividade pública — 2026-10-19

- View pública usa `last_comment_uid` de `comment_entity_statistics` como campo
  oculto; hook OOP prepara o cartão via `aculta_user.author_card_builder`, sem
  expor o nome de login. Install e Config Sync devem permanecer equivalentes.
- Forum Unit 49/354 PASS; PHPCS novo, Portal 970, boundary 1374, mapa 154,
  SDC 516, design 2089 e fixtures 157 PASS. Runtime: campo ativo adicionado de
  forma pontual após backup; View com 8 linhas e HTTP 200. `basic_html` ausente
  nas fixtures causou avisos na renderização. Validação posterior no Runtime
  `toca`: a View pública listou uma fixture com 2 respostas publicadas; uma
  resposta pendente não alterou `comment_count` nem a contagem da View. Firefox/
  X11 mostrou o cartão e “2 respostas” na raiz do Fórum em 1024 px, sem avisos
  de formato de texto. As três respostas e o tópico temporários foram removidos;
  estado final 0 tópicos e 0 Comments, flags de avisos preservadas. Ordenação
  Runtime entre múltiplos tópicos e invalidação visual seguem pendentes.
  RUNTIME STATUS: PARTIAL.
- Auditoria do Core 11.4.10 confirmou que a gravação de Comment recalcula
  somente estatísticas do host atual, invalida só seu cache e calcula thread/lock
  automaticamente apenas em Comment novo. Essa análise guiou o protótipo
  transacional de dev.33. Nenhum SQL direto foi adotado.

## 0.1.0-dev.31 — seguir e salvar tópicos ACULTA — 2026-10-19

- As Flags individuais `seguir_topico` e `salvar_topico` passam a aceitar o
  bundle `aculta_forum_topic`, preservando provisoriamente o bundle legado
  `forum` durante a migração. As mesmas definições ficam nos defaults do módulo
  e em Config Sync; `flag` é dependência executável explícita do Forum.
- O tipo atual da Flag é por usuário, portanto os dados não são globais e os
  registros existentes não são removidos. A implementação continua no Flag; o
  Fórum não cria storage próprio nem automatiza avisos de Messenger ao seguir.
- Testes Forum Unit: 48 testes / 350 assertions PASS (3 deprecações PHPUnit
  preexistentes); PHPCS da configuração testada e gates Portal 11+
  (970), fronteira (1370) e mapa (154) PASS. PHP CLI 8.4.26.
- Runtime `toca`, Core 11.4.10: backup privado dos dois configs antes da escrita;
  somente `bundles` foi estendido e `config:status` não indicou drift nos dois
  IDs após a alteração. Cache rebuild PASS. uid 5 montou os dois links contra
  tópico público atual sem persistir flagging. HTTP/browser e mudança real de
  estado por navegador pendentes. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.30 — link para a primeira resposta não lida — 2026-10-19

- A View autenticada `/discussoes/nao-lidas` adiciona o campo `node_new_comments`
  do módulo History. Exibe apenas contagens positivas e liga à primeira
  resposta nova, incluindo página/âncora calculadas pelo Comment Core. O campo
  não foi adicionado às listas públicas; a View mantém cache `none` por conta.
- Unit verifica plugin, link, ocultação de zeros e isolamento na View privada.
  Forum Unit: 47 testes / 340 assertions PASS (3 deprecações antigas de
  PHPUnit), incluindo configuração do campo (teste direcionado: 15/189);
  Kernel MariaDB: 4 testes / 75 assertions PASS (5 deprecações do Domain
  contrib; cobre access/History adjacente, não renderização do campo novo);
  PHPCS e gates Drupal 11+ (970), fronteira (1368), mapa (154), slugs (387)
  PASS.
- Runtime `toca`: import parcial somente da View com backup recuperável,
  `config:status` sem diferenças, plugin DI resolvido e 8 itens em renderização
  autenticada via Drush; anônimo HTTP 403. Nenhuma resposta aprovada existe para
  verificar contagem positiva/fragmento. O render em Drush também reportou o
  erro preexistente `Missing text format: basic_html` nas fixtures. HTTP/browser
  autenticado e inspeção visual pendentes. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.29 — marcar discussões públicas como lidas — 2026-10-19

- Adicionada ação confirmada `/discussoes/marcar-tudo-como-lido`, disponível
  apenas para contas autenticadas no purpose público do Fórum. A seleção fixa
  `aculta_forum_topic`, publicação, domínio canônico e Node access; o Core Batch
  processa até 200 IDs por requisição e usa a API pública `history_write()` do
  pacote `drupal/history` instalado. Não há storage próprio e cada conta só
  grava o próprio histórico.
- O processamento revalida conta e purpose em cada lote e repete a consulta com
  `accessCheck(TRUE)`. A ação não altera Nodes/Comments nem inclui LMS, Wiki,
  Revista ou conteúdo histórico.
- Adicionado teste Unit para acesso autenticado e isolamento por purpose.
  PHPUnit Unit do Fórum: 47 testes / 335 assertions PASS (3 deprecações antigas
  do PHPUnit). Kernel MariaDB `aculta_simpletest`: 4 testes / 75 assertions
  PASS; 5 deprecações pertencem ao Domain contrib 3.0.1. PHPCS, lint, gates
  Portal 11+ (970), fronteira (1368), mapa (154) e slugs (387 rotas) PASS.
  Runtime `toca`: cache rebuild, container, construção do formulário e rota
  confirmados; acesso anônimo 403. A ação não foi submetida para evitar gravar
  histórico de uma conta real. RUNTIME STATUS: PARTIAL.

# Implementação não marcada — valida agregado de respostas Core — 2026-10-19

- O Kernel do movimento de categoria agora valida o agregado real de
  `comment_entity_statistics`: uma resposta publicada conta, uma pendente não,
  e a contagem sobrevive à mudança de categoria do mesmo Node.
- O teste habilita explicitamente `comment.maintain_entity_statistics`, como a
  instalação oficial do Core faz. Kernel MariaDB dedicado: 2 testes / 48
  assertions PASS; 5 deprecações preexistentes do contrib Domain. Nenhum dado
  do Runtime foi alterado. RUNTIME STATUS: PARTIAL; ordenação visual da View
  com comentários aprovados continua pendente.

# Implementação não marcada — descoberta de tópicos públicos — 2026-10-19

- A View pública ganhou displays de tópicos novos, sem resposta e mais
  comentados, com navegação local. Reaproveitam o escopo da lista pública;
  respostas vêm do agregado Core `comment_entity_statistics`, sem contadores
  paralelos.
- Runtime `toca`: três rotas HTTP 200; cada uma consulta 8 tópicos, e a lista
  sem resposta retorna os 8 porque não existem comentários públicos no Fórum.
  Caso positivo de respostas/ordenação e inspeção visual seguem pendentes.
- O CAPTCHA global abortava o exposed GET form nos novos displays. A exceção
  OOP foi limitada aos filtros read-only do índice público; PHPUnit passa e os
  resultados HTTP agora apresentam os tópicos sem o desafio inválido.
- `drush cr` também revelou a injeção do hook mover tópico por tipo sem alias
  para o service ID. O parâmetro recebeu `#[Autowire(service: ...)]`; cache
  rebuild agora PASS. O teste Unit verifica esse wiring explicitamente.
- Runtime importou somente `views.view.aculta_forum_public_topics`, após cópia
  recuperável da config ativa; nenhum conteúdo foi modificado. RUNTIME STATUS:
  PARTIAL (sem sessão autenticada/browser e sem contagem positiva).
- PHPUnit do módulo: 46 testes / 332 assertions PASS; PHPCS e gates Drupal
  11+, fronteira, mapa, slugs, hierarquia e `.htaccess` PASS. Uma depreciação
  preexistente de metadata PHPUnit permanece em `DiscussionModerationHooksTest`.

# Implementação não marcada — lista personalizada de não lidos — 2026-10-19

- Criada View `/discussoes/nao-lidas` com filtros de History por usuário e
  indicador de conteúdo novo/atualizado que inclui comentários. Exige o papel
  autenticado, mantém filtros de publicação, bundle, Domain, categoria e
  acesso à entidade; usa cache `none` para não compartilhar resultado pessoal.
- `drupal/history:^1.0` promovido de dependência transitiva de `drupal/forum`
  a requisito direto para preservar a função após a futura remoção do Forum.
  Essa versão não está coberta pela política de avisos de segurança do
  Drupal.org; o risco e acompanhamento de releases estão documentados.
- Runtime de teste: configuração criada sem alterar conteúdo; uid 5 autorizado
  pelo plugin e query retorna 7 itens; usuário anônimo recebe 403. HTTP com
  sessão autenticada e inspeção visual pendentes. Nenhum registro de leitura
  foi gravado; o índice público continua com seus caminhos/filtros atuais.
- PHPUnit Forum: 14 testes / 157 assertions; gates Portal 11+, fronteira, mapa,
  slugs, Composer validate e PHPCS PASS. RUNTIME STATUS: PARTIAL.

# Implementação não marcada — hierarquia no índice de categorias — 2026-10-19

- A View `/categorias` agora configura a relação Taxonomy Core `Parent` e uma
  coluna `Categoria principal`; termos de topo continuam visíveis com texto
  fallback, e subcategorias indicam seu pai.
- Runtime `toca` confirmado em Drupal 11.4.10, `aculta_runtime` e URI de teste
  explícita: apenas a View foi sincronizada, após cópia recuperável da config
  ativa. A consulta retornou os 12 termos, com as 8 subcategorias ligadas aos
  pais corretos; `/categorias` respondeu HTTP 200 e apresentou os nomes e
  fallbacks. `config:status` não reporta diferença desta View. Nenhum termo,
  Node ou Comment foi alterado. Inspeção visual autenticada permanece pendente.
- Configuração de instalação e Config Sync equivalentes; PHPUnit direcionado:
  13 testes / 141 assertions PASS; gates Portal 11+, fronteira e mapa PASS.
- O roadmap registra que Core History está deprecated desde Drupal 11.4. A
  versão contrib instalada é transitiva via `drupal/forum`; o roadmap registra
  esse acoplamento e a avaliação necessária antes da remoção do pacote.

# Implementação não marcada — mover tópico público entre categorias — 2026-10-19

- Ação individual em `/painel-administrativo/conteudo/forum/{node}/mover-categoria`,
  com permissão específica, confirmação explícita e nova revisão no mesmo Node.
- O serviço revalida permissão, access no purpose `forum`, Domain canônico, tipo
  e destino Taxonomy; recusa tópicos com `field_autor_legado`. O log registra
  apenas IDs de origem/destino e uid.
- Unit `ForumTopicMoverTest`: 4 testes/31 assertions PASS. Kernel
  `ForumTopicMoverKernelTest`: 2 testes/44 assertions PASS em MariaDB
  `aculta_simpletest`, cobrindo preservação de NID/UUID, nova revisão, alias e
  Comment/CID, e bloqueio histórico mesmo com permissão de administração.
- Os testes Kernel reportam cinco depreciações do contrib Domain 3.0.1. Gates
  Drupal 11+, fronteira, mapa, hierarquia, PHPCS e YAML PASS. Functional e
  Runtime ainda não executados. Sem incremento de versão e sem
  alteração persistente no Runtime.

# `0.1.0-dev.28` — índice público de categorias — 2026-10-19

## Implementação não marcada — contagem na listagem pública

- A View pública prepara o teaser Node existente e `comment_count` do Core,
  dentro do bundle fixo `aculta_forum_topic`, que possui um único campo de
  discussão. A View usa o agregado Core e não executa COUNT por linha; ordena
  por última atividade com `last_updated`. A versão segue sem marcação até
  confirmação de contagens positivas e revisão visual. RUNTIME STATUS: PARTIAL.

### Ordenação pela última atividade

- Mantida a saída Core `comment_ces_last_updated`, com sort local
  `aculta_forum_last_activity` usando a mesma fórmula de data. O sort do Core
  falha em Drupal 11.4.10 ao atribuir o retorno `void` de `Sql::addOrderBy()` a
  propriedade `string` (Drupal core issue
  [#3519952](https://www.drupal.org/project/drupal/issues/3519952)). A primeira
  configuração foi revertida imediatamente. O hook Views data adiciona um
  sort virtual limitado ao Fórum; a View voltou a executar com o adaptador
  ACULTA: SQL contém `GREATEST(node.changed, last_comment_timestamp) DESC`,
  com 8 linhas e a coluna de data do Core renderizada. Sem resposta/comentário
  positivo persistido; Runtime PARTIAL e versão não marcada.

- Criada a página `/categorias` com View Core baseada em Taxonomy, limitada ao
  vocabulário público do ACULTA Forum e com cache por idioma/permissão. A raiz
  do Fórum e a página de categorias têm links de navegação entre si; as linhas
  mostram nome e descrição sem encaminhar às páginas genéricas de Taxonomy.
- PHPUnit `ForumTopicDisplayConfigTest`: 13 testes / 123 assertions PASS; gates
  Portal 11+, fronteira, mapa e slugs PASS. Runtime de teste Drupal 11.4.10:
  importação parcial das duas Views, HTTP 200 na raiz e `/categorias`, 12 termos
  renderizados e zero diferenças de Config Sync para as duas Views. Nenhum
  conteúdo foi alterado. RUNTIME STATUS: PASS para o escopo HTTP; inspeção
  visual e hierarquia continuam pendentes.

# `0.1.0-dev.27` — estados de comentários em abas do Fórum — 2026-10-19

- A aba Fórum apresenta três subabas de estado: publicados, rejeitados e
  aguardando moderação. O Drupal fornece até dois níveis de tarefas locais;
  por isso os estados são filhos diretos da aba Fórum.
- `field_aculta_rejected` distingue a rejeição definitiva de uma pendência
  (`status=0`) e pode ser visto/editado somente por quem tem `administer comments`.
  O marcador fica oculto no display público.
- PHPUnit do Fórum: 37 testes / 220 assertions PASS. Gate Portal, fronteira
  módulo/tema, mapa módulo/tema, slugs e `git diff --check` PASS.
- Runtime `toca` (Drupal 11.4.10, `aculta_runtime`): as três Views retornaram
  somente o estado de Fórum correspondente; a View editorial retornou somente
  comentários da publicação. Render API público apresentou somente os
  publicados em cada contexto. Seis Comments `[QA 20261019]` temporários foram
  removidos e a contagem final nos dois hosts de teste voltou a zero.
  RUNTIME STATUS: PARTIAL; inspeção HTTP autenticada no navegador pendente.

## `0.1.0-dev.26` — Fórum como aba isolada de Conteúdo — 2026-10-19

- A View administrativa de tópicos registra Fórum como aba irmã das demais em
  `/painel-administrativo/conteudo`. “Comentários e moderação” é subaba da
  seção do Fórum. As rotas permanecem iguais; links estáticos duplicados foram
  removidos.
- `views.view.content` recebe exclusão fixa do bundle `aculta_forum_topic`, que
  prevalece sobre o filtro exposto de tipo.
- PHPUnit de configuração valida os pais das abas e a exclusão fixa. Runtime
  confirma as abas e executou a listagem geral com o tipo de Fórum exposto sem
  retornar tópicos. Há 8 Nodes `[TESTE]` e 0 Comments; HTTP autenticado pendente.
  RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.25` — Fórum separado e correção do 500 — 2026-10-19

- Fórum aparece como aba em Conteúdo, no mesmo nível de Conteúdo, Comentários e
  Galerias. “Comentários e moderação” é subaba da seção Fórum. Os caminhos
  permanecem estáveis.
- A coluna Categoria usa o field handler válido `field_aculta_categories`,
  confirmado em Views Data instalado. O handler anterior apontava para
  `field_aculta_categories_target_id`, que só oferece filter/argument/sort e
  carregava como `Broken`, causando `TypeError: array + null` durante render.
- O filtro de categoria da listagem pública permanece inalterado; a View
  administrativa mantém somente os filtros que ela já oferecia.
- RUNTIME STATUS: PARTIAL. Runtime de teste atualizado após backup privado;
  links do Fórum e comentários usam o menu `content`, as rotas passam no access
  para uid 5 e ambas as Views renderizam via Render API. Request HTTP autenticada
  segue pendente; nenhum conteúdo foi alterado.

## `0.1.0-dev.24` — navegação no menu Content como Galerias — 2026-10-19

- Links de Fórum e comentários passam a usar `navigation.content` e o menu
  `content`, ao lado de Galerias. Views não criam abas no overview genérico.

## `0.1.0-dev.23` — links de navegação administrativa — 2026-10-19

- O menu Conteúdo expõe a listagem de tópicos; comentários ficam aninhados em
  Fórum. Os destinos usam as rotas de Views e mantêm suas permissões de acesso.
- RUNTIME STATUS: PARTIAL. Fórum e Comentários do Fórum aparecem na árvore
  administrativa para uid 5, e as rotas passam a checagem de acesso. A sessão
  HTTP temporária não foi autenticada porque o ULI retornou 404.

## `0.1.0-dev.21` — paginação do índice público — 2026-10-19

- A listagem pública usa pager Core completo com 20 tópicos por página, controles
  de navegação em português e sem seletor livre de quantidade. Os filtros de
  domínio, publicação, bundle, categoria e acesso permanecem fixos.
- A configuração de instalação e Config Sync são iguais; validação Unit e HTTP
  do Runtime de teste confirmam pager e visibilidade. Nenhum Node ou Comment foi
  criado/editado/excluído nesta entrega. Navegação com mais de 20 itens e inspeção visual seguem pendentes. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.20` — categorias e listagem pública com filtros — 2026-10-19

- Corrigido o padrão Pathauto para selecionar apenas `aculta_forum_topic`; a
  configuração de instalação e Config Sync agora coincidem. O campo base
  `path` já existe em Node no Runtime e Pathauto o complementa, então não foi
  necessário habilitar globalmente a geração Pathauto para todos os Nodes.
- Pesquisa de organização moderna recomenda poucas categorias com propósito e
  descrição explícitos, subcategorias apenas quando ajudam a navegação e tags
  para dimensões transversais. A hierarquia inicial de homologação usa quatro
  categorias e oito subcategorias; não altera o vocabulário legado `forums`.
- Foram criados conteúdos marcados `[TESTE]` somente no Runtime Homelab para
  validar as listagens e aliases. IDs, URLs e plano de remoção estão em
  `docs/TEST-FIXTURES.md`. Nenhum comentário foi criado; entidades legadas
  permaneceram intactas.
- A montagem da View pública abortava porque o CAPTCHA global validava o
  formulário GET de filtros durante a renderização. Hook OOP remove o desafio
  somente dessa View/página de leitura; CAPTCHA permanece nas ações de escrita.
- Os HTTP smoke tests verificaram raiz, filtro e oito aliases (200), com os
  oito títulos aparecendo na listagem após o Runtime carregar o hook. RUNTIME
  STATUS: PASS para listagem/URLs; navegador visual DEFERRED.
- Pesquisa consultada: orientação do Discourse para categorias e subcategorias
  (descrição e escopo claros, estrutura sem excesso) e API Drupal Taxonomy
  (termos nativos com relação hierárquica). Ver `docs/CATEGORIES-AND-RESEARCH.md`.
- Unit `ForumTopicDisplayConfigTest` + `PublicForumExposedFormHooksTest`:
  11 testes/64 assertions PASS; PHPCS Drupal/DrupalPractice PASS. Gates Portal
  Drupal 11+ (969), module/theme boundary (1334), map (154), public slugs (387)
  e `git diff --check` PASS.

## `0.1.0-dev.18` — contrato de respostas abertas no tópico — 2026-10-19

- O teste de configuração agora fixa que tópicos públicos usam `aculta_discussion_post`, começam com respostas abertas, exibem o formulário no campo Core e restringem envio anônimo; Config Sync deve permanecer equivalente à configuração de instalação.
- Runtime `toca` confirmado em Drupal 11.4.10: campo ativo com status `Open`, papel `email_confirmed` e uid 5 têm `post comments`; continuam 0 tópicos e 0 comentários. Formulário de resposta em nó persistido não foi renderizado para preservar o acervo vazio. RUNTIME STATUS: PARTIAL.
- PHPUnit `ForumTopicDisplayConfigTest`: 8 testes / 55 assertions — PASS; PHPCS Drupal/DrupalPractice — PASS.

## `0.1.0-dev.17` — acesso de participante ao formulário de tópico — 2026-10-19

- Config Sync do papel `email_confirmed` mantém o estado ativo de Wiki/Fórum legado e demais permissões; acrescenta somente a dependência do bundle ACULTA e criar/editar/excluir o próprio tópico. Nenhuma permissão `any` foi concedida.
- Unit `ForumTopicDisplayConfigTest`: 7 testes/47 assertions PASS. Runtime `toca`: importação parcial apenas do papel; uid 5 confirmado tem acesso à rota e formulário em memória renderiza title/body/categoria. Formulário não enviado; zero tópico/Comment novo. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.16` — mensagem para listagem pública vazia — 2026-10-19

- A View pública informa em português quando ainda não há tópicos publicados. A configuração de instalação e Config Sync mantêm o mesmo texto e plugin Core `text_custom`; listagem, filtro de domínio, bundle, publicação e acesso não foram alterados.
- Unit `ForumTopicDisplayConfigTest`: 6 testes/42 assertions PASS, incluindo equivalência Sync/install e mensagem. Runtime `toca`: importação parcial só da View; após cache rebuild, raiz 200 mostra a mensagem e o filtro; `/discussoes` permanece 404. Zero tópicos e Comments compartilhados. RUNTIME STATUS: PARTIAL; navegador sem tópico persistido pendente.

## `0.1.0-dev.15` — filtro hierárquico de categorias na View pública — 2026-10-19

- A View `aculta_forum_public_topics` ganhou filtro exposto de categoria pelo plugin Taxonomy nativo `taxonomy_index_tid`, limitado ao vocabulário `aculta_forum_categories` e com hierarquia. Filtros de tipo, domínio, publicação e acesso permanecem fixos; cache inclui query args.
- Config Sync e config de instalação são equivalentes; Unit de View/config: 6 testes/39 assertions PASS. Runtime `toca`: config isolada aplicada, Core carregou `TaxonomyIndexTid`, raiz e query `?categoria=999999` responderam 200, seletor “Categoria” renderizado. O vocabulário está vazio (0 termos), então seleção real depende da taxonomia editorial. Nenhum termo ou conteúdo foi criado. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.14` — ação contextual de criação de tópico — 2026-10-19

- A View pública acrescenta `/criar-topico` somente quando Entity Create Access permite a ação. O hook OOP `views_pre_render` usa o resultado cacheável da rota tanto na ação quanto na View e aplica o token visual existente `button-primary`.
- PHPUnit Unit do Fórum: 11 testes/51 assertions PASS, incluindo allow/deny, cache e filtros por View/display/purpose; PHPCS PASS; gates Drupal 11+ (969), fronteira (1328), mapa (154), tokens de UI (285), packages (22), slugs (387) PASS. Runtime `toca`: raiz HTTP 200, sem ação para anônimo, `/criar-topico` 403. A apresentação integral e usuário autorizado permanecem pendentes; nenhuma submissão ou conteúdo persistido foi feito. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.13` — formulário de criação em slug público — 2026-10-19

- Nova rota `/criar-topico` para o formulário Core `node.default` do bundle `aculta_forum_topic`. Entity Create Access continua sendo a autorização, complementada pela regra de participação existente; a rota pertence ao purpose `forum`.
- Teste de contrato cobre caminho, form handler, bundle e requisito de acesso. Runtime `toca`: rota resolvida com purpose `forum`; anônimo 403, host principal 404; conta de teste sem permissão negada. Nenhum formulário enviado nem Node criado. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.12` — raiz pública usa a listagem ACULTA Forum — 2026-10-19

- Page display da View `aculta_forum_public_topics` em `/discussoes`; o processador de purpose serve essa rota na raiz `/` do Growroom420. Filtros fixos de bundle, publicação, domínio, access e cache continuam herdados do display padrão.
- Rotas da View são classificadas como purpose `forum`; o caminho interno novo `/discussoes` e o caminho legado `/forum` continuam 404 diretos. Configuração de instalação e Config Sync equivalentes.
- Runtime de teste: importação parcial da View e cache rebuild; Drush confirmou rota `/discussoes`, purpose `forum`, zero itens novos. HTTP confirmou raiz 200 e caminhos diretos `/discussoes` e `/forum` 404; título correto no HTML. Nenhum conteúdo criado/movido. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.11` — listagem pública por domínio — 2026-10-19

- View nova `aculta_forum_public_topics`, em Config Sync e config de instalação, consulta somente `aculta_forum_topic` publicado, filtra `field_domain_source` pelo domínio ativo, mantém SQL rewrite/`node_access` e cache contexts de grants. O bloco CTools Views deixa ajustáveis apenas paginação e quantidade.
- `field_domain_source` passa a integrar o bundle. Hook OOP define o domínio canônico do purpose `forum` quando ausente; criação é negada fora desse purpose ou se sua configuração não existir. Fonte explicitamente definida é preservada.
- A View antiga `aculta_forum_topics` e seu bloco existente seguem intactos para não interromper o acervo contrib antes da fase segura dev.5. A View nova ainda não foi colocada em página ou bloco.
- Testes Unit de View/configuração e hook: 21 testes/102 assertions PASS; PHPCS e PHP lint PASS. Runtime: View executada sem resultados porque não há tópicos novos; hook OOP resolvido/registrado e atribuiu o domínio em Node em memória. Nenhuma rota, colocação, tópico ou comentário foi alterado.

## `0.1.0-dev.10` — formulário e apresentação base do tópico público — 2026-10-19

- Configuração Core de formulário do Node `aculta_forum_topic`: título, corpo e categoria; status de comentário e referência de autoria histórica não ficam editáveis no formulário.
- Displays Core `full` e `teaser`: página integral apresenta o Comment compartilhado; listagens podem usar teaser de corpo/categoria sem expor thread ou autor histórico.
- Configs instaláveis e Config Sync permanecem idênticas. Teste Unit: 15 testes/67 assertions PASS. No servidor `toca`, Drupal 11.4.10, Drush URI explícita `https://growroom420.aculta.toca.net.br` e banco `aculta_runtime`, foram importados somente os três displays por `config:import --partial`; `config:status` confirmou os três sem diferenças. Smoke Form API criou Node apenas em memória, expôs título/corpo/categoria e o Core acrescentou `revision_log` no grupo de revisão. Contagens após a operação: 0 tópicos novos, 0 comentários compartilhados e 30 Nodes legados preservados. Nenhum conteúdo foi criado, alterado ou apagado. RUNTIME STATUS: PARTIAL; navegador e envio persistido pendentes.

## `0.1.0-dev.9` — exibição do Comment compartilhado — 2026-10-19

- Define o display Core `comment.aculta_discussion_post.default` para renderizar o corpo e as ações do Comment; `field_autor_legado` permanece oculto. O `aculta_user` injeta o cartão neutral de autoria.
- RUNTIME STATUS: DEFERRED; configuração ainda não foi importada.

## `0.1.0-dev.8` — importação Growroom somente leitura — 2026-10-19

- A autoria `field_autor_legado` pode ser ligada aos Nodes `aculta_forum_topic` e Comments `aculta_discussion_post`; o `aculta_forum` depende do `aculta_user`, dono do LegacyAuthor.
- Tópicos `aculta_forum_topic` e Comments `aculta_discussion_post` históricos são imutáveis em Entity Access; a thread fecha no presave e a fila administrativa bloqueia moderação em lote. O marcador não classifica os bundles `forum`/`comment_forum` do Drupal Forum contribuído como importação Growroom; a retirada contrib segue em plano próprio.
- Foram adicionados os campos de referência em Config Sync e config de instalação. Runtime e importação permanecem DEFERRED; nenhum conteúdo foi criado, importado ou alterado.

## `0.1.0-dev.7` — moderação de tópico por editor confirmado — 2026-10-19

- `aculta_user` concede acesso a `content_editor` para editar/excluir tópicos novos de outros autores somente quando `EmailConfirmationPolicy` confirma a conta. A própria criação e gestão do tópico continuam sob as permissões nativas do bundle.
- Conta não confirmada recebe negação explícita; operações sobre o próprio tópico permanecem no fluxo normal do Node; o bypass administrativo e a proteção dos conteúdos históricos permanecem.
- Teste Unit dedicado: 4 testes/18 assertions PASS. Runtime de teste sem alteração persistente; RUNTIME STATUS: DEFERRED.
- As futuras listagens configuráveis por colocação devem usar `ctools_views` para pager/quantidade, mantendo filtros de acesso, bundle, publicação e domínio fixos na View. A integração CTools complementa Views e Entity Access; não substitui autorização.

## `0.1.0-dev.6` — permissões de tópico público em Config Sync — 2026-10-19

- `core.extension` inclui `aculta_forum`; Config Sync declara as permissões nativas do Node `aculta_forum_topic` para criação e gestão do próprio tópico por `email_confirmed`. Permissões amplas de moderação por `content_editor` ficam pendentes de access check que respeite confirmação de e-mail.
- O Runtime de teste foi consultado em modo somente leitura: `aculta_forum` está habilitado e `core.extension:module.aculta_forum` é `0`. A configuração local ainda difere de diversos itens do banco; nenhuma importação foi executada. Não foram criados termos/tópicos nem removidos dados legados. RUNTIME STATUS: DEFERRED para aplicar permissões.

# `0.1.0-dev.5` — auditoria para retirada do Forum contrib — 2026-10-19

- Confirmado em leitura somente no Runtime de teste: 30 nodes `forum` (12
  publicados e vinculados a publicações, 1 publicado independente e 17 não
  publicados), 0 comments `comment_forum`, 8 termos `forums` e 2 flaggings no
  total do site (ambos de `site_alert`). O validador Drupal Core impede
  uninstall enquanto houver qualquer node `forum` ou termo `forums`.
- Confirmados consumidores ativos em Magazine e User; a sincronização editorial
  e os vínculos `field_discussao` permanecem. Desinstalar agora deixaria 12
  publicações sem o novo campo de comentários; instalar esse fluxo vazio e
  desligar a sincronização antiga precedem a exclusão dos tópicos-casca. O
  conteúdo antigo de discussão não será migrado para o sistema novo.
- Backups encontrados (17 e 18/10) são anteriores e de outras tarefas; nenhum
  foi validado como restore point desta retirada. Nenhuma exclusão, uninstall,
  remoção Composer, update ou alteração Runtime executada.
- Dev5 permanece em andamento. Próximo gate: completar os contratos dos
  consumidores, disponibilizar novos fluxos vazios e validar restore point atual
  antes de qualquer operação destrutiva. RUNTIME STATUS: PARTIAL (auditoria
  somente leitura no teste).
- Decisão do responsável: o novo Fórum e os contextos de discussão começam sem
  importação de tópicos ou comentários antigos. Na limpeza autorizada serão
  apagados os tópicos e comentários legados, preservando publicações, usuários
  e demais entidades; os 12 tópicos de sincronização da Revista só saem depois
  que os comentários diretos da publicação estiverem prontos e a sincronização
  antiga desligada. A contagem registrada é zero comentários `comment_forum`.
- A API agora registra `PublicationContextProvider`: bundles `article`/`blog`
  somente com `field_aculta_discussion`, access de leitura do host e campo,
  escrita via Comment aberto + `post comments`, moderação via `administer comments`,
  cache por permissões e ID estável. Providers não têm field instances na Revista
  ainda.
- Fields da Revista ainda não foram ativados. A política compartilhada de
  moderação agora cobre o bundle novo em Fórum, Revista, Wiki e qualquer
  contexto Node que use o campo compartilhado, preservando o controle legado; LMS continua
  excluído. Field instances da Revista e integração de apresentação ainda
  aguardam homologação específica.
- Kernel multi-bundle: 1/18 PASS em `aculta_simpletest`; Unit do Forum: 12/52 PASS.
  Runtime de teste recebeu apenas cache rebuild e smoke de DI: resolver carregou
  dois providers e recusou Article sem o field novo. Sem importação de config,
  field editorial ou conteúdo. RUNTIME STATUS: PARTIAL; integração funcional
  permanece pendente.

# 0.1.0-dev.4 — Context API executável — 2026-10-19

- Criado e habilitado no Runtime de teste o módulo `aculta_forum`, usando somente bundles/campos nativos Node, Comment e Taxonomy, em configuração de instalação e Config Sync.
- Criados os contratos de contexto, objeto imutável cacheável, resolver extensível por serviços tagueados e provider inicial de tópicos públicos, com leitura negada fora do purpose `forum`.
- A integração de publicação, LMS e Wiki permanece para suas fases próprias; nenhum adapter declara acesso ou expõe contexto antes de verificar a entidade hospedeira.
- Registrada a interpretação ambiental: no servidor `toca`, Drush sem `--uri` reportar `http://default` é esperado para o Runtime de teste `aculta_runtime`.
- Validações direcionadas: ver `docs/TEST-MATRIX.md`; Runtime de teste aplicado e smoke test limitado à descoberta do serviço/configuração, zero conteúdo criado. PHP CLI 8.4.26; validação do PHP-FPM e UX fica pendente.

# 0.1.0-dev.3 — Author Pane moderno — 2026-10-19

- `aculta_user` ganhou `AuthorCardBuilder`, avatar mediado pelo `aculta_image`, policy explícita da foto e tema hook neutro cacheável.
- ACULTA420 ganhou o SDC experimental do cartão, com variantes separadas de fórum, comentário, editorial, LMS, Wiki e `author_growroom_historico`; fórum empilhado altera apenas layout. Autoria histórica não ganha ações ou perfil individual.
- Nome conceitual `author_legacy` renomeado para `author_growroom_historico` a pedido do responsável.
- Testes Unit: 43/87; gates Portal 968, fronteira 1272, mapa 153, SDC 514 e fundações de design 2065 PASS. Integração no motor novo, teste visual no browser e Runtime continuam pendentes. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.2 — identidade base de autoria (`aculta_user`) — 2026-10-19

- `aculta_user.author_identity_resolver` compõe o User e o perfil principal com o gerador canônico de perfil; retorna `AuthorIdentity`, payload mínimo cacheável de apelido e link.
- Variantes são identificadores de consumidor, ainda sem payloads ampliados ou renderização específica. `author_legacy` permanece fora do resolver User.
- Três testes unitários, 12 assertions; RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.1 — auditoria e fundação documental — 2026-10-19

- PRs e base verificadas: PR #291 está mesclada e é de pagamentos; PRs antigas de Fórum #287–#289 estão fechadas; a branch desta fase parte de `origin/main` `d4a0d66e`. A PR #296 é a linha separada do Portal `0.4.0-dev.162`.
- Inventário confirmou `drupal/forum` 1.1.3 habilitado e dependências de config/código em `aculta_user`, `aculta_magazine`, `aculta_messenger`, Config Sync e ACULTA420.
- Nova arquitetura documental: Node/Comment/Taxonomy/Views/Search API com quatro contextos; cartões centralizados em `aculta_user`; apresentação exclusiva do ACULTA420.
- Inventário completo de capacidades históricas do Advanced Forum e plano em fases para retirada segura; não houve alteração de runtime, dependência, configuração exportada ou dados.
- RUNTIME STATUS: DEFERRED (fase somente documental; nenhuma operação no ambiente).

## F4 — bloco CTools Views de tópicos recentes — 2026-10-18

- Configuração versionada da View `aculta_forum_topics` e bloco na página
  inicial do purpose `forum`; utiliza teaser do Node, filtros fixos de tipo,
  publicação e domínio atual, e query access do Views (`node_access`).
- CTools permite ajustar quantidade de itens e pager no bloco; sem exposição de
  filtros, ordenação ou AJAX. Listagem nativa do Forum continua responsável por
  categorias e listagens de cada categoria.
- `RUNTIME STATUS: DEFERRED`: Drush aponta `aculta_runtime`, mas URI `http://default`
  não confirma um site de teste permitido para esta configuração.

## F4 — tópico: ações sociais, respostas e limpeza de termos — 2026-10-17

- Termo "General discussion" (instalador do módulo forum, sem tópicos, sem referências) removido no teste. `/forum/39` responde 404.
- Denunciar tópico visível para membros (`flag.flag.sinalizar_topico`, `show_as_field: true`).
- Rótulos dos flags quando marcados: "Seguindo" e "Salvo" (antes "Remover: …").
- Campo `comment_forum` em modo encadeado (`default_mode: 1`): respostas diretas aparecem sob a resposta a que respondem.
- Abertura da Revista sem o título repetido (`aculta_magazine` 0.1.0-dev.6).
- Pendentes da F4: conversa vinculada ao histórico (depende da F3); modo escuro; contagem de respostas no cabeçalho.

## Documentação de estado e referência de classes — 2026-10-17

- README: estado do código por parte do fórum (F2, F5.1, F5.2, F9), com o documento de cada módulo.
- Roadmap: resumo por fase em 2026-10-17. A próxima fase é a F4.
- Referência gerada de classes e hooks em `aculta_user/docs/CLASSES-E-HOOKS.md` e `aculta_messenger/docs/CLASSES-E-HOOKS.md` (23 e 30 hooks OOP; cada classe com a primeira frase do docblock).

## F5.2 — política de avisos de resposta (documentação) — 2026-10-16

- Política em `docs/portal/AVISOS-E-NOTIFICACOES.md` e decisão F-D21: sistema existente, aviso obrigatório no site, e-mail opt-in agrupado por tópico, contadores.
- Dúvidas abertas: janela de envio do e-mail e chave de agrupamento dos avisos gerais.

## F9 — associado como marcador puro (F-D19) — 2026-10-16

- Associado não é papel. Marcador em tabela própria, sem efeito de acesso. Nível "associado" dos fóruns removido.
- Visibilidade de fóruns restritos: somente e-mail confirmado (e moderador com e-mail confirmado).

## F9 — moderação e papéis múltiplos — 2026-10-16

- `content_editor` exige e-mail confirmado para restritos e moderação. Moderação (ocultar, despublicar, excluir) de tópicos e comentários, exceto conteúdo histórico. Administrador com bypass total (F-D16).
- Papéis múltiplos: `associado` é papel extra. Regras em `docs/portal/PAPEIS-PARTICIPACAO.md`.
- Lacuna: confirmação manual de e-mail pelo administrador não existe.

## F9 — hierarquia de papéis e 404 para restritos — 2026-10-16

- Hierarquia documentada (`docs/portal/PAPEIS-PARTICIPACAO.md`); `content_editor` é o moderador do Fórum e vê os níveis restritos.
- Visitante e bots recebem 404 em contêiner e tópico restritos (F-D15).
- Dúvidas abertas no documento de papéis, seção 4.

## F9 parcial — fóruns restritos por nível — 2026-10-16

- Papel `associado` versionado (concessão manual). Campo `field_visibilidade` no contêiner: público, e-mail confirmado, associado.
- Visitante não vê contêiner, tópico nem listagem de fórum restrito. Associado vê; e-mail confirmado sem associado não vê o nível associado.
- Verificado em 2026-10-16: processador `content_access` ativo no índice; tópicos de fórum fora do índice de busca (tipo `forum` não selecionado); restrito fora do sitemap; comentário de tópico restrito invisível para anônimo. Bots recebem 403 nos restritos.
- Pendentes: nível de curso (F8); política para `content_editor`; exclusão no sitemap precisa ser mantida se o tipo `forum` entrar no sitemap.

## F5.1 — denúncias e participação com e-mail confirmado — 2026-10-16

- Sinalização de tópico e comentário pelo Flag (`sinalizar_topico`, `sinalizar_comentario`), uma por conta e por conteúdo.
- Fila de moderação em `/painel-administrativo/forum/denuncias`, restrita a `moderar denuncias do forum`.
- Participação no Fórum (criar, editar o próprio conteúdo, comentar, denunciar) exige `email_confirmed` (F-D13).
- Pendências: sinalização de comentário não testada no teste (não há comentário de fórum); papel `authenticated` com divergência anterior; avisos de resposta e busca ficam para F5.2 e F5.3.

# Changelog — ACULTA Fórum

## `0.1.0-dev.61` — manter leitura em lote no purpose Fórum — 2026-10-20

- O endpoint progressivo Batch do Core permanece no purpose `main`, como exige
  a política administrativa central. `ForumReadMarker::markBatch()` agora
  envolve a consulta e a escrita de History em `runInPurpose('forum')`, depois
  de conferir que a conta autenticada corresponde à conta capturada na
  confirmação. Node access e History por usuário continuam obrigatórios.
- Cancelamento e retorno do formulário usam a raiz pública absoluta do purpose
  Fórum. O path `/discussoes` é a rota interna que serve a raiz pelo path
  processor; acesso direto a ele responde 404 conforme a política do Portal.
- Motivo: rotas Core `_admin_route`, incluindo `/batch`, são centralizadas em
  `main`; tentar forçar o endpoint progressivo ao domínio do Fórum é revertido
  pela política de domínio. O callback anterior falhava fechado no purpose
  principal e reportava zero itens mesmo com uma discussão elegível.
- PHPUnit Unit 78/481 e Kernel 1 teste/12 assertions confirmaram a execução
  iniciada com purpose `main`, a consulta/escrita sob `forum` e a restauração
  para `main`; cinco deprecações vieram do Domain contrib instalado. PHPCS e gates Drupal 11+,
  fronteira e mapa passaram. X11 autenticado no Runtime confirmou a mensagem
  “Foram marcadas 1 discussões como lidas” e retorno à raiz pública. Fixture
  NID 417, alias e History específico foram limpos; Runtime encerrou com 0
  tópicos, 0 Comments compartilhados, 24 History (baseline) e as 2 flaggings
  preexistentes preservadas. `RUNTIME STATUS: PASS` neste fluxo. CLI PHP é
  8.4.26, abaixo da baseline PHP 8.5 do projeto.

## F2 estrutura pronta para a importação — 2026-10-16

- Campos `field_nome_historico` e `field_forum_origem_id` no perfil participante, aplicados no Runtime de teste.
- Serviço `aculta_user.legacy_accounts`: cria ou atualiza a autoria pela origem, sem duplicar; conta bloqueada
  com papel `usuarios_legados_forum`; sem e-mail, apelido ou conquista.
- Conta legada não tem página pública individual (404 para anônimo) e não recebe avatar do sorteio.
- Memorial `/u/lendashistoricas` com busca, paginação e estado vazio.
- Pendências que dependem da importação ou de aprovação: lista de autores comprovados e importação das contas;
  arte do avatar especial; texto aprovado do memorial; link de autoria nos tópicos e comentários; verificação
  de colisões de apelido em produção.

## F2 estrutural — memorial e papel legado — 2026-10-15

- Papel `usuarios_legados_forum` criado no Runtime de teste e em `config/sync`.
- Apelido `lendashistoricas` reservado; rota `/u/lendashistoricas` respondendo 200 com placeholder.
- Pendências da F2 (não executadas): lista de autores comprovados e Users bloqueados; arte do
  avatar especial; texto aprovado do memorial; nomes individuais; verificação de colisões de
  apelido em produção.
- Gates do Portal passam. Nada aplicado em produção.

## F1 — inventário e domínio no servidor de testes — 2026-10-15

- Inventário completo do prefixo CDX `growroom.net/board/` até
  `2025-07-19 09:36:40 UTC`: 271.283 capturas e 177.673 URLs distintas. O JSON
  fica no armazenamento privado do Homelab; resumo, caminho e checksum estão em
  `docs/RECONSTRUCAO.md`.
- Cloudflare: criado A direto, sem proxy, para
  `growroom420.aculta.toca.net.br` → `45.191.204.31`; o registro antigo
  `forum.aculta.toca.net.br` foi mantido para responder 404 sem redirect.
- Homelab: VirtualHost e certificado Let's Encrypt do novo host ativos;
  `trusted_host_patterns`, Domain e alias do Runtime atualizados sem trocar IDs
  nem purpose. HTTPS `/forum` respondeu 200, canonical e noindex conferidos.
- Sessão do teste confirmada no container: `.aculta.toca.net.br`, `SameSite=Lax`
  e `Secure`.
- Host antigo documentado como 404 sem redirect. Deployer `domains` PASS (9/9),
  `robots --env=test` PASS e `validate-environment-addresses.php` PASS (1.759
  arquivos).
- O helper externo `/home/piradopirata/scripts/forum-setup.sh`, que ainda
  provisionava o host antigo, foi aposentado por stub seguro; cópia original
  preservada no backup privado da F1.
- Sitemap do Fórum responde 200 e o exclui do índice conforme a política
  instalada. `sitemap --env=test` permanece FAIL por 404 de LINKS, fora desta
  fase.
- Produção não foi alterada: não havia DNS do host antigo, e o TLS direto da
  origem Hostinger para `growroom420.aculta.org` falhou. O novo DNS de produção
  aguarda o cadastro e a validação do hostname na origem.
- `RUNTIME STATUS: PASS (Homelab/teste); produção não alterada.` Nenhum `updb`,
  `cim`, import/export ou conteúdo foi executado.

## 2026-10-15 — ajuste de identidade aprovado

- Marca futura: Growroom420; prefixo público `growroom420.aculta`.
- Purpose interno `forum` e fontes históricas Growroom preservados.
- Decisão documental; nenhum domínio provisionado nesta alteração.

## 2026-10-15 — abertura documental do projeto

- Plano aprovado: Growroom, memória histórica, conversas atuais vinculadas e
  integração com as capacidades existentes do Portal.
- Registradas a mudança futura de prefixo para `growroom`, a autoria individual
  dos legados e a página coletiva `/u/lendashistoricas`.
- Documentados pesquisa, arquitetura, reconstrução, roadmap e gates futuros.
- Entrega exclusivamente documental, sem versão de módulo instalável ou Runtime.
- [Evidência de revisão e validação estática](../../../../../../docs/portal/FORUM-GAME-PESQUISA-VALIDACAO.md).
## `0.1.0-dev.22` — separação administrativa por contexto — 2026-10-19

- Incluída a listagem administrativa de tópicos em
  `/painel-administrativo/conteudo/forum`, isolada do inventário geral.
- Criada a fila `/painel-administrativo/conteudo/forum/comentarios`, filtrada
  por Comment type, field, entidade Node e bundle público; inclui comentários
  aguardando aprovação e requer `administer comments`.
- Documentada a política de separação para Magazine, LMS e Wiki. Os adapters
  futuros deverão fornecer listagens próprias com acesso contextual; não foi
  criada fila LMS/Wiki prematura.
- Revista mantém conteúdo e comentários juntos em
  `/painel-administrativo/conteudo/revista`; não há rota separada de comentários.
- Nenhuma discussão LMS ou Wiki passa pela fila pública do Fórum. Nenhum
  conteúdo foi criado, alterado ou removido nesta etapa.
- Runtime status: a View de tópicos já foi importada no Runtime de teste;
  aplicação da nova View de comentários e smoke de acesso ficam pendentes.

## `0.1.0-dev.28` — índice público de categorias — 2026-10-19

- Criada a página `/categorias` com View Core baseada em Taxonomy, limitada ao
  vocabulário público do ACULTA Forum e com cache por idioma/permissão.
- O link “Categorias” integra a navegação local do índice público de discussões.
- A View usa renderização padrão da entidade Taxonomy. Hierarquia visual,
  contagens de tópicos e ordenação por atividade ficam para as subfases próprias.
## `0.1.0-dev.86` — busca pública contextual — 2026-10-20

- Índice Search API e View separados pesquisam somente tópicos `aculta_forum_topic`.
  O processador `content_access`, status da entidade, acesso da View e indexação
  imediata reduzem exposição por estado/permissão; o subscriber aplica filtro
  pelo Domain canônico e falha fechado fora do purpose Fórum.
- A página `/pesquisar` é classificada como purpose `forum`; mapa de rotas e
  fronteira módulo-tema atualizados. Configuração de instalação e Config Sync
  devem permanecer idênticas.
- Unit Search API/contexto e rota: 5 testes / 17 asserções PASS. Suíte Unit
  completa do Forum: 87 testes / 527 asserções PASS. PHPCS e PHP lint dos novos
  arquivos PASS; equivalência instalação/Config Sync, Drupal 11+ (975),
  fronteira (1467), mapa (152), slugs (378) e caminhos PASS.
- Runtime `toca`: Search API 1.41 e backend `aculta_database` ativos; importação
  parcial criou apenas índice e View, cache rebuild PASS, `/pesquisar` HTTP 200
  e busca vazia HTTP 200. Zero tópicos e Comments antes da alteração; nenhum
  conteúdo foi criado. Firefox/X11 confirmou renderização da navegação/form em
  1024 px, mas mostrou mensagem de validação Turnstile; não houve submissão, e a
  causa não foi isolada. Mobile DEFERRED; RUNTIME STATUS: PARTIAL. Evidência
  privada e limites em `TEST-MATRIX.md`.

## `0.1.0-dev.87` — filtro de categoria na busca pública — 2026-10-20

- A busca usa o ID inteiro do termo já indexado e troca o campo numérico da
  View por um select com rótulos hierárquicos atuais. As opções passam por
  Entity Access e carregam tags/contextos de cache; não há TIDs hardcoded.
- CAPTCHA foi removido apenas das Views públicas de listagem e da busca GET
  somente leitura. Formulários que gravam conteúdo mantêm a política global.
- Search API instalado não reconhece `entity:taxonomy_term`; a tentativa de
  configuração foi revertida no Runtime com cópia privada e importação parcial
  dos dois objetos anteriores. `/pesquisar` voltou a HTTP 200, sem mudança de
  conteúdo. A configuração final mantém `field_aculta_categories:target_id`
  (`integer`).
- `PublicForumExposedFormHooksTest` e `ForumSearchConfigTest` passaram; Unit
  completa do Forum: 87 testes / 553 assertions. Drupal 11+ (979), fronteira
  (1477), mapa (152), slugs (378) e caminhos/textos PASS.
- Runtime `toca`: importada somente a View após backup privado; cache rebuild,
  busca sem filtro e filtro `categoria=76` HTTP 200. 12 categorias acessíveis,
  nenhum CAPTCHA no HTML de busca GET, 0 tópicos e 0 Comments. X11 desktop
  1024 px confirmou a correção visual; Responsive Design Mode 320×480 parcial.
  RUNTIME STATUS: PARTIAL, pois o índice está vazio e não há resultados reais
  para comprovar o filtro.

## `0.1.0-dev.88` — provar o filtro de categoria no Search API DB — 2026-10-20

- Kernel cria vocabulário, termos raiz/filho, tópicos e índice Search API DB
  isolado. O campo indexado `field_aculta_categories:target_id` é inteiro; a
  consulta da categoria filha retorna apenas o tópico associado, sem misturar
  pai ou categoria paralela.
- `ForumCategorySearchKernelTest`: 1 teste / 17 assertions PASS no MariaDB
  dedicado `aculta_simpletest`; duas depreciações são do Search API instalado.
  Runtime não alterado e permanece vazio. RUNTIME STATUS: DEFERRED para esta
  prova de integração; homologação pública segue PARTIAL porque o índice do
  Runtime não tem itens.

## `0.1.0-dev.89` — orientar buscas sem resultados — 2026-10-20

- A View `/pesquisar` mostra um estado vazio em português com orientação para
  ajustar os termos ou abrir `/categorias`. A apresentação visual usa o tema;
  não há consulta nem regra de negócio no ACULTA420.
- `ForumSearchConfigTest`: 1 teste / 18 assertions PASS; instalação e Sync
  idênticas. Runtime `toca`: backup privado, importação parcial de somente uma
  View, rebuild e busca GET com categoria HTTP 200; mensagem e link presentes,
  sem CAPTCHA, 0 tópicos/Comments. Firefox/X11 1024 px confirma o painel visual.
  Nenhum conteúdo alterado. RUNTIME STATUS: PASS para o estado vazio; PARTIAL
  para busca pública, pois o índice continua sem itens publicados.
- Unit completa do Forum: 87 testes / 556 assertions PASS. Gates Drupal 11+
  (979), fronteira (1478), mapa (152), slugs (378), caminhos/textos, SDC (535),
  Design Foundations (2100/fixtures 157) e Shell Contract (11) PASS. Foundation
  reconhece a versão .119, mas reprova por divergência da allowlist Twig devido
  a `email-wrap.html.twig` da linha paralela Mailer; nenhum arquivo Mailer/gate
  de allowlist foi alterado.
## `0.1.0-dev.103` — inspeção visual X11 de categorias — 2026-10-20

- Nova suíte GET-only `tests/browser/aculta-forum/forum-index-visual.mjs`
  verifica `/` e `/categorias` no Firefox/X11, 390/768/1024 CSS px, claro e
  escuro, título/região principal, cards e ausência de tópicos/legado. Resultado
  no Runtime de teste: 26/26 PASS; EntityQuery confirma 0 tópicos públicos,
  0 comentários e 0 Nodes antigos do bundle `forum`.
- Capturas privadas 0600 e `results.json` em
  `/home/aculta-system-data/private/aculta-forum-index-dev102/visual/captures/`.
  Nenhum conteúdo ou configuração foi alterado. RUNTIME STATUS: PASS para as
  listagens vazias; comparação de listas/tópicos com atividade permanece
  DEFERRED para manter o Growroom limpo.

## `0.1.0-dev.102` — instalar config padrão LMS em Kernel — 2026-10-20

- Kernel usa o `ConfigInstaller` do Drupal para aplicar o conjunto de config
  padrão do adapter com os bundles LMS/Group e a storage Comment compartilhada
  preparados; confirma bundle, campo compartilhado, relationship types e
  formatter. O teste não habilita o adapter nem executa `ModuleInstaller`.
- Kernel adapter: 2 testes / 72 assertions PASS, 38 depreciações Core/contrib;
  Unit adapter 12/141 e Forum 87/556 PASS. Runtime DEFERRED.

## `0.1.0-dev.101` — validar coerência da configuração LMS — 2026-10-20

- Unit verifica que os quatro Group roles do Config Sync dependem das
  relationship types do adapter e recebem somente a permission contextual de
  tópico; também protege o mapeamento `grade students` para Educadores de
  curso nas turmas.
- A instalação real do grafo de módulos não foi simulada: o adapter continua
  desabilitado e nenhuma configuração foi importada. Runtime DEFERRED.

## `0.1.0-dev.100` — provar cacheabilidade do acesso LMS — 2026-10-20

- Kernel verifica que os contextos de leitura/escrita/moderação carregam as
  variações por usuário e permissões necessárias, preservam a dependência do
  Node e mantêm a autorização contextual do Educador. Não adiciona nova
  política de acesso nem habilita o adapter.
- Kernel adapter: 2 testes / 67 assertions PASS, 38 depreciações Core/contrib;
  Unit adapter 11/123 e Forum 87/556 PASS. Gates Drupal 11+ (980), fronteira
  (1500), mapa (154), PHPCS e YAML PASS. Runtime DEFERRED.

## `0.1.0-dev.99` — conceder acesso Group Node por membership — 2026-10-20

- Config Sync concede leitura de tópico aos Group roles course teacher, course
  member, course creator e class member. Todos dependem das relationship types
  do adapter; visitante anônimo/outsider e papel global isolado não recebem
  essa permissão. Educador de curso herda leitura para turmas via
  `lms_classes.settings`.
- Kernel cria os Group roles a partir dos arquivos Config Sync e confirma
  estudante/Educador, moderação e bloqueio externo: suite 2 testes / 63
  assertions PASS, 38 depreciações Core/contrib. Config Sync não importado e
  adapter desabilitado. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.98` — provar Comment e moderação em turma LMS — 2026-10-20

- Kernel integrado usa Node/Comment e permissões Group reais, persiste uma
  resposta publicada e exerce `LmsDiscussionModerationHooks`: estudante da
  turma pode escrever; somente Educador com `grade students` no curso edita o
  marcador; outsider é negado e campo fechado bloqueia escrita.
- A configuração Group Node vem do Config Sync do checkout e passa pelo
  `ClassPermissionCalculator`. O adapter não é habilitado e o Runtime não é
  alterado; nenhuma fila administrativa foi criada. Kernel adapter: 2 testes /
  57 assertions PASS, 38 depreciações Core/contrib; Unit adapter 11/123 e Forum
  Unit 87/556 PASS. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.97` — herdar acesso de Educador nas turmas LMS — 2026-10-20

- O Config Sync de `lms_classes.settings` mapeia `grade students` do curso para
  `view group_node:aculta_lms_topic entity` nas turmas.
- Kernel com o `ClassPermissionCalculator` instalado prova Educador de curso
  permitido e nega outsider, inclusive usuário que possui apenas o papel global
  `lms_teacher`: 1 teste / 24 assertions PASS; 37 depreciações Core/contrib.
- O adapter e a configuração não foram ativados/importados no Runtime; nenhum
  tópico ou Comment criado. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.96` — corrigir acesso de participação LMS — 2026-10-20

- Corrige regressão que bloqueava estudantes matriculados e Educadores de turma
  ao combinar duas alternativas que podiam retornar `forbidden` com `orIf()`.
  Membership e `grade students` agora são avaliados como alternativas, com
  negação explícita somente quando nenhuma delas concede acesso. Estudantes
  podem ler/escrever quando matrícula e status permitem; só Educador contextual
  modera.
- Escrita fechada por status do Comment ou ausência de `post comments` termina
  em negação explícita. Novos Unit: 2 testes / 50 assertions PASS.
- Nenhum dado/configuração Runtime foi alterado; adapter segue desabilitado.
  RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.95` — provar Group Node para tópico privado — 2026-10-20

- Kernel no MariaDB dedicado ativa Group 3.3.5 e gnode com bundle/relação de
  tópico. Membro de curso com `view group_node:aculta_lms_topic entity` acessa;
  outsider recebe `forbidden`.
- `LmsGroupNodeAccessKernelTest`: 1 teste / 17 assertions PASS; 17
  depreciações Core/Group/contrib, sem falhas. Nenhum Runtime/configuração real
  foi alterado. RUNTIME STATUS: DEFERRED.

