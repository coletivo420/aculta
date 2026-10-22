## `0.1.0-dev.42` — rodar Unit e Kernel do LMS e registrar o resultado medido — 2026-10-22

- Documento apenas; nenhuma alteração de código, configuração ou Runtime.
- Unit: `tests/src/Unit` — 74 testes, 408 asserções, OK com 8 depreciações do
  PHPUnit.
- Kernel: `tests/src/Kernel/SubmissionUploadPolicyKernelTest` — 5 testes,
  39 asserções, passando. As suítes foram executadas no banco local
  `aculta_simpletest`; o Runtime não foi tocado.
- Nenhuma pendência nova: as tarefas de Wiki que motivaram a rodada ficaram
  bloqueadas em T20–T24 (ver dev.238–dev.234 do `aculta_forum`).

## `0.1.0-dev.41` — escopo mínimo da busca e das notificações privadas LMS — 2026-10-22

- Nada implementado. O ROADMAP vigente deste adapter fica na seção dev.13 de
  `aculta_forum/docs/ROADMAP.md` (o módulo não mantém `docs/` próprio) e agora
  lista o que falta e o menor recorte aceitável.
- Busca: hoje não há índice/datasource LMS, View com argumento de Group, filtro
  de moderação, reindexação por evento nem prova de não vazamento entre
  curso/turma. Mínimo aceito: busca a partir do curso/turma com Group explícito,
  só tópicos válidos e Comments publicados, revalidando Entity Access por conta
  em tempo real e falhando fechado; sem busca global, entre cursos ou pública.
- Notificações: o Comment LMS não emite evento para `aculta_messenger`, não há
  assinatura por tópico, regra contra fila de moderação, idempotência nem
  opt-in. Mínimo aceito: reutilizar Flag/`message_subscribe` e o worker de
  notificação privada, avisar só a primeira resposta publicada a quem segue e
  ainda pode ler, revalidar acesso no envio e ser idempotente por
  (Comment, destinatário); e-mail e digest ficam fora.
- Adiados: busca entre cursos/global, relevância ajustada, digest, push e
  preferências por turma. Qualquer avanço exige Kernel próprio antes de rota
  no Runtime.

## `0.1.0-dev.40` — citação de Comment LMS no Runtime — 2026-10-22

- Verifica no Runtime de teste o contrato de citação de
  `DISCOURSE-EXPERIENCE.md` linha 147 para a Turma. O código alterado é
  `aculta_forum` (dev.232); este adaptador registra a matriz LMS.
- Fixture: Curso 58, Turma 59, estudante membro da Turma e do Curso,
  Educador com `lms_teacher` membro só do Curso, tópico 532 com
  `field_aculta_discussion` em `Open`, Comment publicado 122 e pendente 123.
- Estudante no tópico (200): apenas o Comment publicado exibe `Citar`, com
  `quote=122`. O pendente não exibe link e sua citação responde 403.
- GET da citação: 200 com o corpo prefixado pelo bloco citado e a autoria.
  POST: 303 para `#comment-124`, `pid` 122, `status: 0` pela moderação da
  Turma.
- Forasteiro autenticado: tópico, citação e POST retornam 403 e não recebem o
  texto citado. Educador do Curso fora da Turma: 403 no tópico e na citação.
- Fixture e sessões removidas; Runtime voltou a 0 tópicos LMS e 0 Comments de
  discussão.

## `0.1.0-dev.39` — aprovar/rejeitar LMS pela entity_operations no Runtime — 2026-10-22

- Fecha a verificação de Runtime das dev.109–111 com POST real a partir dos
  links de `hook_entity_operation`. Fixture temporária: Curso, Turma,
  estudante membro, Educador com `lms_teacher` e membership no curso, tópico
  com `field_aculta_discussion` em `Open` e Comment pendente.
- A fila pendente do Educador (200) traz o Comment e os dois links
  `moderacao/approve` e `moderacao/reject` com `destination` para a fila.
- Confirmação de aprovação: GET 200 com o formulário
  `aculta_forum_lms_comment_moderation` e checkbox `confirm` obrigatório.
  POST sem marcar o checkbox retorna 200 com o erro de campo obrigatório e
  não altera o Comment.
- POST de aprovação com `confirm=1`: 303 para a fila pendente, Comment
  publicado (`status: 1`) e `field_aculta_rejected` zerado. O estudante passa a
  ver o texto no tópico; a fila pendente deixa de listá-lo e a de publicados
  passa a listá-lo.
- Revalidação de status: nova confirmação de aprovação retorna 403, a de
  rejeição retorna 200. POST de rejeição com `confirm=1`: 303 para a fila
  pendente, Comment despublicado (`status: 0`) com
  `field_aculta_rejected: 1`. O estudante deixa de ver o texto; a fila de
  rejeitados passa a listá-lo.
