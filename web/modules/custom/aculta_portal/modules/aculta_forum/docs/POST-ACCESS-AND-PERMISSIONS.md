# Acesso às publicações revisionáveis

`ForumPostAccessHooks` protege a entidade `aculta_forum_post` pelo contexto do
tópico hospedeiro. A criação exige a permissão `create aculta forum posts` e o
purpose `forum`. Leitura pública e edição de autoria exigem tópico publicado,
acesso de leitura ao Node hospedeiro e `field_domain_source` ligado ao purpose
`forum`. Publicações não publicadas só são visíveis a quem tem a permissão Core
de ver conteúdo não publicado, respeitando a autoria para a permissão `view own
unpublished content`.

O formulário público vive em `/publicacao/nova?topico={uuid}`, com `resposta={uuid}`
opcional para responder a um post. O
provider `public_forum` só libera escrita para conta autenticada com
`create aculta forum posts`, tópico publicado/legível e purpose `forum`; o
controller revalida os UUIDs e associa tópico, autoria e post pai pelo servidor.
Uma resposta só pode apontar para revisão default publicada, acessível e do
mesmo tópico; tentativas de responder a revisão pendente, rejeitada ou de outro
tópico falham fechadas.
O form mode `public_submission` mostra somente assunto e corpo, sem campo de
estado, tópico ou autoria. `ForumPublicationTimeHooks` consulta a regra
compartilhada `ParticipationRules` para selecionar `review` ou `published`; o
Core sincroniza a publicação com o workflow. O envio volta ao fragmento estável
da publicação no tópico.

O autor pode editar sua publicação enquanto o access handler permite `update`.
A rota `/publicacao/editar?post={uuid}` usa UUID, exige que o post pertença à
conta atual e seja a revisão default publicada; o `ParticipationEditWindow` do
`aculta_user` continua restringindo o acesso pelo timestamp imutável e pela
configuração do Fórum. O mesmo form mode deixa estado, tópico, pai e autoria
fora do input. Cada edição salva uma nova revisão publicada com metadados da
conta atual, sem reiniciar o prazo.

O acesso de atualização do autor é apenas uma condição contextual. O handler da
entidade e o Core Content Moderation continuam responsáveis por permitir a
operação e validar transições. `ParticipationEditWindow` pode negar a edição
depois do prazo, sem concedê-la enquanto está aberto. A permissão
`administer aculta forum posts` continua sendo a porta administrativa no purpose
`main` e para operações de revisão. Exclusão, revert e operações históricas não
são concedidos aos autores.

A fila customizada adapta o algoritmo de `content_moderation`
`ModeratedNodeListBuilder`: consulta revisões `review` no armazenamento interno
`content_moderation_state` do Core e carrega as revisões da entidade pelo
`content_entity_revision_id`. O ACULTA não cria storage de fila próprio. A ação
abre a revisão exata pelo Entity Form do Core, que apresenta as transições
permitidas pelo workflow.

As transições são explicitamente atribuídas aos papéis existentes. `email_confirmed`
pode criar/reenviar e editar publicação própria durante a janela; `content_editor`
pode administrar a fila e aprovar, rejeitar, retirar ou editar publicações. A
função editorial do Portal continua sendo única. As permissões de transição são
as verificadas pelo Core em `StateTransitionValidation`; a autorização é
combinada com os hooks de acesso e não depende apenas do rótulo do papel.

Referências primárias consultadas em 2026-10-21:

- [Drupal 11.x `EntityAccessControlHandler::access()`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityAccessControlHandler.php/function/EntityAccessControlHandler%3A%3Aaccess/11.x)
- [Drupal 11.x `StateTransitionValidation`](https://api.drupal.org/api/drupal/core%21modules%21content_moderation%21src%21StateTransitionValidation.php/class/StateTransitionValidation/11.x)
- [Drupal 11.x `ModeratedNodeListBuilder`](https://api.drupal.org/api/drupal/core%21modules%21content_moderation%21src%21ModeratedNodeListBuilder.php/class/ModeratedNodeListBuilder/11.x) — referência para listar IDs de revisão do estado moderado sem storage paralelo.
- [Drupal 11.x Entity API — rotas `_entity_form`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21entity.api.php/group/entity_api/11.x)
- [Drupal 11.x `EntityFormBuilder::getForm()`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityFormBuilder.php/function/EntityFormBuilder%3A%3AgetForm/11.x)
- [Drupal 11.x `EntityFormDisplay::collectRenderDisplay()`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21Entity%21EntityFormDisplay.php/function/EntityFormDisplay%3A%3AcollectRenderDisplay/11.x)
- Código instalado: `web/core/lib/Drupal/Core/Entity/EntityAccessControlHandler.php`,
  `web/core/modules/content_moderation/src/StateTransitionValidation.php` e
  `web/core/modules/content_moderation/src/Hook/ContentModerationHooks.php`.

A fila `/painel-administrativo/forum/publicacoes-revisionaveis` e o formulário
`/painel-administrativo/forum/publicacao/revisao/{revision_id}` usam a revisão
Core exata e ficam em `main`; o custom access revalida estado `review`,
transição, tópico publicado e purpose `forum`. O histórico administrativo é
somente leitura em
`/painel-administrativo/forum/publicacao/{aculta_forum_post}/revisoes`: requer a
permissão administrativa e valida o tópico e purpose; lista metadados de todas
as revisões sem links de revert/delete. Respostas contextuais estão disponíveis
para posts publicados. A comparação de assunto e corpo está descrita abaixo;
migração dos Comments e corte continuam pendentes, e o Fórum legado segue ativo
até equivalência e aprovação.

O histórico adapta a API Core `VersionHistoryController` e usa a API
`RevisionableStorageInterface`/EntityQuery para carregar revisões paginadas. A
consulta de revisões usa `accessCheck(FALSE)` porque a autorização administrativa
e o vínculo com o tópico são verificados antes na rota; não há exposição em
rotas públicas. Fontes consultadas:

- [Drupal 11.x `VersionHistoryController`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21Controller%21VersionHistoryController.php/class/VersionHistoryController/11.x)
- [Drupal 11.x Entity API — revisões](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21entity.api.php/11.x)

A comparação de revisões fica em
`/painel-administrativo/forum/publicacao/{aculta_forum_post}/revisoes/comparar/{left_revision}/{right_revision}`.
O custom access revalida os dois IDs, exige que sejam distintos e pertençam ao
mesmo post do histórico, além dos critérios de purpose/permissão/tópico desta
seção. A interface mantém o atalho entre revisões adjacentes e permite selecionar
duas revisões dentre as 50 exibidas na página atual. Compara somente assunto e
corpo, não oferece revert/delete e usa o serviço `diff.entity_comparison` do
Diff 2.1.1 instalado; a rota genérica
do Diff é específica para Node e não é reutilizada. O Core DiffFormatter escapa
as linhas resultantes; o controller converte rich text em texto antes de
renderizar a comparação.

Referências primárias consultadas em 2026-10-22:

- [Diff para Drupal — suporte a Drupal 11 e todos os tipos de entidade na linha 2.1](https://www.drupal.org/project/diff)
- [Diff 2.1.1 — release estável e correções](https://www.drupal.org/project/diff/releases)
- Código instalado: `web/modules/contrib/diff/src/DiffEntityComparison.php`,
  `web/modules/contrib/diff/src/DiffEntityParser.php` e
  `web/core/lib/Drupal/Core/Diff/DiffFormatter.php`.

A edição reutiliza `EntityFormBuilderInterface::getForm()` com o form mode
`public_submission`; a entidade é selecionada e autorizada pelo serviço de
access da rota antes de montar o formulário. `ForumPostSubmissionForm` usa a
API de revisão do Core para preservar cada edição como revisão própria.

- [Drupal 11.x `EntityFormBuilder::getForm()`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityFormBuilder.php/function/EntityFormBuilder%3A%3AgetForm/11.x)
- [Drupal 11.x `EntityAccessCheck::access()`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityAccessCheck.php/function/EntityAccessCheck%3A%3Aaccess/11.x)
- Código instalado: `web/core/lib/Drupal/Core/Entity/EntityFormBuilder.php` e
  `web/core/lib/Drupal/Core/Entity/ContentEntityForm.php`.
