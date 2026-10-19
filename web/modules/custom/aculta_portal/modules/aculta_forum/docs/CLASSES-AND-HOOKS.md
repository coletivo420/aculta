# Classes, serviços e hooks — contrato planejado

Não há classes runtime próprias em `aculta_forum` na base auditada. Os nomes
abaixo descrevem responsabilidades, não uma decisão de nomenclatura final. Antes
da implementação, pesquisar pelo comportamento no Core/contrib instalado,
confirmar a assinatura de cada hook e preferir DI/autowiring e `#[Hook]` em Core
11.3+. Não criar service locator ou hook procedural migrável.

| Responsabilidade prevista | Candidato/API | Estado |
| --- | --- | --- |
| Selecionar contexto/provedor | `DiscussionContextResolver` + `DiscussionContextProviderInterface` | Planejado; contrato em `CONTEXTS.md`. |
| Identificar topic/thread | DTO/interface neutra com host e ID imutável | Planejado; evitar persistência duplicada. |
| Consultar regras | `DiscussionAccessPolicy` ou delegação explícita a entity access/Group | Comparar APIs primeiro; `AccessResultInterface` e cacheability obrigatórios. |
| Preparar thread/post | View builders e Views/Core Comment render | Não criar renderer paralelo antes de testar APIs existentes. |
| Listagem e filtro | Views query/access handlers + Search API processors | Filters por domínio/contexto fixos. |
| Leitura/não lidos | History API e serviço pequeno se histórico nativo não cobrir o contrato | Exige decidir armazenamento e invalidação antes de entidade/tabela. |
| Estatísticas | agregação/cache service sobre APIs/views existentes | Sem query cara a cada render ou storage redundante. |
| Evento de resposta | evento interno neutro após persistência, consumido por Messenger | Revalidar access no worker; não duplicar aviso. |
| Author cards | APIs em `aculta_user` | Forum nunca possui builder. |
| Provider LMS | submódulo opcional `aculta_forum_lms` | Somente após contrato e teste de isolamento. |
| Provider Wiki | adapter futuro | Não implementar antes da reimplementação Wiki. |

Hooks existentes relacionados ao legado aparecem em
`aculta_user/docs/CLASSES-E-HOOKS.md` e
`aculta_magazine/docs/README.md`; sua migração ou remoção exige paridade por
comportamento, não renomeação em massa. Lifecycles Drupal que exigem hook
procedural continuam procedurais conforme `DRUPAL-11-STANDARDS.md`.