- Revalidação após rejeição: nova confirmação de rejeição 403, a de aprovação
  200. Estudante e anônimo recebem 403 nas confirmações e nas filas.
- Fixture, memberships, relationships e sessões removidos; o Runtime voltou a
  1 curso preexistente, 0 tópicos LMS e 0 Comments. Recibos em
  `/home/aculta/private/aculta/forum-lms-t10-20261022/smoke-result.json` e
  `cleanup-result.json`.

## `0.1.0-dev.38` — formulário de Comment LMS do estudante aceito no Runtime — 2026-10-22

- Fecha o DEFERRED de Runtime da dev.98 com envio real (POST) no tópico da
  turma. Fixture temporária: Curso e Turma, estudante membro das duas,
  outsider sem membership e um `aculta_lms_topic` com
  `field_aculta_discussion` em `Open`.
- GET do formulário de resposta (`/comment/reply/node/{nid}/field_aculta_discussion`):
  estudante 200, outsider 403, anônimo 403. O formulário não traz CAPTCHA
  (`skip CAPTCHA` herdado do papel `email_confirmed`).
- POST do estudante: 303 para `/node/530#comment-120`, Comment persistido no
  tópico. POST do outsider: 403, nenhum Comment criado. O estudante não vê o
  texto da tentativa do outsider.
- O Comment nasce despublicado (`status: 0`) e entra na fila de moderação LMS:
  o Core `CommentForm` despublica a gravação quando a conta não tem
  `skip comment approval`, e o papel `email_confirmed` tem `post comments` e
  `skip CAPTCHA`, mas não essa permissão. É o fluxo de moderação pretendido,
  não uma falha do adaptador.
- Fixture, memberships, relationships e sessões removidos; o Runtime voltou a
  1 curso preexistente, 0 tópicos LMS e 0 Comments. Recibo em
  `/home/aculta/private/aculta/forum-lms-t9-20261022/smoke-result.json`.

## `0.1.0-dev.37` — fila LMS nega Educador de outro curso — 2026-10-22

- Smoke GET-only no Runtime de teste repete a fila de moderação da dev.113 com
  um Comment pendente real e acrescenta o caso de negação entre cursos:
  Curso A com Turma A, Curso B, estudante membro, um Educador de cada curso.
- Educador do Curso A recebe 200 no overview (com a Turma A), na fila
  pendente (Comment e os dois links), nas filas publicada/rejeitada (sem o
  pendente), nas confirmações de aprovar/rejeitar e no tópico privado.
- Educador do Curso B recebe 200 no overview mas sem a Turma A; fila, filas
  por estado, confirmações e tópico privado retornam 403. A negação é do
  `grade students` no curso pai (`educatorAccess()`), não do papel global
  `lms_teacher`. Anônimo e estudante também recebem 403.
- Nenhum POST foi submetido. Fixture, memberships e sessões removidos; o
  Runtime voltou a 1 curso preexistente, 0 tópicos LMS e 0 Comments. Recibo em
  `/home/aculta/private/aculta/forum-lms-t8-20261022/smoke-result.json`.

## `0.1.0-dev.36` — moderação LMS propaga aprovação para os demais contextos — 2026-10-22

- `LmsModerationPropagationKernelTest` exercita a `ParticipationRules` e o
  `ParticipationHooks` reais com o schema de `aculta_user_test`, sobre Nodes
  `aculta_forum_topic`, `blog` e `aculta_lms_topic` com o campo compartilhado.
- Comment LMS nunca entra na moderação inicial: o hook o mantém publicado.
  Com um Comment não LMS pendente, o bloqueio temporário de criação de tópico
  está ativo (forbidden); publicar um Comment LMS conta como participação
  aprovada (`hasPublishedDiscussionComment`), zera
  `firstDiscussionCommentPending` e libera o bloqueio (neutral). Um Comment
  não LMS posterior já nasce publicado.
- Comment LMS pendente não libera: continua sem participação publicada, o
  bloqueio segue forbidden e a consulta de pendências não LMS ignora o
  registro. Os resultados de acesso mantêm as tags `comment_list` e
  `config:aculta_user.settings` e os contextos `user`/`user.roles`.
- Teste: 2 testes / 46 asserções PASS, sem depreciações. PHPCS limpo. Nenhuma
  configuração foi alterada e o Runtime não recebeu fixture.

## `0.1.0-dev.35` — campo de discussão fechado bloqueia e libera escrita — 2026-10-22

- `LmsClosedDiscussionFieldKernelTest` prova o ciclo pedido na dev.98 sobre um
  tópico de turma com estudante membro: com `field_aculta_discussion` em
  `CommentingStatus::Open` a escrita é permitida; fechar o campo para
  `Closed` persiste o status e torna a escrita forbidden sem esconder a
  leitura; reabrir para `Open` libera de novo. O outsider não lê nem escreve
  em nenhum dos estados.
