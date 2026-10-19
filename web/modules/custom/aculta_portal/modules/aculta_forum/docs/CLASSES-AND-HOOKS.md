# Classes, serviços e hooks — contrato planejado

Dev.4 implementa `DiscussionContextInterface`, `DiscussionContext`,
`DiscussionContextProviderInterface`, `DiscussionContextResolver` e
`PublicForumContextProvider`. A expansão deve continuar por serviços tagueados e
DI explícita. Não criar service locator ou hook procedural migrável.

| Responsabilidade prevista | Candidato/API | Estado |
| --- | --- | --- |
| Selecionar contexto/provedor | `DiscussionContextResolver` + `DiscussionContextProviderInterface` | Implementado na dev.4; provider via tag `aculta_forum.discussion_context_provider`, duplicidade falha explicitamente. |
| Identificar topic/thread | `DiscussionContextInterface` + `DiscussionContext` | Implementado para entidade hospedeira, ID estável, URL canônica, access e cacheability; sem storage duplicado. |
| Consultar regras | `DiscussionAccessPolicy` ou delegação explícita a entity access/Group | Comparar APIs primeiro; `AccessResultInterface` e cacheability obrigatórios. |
| Preparar thread/post | View builders e Views/Core Comment render | Não criar renderer paralelo antes de testar APIs existentes. |
| Listagem e filtro | Views query/access handlers + Search API processors | Filters por domínio/contexto fixos. |
| Ordenação por atividade na View pública | `PublicForumViewsDataHooks` (`#[Hook('views_data_alter')]`) + `PublicForumLastActivity` (`#[ViewsSort('aculta_forum_last_activity')]`) e campo Core `comment_ces_last_updated` | Workaround localizado para o bug confirmado no Core instalado: `StatisticsLastUpdated` armazena o retorno `void` de `Sql::addOrderBy()` em propriedade `string`. Mantém a fórmula Core `GREATEST(node.changed, last_comment_timestamp)` sem substituir o handler de outros Views. Query Runtime confirmada.
| Origem do tópico público | `PublicForumTopicHooks` (`entity_create_access`, `node_presave`) | Implementado na dev.11: restringe criação ao purpose ativo `forum`, exige seu domínio canônico e preenche `field_domain_source` quando ausente. |
| Ação criar tópico na View | `PublicForumTopicCreateActionHooks` (`views_pre_render`) + `AccessManagerInterface` | Implementado na dev.14. Reavalia a rota `aculta_forum.topic_add` e propaga cacheabilidade antes de emitir o link com token visual existente; somente `page_discussions` no purpose `forum`. |
| Marcador de rejeição de comentário | `DiscussionModerationHooks` (`entity_field_access`) | Implementado na dev.27: somente a permissão `administer comments` pode ver ou editar `field_aculta_rejected`; resultado varia por `user.permissions`. |
| Leitura/não lidos | History API e serviço pequeno se histórico nativo não cobrir o contrato | Exige decidir armazenamento e invalidação antes de entidade/tabela. |
| Estatísticas | agregação/cache service sobre APIs/views existentes | Sem query cara a cada render ou storage redundante. |
| Evento de resposta | evento interno neutro após persistência, consumido por Messenger | Revalidar access no worker; não duplicar aviso. |
| Author cards | APIs em `aculta_user` | Forum nunca possui builder. |
| Provider LMS | submódulo opcional `aculta_forum_lms` | Planejado após contrato e teste de isolamento. |
| Provider Wiki | adapter futuro | Não implementar antes da reimplementação Wiki. |

Hooks existentes relacionados ao legado aparecem em
`aculta_user/docs/CLASSES-E-HOOKS.md` e
`aculta_magazine/docs/README.md`; sua migração ou remoção exige paridade por
comportamento, não renomeação em massa. Lifecycles Drupal que exigem hook
procedural continuam procedurais conforme `DRUPAL-11-STANDARDS.md`.
