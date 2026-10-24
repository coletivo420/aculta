## `0.1.0-dev.300` — provar o formulário de moderação por revisão — 2026-10-24

- Novo Kernel `ForumPostModerationFormControllerKernelTest`: o identificador de
  revisão solicitado chega ao form builder, inclusive quando não é a revisão
  padrão; revisão inexistente resulta em não encontrado sem construir formulário.
- Testes: moderação por revisão 2/28; suíte Kernel do Fórum 134/2.573; PHPCS limpo.

## `0.1.0-dev.299` — provar o formulário público de edição — 2026-10-24

- Novo Kernel `ForumPostEditControllerKernelTest`: a autora abre o modo
  `public_submission` para a própria publicação publicada no purpose `forum`;
  UUID ausente ou desconhecido, publicação de outra pessoa, fora do purpose e
  publicação despublicada resultam em não encontrado.
- Testes: edição 2/32; suíte Kernel do Fórum 132/2.545; PHPCS limpo.

## `0.1.0-dev.298` — provar a fila de moderação do Fórum — 2026-10-24

- Novo Kernel `ForumPostModerationQueueControllerKernelTest`: a fila lista
  apenas revisões em `review` de tópicos publicados no purpose `forum` que o
  moderador pode ver; publicações do purpose `main` e já publicadas ficam de
  fora; a linha aponta para revisão e histórico; sem revisões a fila fica vazia.
- Testes: fila 2/39; suíte Kernel do Fórum 130/2.513; PHPCS limpo.

## `0.1.0-dev.297` — provar a construção do formulário público de publicação — 2026-10-24

- Novo Kernel `ForumPostSubmissionControllerKernelTest`: tópico vazio ou
  desconhecido e resposta pai desconhecida resultam em `NotFoundHttpException`;
  tópico válido com pai publicado no purpose `forum` entrega ao form builder um
  rascunho com autor, tópico, pai e `moderation_state = draft`.
- Testes: controlador 2/41; suíte Kernel do Fórum 128/2.474; PHPCS limpo.

## `0.1.0-dev.296` — provar acesso ao histórico e à comparação de revisões — 2026-10-24

- Novo Kernel `ForumPostRevisionAccessKernelTest`: histórico liberado só no
  purpose `main` com acesso ao tópico publicado; comparação aceita duas
  revisões distintas do mesmo post e recusa revisão repetida, inexistente ou de
  outro post.
- Testes: revisões 3/39; suíte Kernel do Fórum 126/2.433; PHPCS limpo.

## `0.1.0-dev.295` — provar o "marcar tudo como lido" sem tópicos — 2026-10-24

- Novo Kernel `ForumMarkAllReadFormKernelTest`: sem tópicos acessíveis, o
  formulário informa que não há discussões e redireciona para o domínio do
  Fórum; fora do purpose `forum` a contagem elegível é zero.
- Testes: formulário 2/19; suíte Kernel do Fórum 123/2.394; PHPCS limpo.

## `0.1.0-dev.294` — provar a injecção da thread no tópico público — 2026-10-24

- Novo Kernel `ForumPostThreadHooksKernelTest`: a visualização `full` dentro do
  purpose `forum` recebe `aculta_forum_revisionable_posts` com a thread
  construída; outros modos de exibição e outros purposes não recebem.
- Testes: thread 2/26; suíte Kernel do Fórum 121/2.375; PHPCS limpo.

## `0.1.0-dev.293` — provar o link do resultado de busca para a resposta — 2026-10-24

- Novo Kernel `ForumPostSearchTopicLinkFormatterKernelTest`: post publicado no
  purpose `forum` gera link para o tópico com página e âncora
  `forum-post-{id}`; post despublicado renderiza `#access` falso; fora do
  purpose o elemento é omitido.
- Testes: formatter 2/28; suíte Kernel do Fórum 119/2.349; PHPCS limpo.

## `0.1.0-dev.292` — provar o acesso ao formulário público de publicação — 2026-10-24

- Novo Kernel `ForumPostSubmissionAccessKernelTest`: anônimo, tópico ausente,
  UUID desconhecido e purpose fora do Fórum não são liberados; membro com
  `create aculta forum posts` em tópico publicado do purpose `forum` pode
  enviar; resposta exige post pai publicado e do mesmo tópico.
- O fixture `aculta_forum_post_test` passa a delegar `entity_access` para os
  hooks de produção, tornando as checagens de acesso realistas.
- Testes: envio 4/55; suíte Kernel do Fórum 117/2.321; PHPCS limpo.

## `0.1.0-dev.291` — restringir a fila de moderação ao purpose main — 2026-10-24

- Novo Kernel `ForumPostModerationAccessKernelTest`: a fila de revisões é
  liberada no purpose `main`, não é liberada no purpose `forum`, e o purpose
  anterior é restaurado após a checagem.
- Testes: acesso 1/9; suíte Kernel do Fórum 113/2.266; PHPCS limpo.

## `0.1.0-dev.290` — remover rota de movimentação que só redirecionava — 2026-10-24

- A rota antiga `aculta_forum.topic_move` (`/painel-administrativo/forum/{node}/mover-categoria`) apontava apenas para o painel e não executava movimentação. Foi removida; o serviço `ForumTopicMover` e os formulários funcionais de moderação permanecem.
- `ForumAdminOverviewController::redirectToOverview()` e seu teste de redirect foram removidos. Política global sem aliases: `scripts/validate-no-temporary-route-aliases.php`.
- Testes Drupal/Runtime deste incremento não executados nesta edição por GitHub; a suíte do agente paralelo documentada abaixo mantém suas evidências históricas.

## `0.1.0-dev.289` — fixar os Kernels de acesso em pt-br — 2026-10-24

- Kernels de busca, listagem e contador de categorias agora declaram `pt-br`
  como idioma padrão; node grants e IDs Search API usam o mesmo idioma. O
  Kernel do mover confirma que Comment mantém `pt-br` após a operação, sem
  provisionar traduções adicionais.
- Search API: 2/27 (SQLite; 2 depreciações upstream Search API); listagem:
  2/26 SQLite; contador: 4/73 SQLite; mover: 19/388 SQLite (19 depreciações
  Drupal/contrib). PHPCS Drupal/DrupalPractice nos quatro arquivos: PASS.
- Nenhuma alteração de conteúdo/Runtime. Este snapshot SQLite não substitui
  os gates MariaDB e Runtime do Fórum.

## 0.1.0-dev.288 — remoção de aliases temporários em desenvolvimento (2026-10-24)

- Removidas oito rotas antigas de redirecionamento administrativo para /painel-administrativo/forum. Os destinos administrativos canônicos e formulários efetivos permanecem. Testes/rotas devem usar as URLs canônicas; sem migração de dados nem homologação Runtime neste incremento.
- Regra transversal: `AGENTS.md` e `scripts/validate-no-temporary-route-aliases.php`.
## `0.1.0-dev.287` — provar o filtro de não lidas e alinhar rotas — 2026-10-24

- Novo Unit `ForumUnreadContentFilterTest`: anônimo recebe `1 = 0`; membro
  recebe as condições de History, tópico, Comments e ForumPosts com os limites
  do History. Garante o comportamento fail-closed antes da consulta real.
- O teste de configuração administrativa deixa de exigir os aliases legados
  removidos na limpeza de rotas e passa a afirmar sua ausência.
- Testes: Unit do Fórum 182/1.019; PHPCS limpo.

## `0.1.0-dev.286` — provar grants na busca pública — 2026-10-24

- Kernel indexa tópicos no `search_api_db` com o processor `content_access` e
  consulta com a conta: o anônimo recebe total 1, sem o tópico restrito; o
  membro recebe total 2 e o item restrito. O filtro de node grants acontece
  antes da contagem/paginação.
- Testes: busca 2/27; suíte Kernel do Fórum 112/2.260; PHPCS limpo.

## `0.1.0-dev.285` — contar atividade só na primeira publicação — 2026-10-24

- `ForumActivityEventHooks` deixa de emitir atividade na inserção de tópico,
  Comment ou ForumPost pendente/não publicado. A primeira publicação dispara
  uma única vez (incluindo aprovação posterior); edição publicada, revisão não
  padrão e conteúdo sincronizado não disparam.
- Testes: Unit de atividade 10/18; Kernel do post 3/42; Unit do Fórum 180/1.018;
  Kernel do Fórum 110/2.233; PHPCS limpo.
- Nenhuma alteração de storage, configuração ou Runtime.

## `0.1.0-dev.284` — registrar os gates estáticos do módulo — 2026-10-24

- Snapshot sem mudança de comportamento: Unit do Fórum 174/1.008; Unit de
  fóruns restritos 61/172; Kernel 107/2.191; PHPCS do módulo inteiro limpo,
  após comentários de documentação nos hooks de membership/relationship.
- Validadores do tema: SDC schemas PASS (588 verificações) e design foundations
  PASS. O gate de fronteira módulo×tema não acusa mais `aculta_forum`; as
  falhas restantes são dívidas preexistentes de `aculta_wiki`/`aculta_studio`.
- Status index PASS. Gates de Runtime (servidor de testes, e-mail, navegador
  autenticado) e a homologação F-M7 continuam pendentes.

## `0.1.0-dev.283` — provar o gate de purpose da galeria — 2026-10-24

- Kernel confirma que a galeria do Fórum é negada fora do purpose `forum` e
  que, dentro dele, a galeria não publicada volta à política do dono.
- Testes: anexos 13/373; suíte Kernel do Fórum 107/2.191; PHPCS limpo.

## `0.1.0-dev.282` — provar o gate de purpose do anexo — 2026-10-24

- Kernel confirma que o anexo do Fórum é negado fora do purpose `forum` e que,
  dentro dele, a mídia não publicada volta à política do dono.
- Testes: anexos 13/371; suíte Kernel do Fórum 107/2.189; PHPCS limpo.

## `0.1.0-dev.281` — provar grants antes da paginação na listagem — 2026-10-24

- Kernel executa uma View pública baseada em nodes com pager de uma linha: o
  anônimo vê total 1 e só o tópico público; o membro vê total 2 e a segunda
  página traz o tópico restrito. O filtro de node access ocorre antes da
  paginação e a contagem total respeita o grant.
- Testes: listagem 2/26; suíte Kernel do Fórum 106/2.164; PHPCS limpo. Nenhuma
  configuração ou Runtime foi alterado.

## `0.1.0-dev.280` — provar revogação de acesso ao anexo — 2026-10-24

- Kernel despublica o tópico hospedeiro e confirma que a Media do Fórum deixa
  de ser acessível à conta na checagem seguinte; republicar devolve o acesso.
  O teste reseta os caches estáticos da entidade porque o Comment guarda a
  referência ao host no campo, como numa requisição nova.
- Testes: anexos 12/346; suíte Kernel do Fórum 104/2.138; PHPCS limpo. Nenhuma
  configuração ou Runtime foi alterado.

## `0.1.0-dev.279` — reverter migrations dependentes na ordem correta — 2026-10-24

- O Kernel importa o relacionamento pai/filho pelo map real, depois reverte a
  migration de relações antes da migration de posts. Ambos os maps são limpos,
  todos os ForumPosts somem e as Comments de origem permanecem. Focal: 3 testes /
  82 assertions; cinco depreciações Core/Domain contrib.
- `ForumCommentMigrationBatchKernelTest` preserva também a prova standalone do
  rollback do destino. PHPCS Drupal/DrupalPractice permanece PASS.

## `0.1.0-dev.278` — provar revogação de grant na contagem — 2026-10-24

- Kernel remove o grant do tópico restrito e confirma que a consulta de
  produção do contador deixa de contar a categoria na execução seguinte;
  reinserir o grant devolve a contagem.
- O fixture de contagem corrige o nome da permissão (`view restricted forum
  counter topics`) para que a role autenticada a receba; o membro do teste
  deixou de ser uid 1, que ignora node access.
- Testes: contador 4/73; suíte Kernel do Fórum 102/2.079; PHPCS limpo.

## `0.1.0-dev.277` — comprovar rollback da migração de Comments — 2026-10-24

- Kernel importa raiz e resposta com o destino real e executa o rollback pelo
  `MigrateExecutable`: posts e mapa de destino são removidos, Comments legados
  permanecem intactos. Suíte focal: 3 testes / 76 assertions.
- Unit Forum: 174 testes / 1.008 assertions; PHPCS Drupal/DrupalPractice:
  PASS. Cinco depreciações são originadas em Core/Domain contrib.
- `--simulate` do Drush não executa a migration; dry-run que valide o pipeline
  continua pendente. Nenhum Runtime ou dado persistente foi usado.

## `0.1.0-dev.276` — validar vínculo pai/filho no mapa da migração — 2026-10-24

- Kernel executa a migration de relacionamentos com `migration_lookup` sobre
  o map SQL persistente e comprova que a resposta aponta ao ForumPost da raiz.
  O fixture compara process pipeline, destino e dependências com o YAML de
  produção para evitar drift. Kernel focal: 2 testes / 53 assertions.
- Cinco depreciações reportadas por Core/Domain contrib; PHPCS Drupal e
  DrupalPractice: PASS. Naquele incremento, rollback e homologação Runtime
  seguiam pendentes.

## `0.1.0-dev.275` — provar o lote da migração com mapa persistente — 2026-10-24

- Kernel executa `aculta_forum_comment_posts` pelo `MigrateExecutable` com o
  source/destination de produção e o map SQL persistente: duas Comments viram
  dois ForumPosts, a reexecução não duplica e uma Comment nova entra na
  execução seguinte, reutilizando o mesmo map.
- Corrigido defeito real do destino: como o plugin ID não é `entity:*`, o trait
  do Migrate não inferia o entity type e o map falhava em `getIds()`. O destino
  passa a declarar `aculta_forum_post` explicitamente.
- Testes: lote 1/26; suíte Kernel do Fórum 101/2.060; PHPCS limpo. Naquele
  incremento, a resolução pai/filho, dry-run e rollback seguiam pendentes.

## `0.1.0-dev.274` — provar o link de não lidas — 2026-10-23

- Kernel exercita `ForumUnreadPosts`: tópico com duas não lidas gera o link
  "2 novas publicações" para a primeira publicação não lida, com página e
  âncora; tópico sem pendência não renderiza link.