- A negação carrega a tag de cache `node:<id>` e o contexto
  `user.permissions`, então o fechamento invalida um resultado "permitido" já
  cacheado. A moderação do estudante continua forbidden nos dois estados.
- A tarefa nomeou `field_aculta_rejected`, mas esse campo é o marcador
  booleano de Comment usado pela moderação: o portão da nova escrita é o
  status Comment do Node em `field_aculta_discussion`. Um segundo teste
  publica um Comment com o marcador ligado e confirma que a escrita segue
  permitida, e que o fechamento real continua negando.
- Teste: 2 testes / 76 asserções PASS; 40 depreciações de Core/contrib
  (preexistentes). PHPCS limpo. Nenhuma configuração foi alterada e o Runtime
  não recebeu fixture.

## `0.1.0-dev.34` — ciclo de vida do módulo LMS — 2026-10-22

- `LmsModuleLifecycleKernelTest` usa o `ModuleInstaller` real para ativar,
  desativar e reativar `aculta_forum_lms`, a partir dos mesmos pré-requisitos
  da dev.33 (tipos de Group do LMS, Comment e campos compartilhados de
  `aculta_forum`, `field_aculta_rejected`) e dos stubs de serviço da fixture
  `aculta_forum_lms_test`.
- Ativação: a lista de configuração iguala a linha de base mais exatamente os
  YAML de `config/install`; cada objeto mantém o `uuid` exportado,
  `aculta_forum_lms.settings` difere do export só pelo `_core` que o
  `ConfigInstaller` grava, e bundle, campo, View e serviço
  `aculta_forum_lms.moderation_manager` existem.
- Desativação: a lista volta à linha de base, cada nome do adaptador fica
  vazio, bundle, campo e as duas relações Group somem, nenhum nome de
  configuração menciona `aculta_lms_topic` ou `aculta_forum_lms`, o serviço
  sai do container, `core.extension` perde o módulo e
  `lms_classes.settings` — de outro módulo — não é reescrito.
- Reativação: os dados de configuração do adaptador são idênticos aos da
  primeira ativação, sem duplicar nem vazar nomes.
- O config opcional de H5P é escrito do export apenas durante a chamada de
  instalação e apagado em `finally`, reproduzindo a dependência que o
  `aculta_lms_h5p` fornece no site real sem passar a ser configuração do
  adaptador.
- O defeito do contrib Group 3.3.5 (`GroupRelationshipType::postDelete()` não
  chama o pai e deixa bundle obsoleto) está documentado em
  `aculta_forum/docs/ROADMAP.md`; a desinstalação do teste remove as relações
  e limpa os caches antes, e todo o resto sai pelo `ModuleInstaller`.
- Teste: 3 testes / 375 asserções PASS; 37 depreciações de Core/contrib
  (ViewsConfigUpdater sobre YAML de módulos contrib, preexistentes). PHPCS
  limpo. Nenhuma configuração foi exportada ou alterada e o Runtime não
  recebeu fixture.

## `0.1.0-dev.33` — papéis e Config Sync do LMS — 2026-10-22

- `LmsRolePermissionsConfigSyncKernelTest` importa os quatro papéis Group
  exportados (`lms_course-teacher`, `lms_course-member`,
  `lms_course-course_creator`, `lms_class-member`) pelo caminho real de
  Config Sync, `GroupRoleStorage::importCreate()`, em vez de recriá-los com
  `GroupRole::create()`. A importação preserva `uuid`, `status`, permissões na
  ordem exportada e as dependências declaradas — recálculo só ocorreria fora
  do modo de sincronização.
- As dependências de módulo declaradas nos papéis
  (`aculta_document_viewer_group` e `aculta_forum_lms`) são validadas pelo
  Group na importação. O Kernel habilita esses módulos, mais `file`, `image` e
  `media` (pré-requisitos do plugin de documento), e a fixture
  `aculta_forum_lms_test` fornece apenas stubs dos serviços de outros módulos
  (`aculta_lms.class_activity_deadline_manager`, `aculta_lms_h5p.course_access`
  e `aculta_forum.discussion_context_resolver`) para o container compilar. Os
  stubs nunca são executados.
- Cada papel lido de volta é idêntico ao YAML exportado, inclusive
  `global_role` e a relação Group (`group.relationship_type.lms_course-*` e
  `group.relationship_type.lms_class-*`). Nenhum papel recebe
  `administer comments`; somente Educador e criador do curso têm
  `grade students`.
