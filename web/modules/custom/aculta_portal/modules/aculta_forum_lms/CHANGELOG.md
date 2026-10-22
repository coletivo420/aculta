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