- Testes: leitura 7/95; a suíte completa permanece verde em 98/2.007 antes
  desta adição e não foi reexecutada no encerramento; PHPCS limpo.
- A View `/discussoes/nao-lidas` e o Runtime continuam pendentes.

## `0.1.0-dev.273` — provar a leitura de posts revisionáveis — 2026-10-23

- Kernel exercita `ForumPostUnreadManager`: anônimo não recebe dados; post mais
  novo que o recibo ou que o limite do History conta como não lido; o recibo
  marca os anteriores como lidos; a página do primeiro não lido considera os
  posts que o precedem e um recibo após o último post zera a pendência.
- Testes: leitura 5/68; suíte Kernel do Fórum 98/2.007; PHPCS limpo. A View de
  não lidas e o Runtime seguem como gate próprio.

## `0.1.0-dev.272` — provar a contagem combinada da listagem — 2026-10-23

- Kernel exercita `PublicForumReplyCount` sobre o agregado real: dois Comments
  mais dois ForumPosts publicados resultam em quatro, ignorando o post não
  publicado.
- Testes: agregado 4/64; suíte Kernel do Fórum 93/1.939; PHPCS limpo. A
  apresentação na View/tema e o Runtime seguem pendentes.

## `0.1.0-dev.271` — provar a agregação de posts da listagem pública — 2026-10-23

- Kernel exercita `ForumPublicPostStatistics`: conta só posts publicados,
  devolve a atividade mais recente, resolve o último post em caso de empate de
  timestamp e devolve zero para tópico sem post. Tópicos não pedidos não entram
  no resultado.
- Testes: agregado 3/44; suíte Kernel do Fórum 92/1.919; PHPCS limpo. A
  apresentação na listagem e a validação no Runtime seguem pendentes.

## `0.1.0-dev.270` — fechar os guardas da denúncia migrada — 2026-10-23

- Kernel cobre escopo global, timestamp zero, origem com outra Flag, denúncia
  anônima sem sessão e linha cujo Comment não tem post mapeado; todos são
  recusados antes do salvamento.
- Testes: contexto 7/131; suíte Kernel do Fórum 89/1.875; PHPCS limpo. Nenhuma
  migration foi executada.

## `0.1.0-dev.269` — provar a denúncia importada no post — 2026-10-23

- Kernel exercita `ForumFlaggingMigrationContext`: a denúncia de destino com a
  mesma identidade da origem é salva no ForumPost mapeado e o contexto deixa de
  autorizar o presave depois. Dono/timestamp divergentes, origem fora do Domain
  `forum`, post de outro tópico e duplicidade no destino são recusados.
- O map de migração é simulado com `MigrateLookupInterface`; nenhuma migration
  foi executada e nenhum dado foi convertido.
- Testes: contexto 5/94; suíte Kernel do Fórum 87/1.838; PHPCS limpo.

## `0.1.0-dev.268` — provar a fonte das denúncias — 2026-10-23

- Kernel cria o Flag `sinalizar_comentario` e duas Flaggings; a fonte
  `aculta_forum_comment_flagging` devolve só a denúncia do Comment elegível,
  com `fid`, `cid`, `uid`, `session_id` e `global` originais.
- Denúncia em Comment de tópico fora do Domain `forum` é excluída. A migration
  não foi executada e nenhum dado foi convertido.
- Testes: fonte 6/88; suíte Kernel do Fórum 82/1.744; PHPCS limpo.

## `0.1.0-dev.267` — provar a relação pai/filho da migração — 2026-10-23

- Kernel importa raiz e resposta, atualiza a resposta com o ID de destino da
  raiz e confirma que o campo `parent` aponta para o post raiz sem alterar o
  conteúdo da raiz.
- O caminho usa a atualização por `old_destination_id_values`, como a migration
  de parents; o `migration_lookup` e o map de migração ainda não são exercitados.
- Testes: destino 9/216; suíte Kernel do Fórum 81/1.723; PHPCS limpo.

## `0.1.0-dev.266` — provar a paginação por keyset da fonte — 2026-10-23

- Kernel cria cinco Comments e lê a fonte com lote de dois: todos aparecem uma
  única vez, em ordem de CID, comprovando o keyset entre lotes.
- Testes: fonte 5/67; suíte Kernel do Fórum 80/1.697; PHPCS limpo. O lote com
  map de migração, as relações pai/filho e a equivalência continuam pendentes.

## `0.1.0-dev.265` — reimportar atualiza o post e protege o idioma padrão — 2026-10-23

- Kernel reimporta a mesma linha com `old_destination_id_values`: o post é
  atualizado no lugar, sem duplicar, e as traduções recebem os novos valores.
- Trocar o idioma padrão de um post existente é recusado com `MigrateException`
  e o conteúdo anterior permanece.
- Testes: destino 8/190; suíte Kernel do Fórum 79/1.686; PHPCS limpo. O lote
  com map de migração e a execução real continuam pendentes.

## `0.1.0-dev.264` — provar a criação de um post pela migração — 2026-10-23

- Kernel importa uma linha válida no destino: cria o ForumPost publicado com
  tópico, posição na thread, timestamps, autoria e estado de moderação, e
  adiciona a tradução `pt-br` com assunto, corpo e publicação próprios.
- O fixture `aculta_forum_post_test` restaura o handler de Content Moderation,
  que a descoberta de produção recebe antes dos alters; o teste de workflow
  passa a instalar o schema de `content_moderation_state`.
- Testes: destino 6/142; suíte Kernel do Fórum 77/1.638; PHPCS limpo. Nenhuma
  migration foi executada em lote e o Runtime não foi alterado.

## `0.1.0-dev.263` — provar as recusas do destino da migração — 2026-10-23

- Kernel exercita `ForumCommentPost::import()` nos contratos que falham antes
  de criar o post: linha sem conta nem autoria histórica válida, tradução
  padrão ausente, rejeição combinada com publicação, corpo vazio, formato
  diferente de `forum`, embed sem UUID e embed cuja entidade não existe.
- Nenhuma migration foi executada e nenhum post foi criado; o Runtime não foi
  alterado.
- Testes: arquivo 5/87; suíte Kernel do Fórum 76/1.579; PHPCS limpo.

## `0.1.0-dev.262` — provar a fonte da migração de Comments — 2026-10-23

- Kernel exercita `aculta_forum_comment` com Comments reais: um Comment
  elegível vira uma linha com tópico, autoria, timestamps, hierarquia,
  marcadores de rejeição/autoria histórica e todas as traduções agrupadas.
- Tópico fora do Domain `forum`, tópico de outro bundle e Comment fora do
  contexto público do Fórum não geram linha.
- Testes: arquivo 4/56; suíte Kernel do Fórum 71/1.492. A migration não foi
  executada, nenhum dado foi migrado e o Runtime não foi alterado.

## `0.1.0-dev.261` — validar o trigger ForumPost do Game — 2026-10-23

- Kernel com entidades reais de ForumPost prova a política do Game: publicação
  inicial em tópico publicado do Domain `forum` é elegível; rascunho, revisão
  não default, Domain fora do Fórum, sincronização, original já publicado,
  autoria histórica no post ou no tópico e post sem tópico não são.
- Testes: arquivo 8/100; suíte Kernel do Fórum 67/1.436. Nenhuma conquista,
  configuração ou Runtime foi alterado; a concessão no Runtime segue pendente.
- O hook `forumPostSave` continua sem teste próprio porque `BadgeService` é
  final.

## `0.1.0-dev.260` — contador de tópicos access-aware nas categorias — 2026-10-23

- Novo campo Views `aculta_forum_category_topic_count` conta tópicos publicados
  e acessíveis por categoria em uma única consulta com a tag `node_access`.
  Cada cartão mostra "Nenhum tópico", "1 tópico" ou "@count tópicos"; a regra
  de plural do português trata zero como singular, então o zero tem texto
  próprio.
- A View `aculta_forum_categories` (instalação e sync) recebe o campo e o
  contexto `user.node_grants:view`. O cartão SDC ganha o slot `count` e o tema
  mapeia o token `forum-category-count`, sem classe de design no módulo.
- Kernel do arquivo 3/54: o contador sem a tag vaza o restrito; com a tag, o
  anônimo vê só a categoria pública e o membro vê as duas; o render cobre
  plural, zero e tradução.
- Runtime local: 12 cartões com "Nenhum tópico" (banco sem tópicos); suíte
  visual `categorias-visual.py` 9/9 em ACULTA420 e 9/9 em Olivero, sem erros
  nem overflow. Suíte Kernel 59/1.336; Unit 174/1.008; PHPCS limpo.

## `0.1.0-dev.259` — provar contadores access-aware nas categorias — 2026-10-23

- Nova prova Kernel constrói uma View de categorias com agregação COUNT sobre a
  relação reversa de `field_aculta_categories` e grants reais em MariaDB.
- Sem a tag, o relacionamento `EntityReverse` do Core não propaga `node_access`
  e o anônimo conta tópicos restritos. Com a tag adicionada pelo alter, o
  anônimo vê só a categoria pública e o membro vê as duas.
- A implementação da contagem deve adicionar `node_access` explicitamente; a
  próxima microfase aplica isso na View e na apresentação.
- Testes: arquivo 2/36, suíte Kernel do Fórum 58/1.318; PHPCS limpo.

## `0.1.0-dev.258` — revisão visual de `/categorias` com X11 e Olivero — 2026-10-23

- Firefox ESR/X11 no servidor de testes: `forum-index-visual.mjs` passou 15/15
  em 390/768/1024 CSS px, claro/escuro, com link de salto funcional e 12
  categorias no enquadramento, sem tópicos na listagem pública.
- Olivero no ambiente local: novo `tests/browser/aculta-forum/categorias-olivero.py`
  passou 8/8; 12 categorias e links `?categoria=` renderizam sem assets do
  ACULTA420, sem erros e sem overflow. O tema foi restaurado para `aculta420`
  por trap.
- O script documenta as limitações: sem BiDi, a menor largura medida é a que o
  Firefox aceita na janela; zoom e aparelho real seguem pendentes.
- Sem alteração de configuração versionada, conteúdo ou código do módulo.

## `0.1.0-dev.257` — falha na exclusão e auditoria dos caminhos de limpeza — 2026-10-23

- Fixture `aculta_forum_cleanup_test` simula falha em `hook_entity_predelete`
  de Media `forum_attachment`. O Kernel prova que a API compartilhada devolve
  `Unverifiable`, mantém Media e File, não conta remoção e recupera no lote
  seguinte.
- Auditoria dos caminhos: o Fórum não chama `delete()` em Media/File fora de
  `ForumAttachmentCleanup`; a galeria exclui apenas a entidade de galeria; e
  `aculta_image.ImageCleanup` usa `aculta_portal.unused_file_cleaner` quando
  disponível. Concorrência real na limpeza e validação no Runtime seguem
  pendentes.
- Testes: arquivo 12/343, Kernel do Fórum 56/1.282, Unit 174/1.008; PHPCS
  limpo.
- Sem alteração de configuração, schema, rotas ou Runtime.

## `0.1.0-dev.256` — limpeza de anexos pela API compartilhada (revisões e referências) — 2026-10-23

- Kernel novo cobre a paridade da limpeza: Media embutida apenas em revisão
  antiga de ForumPost é retida enquanto a revisão existir e liberada quando o
  post é removido; Media referenciada por outro tipo de conteúdo é retida pelo
  inspetor compartilhado do Portal; quando o Media órfão é excluído mas o File
  segue em uso por outro anexo, o arquivo é preservado e não conta como remoção.
- O teste reaproveita a construção de produção de `ForumAttachmentCleanup` sobre
  `aculta_portal.unused_media_cleaner`/`unused_file_cleaner`; nenhuma lógica de
  exclusão foi duplicada no Fórum.
- Testes: Kernel do arquivo 11/314 e suíte Kernel do Fórum 55/1.253; Unit do
  Fórum 174/1.008; PHPCS limpo. Concorrência e validação no Runtime seguem
  pendentes.
- Sem mudança de configuração, schema, rotas ou Runtime.

## `0.1.0-dev.255` — download real, índice atrasado, cache e Olivero (F-GROUP 0.7) — 2026-10-23

- Download HTTP do arquivo privado validado: anônimo 403, ativo com membership
  200 (15 bytes), suspenso 403. A fixture usa a raiz `private://` porque
  `private://forum/anexos` pertence ao usuário do PHP-FPM.
- Índice atrasado corrigido: `RestrictedForumGrantRebuilder` usa o item ID do
  datasource e, após gravar os grants, chama `trackItemsInserted()` e
  `deleteItems()` no servidor. O tópico restrito fica ausente do índice até a
  reindexação (fail closed); sem a correção o anônimo via o título restrito.
- Cache da View de busca: MISS após a restrição/reindexação, título ausente;
  HIT seguinte sem ele. Não há vazamento entre contas.
- Olivero: matriz de contas e busca confirmadas; tema restaurado para
  aculta420 no mesmo comando, com trap.
- Testes: Unit 174/1.008, Kernel 52/1.170, rebuilder 6/21 PASS; PHPCS limpo.
- Teardown: tema aculta420, restricted_group_type vazio, realm=all:21, zero
  tópicos/contas/Media/galerias/Files e zero itens no índice. As fases técnicas
  0.1–0.7 estão concluídas; a ativação em produção depende de aprovação.

## `0.1.0-dev.254` — busca e contagem sob grants (F-GROUP 0.6) — 2026-10-23

- Novo Unit `ForumPostSearchAccessTest` (4 testes / 11 asserções) cobre o grant
  público, o grant restrito do membro, o abort sem `access content` e o bypass.
- Runtime: índice `aculta_forum` indexado com um tópico restrito e um público.
  Com `range(0,1)` por conta, anônimo/sem membership/suspenso/comum recebem
  total 1 (público) e o ativo com membership recebe total 2; o total é filtrado
  antes da paginação. A View `/pesquisar?keys=fgroup` confirma o mesmo por HTTP.
- A medição em CLI roda em `runInPurpose('forum', ...)`; fora do purpose, o
  subscriber aplica `__forum_scope_denied__` e zera o resultado.