- `lms_classes.settings` confere exatamente com o export
  (`create_default_class` falso, `class_name_pattern` vazio, apenas o
  mapeamento `view group_node:aculta_lms_topic entity` → `grade students`) e
  difere do padrão de instalação do contrib apenas nas chaves que o export
  controla.
- O mapeamento é exercitado com Groups reais: Educador do curso recebe a
  permissão de tópico na turma pelo mapeamento, estudante da turma pelo papel
  de turma, e estudante só do curso ou terceiro não a recebem.
- Teste: 5 testes / 258 asserções PASS; 40 depreciações de Core/contrib — as 38
  preexistentes mais duas fontes adicionais vindas dos módulos extras
  habilitados. PHPCS limpo. Nenhuma configuração foi exportada ou alterada e o
  Runtime não recebeu fixture.

## `0.1.0-dev.32` — matriz de publicação e rejeição do LMS — 2026-10-22

- `LmsCommentModerationMatrixKernelTest` cruza os três atores do curso
  (Educador, estudante e terceiro autenticado) com as decisões de aprovar
  pendente e rejeitar publicado, usando o `LmsForumModerationManager`,
  o `LmsCommentModerationOperationsHooks` e os papéis importados de
  `config/sync` (`lms_course-teacher`, `lms_course-member`,
  `lms_course-course_creator`, `lms_class-member`) com o mapeamento real de
  `lms_classes.settings`.
- O terceiro também carrega o papel global `lms_teacher`, provando que o
  papel global sozinho não modera: sem membership o acesso é negado e nenhuma
  operação é oferecida. O estudante é membro do curso e da turma e também não
  modera.
- O Educador publica o pendente (`status` 1, rejeitado 0) e rejeita o
  publicado (`status` 0, rejeitado 1); o estado é reconferido com
  `loadUnchanged()`. As chamadas negadas de estudante e terceiro devolvem
  FALSE e não alteram o Comment.
- Matriz registrada em `aculta_forum/docs/TEST-MATRIX.md`. Nenhuma entidade de
  configuração foi exportada e o Runtime não recebeu fixture.
- Teste: 1 teste / 61 asserções PASS. Depreciações de Core/contrib permanecem
  as já registradas.

## `0.1.0-dev.30` — Kernel de instalação do bundle LMS — 2026-10-22

- `LmsTopicBundleInstallKernelTest` instala a configuração padrão do adaptador
  a partir dos pré-requisitos reais: tipos de Group `lms_course`/`lms_class` do
  contrib LMS e o Comment/campo de discussão exportados por `aculta_forum`.
- Confere `aculta_lms_topic` (revisões e preview), o campo
  `field_aculta_discussion`, os displays default/full/teaser e as duas relações
  Group Node com cardinalidade 1 por entidade. O config opcional de H5P fica
  fora quando `aculta_lms_h5p` não está ativo.
- Só entidades de configuração são instaladas. A fixture
  `aculta_forum_lms_test`, habilitada no Kernel, registra o schema de
  `aculta_forum_lms.settings` para que o `ConfigInstaller` valide a
  configuração simples sem habilitar o adaptador e sua cadeia de dependências.
- Teste: 4 testes / 129 asserções PASS. Depreciações de Core/contrib
  permanecem as já registradas.

## `0.1.0-dev.31` — Kernel de isolamento do contexto LMS — 2026-10-22

- `LmsDiscussionContextIsolationKernelTest` instancia
  `LmsDiscussionContextProvider` diretamente e prova que o contexto falha
  fechado fora da relação Group exata: nó não suportado, tópico novo, tópico
  sem relação, com duas relações, ligado a Group fora de `lms_course`/
  `lms_class`, ou turma sem único curso pai (nenhum ou dois).
- Prova o caminho permitido: membro do curso lê e responde enquanto o
  `status` está aberto, fecha a escrita quando a discussão é encerrada, não
  recebe moderação e mantém as políticas `group_members_only`,
  `private_search_only` e `membership_revalidated` com ID estável
  `lms:node:{uuid}`. O não membro não lê nem escreve.
- Prova o isolamento de turma: membro de uma turma não acessa tópico de outra
  turma do mesmo curso.
- Teste: 6 testes / 205 asserções PASS. Depreciações de Core/contrib
  permanecem as já registradas.

## `0.1.0-dev.29` — configura janela de edição das discussões LMS — 2026-10-21

- Adiciona configuração administrativa da janela do autor, padrão de 10 minutos desde a primeira publicação; editar não reinicia o prazo e zero desativa edição do autor.
- A janela é independente por Fórum LMS e não substitui permissões contextuais de Educadores/administradores. Rota declarada: `/painel-administrativo/lms/forum/configuracoes`; Runtime DEFERRED.

## F15 — compatibilidade do Kernel com referências H5P–Media — 2026-10-21

