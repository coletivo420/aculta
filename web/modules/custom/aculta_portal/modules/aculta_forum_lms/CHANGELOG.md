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
- Unit do adapter: 25 testes / 293 assertions PASS; inclui o resolver para
  conteúdo permitido/não pertencente ao curso e transmissão do ID Group ao
  autocomplete. Kernel existente: 3 testes / 168 assertions PASS, 47
  depreciações Core/contrib. Kernel específico de associação/seletor e smoke
  visual/autenticado permanecem pendentes. PHP CLI 8.4.26; PHP 8.5 não validado.
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