- Teardown desindexa os itens (`indexed_total=0`) e restaura
  `realm=all:21` e `restricted_group_type` vazio. Suíte Unit: 173/1.003 PASS.
- Script `tests/runtime/fgroup-search.php` documenta o fluxo. Restam download
  HTTP do anexo, cache da busca, índice atrasado e Olivero na F-GROUP 0.7.

## `0.1.0-dev.253` — galeria restrita, cache de acesso e regressão pública — 2026-10-23

- `ForumGalleryPublication` publica a galeria `forum` pela referência ativa e
  `ForumAttachmentAccessHooks::entityAccess()` decide a conta; o slideshow
  continua filtrando cada imagem por `media->access()`. Kernel novo cobre
  anônimo negado e membro liberado.
- `RestrictedForumAccessPolicy::result()` passa a usar `setCacheMaxAge(0)`.
  Sem isso, o `cache.access` mantinha o 403 depois da reativação do associado;
  suspender e reativar agora têm efeito na requisição seguinte, sem `drush cr`.
- Ativação documentada com role **sincronizada insider** (`authenticated`): a
  role individual não é reatribuída quando o sync recria a membership após a
  suspensão, o que deixava a reativação sem acesso.
- Revisão de consumidores: limpeza, listagens administrativas, Index, formato
  `forum`, PDF/documento e galeria não tratam `status=1` como acesso
  irrestrito.
- Runtime: restrito negado a anônimo/suspenso e liberado ao ativo; público
  legível por todos; home anônima só com o público; 403 anônimo cacheado sem
  título; autenticados `UNCACHEABLE`. Galeria e acesso de download medidos.
  O arquivo físico do anexo não foi gravado (`file_exists: false`), então o
  download HTTP real fica pendente.
- Suíte: Unit 169/992 e Kernel 52/1.170 PASS; PHPCS limpo. Fixture removida e
  ambiente restaurado a `realm=all:21`.

## `0.1.0-dev.252` — anexos pela conta e Runtime controlado (F-GROUP 0.4) — 2026-10-23

- `ForumAttachmentPublication` separa publicação de acesso: a Media é
  publicada por referência ativa em post/comment de tópico publicado, inclusive
  restrito (`check_access: FALSE`), e `hasCurrentPublicReference()` avalia o
  host para a conta informada. `ForumAttachmentAccessHooks` passa a decidir
  `allowed`/`forbidden` para a Media publicada, e o download privado do File
  revalida a Media.
- Kernel do Fórum: 51 testes / 1.140 asserções PASS; Unit 169 / 992 PASS;
  PHPCS limpo.
- Runtime de testes de 2026-10-23, com backup validado e reversão completa:
  grants, `node->access`, query `node_access`, HTTP anônimo 403 e Firefox
  ESR/X11 com perfis isolados confirmam acesso só ao ativo com membership.
  Anexo e download negados a anônimo/suspenso e liberados ao membro ativo.
- Fixture removida e frente desligada; `node_access` voltou a `realm=all`
  (21 linhas). Scripts de provisionamento/medição em `tests/runtime/` e teste
  de navegador em `tests/browser/aculta-forum/`. Nenhuma alteração de
  produção.

## `0.1.0-dev.251` — herança do tópico restrito em posts e auditoria de anexos (F-GROUP 0.3) — 2026-10-23

- Unit `ForumPostAccessHooksTest` ganha
  `testRestrictedTopicAccessPropagatesToPost`: com `$topic->access()` forbidden,
  `view` e `update` do post são negados, inclusive para o dono. 4 testes /
  84 asserções PASS.
- Auditoria: o Comment já herda a restrição pelo Core
  (`CommentAccessControlHandler` revalida o host); os anexos usam a conta
  anônima como referência pública, então anexo de tópico restrito é negado a
  todos (fail closed), sem acesso ainda para o associado ativo.
- Nenhuma alteração de produção. A correção dos anexos para a conta atual fica
  na F-GROUP 0.4; documentação atualizada em `RESTRICTED-FORUMS.md`.

## `0.1.0-dev.250` — grants reais do fórum restrito em Kernel (F-GROUP 0.2) — 2026-10-23

- Novo `RestrictedForumNodeAccessRebuildKernelTest` (4 testes / 92 asserções
  PASS) registra os hooks de produção no fixture `aculta_forum_restricted_test`
  e exercita `NodeAccessRebuild`, a tabela `node_access`,
  `NodeAccessControlHandler::access()` e consulta com tag `node_access`.
- O fixture ganhou `services.yml` com os serviços de produção instanciados sobre
  o banco do teste e um adaptador fino `RestrictedForumAccessTestHooks`, que só
  delega aos três hooks reais.
- Prova: grant `aculta_forum_restricted` por gid no tópico relacionado,
  `all:0` no tópico público, leitura apenas para associado `ativo` com
  membership, listagem filtrada por conta e rebuild de um tópico acompanhando
  inclusão/remoção da relação.
- Descoberta de ativação: o módulo Group proíbe a leitura de conteúdo agrupado
  sem `view group_node:aculta_forum_topic entity` na role de membro. Passo
  incorporado a `docs/RESTRICTED-FORUMS.md`.
- Suíte Kernel do Fórum: 50 testes / 1.112 asserções PASS; PHPCS
  Drupal/DrupalPractice limpo nos arquivos novos. Nenhum Runtime alterado.

## `0.1.0-dev.249` — corrigir timestamp de Comment no provider Index

- `ForumContentIndexProvider` normaliza `Comment::getCreatedTime()` para int,
  conforme o contrato de projeção do Index.
- Kernel integrado cobre Comments legados, relação com tópico, atualização,
  exclusão e omissão de comentário rejeitado (1 teste / 21 assertions PASS).
- O harness usa a classe de produção e tag de fixture; ForumPost revisionável,
  boot completo e grants/Domain reais permanecem pendentes.

## `0.1.0-dev.248` — contrato DI dos providers de Index

- Teste Unit lê `aculta_forum.services.yml`, compila os serviços tagueados no
  `ContentIndexProviderRegistry` e confirma ownership de tópicos, ForumPosts,
  Comments legados e Media `forum_attachment`.
- Suíte Unit de Content Index do Fórum: 3 testes / 14 assertions PASS, 4
  depreciações PHPUnit. Boot do módulo Drupal e Kernel Group/grants permanecem
  pendentes.

## `0.1.0-dev.247` — propósito canônico nas respostas do Index

- Unit do provider confirma `forum` para `ForumPost` revisável e Comment legado
  `aculta_discussion_post`, e nega Comment de outro bundle.
- Unit focado: 2 testes / 8 assertions PASS, 3 deprecações PHPUnit. Kernel de
  Entity Access/Group, DI e Runtime continuam pendentes.

## `0.1.0-dev.246` — versão da projeção no fingerprint do Index

- Provider do Fórum declara sua versão de projeção para que rebuilds parciais
  não sejam retomados após mudança de semântica.

## `0.1.0-dev.243` — provider do Content Index para discussões coexistentes

- Provider opcional cobre tópicos, ForumPosts revisáveis e Comments legados
  vinculados a tópicos do Fórum. Rejeitados e revisões não padrão ficam fora;
  corpo e autoria não são projetados.
- Relações nomeadas apontam para o tópico e para o item pai. O filtro do Index
  revalida Entity Access sob o purpose canônico `forum`, preservando grants e
  acesso Group da entidade original.
- Provider de `forum_attachment` separado projeta só metadados da entidade
  Media e usa purpose `forum`; não copia arquivo nem URL.
- Unit tests focados passaram; Kernel/Runtime não executados.

## `0.1.0-dev.242` — prova de ACL de Group e revogação (XK-C3) — 2026-10-22

- Documento novo
  [`docs/FORUM-PART-0.1-C3-CHARACTERIZATION.md`](docs/FORUM-PART-0.1-C3-CHARACTERIZATION.md)
  com a matriz conjunta de acesso, a regra de revogação na checagem e as tags
  de cache do subsistema `RestrictedForum*`.
- Teste novo
  `tests/src/Kernel/RestrictedForumGroupAclKernelTest.php` — 12 testes, 261
  asserções, OK. Usa Group real, `aculta_associado` real e os serviços de
  produção, sem habilitar `aculta_forum` nem `aculta_user`; cobre associado
  ativo, pendente, convidado, suspenso, desligado e ausente, membros de Groups
  distintos, anônimo, tópico público, Group de outro tipo, `lms_course`
  recusado, falha do resolvedor com max-age 0 e a sincronização
  `unchanged`/`added`/`removed`.
- Fixture novo `tests/modules/aculta_forum_restricted_test`: registra a
  entidade de produção `AssociadoMarcador` por Reflection e declara o schema de
  `aculta_forum.settings` usado no teste. Não entra em produção.
- Verificação registrada: `GroupRelationship::getListCacheTagsToInvalidate()`
  inclui a tag por usuário e `EntityBase::invalidateTagsOnSave()` a invalida em
  cada create/update, então `addMember()`/`removeMember()` limpam o cache de
  `GroupMembership::loadByUser()`; não há defeito de membership obsoleta.
- Nenhuma mudança funcional, de DI, de schema ou de Runtime.

## `0.1.0-dev.241` — fundação do fórum restrito de associados (F-GROUP 0.1) — 2026-10-22

- Acrescenta o adaptador `RestrictedForumGroupResolver`: lê
  `aculta_forum.settings.restricted_group_type` (vazio desativa a frente),
  valida o tipo de Group e recusa `lms_course`/`lms_class`. Tópicos restritos
  vêm de relações `group_node:aculta_forum_topic` e
  `group_taxonomy_term:aculta_forum_categories`.
- Acrescenta `RestrictedForumAccessPolicy` e `RestrictedForumAccessHooks`:
  `entity_access` revalida o tópico no servidor (associado ativo **e** membro de
  um Group do tipo configurado); `node_grants` emite o realm
  `aculta_forum_restricted` só para `view`; `node_access_records` grava grant
  por Group e falha fechado (`gid = 0`). Fora do caso restrito devolve
  `neutral`, preservando o comportamento público.
- Acrescenta `RestrictedForumMembershipSync` e `RestrictedForumMembershipHooks`:
  ativação, suspensão, desligamento, readmissão e alteração administrativa
  reconciliam membership de forma idempotente, com lock por conta, log,
  `try/catch` por Group e invalidação de `user:<uid>`,
  `aculta_associado_list`, `group_relationship_list` e `node_list`.
- Acrescenta `RestrictedForumGrantRebuilder` e `RestrictedForumRelationshipHooks`:
  regrava grants do tópico ao mudar relação de tópico ou de categoria.
- `aculta_user` passa a expor `aculta_user.active_associate`
  (`ActiveAssociateInterface`) como contrato público de elegibilidade.
- Configuração nova `restricted_group_type: ''` em instalação, schema e
  `config/sync`; nenhum import foi executado. `aculta_forum.info.yml` declara
  dependência de `group:group`.
- Testes: `tests/src/Unit/RestrictedForum` — 60 testes, 167 asserções, OK;
  PHPCS e PHPStan nível 5 limpos. Kernel/Runtime/busca/anexos ficam DEFERRED,
  com motivo em `docs/RESTRICTED-FORUMS.md`.
- Documento novo [`docs/RESTRICTED-FORUMS.md`](docs/RESTRICTED-FORUMS.md) com a
  matriz F-GROUP-A, decisões, passos de ativação (inclui
  `NodeAccessRebuild::rebuild()`), matriz de testes e riscos residuais.

## `0.1.0-dev.240` — corrigir as falhas Unit do Fórum — 2026-10-22

- Corrige as sete falhas registradas no dev.239, sem tocar o Runtime.
- `config/sync/aculta_forum.settings.yml` recebe `edit_window_minutes: 10`,
  alinhado ao `config/install` desde `164aeff2`.
- `config/sync/views.view.aculta_forum_public_topics.yml` e
  `config/sync/views.view.aculta_forum_unread_topics.yml` são republicados a
  partir de `config/install`, restaurando `aculta_forum_reply_count`,
  `aculta_forum_last_activity`, `aculta_forum_no_replies`,
  `aculta_forum_unread_content` e `aculta_forum_unread_posts`. Sem `_core` e
  com os mesmos UUIDs anteriores.
- `ForumTopicDisplayConfigTest` passa a esperar a chave
  `aculta_forum.admin_revisionable_posts` no menu, o formato e o editor
  `forum` no corpo do comentário, os plugins customizados nas Views e o filtro
  `aculta_forum_no_replies` na descoberta. Import não usado de
  `UploadPolicy` removido.
- Medição (banco local `aculta_simpletest`, Runtime intocado):
  `tests/src/Unit` — 105 testes, 799 asserções, OK.

## `0.1.0-dev.239` — precisar as causas das falhas Unit do Fórum — 2026-10-22

- Documento apenas; nenhuma alteração de código, configuração ou Runtime.
- Complementa o dev.238 com a causa de cada uma das 7 falhas de
  `ForumTopicDisplayConfigTest`, todas anteriores a esta sequência de
  documentação:
  - Lista de chaves do menu administrativo: `ad33ab56` incluiu
    `aculta_forum.admin_revisionable_posts` sem atualizar a expectativa.
  - Janela de edição: `164aeff2` acrescentou `edit_window_minutes: 10` ao
    `config/install` e ao formulário, mas a exportação `b6e49d5e`
    ("sincroniza config/sync com o Runtime de teste") não levou a chave ao
    `config/sync`; o teste compara instalação e sincronização.
  - Formato do corpo do comentário: `c56a0cd6` mudou o campo para o formato
    `forum`, e o teste ainda espera `usuario_aculta`.
  - Views públicas (listagem, categorias, não lidas e descoberta): `b6e49d5e`
    exportou as Views do Runtime, que não têm o plugin
    `aculta_forum_reply_count`, o campo `forum_unread_posts` nem os campos
    derivados de último comentário, e têm a display `block_landing` nas
    categorias; a instalação do módulo mantém o contrato anterior.
- Nenhuma dessas falhas vem dos commits de T19–T25, que só tocam
  `CHANGELOG.md` e `docs/ROADMAP.md`. A correção pertence aos commits que
  alteraram cada contrato acima.

## `0.1.0-dev.238` — rodar Kernel e Unit do Fórum e registrar o resultado medido — 2026-10-22