- A fixture do Kernel de discussão H5P habilita o módulo Core `media`, agora
  exigido como tipo-alvo da referência de PDFs existentes na entidade H5P.
- Suíte Kernel Forum LMS: 4 testes / 205 assertions PASS; 47 depreciações de
  Core/contrib. Unit Forum LMS: 25 testes / 292 assertions PASS. PHP CLI 8.4.26.

## `0.1.0-dev.28` — apresentação das discussões do LMS por tokens neutros — 2026-10-21

- Discussões de atividade, wrapper do player H5P e cartão das conversas agora
  emitem tokens `data-portal-ui`; ACULTA420 mapeia-os para primitives
  Bootstrap. O módulo não emite classes de apresentação.
- Kernel H5P–Fórum valida a composição dos tokens junto com associação,
  autorização e isolamento por curso/turma: 1 teste / 37 assertions PASS. Suíte
  completa Forum LMS: 29 testes / 497 assertions PASS; 47 depreciações de
  Core/contrib. PHP 8.4.26; PHP 8.5 não validado.
- A revisão visual autenticada do player com tópico segue pendente: não há
  conteúdo H5P/tópico vinculado no Runtime de teste.

## `0.1.0-dev.27` — vincular discussões diretamente ao H5P — 2026-10-21

- Tópicos LMS podem referenciar diretamente um `h5p_content` do ACULTA H5P.
  O formatter do H5P injeta as discussões abaixo do conteúdo somente quando o
  acesso atual ao conteúdo no curso é permitido; o resolver consulta por ID,
  revalida a relação Group única, o curso ou turma filha e o Entity Access do
  tópico. Cache preserva usuário, permissões, membership e relações Group.
- A seleção no autocomplete é contextual ao Group do curso/turma e lista só
  conteúdos H5P pertencentes ao curso que o educador pode editar. Sem Group
  válido, curso pai único ou permissão, falha fechada. `aculta_forum_lms`
  depende do H5P ACULTA; o H5P não depende do Fórum. Campo permanece oculto no
  tópico público e assuntos genéricos de atividade continuam em
  `field_lms_activity`.
- Unit do adapter: 25 testes / 291 assertions PASS; inclui o resolver para
  conteúdo permitido/não pertencente ao curso, tópico de outro curso excluído
  e transmissão do ID Group ao autocomplete. Kernel existente: 3 testes / 168
  assertions PASS, 47 depreciações Core/contrib. Kernel específico que exercite
  associação/seletor e smoke visual/autenticado permanecem pendentes. PHP CLI
  8.4.26; PHP 8.5 não validado.
- Runtime de teste `cursos`: campos opcionais instalados e displays existentes
  alterados somente para o novo campo; plugin do seletor resolvido e vazio sem
  Group contextual, como esperado. Não havia tópicos LMS; nenhum conteúdo foi
  alterado. `drush cr` passou com o aviso preexistente de cópia de asset H5P.
  RUNTIME STATUS: PARTIAL; produção não alterada.

## `0.1.0-dev.26` — validar vínculo atividade-discussão no Kernel — 2026-10-21

- Novo Kernel MariaDB exercita atividade publicada, aula, curso, acesso de
  estudante por Group e dois tópicos em cursos diferentes. O resolver exibe
  somente a discussão autorizada do curso da atividade e preserva metadados de
  cache de permissões e relações.
