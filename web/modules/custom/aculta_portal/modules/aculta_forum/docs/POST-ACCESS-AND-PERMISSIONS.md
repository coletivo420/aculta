# Acesso às publicações revisionáveis

`ForumPostAccessHooks` protege a entidade `aculta_forum_post` pelo contexto do
tópico hospedeiro. A criação exige a permissão `create aculta forum posts` e o
purpose `forum`. Leitura pública e edição de autoria exigem tópico publicado,
acesso de leitura ao Node hospedeiro e `field_domain_source` ligado ao purpose
`forum`. Publicações não publicadas só são visíveis a quem tem a permissão Core
de ver conteúdo não publicado, respeitando a autoria para a permissão `view own
unpublished content`.

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
- Código instalado: `web/core/lib/Drupal/Core/Entity/EntityAccessControlHandler.php`,
  `web/core/modules/content_moderation/src/StateTransitionValidation.php` e
  `web/core/modules/content_moderation/src/Hook/ContentModerationHooks.php`.

A fila `/painel-administrativo/forum/publicacoes-revisionaveis` e o formulário
`/painel-administrativo/forum/publicacao/revisao/{revision_id}` usam a revisão
Core exata e ficam em `main`; o custom access revalida estado `review`,
transição, tópico publicado e purpose `forum`. A criação pública, o histórico
administrativo e a migração dos Comments continuam nas fases seguintes; o Fórum
legado segue ativo até equivalência e corte aprovados.