- Documento apenas; nenhuma alteração de código, configuração ou Runtime.
- Kernel: `tests/src/Kernel` — 34 testes, 758 asserções, OK com 25 depreciações
  conhecidas (storage de `comment.comment_body` sem schema instalado no Kernel;
  já presentes antes desta sequência).
- Unit: `tests/src/Unit` — 105 testes, 656 asserções, 7 falhas em
  `ForumTopicDisplayConfigTest`, todas por expectativas desatualizadas em
  relação a commits de funcionalidade anteriores a esta sequência:
  `ad33ab56` inclui `aculta_forum.admin_revisionable_posts` no menu sem
  atualizar a lista esperada; outras divergem da configuração vigente de janela
  de edição, consumidor de upload (`forum`) e filtros da View pública.
- Os commits de T19–T25 tocaram somente `CHANGELOG.md` e `docs/ROADMAP.md`;
  logo as falhas não são regressão desta sequência. Ficam registradas como
  pendência de teste, para correção junto do commit que alterou cada contrato,
  e não foram "consertadas" aqui para não reescrever trabalho paralelo.

## `0.1.0-dev.237` — registrar o bloqueio de T22–T24 por dependência de T21 — 2026-10-22

- Documento apenas. T22 (isolamento de contexto), T23 (smoke de comentários com
  moderação inicial) e T24 (citação em contexto) dependem de T21; sem campo e
  provider da Wiki não existe o lado Wiki do isolamento nem cenário HTTP a
  registrar. T24 já é condicionada pela especificação a T19–T23 verdes.
- Nenhum Kernel, Unit ou Runtime executado nesta etapa.

## `0.1.0-dev.236` — registrar o bloqueio dos comentários da Wiki — 2026-10-22

- Documento apenas. T21 está bloqueada: `wiki_entry` não tem instância de
  `field_aculta_discussion`; as instâncias existentes são `revista`,
  `aculta_forum_topic` e `aculta_lms_topic`.
- O `CONTEXTS.md` descreve o contexto `wiki` como contrato futuro que
  "aguarda a reimplementação da Wiki" e usa `author_wiki`, papel ausente de
  `config/sync`. A moderação do `aculta_user` é genérica ao campo, mas criar a
  instância e o provider equivale a reimplementar a Wiki.
- T22, T23 e T24 dependem de T21 e ficam bloqueadas na sequência. Sem alteração
  de código, configuração ou Runtime.

## `0.1.0-dev.235` — registrar o bloqueio do upload contextual da Wiki — 2026-10-22

- Documento apenas. T20 está bloqueada: `wiki_entry` não tem campo de envio do
  usuário, e `field_wiki_image` é `entity_reference_autocomplete` para Media
  `image` existente (`auto_create: false`).
- O papel `email_confirmed` não tem `create media` nem `edit own media`, então
  não há envio cuja Media se possa publicar com a página visível sem criar
  permissão e fluxo novos.
- A aba por consumidor é administrativa (`UserUploadSettingsForm::CONSUMERS`);
  registrar `wiki` antes do consumidor criaria política sem envio que a use.
- Decisão humana pendente sobre campo de upload e permissão de Media do
  verbete. Sem alteração de código, configuração ou Runtime.

## `0.1.0-dev.234` — localizar a Wiki e registrar pendências de upload e comentários — 2026-10-22

- Documento apenas. A Wiki não tem módulo nem pasta `wiki`: é conteúdo e
  configuração do `aculta_portal` — Node `wiki_entry` ("Verbete", revisável,
  workflow `wiki_editorial`), rotas `/wiki`, `/wiki/busca` e `/wiki/busca/previa`
  no `WikiController`, campos `field_wiki_*`, Media `field_wiki_image` e purpose
  Domain `wiki420`.
- Regras ativas: `web/modules/custom/aculta_portal/AGENTS.md` e a seção
  "Wiki (regra permanente)" de `docs/operations/AGENT-CONTENT.md`, indexada em
  `AGENT-POLICY-INDEX.md`. Não existe `AGENTS.md` específico de Wiki, e
  `AGENT-CONTENT.md` confirma que a implementação é de conteúdo, não de módulo.
- Pendência de upload (T20): `wiki_entry` não tem campo de envio do usuário;
  `field_wiki_image` é referência a Media editorial. Não há superfície para a
  política central `aculta_portal.user_upload_policy` sem criar o campo ou
  decidir que o Media editorial fica fora da política.
- Pendência de comentários (T21–T24): `wiki_entry` não tem campo Comment nem
  `field_aculta_discussion`, e `PublicationContextProvider::HOST_BUNDLES` cobre
  só `revista`. A moderação de primeiro comentário do `aculta_user` é genérica ao
  campo, mas a Wiki precisa de instância do campo e de provider/decisão de
  contexto. O `CONTEXTS.md` já descreve a Wiki como contexto contemplado; a
  divergência fica registrada para decisão humana, não corrigida aqui.
- Nada implementado; sem alteração de código, configuração ou Runtime.

## `0.1.0-dev.233` — registrar escopo mínimo da busca e das notificações privadas LMS — 2026-10-22

- Documento apenas. A seção dev.13 do ROADMAP passa a listar o que falta para
  a busca privada (índice/View por Group, filtro de moderação, reindexação,
  cache por conta) e para as notificações (evento do Comment LMS, assinatura,
  idempotência, revalidação de acesso no envio) e fixa o menor recorte aceitável.
- Busca mínima: a partir do curso/turma, com Group explícito, somente tópicos
  LMS válidos e Comments publicados, revalidando Entity Access por conta e
  falhando fechado; sem busca global nem entre cursos.
- Notificações mínimas: reutilizar Flag/`message_subscribe` e o worker de
  `aculta_messenger`, avisar só a primeira resposta publicada a quem segue e
  ainda pode ler, nunca fila de moderação, com revalidação no envio e
  idempotência por (Comment, destinatário).
- Nada implementado; sem alteração de código, configuração ou Runtime.

## `0.1.0-dev.232` — preencher a citação antes do processamento do text_format — 2026-10-22

- `ForumQuoteHooks::commentFormAlter()` roda dentro de
  `FormBuilder::prepareForm()`, antes de `doBuildForm()`, quando o elemento
  `text_format` ainda guarda `#default_value` na própria raiz e só depois copia
  as propriedades para os filhos `value`/`format`. Como uma resposta nova tem
  corpo vazio, esse `#default_value` é `NULL`; `isset()` dava falso e o hook
  retornava sem preencher. A checagem passou a usar `array_key_exists()`,
  preservando o suporte ao formato já processado (`widget[0]['value']`).
- Runtime LMS (`https://aculta.toca.net.br`, Tópico 532 na Turma 59 do Curso
  58): o Comment publicado 122 recebe um único link `Citar` com `quote=122`; o
  Comment pendente 123 não recebe link e a citação responde 403. O GET de
  citação retorna 200 com o textarea preenchido:
  `<blockquote><p>Conteúdo publicado da fixture T12.</p><cite>t12.aluno.20261022</cite></blockquote><p></p>`.
  O POST grava a resposta (303 para `#comment-124`, `pid` 122, `status: 0`
  pela moderação da Turma).
- Forasteiro autenticado: tópico 403, citação 403 e POST 403, sem o texto
  publicado na resposta. Educador membro do Curso, mas não da Turma, também
  recebe 403 no tópico e na citação.
- Unit `ForumQuoteHooksTest` cobre as duas formas do elemento, incluindo
  `#default_value: NULL`: 7 testes, 25 asserções; PHPCS PASS. Fixture (dois
  conjuntos de usuários, grupos, tópico e Comments) removida; o Runtime voltou
  a 0 tópicos LMS e 0 Comments de discussão.

## `0.1.0-dev.231` — revalidar acesso de anexos pelo host atual

- Media `forum_attachment` só pode ser visualizada no purpose `forum`. Em cada
  acesso, o Fórum confirma que existe referência atual em Comment, ForumPost ou
  Gallery ligada a conteúdo publicado e acessível anonimamente; isso evita que
  publicação Media antiga contorne uma mudança posterior de grants do tópico.
  A rota canônica retorna 404 fora do purpose Fórum via `ContentPurposeResolver`;
  o download privado permanece protegido pelo access de Media/File. A inspeção
  do Core instalado confirmou que `FileDownloadHook` exige referência da File e
  `FileAccessControlHandler` revalida acesso `view` à entidade referenciadora e
  ao campo; a referência à Media aciona a política deste módulo. Os dois storages
  de arquivo do bundle declaram `uri_scheme: private` no install e no Sync.
  Acesso varia por Domain e não é cacheado entre requests. No Runtime de teste
  `toca`, rebuild de cache registrou o hook `media_access`; há 0 Media
  `forum_attachment`, então a revisão HTTP de publicação, download e grants
  ainda depende de fixture autorizada. Nenhum conteúdo foi criado ou alterado.

## `0.1.0-dev.230` — alinhar guardrails e roadmap do Portal

- Contratos gerais e políticas locais deixam de descrever `drupal/forum` como
  implementação vigente e passam a apontar para a reconstrução aprovada, a
  coexistência temporária de Comments/ForumPost e a sequência atual do módulo.
  A fixture do ForumPost também passa a declarar o pacote próprio exigido pelo
  guardrail de módulos ACULTA. Sem alteração Runtime.

## `0.1.0-dev.229` — agregar último post sem subconsulta correlacionada

- A consulta da página agrega a data mais recente por tópico e junta o ID de
  desempate por uma relação agregada, em vez de executar uma subconsulta
  correlacionada para cada tópico. Filtros de publicação/idioma, contagem,
  ordenação de desempate e acesso permanecem iguais. Lint e gates estáticos;
  sem testes automatizados ou alteração Runtime.

## `0.1.0-dev.228` — preparar autores uma vez por View

- A pré-carga de estatísticas, ForumPosts, contas e autores históricos da página
  da View agora roda uma vez por instância de `ViewExecutable`; cada linha
  continua escolhendo sua atividade e cartão pelas mesmas regras. A referência
  é uma `WeakMap`, limitada ao ciclo de vida da View. Sem mudança Runtime.
- Lint PHP e gates estáticos; sem testes automatizados.

## `0.1.0-dev.227` — evitar contagens repetidas do mesmo post pai

- Durante a renderização de uma página da thread, a posição de um post pai fora
  do pager atual é calculada uma vez por ID. Respostas distintas ao mesmo post
  pai deixam de repetir a mesma consulta de contagem; estado fica restrito à
  chamada atual de `build()`. Sem alteração de acesso ou destino dos links.
- Lint PHP e gates estáticos; sem testes automatizados ou alteração Runtime.

## `0.1.0-dev.226` — remover limite agregado de descrições da galeria

- O textarea não limita mais o total de caracteres com teto fixo que também
  limitava indiretamente a quantidade de imagens. Cada linha continua validada
  no servidor até 512 caracteres, e cardinalidade/tamanho dos arquivos seguem
  a política central. Sem Runtime/testes.

## `0.1.0-dev.225` — variar cache pela query preservada nos links

- Links entre posts usam `getUpdatedParameters()` e mantêm todos os parâmetros
  da requisição; a thread agora varia por `url.query_args`, incluindo filtros
  locais além dos pagers. O Core aplica o mesmo contexto aos seus pagers
  ([API](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Pager%21PagerPreprocess.php/class/PagerPreprocess/11.x)).
  Esta abrangência substitui a variação apenas pelos IDs 0 e 1 da dev.224.
  Sem Runtime/testes.

## `0.1.0-dev.224` — variar cache pelo pager de Comments

- A thread agora varia também por `url.query_args.pagers:0`, pois os links para
  posts pais preservam o pager Core de Comments. Evita reutilizar links de uma
  página de Comments em outra; Core define contexto calculado por pager ID
  ([API](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21Context%21PagersCacheContext.php/class/PagersCacheContext/11.x)).
  Sem Runtime/testes.

## `0.1.0-dev.223` — manter pagers ativos nos links entre posts

- Links para post pai sempre atualizam o pager da thread pelo Core, inclusive
  quando o post já está na página atual; preservam pager de Comments e query da
  thread. Evita voltar à página zero e perder a âncora. Sem Runtime/testes.

## `0.1.0-dev.222` — redirecionar post publicado à página correta

- Após publicar ou editar um post publicado, o formulário calcula a página da
  thread e redireciona ao pager limpo do tópico com a âncora estável. Sem
  Runtime ou testes automatizados.

## `0.1.0-dev.221` — isolar também o link de não lidas

- `ForumUnreadPosts` usa o mesmo estado de pager limpo do tópico empregado pela
  busca, sem transportar para a rota de destino a página/filtros da View de não
  lidas. Sem Runtime ou testes automatizados.

## `0.1.0-dev.220` — aplicar purpose também no cálculo da página

- `getPageForPost()` agora reutiliza a validação de tópico público da thread,
  incluindo publicação, associação a Domain do purpose `forum` e purpose atual.
  Evita calcular a posição do post em contexto/domínio incorreto. Sem Runtime ou
  testes automatizados.

## `0.1.0-dev.219` — isolar a paginação do destino da busca

- Links entre `/pesquisar` e o tópico montam `page=0,{página}` para iniciar o
  pager de Comments no destino em zero e definir somente o pager da thread.
  Evita carregar paginação e filtros da requisição de busca; substitui a
  variação ampla de cache introduzida em dev.218. Sem Runtime/testes.

## `0.1.0-dev.218` — variar o link da busca por query string

- O formatter varia sua cache pelos parâmetros da URL usados pelo pager ao
  montar o destino da resposta. Sem alteração no Runtime ou testes.

## `0.1.0-dev.217` — abrir na página da resposta encontrada

- O link de busca calcula a página da resposta usando a ordenação e o pager da
  thread, preserva os demais parâmetros de pager e mantém a âncora estável.
- A renderização inclui `aculta_forum_post_list` para invalidar links quando a
  lista muda. Sem alteração no Runtime ou testes automatizados.

## `0.1.0-dev.216` — compartilhar a paginação da thread e dos não lidos

- O tamanho de página e o ID do pager são definidos uma vez no builder da
  thread. O cálculo de página não lida e o link ao primeiro post novo consomem
  os mesmos valores e `PagerManagerInterface`, evitando divergência de âncora.