- Kernel Forum LMS: 3 testes / 168 assertions PASS, 46 depreciações upstream;
  Unit adapter: 22 testes / 256 assertions PASS. PHP CLI 8.4.26; PHP 8.5 não
  validado. Runtime topic fixture, smoke autenticado e revisão visual seguem
  pendentes. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.25` — confirmar imagens inline no Comment compartilhado — 2026-10-21

- O Kernel Unit do adapter verifica que respostas LMS reutilizam o campo
  Comment `aculta_discussion_post`, o formato `usuario_aculta`, o upload inline
  CKEditor e o filtro de imagem de `aculta_image`. O LMS não cria campo nem
  pipeline de imagem duplicado; anexos independentes continuam sem fluxo próprio.
- Unit adapter: 22 testes / 256 assertions PASS. Gate `validate-aculta-image`
  confirma o catálogo e o upload compartilhado.

## `0.1.0-dev.24` — ligar discussões existentes às atividades — 2026-10-21

- Tópicos Node do Forum podem referenciar uma atividade LMS pela entidade
  nativa `lms_activity`. Na resposta da atividade, o estudante vê tópicos da
  atividade no curso atual somente quando o Entity Access do Node/Group permite;
  o vínculo aceita fórum no curso ou em turma pertencente a esse curso.
- A lista revalida associação da atividade ao curso, cadeia Group turma→curso,
  relação única do tópico e acesso atual. Tags e contexts de cache incluem
  usuário, permissões, memberships e relações de turma. Nenhuma entidade ou
  comentário paralelo foi criado.
- Unit adapter: 22 testes / 250 assertions PASS, incluindo resolver e contrato
  de configuração; Kernel existente: 2 testes / 132 assertions PASS, com 43
  depreciações upstream. Os cinco itens de Config Sync foram aplicados
  parcialmente no Runtime de teste; cache rebuild e resolução dos serviços do
  resolver/hook passaram. O Runtime não contém tópico LMS, então smoke
  autenticado e revisão visual da atividade continuam pendentes. O rebuild
  reportou o warning preexistente de cópia do asset H5P para `assets://`.
  PHP CLI 8.4.26; PHP 8.5 não validado. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.23` — aninhar moderação de fórum sob o curso — 2026-10-21

- Filas e ações de moderação passaram a exigir o curso na rota, e a autorização
  confere se o Group da discussão é o próprio curso ou uma turma vinculada a
  ele. Unit: 18 testes / 194 assertions PASS; Kernel: 1 teste / 115 assertions
  PASS, com 42 depreciações upstream. rebuild no Runtime de teste confirmou os caminhos com curso explícito e a árvore de cursos; smoke autenticado pendente.

## `0.1.0-dev.22` — confirmar aprovação e rejeição HTTP no LMS

- Educador submeteu as duas confirmações Form API com sessão de teste
  contextual: aprovação publicou o Comment e retirou o marcador de rejeição;
  rejeição manteve-o não publicado e marcou o estado rejeitado. As filas
  publicadas, rejeitadas e pendentes refletiram cada transição. Anônimo não
  acessa fila/tópico privado. Nenhum aviso público foi criado.
- Fixture Group/Node/Comment e sessão foram removidos; o estado final ficou com
  1 Group de curso preexistente, 0 tópicos LMS e 0 Comments. `drush cr` e
  resolução do controller pelo container PASS no Runtime de teste.
  RUNTIME STATUS: PASS para estas transições; busca e notificação de eventos
  permanecem fora desta validação.

## `0.1.0-dev.21` — validar a fila LMS com Comment e ações reais

- Educador abriu a fila com uma fixture temporária em Group privado. O Comment
  aparece somente na fila pendente; as ações `approve` e `reject` são emitidas,
  e as duas confirmações Form API respondem 200 por GET. Nenhum POST foi feito.
- A fila e o tópico negam acesso anônimo. Group, membership, Node, relationship,
  Comment e sessão de teste foram removidos; estado LMS original confirmado.
  RUNTIME STATUS: PARTIAL; execução HTTP das transições ainda pendente.

## `0.1.0-dev.20` — validar montagem contextual da fila no controller

- O teste Kernel chama `LmsForumModerationController::comments()` com a View
  persistida e confirma tipo de renderização, ID do display pendente, argumento
  do Group da rota e cache tag da entidade. Isso verifica a composição das
  dependências de View/controller sem usar o Runtime.
- Kernel do arquivo: 1 teste/110 assertions PASS; suíte Kernel do adapter:
  2 testes/127 assertions PASS, 40 depreciações Core/contrib/LMS. PHP CLI
  8.4.26 está abaixo da baseline 8.5. RUNTIME STATUS: PARTIAL; smoke das filas
  vazias PASS, linhas e operações ainda não renderizadas com Comments LMS.

## `0.1.0-dev.19` — validar conversão de entidades na rota de moderação — 2026-10-20

- Kernel registra a definição de rota com os conversores instalados `entity:group`
  e `entity:comment`, converte IDs reais em entidades e aciona o `CustomAccessCheck`
  usando o callback de serviço configurado. Group/Comment, decisão e conta são
  resolvidos; aluno e Group divergente não passam.
- Unit: 18 testes / 190 assertions PASS. Kernel MariaDB: 2 testes / 122
  assertions PASS, 40 depreciações Core/contrib/LMS. Verificação posterior no
  Homelab: `drush cr` PASS, controller instanciado pelo container, três rotas
  ativas, respostas anônimas 403 e `/` HTTP 200. Educador autenticado abriu o
  painel e as três filas por GET (200); sessão temporária removida. Nenhum dado
  ou configuração alterado; ações e linhas com comentários não exercitadas.
  RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.18` — cobrir callback de rota de moderação — 2026-10-20

- Kernel executa o mecanismo Core `CustomAccessCheck` com a assinatura da rota
  de moderação. Confirma que Groups e Comments upcastados e a decisão escalar
  chegam ao manager, Educador permitido, estudante negado e contexto Group
  divergente sem autorização.