- Sem mudança de URL/comportamento esperado, Runtime ou testes.

## `0.1.0-dev.215` — invalidar a cache das listas ao publicar ForumPost

- A View pública usa um plugin cache tag-based que também inclui
  `aculta_forum_post_list`; salvar ou remover post invalida resultados e saída
  que dependem de contagem, atividade e ordenação por ForumPost.
- Instalações novas, Sync e atualização `9005` usam o mesmo plugin. Runtime de
  teste `toca`/`aculta_runtime`: update aplicado; o plugin foi descoberto após
  cache rebuild e `ViewExecutable::getCacheTags()` retornou
  `aculta_forum_post_list` e `node_list`. Backup da View ativa em
  `/home/aculta/private/aculta/forum-public-topics-before-9005-20261022.yml`.
  Nenhum tópico/post criado. Sem testes automatizados.

## `0.1.0-dev.214` — remover classes de apresentação do Fórum

- Scripts de progresso/histórico localizam posts pelo marcador funcional existente
  `data-aculta-forum-post-timestamp`; classes de apresentação sem consumidores
  foram removidas do markup do módulo.
- `field-name` permanece apenas na comparação de revisões porque o CSS instalado
  do módulo Diff o consome; o gate registra essa exceção de integração. Sem testes.

## `0.1.0-dev.213` — corrigir níveis semânticos dos títulos de categoria

- `/categorias` usa H2 para cada categoria-pai e H3 para os links das
  categorias; a atualização `9004` ajusta a View existente sem tocar no bloco.
- Firefox ESR/X11 no Runtime de teste: 12 cartões em cinco grupos, sem overflow
  horizontal em 320, 375, 390, 768, 1024 e 1440 CSS px. Capturas privadas em
  `/home/aculta/private/aculta/visual-tests/forum-categories-20261022`.
  O banner global de consentimento aparece sobre o conteúdo até a escolha da
  pessoa; modo escuro, zoom e aparelho real continuam pendentes. Sem testes.
- A revisão posterior do Fórum em 2026-10-22 confirmou 15/15 verificações
  Firefox ESR/X11 em 390/768/1024 px, com capturas claras e escuras e sem
  overflow. A sobreposição foi corrigida no tema ACULTA420 0.6.1-dev.3;
  capturas inspecionadas em 390 px (claro/escuro) e 1024 px (escuro) mostram os
  cartões livres. Zoom e aparelho real continuam pendentes.

## `0.1.0-dev.212` — aplicar agrupamento no Sync e em instalações existentes

- A definição agrupada de `/categorias` também foi registrada em
  `config/sync`; a atualização `9003` adapta a View já instalada sem alterar
  `block_landing`, o display padrão ou os filtros/acesso existentes.
- Runtime de teste: atualização `9003` aplicada após conferir View, versão de
  schema e backup do YAML ativo. Categorias com hierarquia real disponíveis
  para revisão visual. Testes automatizados não executados.

## `0.1.0-dev.211` — agrupar categorias por categoria-pai

- A página `/categorias` agrupa cartões pela relação Parent do Taxonomy. Cada
  grupo tem título semântico; termos raiz ficam em “Categoria raiz”. O display
  do bloco lateral não muda. Nenhuma contagem de tópico foi adicionada, pois
  exige agregação que respeite Node Access e Domain.
- Configuração apenas no Views Core; sem rota ou storage novo. A análise YAML,
  Drupal 11+, status index, design foundations e SDC schemas passam. O gate
  módulo-tema aponta dívida preexistente; o gate Foundation ainda espera a
  versão anterior do tema no Runtime. Inspeção visual com termos filhos
  continua pendente. Sem testes.

## `0.1.0-dev.210` — carregar autores da lista de tópicos em lote

- A preparação da View coleta autores User e históricos das respostas recentes
  da página e carrega entidades em lotes, junto com os ForumPosts já agregados.
  Remove consultas individuais de usuário/ForumPost por linha, sem mudar as
  regras de seleção de última atividade ou o builder proprietário `aculta_user`.
- O README esclarece o estado real da View de categorias: cartões indicam a
  categoria-pai; agrupamento hierárquico e contadores de tópicos seguem pendentes.
- Não houve alteração Runtime. Validação integrada/performance permanece
  pendente; não foram adicionados nem executados testes.

## `0.1.0-dev.209` — apresentar a autoria da atividade mais recente

- O cartão da listagem pública compara timestamps de tópico, Comments e
  ForumPosts e apresenta a identidade de quem publicou a resposta mais recente.
  Posts revisionáveis resolvem autoria User ou histórica pela Entity API e pelo
  `aculta_user.author_card_builder`; Comments legados usam o UID do agregado
  Core. Tópicos sem Comment publicado não tratam o autor do tópico como autor de
  resposta, e edição posterior do tópico não recebe cartão de resposta incorreto.
- Campos ocultos da View fornecem NID e timestamp Core diretamente pelo handler
  Views, inclusive quando excluídos do template. O agregado de ForumPost mantém
  uma consulta em lote por página.
- Não houve alteração Runtime. Validação integrada/browser pendente; não foram
  adicionados nem executados testes.

## `0.1.0-dev.208` — incluir ForumPost na atividade e contagem de tópicos

- A listagem pública combina Comments publicados e ForumPosts publicados na
  contagem, ordenação por respostas e filtro “Sem resposta”. A atividade recente
  considera a atualização do tópico, o último Comment e o último ForumPost.
- Agregados de ForumPost consultam somente status publicado e idioma padrão;
  filtros públicos existentes de acesso e Domain permanecem no View. O cartão
  de autor continua baseado no último Comment até que a View exponha e compare
  corretamente a autoria da atividade mais recente.
- Não houve alteração Runtime. Não foram adicionados nem executados testes; a
  validação integrada da View, contagem, ordenação e filtro permanece pendente.

## `0.1.0-dev.207` — incluir ForumPost na lista de não lidas

- A View autenticada agora considera posts revisionáveis publicados no filtro
  de tópicos não lidos e mostra a contagem com link para o primeiro post novo,
  incluindo o pager da thread. Uma consulta em lote compara `first_published`
  com o recibo History e segue o `HISTORY_READ_LIMIT` instalado; acesso do tópico
  continua sob Node grants e posts públicos seguem as regras do
  `ForumPostAccessHooks`.
- A View continua sem cache compartilhado por usuário. A validação integrada de
  consulta, grants, paginação, link e banco MariaDB ainda está pendente; sem
  testes ou alteração Runtime nesta etapa.

## `0.1.0-dev.206` — incluir ForumPost no progresso de leitura

- A thread revisionável publica o NID do tópico e o `first_published` de cada
  post. Para contas autenticadas, um comportamento cliente usa os recibos da API
  History para aplicar `.new`; a navegação de progresso reconhece posts e
  Comments. Dados pessoais de leitura não são renderizados no HTML cacheável.
- A lista `/discussoes/nao-lidas` e sua contagem continuam cobrindo Comments:
  os plugins History consultam `comment_field_data` e
  `comment_entity_statistics`, não `ForumPost`. Sem teste ou mudança Runtime.

## `0.1.0-dev.205` — integrar a primeira publicação de ForumPost às conquistas

- `aculta_game 0.1.0-dev.16` reutiliza o trigger `forum_comment` quando um post
  público revisionável é publicado pela primeira vez; Game continua opcional e
  não cria dependência inversa. O ownership e a matriz Advanced Forum agora
  distinguem ForumPost público dos Comments contextuais. Runtime e testes não
  foram executados nesta etapa.

## `0.1.0-dev.204` — rastrear mudanças no tópico hospedeiro da busca

- O índice inclui o campo oculto `post_topic_changed` (`topic:entity:changed`)
  para o rastreador de referências do Search API enfileirar posts quando o Node
  hospedeiro é salvo. Um rebuild de grants sem salvar Nodes ainda pede
  reindexação explícita. Nenhuma reindexação/alteração de Runtime foi feita.

## `0.1.0-dev.203` — alinhar README à busca de posts revisionáveis

- A entrada principal do módulo agora descreve a busca por tópicos e posts,
  o link contextual do resultado e o filtro de grants/publicação anterior à
  paginação. A matriz automatizada continua pendente antes de reindexar ou
  ativar a configuração no Runtime.

## `0.1.0-dev.202` — incluir posts revisionáveis na busca pública

- O índice e a View Search API incluem `aculta_forum_post`, com assunto/corpo,
  categoria e Domain derivados do tópico hospedeiro; filtros fixos continuam
  no servidor. O processor aplica grants do Node e publicação do tópico/post
  antes do pager. Resultados usam view mode próprio e link para a âncora do post.
- A checagem de acesso da View segue ativa. A matriz automatizada de grants,
  publicação e purpose precisa passar antes de reindexação/Runtime; sem testes
  ou alteração no Runtime nesta etapa.

## `0.1.0-dev.201` — preservar autoria histórica em Comments anônimos

- A migração aceita `uid=0` quando a origem contém uma referência existente a
  `aculta_legado_autor`, que é mapeada para `ForumPost.legacy_author`. Sem
  usuário ou referência válida, interrompe a linha; a auditoria agora conta
  somente esses casos sem representação histórica válida.
- Sem execução de migration, Runtime ou testes nesta etapa.

## `0.1.0-dev.200` — bloquear perda de autoria anônima na migração

- A auditoria conta Comments elegíveis sem proprietário. O destino da migration
  interrompe a linha quando `uid` é zero e não há referência de autoria
  histórica válida; não converte silenciosamente a autoria em conta genérica
  nem migra contato pessoal.
- Sem execução de migration, Runtime ou testes nesta etapa.

## `0.1.0-dev.199` — evitar link duplicado no cartão PDF

- Cartões construídos pelo `aculta_pdf_viewer` mantêm as ações próprias de
  download e abertura sem receber um segundo `file_link` genérico. O link Core
  continua no fallback quando o builder nega o PDF; DOCX/planilhas continuam
  usando-o como fallback local.
- Sem alteração Runtime; revisão visual do cartão pendente.

## `0.1.0-dev.198` — integrar cartão PDF aos anexos do Fórum

- O formatter de `forum_attachment` delega PDFs reconhecidos pelo MIME e pela
  extensão ao builder `aculta_pdf_viewer`, que confere Media, campo, File,
  esquema privado, acesso e cache; imagens e previews Office mantêm seus
  builders próprios. Se o viewer negar ou não conseguir construir o cartão, o
  formatter preserva o link Core autorizado.
- O Fórum declara dependência de `aculta_pdf_viewer`. Sem alteração Runtime;
  cartão, miniatura, janela e fallback ainda precisam de validação integrada.

## `0.1.0-dev.197` — limitar memória na sincronização de anexos por tópico

- Ao mudar a publicação de um tópico, os sincronizadores de Media e Gallery
  agora percorrem Comments e posts em lotes de 100 por ID, com limite máximo
  capturado no início. A sincronização preserva o mesmo escopo e estado público
  e deixa de carregar todas as entidades do tópico na memória de uma vez.
- Sem alteração de Runtime. Não executei testes nesta etapa.

## `0.1.0-dev.196` — restringir diálogos de upload a contas autenticadas

- Os acessos dos diálogos de anexo e galeria agora exigem conta autenticada,
  além de formato `forum` e purpose Fórum. A decisão inclui o contexto de cache
  `user.roles:authenticated`, conforme o contrato central de uploads por
  pessoas autenticadas.
- Sem alteração de Runtime; não executei teste autenticado nesta etapa.

## `0.1.0-dev.195` — auditar os dois watermarks dos avisos

- A auditoria somente leitura do Messenger também classifica `ultimo_post`,
  separadamente de `ultimo_comentario`, como post do Fórum elegível, post fora
  do escopo, referência ausente ou post associado a outro tópico. Isso dá
  visibilidade aos avisos já vinculados a posts revisionáveis sem expor IDs,
  destinatários ou conteúdo.
- Sem alteração de Runtime ou execução da auditoria nesta etapa.

## `0.1.0-dev.194` — saída segura em falhas da auditoria migratória

- Falhas em `aculta-forum:audit-comment-migration` deixam de imprimir a
  mensagem da exceção, que poderia conter parâmetros ou identificadores. A
  saída informa somente que a auditoria falhou sem alterar dados; o log guarda
  a classe da exceção, sem mensagem ou stack trace.
- Sem alteração Runtime ou execução da auditoria nesta etapa.

## `0.1.0-dev.193` — respeitar idade máxima dos Files temporários

- A limpeza de arquivos ainda temporários do diretório privado usa
  `system.file:temporary_maximum_age` e o campo `changed`, como o cron File do
  Core. Idade zero desativa essa limpeza; imagens passam pelo `aculta_image`.
  Media/Galleries órfãos continuam com retenção própria de uma hora.
- Atualizado o contrato de uploads do Fórum para distinguir as duas retenções.

## `0.1.0-dev.192` — manter a classificação de imagem do upload

- O diálogo classifica o Media de imagem pelo controle que recebeu o arquivo,
  validado por `aculta_image`, em vez de depender somente do MIME reportado.
  O formatter também reconhece essa referência explícita ao mesmo File; MIME
  continua como fallback para imagens existentes.
- Sem upload autenticado ou revisão visual nesta etapa.

## `0.1.0-dev.191` — revalidar dados da galeria no callback AJAX

- O callback repete a validação de título não vazio (até 255 caracteres) e de
  uma descrição alternativa por imagem (1–512 caracteres) antes de criar Media
  e Gallery. Lista vazia, arquivo alterado/removido ou política incompatível
  falham mantendo o diálogo com erro.
- Sem upload autenticado, revisão visual ou fixture Runtime nesta etapa.

## `0.1.0-dev.190` — revalidar anexo antes de criar Media

- O callback AJAX do anexo agora confirma novamente exatamente um arquivo,
  existência, política central de extensão/tamanho/inspeção raster e descrição
  alternativa antes de criar Media. Alteração entre a validação do Form API e a
  gravação falha fechada e mantém o diálogo com erro recuperável.
- Sem alteração Runtime ou upload autenticado nesta etapa.

## `0.1.0-dev.189` — validar tamanho alternativo no servidor