- Unit: 18 testes / 190 assertions PASS. Kernel MariaDB: 2 testes / 118
  assertions PASS, com 40 depreciações Core/contrib/LMS. Runtime não alterado.
  RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.17` — executar moderação LMS em contexto — 2026-10-20

- Filas agora usam a coluna Core `entity_operations`, com ações `Aprovar` e
  `Rejeitar` emitidas apenas para Comments do tópico e Group LMS autorizados.
  Form API confirma cada transição e revalida acesso no submit; aprovar publica
  e limpa o marcador, rejeitar despublica e marca. Nenhuma permissão global de
  `update`, `delete` ou `administer comments` é concedida.
- Unit: 18 testes / 190 assertions PASS. Kernel MariaDB: 2 testes / 112
  assertions PASS, incluindo operações por estado, aluno negado, Group incorreto
  e transições de status. 40 depreciações Core/contrib/LMS. View atualizada por
  importação parcial de um único item e cache reconstruído no Runtime de teste;
  sem comentário LMS de teste ou smoke autenticado. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.16` — criar filas de moderação LMS por contexto — 2026-10-20

- Adiciona painel de cursos/turmas autorizados e três rotas de Comments
  publicados, rejeitados e pendentes. O acesso exige `grade students` no curso
  contextual; uma View Embed fixa Group, bundle e estado e não possui página ou
  bloco global. Kernel verifica curso/turma, status e Group fora da atribuição.
- Adapter Unit: 18 testes / 184 assertions PASS. Kernel: 2 testes / 102
  assertions PASS, 40 depreciações de Core/contrib/LMS. Revisão corrigiu o ID da
  relação Group de turma na View. O primeiro rebuild revelou conflito de
  propriedades herdadas de `ControllerBase`; o controller agora usa DI sem a
  classe base. `drush cr` passou após a correção.
- A View foi importada isoladamente no Runtime com `config:import --partial`.
  `/` respondeu 200, overview anônimo 403 e rotas com Group inexistente 404.
  Não há sessão autenticada de Educador nem Comments LMS para validar a
  renderização de linhas e operações. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.15` — permitir moderação contextual de Comments no LMS — 2026-10-20

- Educadores com `grade students` no curso contextual podem ver Comments
  pendentes/rejeitados e usar a operação Core `approve` em Comments ligados a
  `field_aculta_discussion` de tópicos LMS. A permissão é avaliada pelo
  `DiscussionContextResolver`, inclui cacheabilidade do contexto e não concede
  `update`, `delete` nem `administer comments`.
- Teste Unit verifica Educador versus estudante e restringe as operações; a
  integração do hook com autorização real de entidades e filas Views segue para
  Kernel/Functional. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.14` — sincronizar permissões contextuais LMS — 2026-10-20

- `core.extension` passa a rastrear `aculta_forum_lms` e `gnode`. Importação
  parcial revisada aplica os quatro Group roles e a herança `grade students` em
  `lms_classes.settings`, sem deletar nem alterar outros itens do Runtime.
- Unit: 14 testes / 147 assertions PASS; Kernel: 2 testes / 74 assertions
  PASS, com 38 depreciações Core/contrib/LMS. Os cinco objetos ativos passaram
  a coincidir com Config Sync; tópicos e Comments continuam zerados. Autorização
  de conteúdo real no Runtime, UI/Views/busca/filas seguem pendentes.
  RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.13` — corrigir ativação e registrar Runtime de teste — 2026-10-20

- Hook OOP injeta explicitamente `aculta_forum.discussion_context_resolver`,
  corrigindo a compilação do container na ativação. Unit: 13/143 PASS; Kernel:
  2/74 PASS, com 38 depreciações Core/contrib/LMS.
- `aculta_forum_lms` e `gnode` habilitados no Runtime de teste; bundle,
  Comment, displays e relações Group instalados. HTTP `/` e `/categorias`: 200;
  tópicos públicos/LMS/legados e Comments compartilhados: zero. Naquele
  incremento, roles ainda não haviam sido importadas. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.12` — provar Educador sem membership direto na turma — 2026-10-20

- Kernel mantém curso e turma sob propriedade de uma terceira conta. O Educador
  é apenas membro Educador do curso; ainda assim pode ver a turma e o tópico
  Group associado por `grade students`, sem membership direto na turma.
- A prova continua negando o outsider com papel global `lms_teacher` e confirma
  leitura/moderação contextual. Kernel do adapter: 2 testes / 74 assertions
  PASS; 38 depreciações observadas de Core/contrib/LMS.
- Nenhum módulo ou Config Sync foi ativado no Runtime. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.11` — aplicar config padrão em fixture Kernel — 2026-10-20

- Kernel executa o `ConfigInstaller` do Drupal para as configurações padrão do
  adapter e confirma bundle, campo Comment compartilhado, duas relações Group
  e formatter. Não habilita o módulo nem testa o ciclo completo do
  `ModuleInstaller`.
- Kernel adapter: 2 testes / 72 assertions PASS, 38 depreciações Core/contrib;
  Unit adapter 12/141 PASS. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.10` — validar dependências dos Group roles — 2026-10-20

- Unit confronta os Group roles do Config Sync com as duas relationship types
  entregues pelo adapter e verifica o mapeamento LMS de Educador para turma.
- Unit do adapter: 12 testes / 141 assertions PASS. Config Sync não importado;
  instalação real do módulo continua pendente do teste do grafo completo.
  RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.9` — validar metadados de cache do contexto LMS — 2026-10-20

- Kernel confirma contextos `user`, `user.permissions` e
  `user.group_permissions`, além da dependência cacheável do tópico, ao compor
  as decisões de leitura/escrita/moderação para Educador, estudante e outsider.
- Nenhuma rota ou fila foi criada; o adapter e a Config Sync continuam fora do
  Runtime. Kernel adapter: 2 testes / 67 assertions PASS, 38 depreciações
  Core/contrib. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.8` — limitar tópicos LMS pelos Group roles — 2026-10-20

- Roles de estudante/educador/criador recebem permission Group Node somente
  nas relações do adapter (curso/turma); herança de `grade students` leva o
  acesso de Educador às turmas. Outsiders e role global sem membership não
  acessam tópicos.
- Kernel lê e cria roles a partir do Config Sync; 2 testes / 63 assertions
  PASS, 38 depreciações. Config não importada, adapter segue desabilitado.

## `0.1.0-dev.7` — validar Comment e moderação contextual em Kernel — 2026-10-20

- Kernel cria tópico LMS em Group de turma, persiste Comment compartilhado,
  permite escrita de membro, restringe o marcador de moderação a Educador do
  curso e bloqueia escrita com campo fechado. Outsider não lê. Kernel adapter:
  2 testes / 57 assertions PASS, 38 depreciações Core/contrib; Unit 11/123.
- Adapter e Config Sync continuam desabilitados/não importados no Runtime.
  Nenhuma fila foi criada. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.6` — herdar permissão do Educador nas turmas — 2026-10-20

- Config Sync de `lms_classes.settings` concede permissão Group Node de tópico
  na turma quando o membro tem `grade students` no curso pai.
- Kernel Group/LMS comprova a herança real: Educador do curso passa; outsider e
  pessoa com apenas papel global `lms_teacher` não passam. 1 teste / 24
  assertions PASS; 37 depreciações Core/contrib. Config não importada no
  Runtime; adapter segue desabilitado.

## `0.1.0-dev.5` — corrigir composição de acesso contextual — 2026-10-20

- Corrige a combinação de acesso: membership e permissão `grade students` são
  alternativas explícitas, sem `orIf()` que deixava uma negação de matrícula
  dominar uma permissão válida de Educador. A negação acontece somente quando
  nenhum dos caminhos autoriza; moderação segue negada para não Educadores.
- Escrita exige status Comment aberto e permission `post comments`; os dois
  caminhos negados são explícitos e preservam cache contexts.
- Unit: 2 testes / 50 assertions PASS cobrindo estudante, Educador, escrita,
  moderação e campo fechado. Runtime DEFERRED; adapter segue desabilitado.

## `0.1.0-dev.4` — provar permissões Group Node — 2026-10-20

- Kernel com Group 3.3.5/gnode prova que permission contextual do Group concede
  acesso ao membro e que outsider não lê o tópico. 1 teste / 17 assertions
  PASS; 17 depreciações do Core/Group/contrib. Sem instalação no Runtime.

## `0.1.0-dev.3` — configuração instalável de tópico — 2026-10-20

- Adiciona bundle Node, campo Comment compartilhado, displays e relações
  Group Node específicas para curso e turma. Sem Config Sync de ativação,
  permissão de roles, rotas ou Runtime.
- Validação de config: 2 testes / 29 assertions; suíte completa: 8 testes /
  48 assertions PASS.

## `0.1.0-dev.2` — acesso direto fail-closed — 2026-10-20

- Protege a visualização direta do Node: exige relação Group única e, para
  turma, um curso pai único. Com relação válida, delega autorização ao Group.
- Testes Unit novos: 3 testes / 11 assertions PASS; suíte do adapter: 6 testes / 19 assertions. Não habilitado no Runtime.

## `0.1.0-dev.1` — contrato inicial do adapter — 2026-10-20

- Implementa resolução fail-closed de tópico LMS vinculado a Group e o acesso
  contextual de Educador ao marcador de moderação compartilhado.
- O adapter não possui configuração instalável nem deve ser habilitado antes
  das provas Kernel e da configuração específica de Group Node.