- O formulário de anexo agora exige texto alternativo não vazio de até 512
  caracteres no servidor, limite correspondente ao widget de imagem do Core.
  O limite HTML sozinho não impedia um POST maior.
- Sem alteração Runtime ou upload autenticado nesta etapa.

## `0.1.0-dev.188` — refletir política de imagens nos diálogos

- O anexo só mostra o campo de descrição quando a política efetiva habilita
  upload de imagem. O diálogo de galeria explica quando nenhuma extensão de
  imagem está autorizada e não exibe ação para criar uma galeria vazia.
- Sem alteração no Runtime ou smoke autenticado nesta etapa.

## `0.1.0-dev.187` — alinhar tamanho do texto alternativo ao Core

- O diálogo de galeria informa e valida o limite de 512 caracteres por
  descrição, igual ao widget de imagem do Drupal Core. Unicode é contado pelo
  utilitário do Core antes da criação de Media.
- Sem smoke autenticado ou alteração no Runtime nesta etapa.

## `0.1.0-dev.186` — descrever cada imagem enviada à galeria

- O diálogo de galeria exige uma descrição não vazia por imagem, na mesma
  ordem do upload, e salva cada texto no campo alternativo correspondente.
  Antes, todas as imagens recebiam um rótulo genérico baseado em título e
  posição.
- O callback revalida arquivos e política antes de criar entidades. O limite
  individual de 512 caracteres foi alinhado ao widget Core em dev.187. Sem smoke
  autenticado ou alteração no Runtime nesta etapa.

## `0.1.0-dev.185` — exigir texto alternativo para imagem anexada

- O diálogo contextual de anexo pede uma descrição acessível ao enviar imagem e
  a validação do servidor exige o texto antes de criar Media. Upload de PDF e
  Office continua sem esse campo; a galeria mantém seu fluxo próprio.
- Sem alteração no Runtime nesta etapa. O smoke visual e o upload real seguem
  pendentes.

## `0.1.0-dev.184` — liberar submissão, edição e moderação revisionáveis

- O GET de submissão carregava `ForumPostSubmissionForm`, mas PHP fatalizava ao
  declarar `$messenger` readonly em conflito com `FormBase::$messenger`. A
  dependência foi renomeada; o construtor também usava `TimeInterface` no
  namespace Core, enquanto `ContentEntityForm` injeta
  `Drupal\Component\Datetime\TimeInterface`.
- O controller de edição chamava `EntityInterface::access()` sem solicitar
  `AccessResult`, depois invocava `isAllowed()` sobre o booleano. Acesso de
  revisão de post pai tinha a mesma omissão; ambos agora passam `TRUE`.
- O form mode e display `public_submission` não estavam no Config Sync nem no
  Runtime. O form display limita novos envios/edições a assunto e corpo; os
  dois objetos foram adicionados a `config/sync` e importados isoladamente no
  Runtime, sem importar a configuração global.
- Runtime de teste `toca`, Drupal 11.4.10: GET autenticado do formulário,
  submissão com CSRF (303), estado inicial `review`, fila acessível em `main`,
  aprovação pelo papel `content_editor` (revisão 5 `published`), edição do autor
  (revisão 6 `published`, log preservado) e GET de resposta contextual (200).
  A página pública exibiu o corpo; a revisão aprovada não permaneceu na fila.
- Fixtures removidas após confirmar UUIDs. O Runtime voltou ao baseline de zero
  tópicos, posts, Comments dedicados e anexos e 10 usuários; a única métrica
  diária de post criada pela fixture foi reposta a zero pelo `AggregateStorage`.
  Sem envio de e-mail. O Runtime usa PHP 8.4, abaixo da baseline 8.5 do projeto.
  Estado Dev; upload e previews continuam pendentes.

## `0.1.0-dev.183` — corrigir resolução dos controllers públicos

- As rotas de submissão e edição agora usam serviços públicos explícitos com
  `service:method`; argumentos continuam injetados por definição de serviço.
  No Core 11.4.10, o uso anterior de `Class::method` caía no `create()` de
  `ControllerBase`, cujo autowiring por tipo não resolve IDs específicos do
  container, causando exceção para o resolver de contexto.
- Auditoria pelo `callable_resolver` confirmou 14 controllers/rotas do Fórum sem
  falhas. A requisição HTTP autenticada à submissão segue respondendo 500 vazio,
  sem novo evento no watchdog/logs consultados; a abertura e gravação do
  formulário, a moderação e o motivo do 500 continuam pendentes.
- Fixture removida após conferir UUIDs; Runtime voltou ao baseline: zero
  tópicos, posts, Comments dedicados e anexos, dez usuários. Nenhum post foi
  criado. Estado Dev.

## `0.1.0-dev.182` — corrigir renderização de posts no Runtime

- `ForumPostThreadBuilder` agora injeta `Drupal\Core\Session\AccountInterface`;
  o tipo anterior apontava para um namespace inexistente e fazia a construção
  do serviço falhar, retornando HTTP 500 nas páginas do Fórum.
- Cada post usa `EntityViewBuilder::view()` para manter o callback de pré-render
  no item renderizado. Extrair itens de `viewMultiple()` descartava o pré-render
  do array pai e deixava o article vazio. A documentação oficial do Core
  descreve esse processamento diferido em
  [EntityViewBuilder::viewMultiple()](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityViewBuilder.php/function/EntityViewBuilder%3A%3AviewMultiple/main).
- No Runtime de teste, importação parcial alinhou `field_gallery_purpose` e os
  displays padrão de `aculta_forum_post` e `forum_attachment`; as duas galerias
  existentes mantiveram o campo vazio e suas 97 imagens intactas.
- Firefox ESR headless confirmou tópico/post públicos, markup do viewer DOCX e
  ausência de overflow em viewports efetivos 500, 768, 1024 e 1428 CSS px. A
  revisão posterior identificou que a primeira fixture tinha proprietário
  incompatível com o PHP-FPM. Com uma fixture criada sob `aculta`, a entrega do
  DOCX retornou HTTP 200 em query e path de `system.files`; o Firefox exibiu
  controles e fallback, sem confirmar a renderização do documento. A tentativa
  em 320/390 px foi limitada pelo viewport mínimo de 500 px do Firefox
  headless. Preview Office permanece pendente.
- Fixture temporária removida: tópico 524, post 1, Media 243 e File 586; o
  Runtime voltou a zero tópicos, posts, comentários dedicados e anexos. Raiz e
  `/categorias` retornaram 200; URL do tópico removido retornou 404. PHP lint
  passou; testes não foram repetidos nesta revisão.
- Fixture de revalidação DOCX removida: tópico 525, post 2, Media 244 e File
  587. A verificação final confirmou zero tópicos, posts e anexos de Fórum, zero
  usos e ausência do arquivo no disco; as 97 imagens da galeria permaneceram.
- Na revalidação seguinte, um DOCX OOXML completo retornou HTTP 200 pelas duas
  formas Core e Firefox ESR confirmou que o conteúdo foi renderizado. A captura
  mostrou que o iframe sandboxed era removido mesmo após `done`; correção
  versionada como `aculta_document_viewer` dev.12 o mantém no sucesso e o remove
  em erro/timeout. O iframe permaneceu, status foi `done`, e a página não teve
  overflow horizontal no viewport efetivo de 1354 CSS px. Fixture removida:
  tópico 526, post 3, Media 245 e File 588; categorias 11 e 18 preservaram 63 e
  34 imagens e a URL/arquivo removidos retornaram 404.

## `0.1.0-dev.181` — conectar preview local de Office nos anexos do Fórum

- O formatter do Media `forum_attachment` delega DOCX e planilhas aos builders
  do `aculta_document_viewer` depois de verificar acesso ao Media, campo e
  download. Validação do formato/arquivo, iframe isolado e link protegido são
  mantidos pelo viewer; formatos não suportados ou inválidos continuam com link
  do Core.
- A dependência do viewer fica explícita no manifest. PDF permanece como link
  até a etapa prevista para integração com o leitor PDF.
- No Runtime de teste (purpose `forum`), treze configurações ausentes foram
  importadas seletivamente: sete objetos de Media/anexo, formato/editor `forum`,
  workflow dos posts, Flag de denúncia e displays padrão. Rebuild descobriu
  formatter e builders DOCX/planilha. Nenhuma importação global foi feita.
- Após comparar papéis ativos com o Sync, o Role API acrescentou somente as
  permissões de Fórum ausentes: `email_confirmed` cria rascunho, envia para
  moderação, edita seu post publicado pela janela aprovada e usa o formato
  `forum`; não aprova, rejeita ou retira posts. `content_editor` recebe as seis
  transições do workflow. Os papéis não foram importados por inteiro. Havia três
  contas `email_confirmed` e uma `content_editor` no Runtime.
- O campo `comment_body` do bundle dedicado `aculta_discussion_post` divergia do
  Sync: permitia `usuario_aculta`, enquanto CKEditor e os diálogos de anexo usam
  o formato `forum`. Após confirmar zero Comments nesse bundle, foi sincronizado
  somente esse objeto de configuração; o Runtime agora restringe o campo ao
  formato `forum`.
- A configuração central `aculta_portal.user_upload` também estava ausente no
  Runtime. Foi criada por importação parcial do Sync, registrando a aba Fórum
  com herança da política global. A comparação confirmou os mesmos nove formatos
  e limite de 10 MiB efetivos antes e depois; agora o override pode ser
  administrado explicitamente.
- O Runtime ainda tem zero `ForumPost` e zero Media `forum_attachment`; o fluxo
  com post/arquivo e a revisão visual seguem pendentes. Kernels de anexos,
  workflow e política de upload passaram: 13 testes / 304 assertions no
  `aculta_simpletest`; 15 depreciações de Core/contrib foram reportadas. A suíte
  de anexos agora instala a definição de `ForumPost` pelo fixture de teste
  existente; nenhuma fixture de conteúdo foi criada no Runtime.

## `0.1.0-dev.180` — preparar migração de denúncias para posts

- Migration própria lê flaggings `sinalizar_comentario` em lotes keyset e
  resolve cada CID pelo mapa de `aculta_forum_comment_posts`. O destino grava
  `sinalizar_post_forum` por Entity API com dono, sessão, escopo e timestamp
  originais; a guarda de presave permite somente o mesmo objeto validado numa
  chamada de importação, conferindo origem, destino e tópico.
- Denúncias globais, anônimas sem sessão, com timestamp inválido, usuário
  ausente, duplicadas no destino ou sem configuração compatível falham fechadas.
  A auditoria conta flaggings anônimas sem sessão. A API pública do Flag não recebe
  timestamp histórico; a implementação atribui `created` antes do primeiro
  save e emite o ciclo de vida normal do Flag. Isso ainda precisa de prova
  Kernel com dados e revisão dos eventos antes de executar a migration.
- O smoke somente leitura de `aculta-forum:audit-comment-migration` no Runtime
  de teste (purpose `forum`) encontrou 0 Comments elegíveis, 0 posts e 0 flags;
  o acervo vazio não valida casos com associações reais. Nenhuma configuração
  foi importada nem migration executada.
- `migrate` estava desabilitado no Runtime apesar de constar como dependência do
  manifest. Foi habilitado no servidor de teste para descobrir os plugins; a
  source/destination e a migration instanciam corretamente, e a source não
  produz linhas. A configuração nova `sinalizar_post_forum` permanece apenas no
  Config Sync; não foi importada.

## `0.1.0-dev.179` — auditar integridade das denúncias migráveis

- A auditoria somente leitura agora conta flaggings globais, donos de usuário
  ausentes e registros sem timestamp `created` positivo. Os dados são carregados
  por lote e a saída permanece agregada, sem IDs ou identidade dos denunciantes.
  Isso documenta pré-condições para recriar associação, escopo e data sem
  executar a migração.
- Nenhuma configuração foi importada nem migration executada.

## `0.1.0-dev.178` — incluir posts revisionáveis na fila de denúncias

- Flag `sinalizar_post_forum` mostra a ação no display de `aculta_forum_post`;
  o hook restringe a ação a conta com permissão, post publicado/default e tópico
  no purpose `forum`, e recusa alvos históricos também no save de Flagging.
  `aculta_user.ReportQueue` agrupa esse tipo e valida o host sem criar
  dependência reversa do Fórum. A auditoria mapeia a flag antiga de Comment ao
  ID de Flag de destino.
- Migração de flaggings e preservação do timestamp histórico continuam
  pendentes. Nenhuma configuração foi importada nem migration executada.

## `0.1.0-dev.177` — evitar publicação de anexos durante importação

- `ForumAttachmentPublicationHooks` não reconcilia Media/Gallery quando um
  `ForumPost` está marcado como sincronizado pelo Core Migrate. Isso evita
  alterar publicação contextual antes do corte, enquanto Comments legados ainda
  existem; a exclusão normal dos Comments reavalia as referências após importar
  os posts.
- Nenhuma migration foi executada; somente lint/PHPCS e gates estáticos.

## `0.1.0-dev.176` — validar embeds antes de migrar

- O destino verifica em lote os UUIDs de Media e Gallery em todas as traduções
  antes de salvar o post. Embeds sem UUID, referências ausentes ou tipos de
  entidade indisponíveis interrompem a linha, em vez de persistirem markup
  quebrado. Entity Usage rastreia campos `text_long` no save; a publicação
  contextual dos anexos e galerias continua pendente de validação integrada.
- Nenhuma migration foi executada; somente lint/PHPCS e gates estáticos.

## `0.1.0-dev.175` — reconciliar traduções em reexecuções

- Reimportações da migration removem traduções de destino que deixaram de
  existir no Comment de origem, mantendo o conjunto atual igual à origem.
  Tradução padrão divergente falha antes de salvar: o post existente não pode
  trocar idioma padrão implicitamente. A API `removeTranslation()` do Core é
  usada somente para idiomas não padrão ausentes na linha de origem.
- Nenhuma migration foi executada; somente lint/PHPCS e gates estáticos.

## `0.1.0-dev.174` — preservar estado e timestamp na migration

- `ForumPublicationTimeHooks` agora respeita `SynchronizableInterface::isSyncing()`
  para posts revisionáveis: importação não substitui `rejected`/`review` por
  estado inicial da participação nem grava o instante atual como primeira
  publicação. O destino continua importando `first_published`; quando a origem
  não tem esse marcador, `ParticipationEditWindow` usa `created` como limite
  conservador.
- O comportamento veio da integração entre `EntityContentBase::save()` (Core
  Migrate marca a entidade como syncing) e o hook de primeira publicação. Não
  houve execução da migration nem alteração no Runtime.

## `0.1.0-dev.173` — verificar destino das denúncias migradas

- A auditoria estima quantas flaggings de Comments não têm configuração Flag
  para `aculta_forum_post`. A flag atual `sinalizar_comentario` é específica
  para Comment; a fila `aculta_user` também só carrega Node e Comment.
- A Flag API 5.1.1 instalada valida tipo/bundle e duplicidade ao criar uma
  flagging. Seu método público não recebe timestamp histórico. A migração das
  denúncias depende de configuração e integração da fila para posts, além de
  preservar dono/global/timestamp; não foi criada configuração nem flagging.
- Smoke somente leitura no Homelab: 0 flaggings de origem; o caminho do novo
  contador executou, mas não validou compatibilidade com associações reais.

## `0.1.0-dev.172` — identificar pais pela API do Comment Core

- Fonte e auditoria usam `CommentInterface::hasParentComment()` para distinguir
  raízes de referências pai, em vez de interpretar diretamente o valor bruto
  de `pid`. Raízes não geram lookup nem falso positivo de pai ausente; um ID de
  pai não resolvido continua sendo inventariado.
- A API foi conferida no Core 11.4.10 instalado e na
  [interface CommentInterface 11.x](https://api.drupal.org/api/drupal/core%21modules%21comment%21src%21CommentInterface.php/11.x).
- O smoke somente leitura do Homelab repetiu 0 Comments elegíveis e 0 pais
  ausentes; não havia raízes ou relações com dados para cobrir.

## `0.1.0-dev.171` — auditar impeditivos de conteúdo da migration

- A auditoria conta Comments que têm ao menos uma tradução sem corpo, com
  formato diferente de `forum`, ou publicada apesar da marca de rejeição.
  Esses são os mesmos casos que o destino da migration interrompe; contagens
  agregadas permitem detectá-los sem imprimir conteúdo ou IDs.
- No smoke Homelab da dev.170, o acervo elegível estava vazio; estes novos
  cenários também não têm validação Runtime com dados. O comando foi executado
  novamente no URI de teste do Fórum: todas as três contagens foram 0 e nenhuma
  escrita ocorreu.

## `0.1.0-dev.170` — auditar referências de autoria ausentes

- A auditoria paginada verifica em lote se usuários autores e entidades
  `aculta_legado_autor` referenciadas ainda existem. A saída apresenta somente
  contagens, sem IDs ou dados pessoais, para identificar referências que
  reprovariam a validação do destino antes de executar a migration.
- Smoke somente leitura no Homelab (`toca`, URI de teste do purpose `forum`):
  0 Comments elegíveis, 0 referências de usuário/autoria legada ausentes e 0
  posts revisionáveis existentes. O acervo vazio não valida casos com dados.
  Nenhum conteúdo ou configuração do Runtime foi alterado.

## `0.1.0-dev.169` — criar destino multilíngue e migrations de posts/pais

- O destino `aculta_forum_comment_post` grava todas as traduções do Comment em
  um post e uma revisão. Publicado/rejeitado ficam explícitos por idioma;
  Comments não publicados entram em `review`. Formatos diferentes de `forum`,
  corpo vazio e conflito entre publicado/rejeitado falham fechados.
- Duas definições Core Migrate usam um mapa `cid → post ID`; a segunda depende
  da primeira, resolve pais com `migration_lookup` e `no_stub`, e só sobrescreve
  `parent`. Definições não foram executadas; destino/runtime ainda carecem de
  validação de integração.

## `0.1.0-dev.168` — criar fonte Migrate paginada para Comments do Fórum

- O plugin `aculta_forum_comment` percorre Comments elegíveis por CID em lotes
  keyset limitados, fixa o CID máximo inicial e produz uma linha por Comment
  com todas as traduções agrupadas. Inclui somente metadados necessários; não
  extrai nome, e-mail ou hostname do autor.
- Core Migrate passa a ser dependência do módulo e entra na configuração
  sincronizável. O plugin não grava entidades; destino e migrations dependentes
  ainda serão implementados. Sem Runtime ou execução de migração.

## `0.1.0-dev.167` — definir migração dos posts e vínculos em duas passagens

- Cada source row representa um Comment (`cid`) e contém seu conjunto de
  traduções. Um destino próprio cria/atualiza um único post, grava todas as
  traduções e salva uma vez, mantendo mapa simples `cid → post ID`.
- Uma migration dependente resolve `parent_cid` pelo mapa da primeira e aplica
  somente `parent` no destino com `no_stub`, sem depender da ordem dos CIDs nem
  criar posts provisórios para pais fora do escopo. As APIs instaladas e
  upstream confirmam lookup pelo mapa e dependências entre migrations.
- Arquitetura documentada; plugins/configuração e provas ainda pendentes. Sem
  execução de migração ou alteração Runtime.

## `0.1.0-dev.166` — auditar ordem de pais e filhos por CID

- O inventário identifica relações válidas do mesmo tópico em que o CID do pai
  é maior que o do filho. A contagem sinaliza árvores que uma importação
  sequencial por CID não pode resolver por lookup imediato; não expõe IDs.
- Smoke no Homelab (`toca`, Runtime `aculta_runtime`, URI do purpose `forum`):
  auditoria concluiu; zero Comments de origem, zero posts de destino e zero
  relações pai/filho. Somente leitura; nenhuma migração foi executada.

## `0.1.0-dev.165` — definir os limites do adapter Migrate

- A pesquisa do Core 11.4.10 confirma que `content_entity` materializa a lista
  completa de IDs antes de iterar e que `EntityContentBase` importa/salva uma
  linha por vez. Com tradução, o mapa de destino inclui também o idioma; isso
  não resolve sozinho o vínculo de todas as linhas de um Comment ao mesmo post
  de ID automático.
- A implementação precisa de fonte paginada e de estratégia explícita de
  agrupamento/mapa por Comment antes de importar traduções. Não adicionar uma
  definição executável que não garanta lotes e idempotência. Nenhuma migração foi
  executada.

## `0.1.0-dev.164` — permitir traduções de assunto e corpo dos posts

- Os campos `subject` e `body` dos posts revisionáveis passam a aceitar
  traduções, requisito para a futura migração dos idiomas dos Comments. Tópico,
  pai e posição da thread continuam compartilhados entre traduções.
- Mudança de schema pendente de atualização de entidade no Runtime; nenhuma
  migração foi executada.

## `0.1.0-dev.163` — paginar avisos do Messenger na auditoria

- O inventário seleciona avisos por blocos de tópicos e percorre os IDs de cada
  bloco em lotes com limite inicial. Carrega e resolve marcadores junto a cada
  lote; não retém a lista completa de avisos ou marcadores na memória.
- Somente leitura; IDs e dados pessoais não aparecem na saída. Sem Runtime.

## `0.1.0-dev.162` — limitar mapas de relações aos lotes da auditoria

- A análise pai/filho resolve os Comments pais junto a cada lote de origem e
  remove o mapa que retinha a associação de todos os Comments elegíveis. Os
  marcadores Messenger são resolvidos em lotes somente para os CIDs citados.
- O inventário continua somente leitura. Conjuntos únicos de tópicos, UUIDs e
  avisos ainda crescem com os dados necessários ao relatório; sem Runtime.

## `0.1.0-dev.161` — paginar IDs da auditoria de migração

- A auditoria fixa o maior CID de origem no início e busca os Comments em lotes
  por intervalo de IDs, sem materializar de antemão a lista completa de origem.
  O limite exclui inserções concorrentes posteriores ao início do inventário.
- Somente leitura; mapas auxiliares necessários para relações, referências e
  UUIDs continuam proporcionais ao conteúdo analisado. Sem alteração Runtime.

## `0.1.0-dev.160` — mostrar atividade agregada no painel do Fórum

- O painel administrativo apresenta sete dias UTC de tópicos, Comments legados
  e posts revisionáveis a partir de `aculta_statistics`, sem registrar
  identificadores ou autoria. O serviço de Statistics é opcional; sem ele, a
  tabela informa que a série está indisponível. Cache limitado a 60 segundos.
- Eventos de tópicos e Comments sincronizados também são ignorados, evitando
  atribuir importações a atividade nova. Sem rota ou escrita Runtime; testes e
  revisão visual pendentes.

## `0.1.0-dev.159` — emitir atividade de posts revisionáveis

- A criação de `aculta_forum_post` emite `aculta_forum.post_created` com ID e
  instante, sem autor ou conteúdo. Entidades sincronizadas/importadas são
  ignoradas para evitar contar migração como participação nova.
- O contrato de evento permite que `aculta_statistics` conte posts sem depender
  do módulo Fórum. Validação Runtime e testes pendentes.

## `0.1.0-dev.158` — especificar acesso para busca de posts revisionáveis

- A busca pública segue indexando apenas tópicos Node. A documentação registra
  os requisitos de escopo, grants do tópico hospedeiro, paginação, Domain,
  categoria e link canônico que precisam estar cobertos antes de incluir
  `aculta_forum_post`; `content_access` sozinho não cobre essa entidade.
- Pesquisa no Search API 1.41 instalado e na documentação upstream. Sem mudança
  de código funcional ou Runtime.

## `0.1.0-dev.157` — conferir UUIDs de anexos citados pelos Comments

- A auditoria reúne UUIDs únicos de Media e `media_gallery` encontrados nos
  embeds dos Comments elegíveis, conta referências sem entidade correspondente
  e embeds sem UUID. Só imprime contagens; não carrega conteúdo binário, altera
  uso de arquivos ou modifica entidades. Ingestão/reatribuição continua sob
  `aculta_image` e `aculta_gallery`.

## `0.1.0-dev.156` — incluir posts revisionáveis na atividade da conta

- A aba Comentários de Minha Conta passa a combinar Comments públicos e posts
  revisionáveis publicados pelo próprio usuário, ordenados juntos por data.
  Links de posts usam o fragmento canônico `forum-post-{id}` e revalidam acesso
  ao tópico e à publicação no purpose `forum`. `aculta_user` detecta a entidade
  opcionalmente, sem dependência circular. Testes e revisão autenticada pendentes.

## `0.1.0-dev.155` — inventariar agregados de avisos do Fórum

- Quando `aculta_messenger` está habilitado, a auditoria conta avisos
  `forum_resposta` nos tópicos elegíveis e classifica seus marcadores
  `ultimo_comentario` como elegíveis, externos, ausentes ou ligados a outro
  tópico. Não exibe destinatários nem altera avisos. Sem Messenger, informa que
  essa parte do inventário não está disponível.
- No Runtime de testes confirmado, `drush cr` concluiu e a execução encontrou
  zero Comments elegíveis, Flaggings e avisos nos tópicos do Fórum; não houve
  gravação de dados/configuração.

## `0.1.0-dev.154` — inventariar flags antes da migração

- A auditoria soma Flagging entities ligadas aos Comments elegíveis e agrupa
  as contagens pelo ID da configuração de flag. A consulta não divulga quem
  sinalizou o conteúdo; o inventário confirma o vínculo que precisará ser
  recriado no destino, pois o Flag contrib não permite mover uma flagging.

## `0.1.0-dev.153` — distinguir pais existentes fora do escopo

- A auditoria resolve também Comments pais que não entram no escopo elegível.
  Relações com pai existente em outro tópico/purpose agora contam como fora do
  escopo, em vez de pai ausente; a saída continua agregada e sem IDs.

## `0.1.0-dev.152` — auditar Comments para a migração revisionável

- O comando `drush aculta-forum:audit-comment-migration` gera inventário somente
  leitura do escopo, hosts/purposes, estados, idiomas, formatos, autoria
  histórica, timestamps, traduções, embeds Media/Gallery, relações pai/filho e
  posts revisionáveis existentes. A saída contém contagens, sem IDs ou conteúdo;
  nenhuma entidade/configuração é alterada. A migração Migrate API, o mapa
  idempotente e a validação de equivalência seguem pendentes.
- A inspeção do Core 11.4.10 identificou colisões fatais entre propriedades
  promovidas por controladores do Fórum e propriedades já declaradas em
  `ControllerBase`; os nomes das propriedades de DI foram ajustados sem mudar
  os serviços. No Runtime de testes confirmado (`aculta_runtime`), `drush cr`
  concluiu e a auditoria encontrou zero Comments de origem e zero posts
  revisionáveis existentes. Nada foi gravado; o inventário deve ser refeito em
  cada ambiente antes da migração.

## `0.1.0-dev.151` — comparar revisões entre páginas do histórico

- O formulário aceita IDs de duas revisões vistos em quaisquer páginas do histórico e valida no servidor que ambas existam e pertençam ao mesmo post. A rota de comparação repete a validação de vínculo e acesso. Sem mudança Runtime; testes e revisão visual pendentes.

## `0.1.0-dev.150` — navega até respostas em outras páginas

- O contexto da resposta mantém link para o post pai acessível mesmo quando ele está em outra página; o destino usa a mesma ordenação/paginação e preserva os demais pagers e argumentos da URL. Pais ausentes, não publicados ou sem acesso continuam sem URL. Sem rota nova ou alteração Runtime; testes e inspeção visual pendentes.

## `0.1.0-dev.149` — integra anexos e galerias a posts revisionáveis

- O corpo de `aculta_forum_post` aceita apenas o formato `forum`, habilitando os diálogos CKEditor já integrados com `aculta_portal.user_upload_policy`, `aculta_image` e `aculta_gallery`.
- Publicação/despublicação de Media e Gallery acompanha referências em posts publicados e o estado do tópico, além do fluxo existente de Comments. A limpeza preserva arquivos usados por qualquer revisão de post. Runtime e regressão Kernel pendentes; sem migração ou exclusão de dados.

## `0.1.0-dev.148` — permite editar publicação própria

- Posts publicados/default agora mostram ação de edição ao próprio autor enquanto `update` é permitido, incluindo a janela configurável que `aculta_user.ParticipationEditWindow` aplica sobre o access handler.
- A rota `/publicacao/editar?post={uuid}` revalida dono, publicação/revisão default e acesso. Reutiliza o form mode público, preserva estado/tópico/pai/autoria e salva uma nova revisão com metadados do autor. Sem alteração Runtime; smoke autenticado e revisão visual pendentes.

## `0.1.0-dev.147` — seleciona revisões para comparação

- O histórico permite comparar quaisquer duas revisões exibidas na página atual; o atalho entre revisões adjacentes continua disponível. A seleção valida no servidor que ambos os IDs pertencem às opções renderizadas e são distintos; a rota de comparação repete a validação de pertencimento ao post e acesso.
- As opções ficam limitadas às 50 revisões da página corrente. Não oferece revert/delete nem altera Runtime. Revisão visual e seleção entre páginas seguem pendentes.

## `0.1.0-dev.146` — compara revisões de posts

- O histórico administrativo oferece comparação entre revisões adjacentes da mesma publicação, limitada ao assunto e corpo; o Diff contrib fornece a comparação textual e o Core escapa as linhas exibidas.
- A rota `main` valida no servidor que ambas as revisões pertencem ao post do histórico e que o tópico publicado pertence ao purpose `forum`; sem operação de revert/delete ou mudança Runtime.
- Reutiliza `drupal/diff` 2.1.1 já instalado/habilitado; nenhuma dependência Composer nova. Smoke e revisão visual ainda pendentes.

## `0.1.0-dev.145` — identifica o contexto de respostas

- A leitura cronológica apresenta o assunto do post pai junto às respostas publicadas; quando o pai está na página atual, o contexto liga à âncora estável do post.
- O composer de resposta identifica o assunto escolhido e revalida visibilidade/cacheabilidade do pai; pai ausente, oculto ou de outro tópico não é descrito.
- Sem rota nova, CSS/JS ou alteração Runtime. Hierarquia visual, paginação até o pai e revisão visual continuam pendentes.

## `0.1.0-dev.144` — permite responder a posts revisionáveis

- Cada post publicado mostra ação contextual de resposta para contas autorizadas; o composer recebe UUID do post pai e salva a referência junto ao tópico.
- A rota repete a validação: post pai precisa ser a revisão default publicada, acessível e pertencer ao mesmo tópico público. UUIDs inválidos, posts pendentes/rejeitados e pais de outro tópico são negados.
- Sem rota nova ou alteração Runtime. Apresentação hierárquica, respostas a posts pendentes e revisão visual seguem pendentes.

## `0.1.0-dev.143` — abre a criação contextual de posts revisionáveis

- O botão de resposta usa `DiscussionContextResolver`; formulário `/publicacao/nova?topico={uuid}` revalida o contexto e associa no servidor a autoria e o tópico.
- Form mode `public_submission` mostra assunto/corpo, mantém estado e referências ocultos, e o `ContentEntityForm` do Core salva a entidade. `ParticipationRules` escolhe estado inicial; conteúdo publicado retorna ao fragmento estável, e envio em revisão retorna ao tópico sem fragmento inexistente.
- Comments continuam ativos; esta rota cria somente `aculta_forum_post`. Respostas a posts, migração, Runtime e revisão visual seguem pendentes.

## `0.1.0-dev.142` — exibe histórico administrativo das revisões de posts

- A rota `/painel-administrativo/forum/publicacao/{aculta_forum_post}/revisoes` mostra ID, instante, autoria, estado, assunto, mensagem e revisão default; a fila permite acessar o histórico.
- A rota exige a permissão administrativa no purpose `main` e valida novamente tópico publicado no purpose `forum`; a consulta de todas as revisões ocorre somente depois da autorização da rota e não inclui link de reversão ou exclusão.
- Sem alteração no Runtime. Histórico público, comparação textual e revisão visual ainda não concluídos.

## `0.1.0-dev.141` — adiciona fila de revisão dos posts revisionáveis

- Nova aba administrativa consulta `content_moderation_state` do Core e lista revisões em estado `review`; a ação abre a revisão exata em `ContentEntityForm`, com as transições do workflow Core.
- A fila e a revisão ficam no purpose `main`, exigem `administer aculta forum posts` e revalidam que a revisão pertença a post do Fórum, tópico publicado do purpose `forum` e transição válida. Nenhum conteúdo de outro host entra na fila.
- Sem criação pública, sem migração e sem alteração no Runtime. O histórico de revisão e a apresentação visual da fila seguem pendentes.

## `0.1.0-dev.140` — renderiza posts revisionáveis na leitura do tópico

- A página canônica em modo `full` consulta `aculta_forum_post` com EntityQuery e `accessCheck(TRUE)`, limita pelo tópico público/purpose `forum`, e renderiza somente revisão default publicada com o view mode `default`.
- A thread mostra 50 respostas por página com pager Core ID 1 (Comment usa ID 0). Cada resposta recebe fragmento estável `#forum-post-{id}`; referências ao pai só aparecem quando o pai também está visível nessa página. A cache varia por domínio, conta, permissões e página, e invalida pela tag da lista.
- A saída é acrescentada antes do campo Comment existente; a leitura antiga continua para permitir migração gradual. Nenhuma rota foi criada e nenhum Runtime foi alterado.

## `0.1.0-dev.139` — prepara formulário da publicação revisionável

- A entidade registra `ContentEntityForm` como handler padrão e define widgets de assunto, corpo e estado de moderação no display `default`. Campos contextuais e metadados permanecem ocultos.
- Nenhuma rota ou entrada de formulário foi ligada; a criação continua indisponível até a integração contextual e a equivalência da migração.
- Configuração de instalação e Config Sync mantidas idênticas. Sem alteração no Runtime.

## `0.1.0-dev.138` — prepara view mode de post revisionável

- O view mode `default` renderiza assunto e corpo pelo formatter de texto do Core; campos internos, relações e metadados de revisão ficam ocultos.
- `aculta_user 0.4.0-dev.64` acrescenta o cartão de autoria User/histórica. A leitura dentro do tópico e os formulários ainda não foram ligados; Comments continuam sendo a apresentação ativa.
- Nenhuma alteração no Runtime.

## `0.1.0-dev.137` — preserva autoria histórica em posts revisionáveis

- Adiciona o campo-base revisionável `legacy_author` a `aculta_forum_post`, apontando para `aculta_legado_autor`, sem converter autor histórico em conta Drupal.
- Unit valida tipo, destino, revisionabilidade, somente leitura e escopo exclusivo da entidade do Fórum.
- Runtime de teste após `drush cr`: definição do campo carregada, sem tabelas de storage da nova entidade; rotas verificadas responderam 200 e Conta 302 esperado. Nenhum conteúdo ou configuração foi alterado.
- A migração de conteúdo ainda depende de mapear estado de rejeição, relações de mídia/arquivos, flags, busca e notificações; nenhuma cópia ou exclusão de Comments foi executada.

## `0.1.0-dev.136` — autoriza criação e acesso contextual a posts revisionáveis

- `aculta_forum_post` agora só é criado no purpose `forum` com permissão dedicada; leitura e edição de autoria exigem tópico público publicado no purpose correto e acesso de leitura ao Node hospedeiro. Administração continua passando pelo handler da entidade no painel `main`; transições seguem as permissões Core de Content Moderation.
- `email_confirmed` recebe criar/reenviar e editar próprio post publicado; `content_editor` recebe administração e transições de moderação. A janela de edição não concede permissão base e cobre padrão de 10 minutos, vencimento, `0` e exceção administrativa.
- Unit access: 2 testes / 9 assertions; Unit da janela: 2 / 22. Kernel do workflow: 1 / 35; Kernel da política compartilhada: 8 / 96. PHPCS PASS. PHP CLI 8.4.26 (PHP 8.5 pendente). Runtime testado após `drush cr`: home e rotas Growroom420 HTTP 200; Conta 302 esperado; nenhum erro novo no log nas verificações após o rebuild; erros anteriores a 16:45 permanecem no histórico. Nenhum dado ou configuração do Runtime alterados.

## `0.1.0-dev.135` — registra serviços dos hooks e expõe bundle ao workflow

- Todos os hooks OOP do Fórum com construtores passam a ser serviços DI explícitos; `#[Autowire]` isolado não é consumido pelo `ClassResolver` ao instanciar hooks.
- `ForumPostBundleHooks` declara o bundle fixo `aculta_forum_post`, necessário para o Core Content Moderation associar o workflow e fornecer `moderation_state`.
- As rotas administrativas automáticas do post revisionável ficam desativadas até schema, formulários e leitura pública estarem integrados; o mapa registra esses caminhos como não ativos.
- Kernel focal: 1 teste / 35 assertions PASS; uma depreciação Core no setup de `comment.comment_body`. Sem alteração persistente de conteúdo/configuração no Runtime; cache reconstruído para remover o 500 de hook. PHP CLI 8.4.26, abaixo da baseline 8.5. RUNTIME STATUS: serviço Forum não instalado no Runtime.

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

## Pesquisa de anexos no Fórum e integração com Gallery (2026-10-21)

- Registra `aculta_gallery` como integração obrigatória do editor do Fórum,
  reutilizando o botão, diálogo, Entity Embed e display PhotoSwipe existentes.
  A publicação imediata da galeria e dos arquivos exige resolver o vínculo de
  visibilidade com comentário moderado antes de habilitar o fluxo.
- Pesquisa Office confirma que não há renderer local instalado; previews locais
  seguem por formato, com segurança e fidelidade avaliadas, e cartão/download
  permanece como fallback. Não usar viewer externo para anexos privados.
- Google Docs/Drive tem quotas de API sem cobrança adicional, mas não oferece
  renderer pronto para anexos do Fórum. A conversão transfere o documento para
  Drive e exige OAuth, política de dados, retenção e revisão LGPD; integração
  permanece fora do escopo sem aprovação explícita do fluxo de dados.
- Avalia renderização Office local: Mammoth/SheetJS e renderizadores JS são
  parciais; LibreOffice headless pode produzir PDF para o leitor existente se
  for executado isoladamente; ONLYOFFICE/Collabora oferecem experiência mais
  próxima de Docs por WOPI, com serviço e integração adicionais. Nenhuma
  biblioteca foi adicionada nesta pesquisa.
- Ordem definida pelo responsável: fechar primeiro imagem/Gallery, depois avaliar
  preview de DOCX e planilhas. A integração do cartão/leitor de PDF com o ACULTA
  Document Viewer (`aculta_pdf_viewer`) fica por último; nenhum reader é duplicado
  no Fórum.

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

## `0.1.0-dev.132` — publica mudança de tópico para transferência de avisos agregados

- `ForumCommentSubtreeMovedEvent` publica IDs da origem/destino quando uma árvore que contém comentário publicado é movida; o evento ocorre dentro da transação da operação.
- O Messenger move o registro inteiro de respostas agregadas por usuário, preservando o histórico; quando já há aviso no destino, soma contagens e conserva o maior ID de comentário. Acesso ao destino é revalidado.
- Kernel `ForumCommentMoverKernelTest`: 19 testes / 391 assertions PASS; evento verificado para árvore publicada e ausente em árvore somente pendente. Unit Messenger: 2 testes / 4 assertions PASS (assinatura/condição do consumidor). 19 depreciações Core/contrib no Kernel.
- Sem deploy. Anexos diretos ainda precisam do modal contextual, armazenamento/publicação vinculados ao comentário, opções administrativas de tipo/tamanho e cartão PDF sem leitor embutido; o módulo permanece Dev.

## `0.1.0-dev.133` — consome política central de upload

- Formulários de anexo e galeria consultam `aculta_portal.user_upload_policy`; extensões e limite deixam a configuração do Fórum.
- A aba Fórum no painel central herda a política global por padrão e pode receber override próprio. A aba global define padrão de 10 MB.
- Serviço de publicação continua sendo dono do vínculo entre Media e comentário/galeria; não há validação de Runtime nesta mudança. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.134` — mantém a limpeza de imagens sob aculta_image

- O cleanup agora detecta conteúdo raster além do MIME declarado e encaminha imagens ao `aculta_image.file_remover`; documentos sem referências são excluídos pela Storage Core.
- O campo File misto declara a política `aculta_image` em `third_party_settings`. O gate documenta a exceção restrita aos validadores Core de documentos PDF/Office.
- Reconciliação usa o snapshot original disponível durante o hook de atualização para retirar publicação de mídia removida de comentário ou galeria; Entity Usage desatualizado não prevalece sobre o conteúdo atual.
- `ForumAttachmentPublicationKernelTest`: 6 testes / 148 assertions PASS no MariaDB dedicado (10 depreciações Core/contrib e uma PHP 8.4). PHPCS nos arquivos PHP alterados e gates `validate-aculta-image.php` e `validate-aculta-gallery.php` PASS.
- Runtime não foi alterado; PHP CLI 8.4.26, abaixo da baseline 8.5. Estado Dev.

## Não lançado — corrigir erro 500 na página inicial do Fórum — 2026-10-22

- As consultas das Views e serviços de atividade, contagem e não lidas passam a
  usar a coluna `topic` existente na tabela de dados do `aculta_forum_post`.
  `topic_target_id` não existe nessa entidade; seu uso fazia a página inicial
  do Fórum retornar HTTP 500 e também afetava consultas relacionadas.
- Confirmado no Runtime de testes (`aculta_runtime`): `SHOW COLUMNS` lista
  `topic`, e a requisição pública do domínio do Fórum reproduzia o erro SQL.

# `0.1.0-dev.245` — scanner de referências textuais em revisões

- O Portal consulta embeds por Entity Query e `allRevisions()`, cobrindo corretamente campos base do ForumPost.
- Kernel `ForumAttachmentPublicationKernelTest`: 6 testes / 173 assertions em SQLite temporário; limpeza de órfão e retenção de anexos referenciados passaram.

# `0.1.0-dev.244` — limpeza compartilhada de anexos

- `ForumAttachmentCleanup` mantém a descoberta específica de candidatos e verificações de Fórum, e usa `aculta_portal.unused_media_cleaner` para revalidar/remover Media e `aculta_portal.unused_file_cleaner` para Files.
- Retenção, descoberta de referências de Fórum e regras de publicação continuam no módulo; sem alteração de Runtime.

