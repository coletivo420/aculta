# USER-PART 0.1-A — Inventário transversal de participação e moderação

**Data:** 2026-10-22 · **PR:** #297 · **Estado:** inspeção estática dirigida dos fontes; não executa Composer, testes nem Runtime.

## Decisão aprovada

O módulo **`aculta_user`** é proprietário dos contratos de participação, primeira contribuição e decisões compartilháveis de moderação. `aculta_portal` não recebe essas regras. Os kernels Fórum, Fórum LMS e Wiki mantêm entidades, queries específicas, fila, workflows, regras de Domain/Group e formulário de conteúdo. Não reaproveitar Twig sem Core SDC/UI Suite.

## Evidência dos fontes lidos

| Fonte | Contrato existente | Centralização possível |
| --- | --- | --- |
| `aculta_user/src/ParticipationRules.php` | Serviço final com `config.factory` e `entity_type.manager`; `requiresFirstDiscussionModeration()`, `hasPublishedDiscussionComment()`, `hasPendingDiscussionComment()`, `firstDiscussionCommentPending()`, `publishedPublicForumCommentCount()`, `isNonLmsDiscussionComment()`, `isExempt()` | **Manter e estender em aculta_user** via consulta separada à primeira publicação Wiki, sem transformar comentários do Fórum em verbetes |
| `aculta_user/src/Hook/ParticipationHooks.php` | `comment_presave` despublica primeira discussão não LMS; `entity_create_access` exige participação/pendência para criação de tópico público; tem cache por usuário, papéis e `comment_list` | Preservar hooks existentes; **não aplicar bloqueio de tópico a wiki_entry**; extrair somente política comprovadamente comum |
| `aculta_user/src/Hook/ForumModerationHooks.php` | Proteções para acervo Growroom, `content_editor` com confirmação real, Node Access contextual | Política de identidade/confirmação pode alimentar contrato compartilhado; regras de acervo histórico e grants de tópico **continuam do Fórum**. Refatorar eventual ownership posteriormente, não por substituição cega |
| `aculta_user/src/ParticipationEditWindow.php` | Verifica proprietário, instante de publicação, configuração por tipo, prazo, exceções por `EditWindowExemptionInterface`, AccessResult cacheável | **Reuso existente**; não ampliar janela a Wiki automaticamente. Wiki usa revisões e workflow próprios, portanto abrir revisão ≠ editar uma mensagem publicada |
| `aculta_forum/src/Hook/DiscussionModerationHooks.php` | Campo `field_aculta_rejected` protegido por `administer comments` | Padrão neutro de decisão/cache pode ser avaliado; propriedade do campo e permissão de comentários ficam no Fórum |
| `aculta_forum/src/Form/ForumPostSubmissionForm.php` | Formulário de post público, com fluxo específico do fórum | **Não mover** Form API, publicação, markup ou gravação do ForumPost para User |
| `aculta_forum_lms/src/Access/LmsForumModerationManager.php` | Moderação por curso/turma Group, com membership/context provider e AccessResult/cache | Possível interface neutra para avaliar capacidade de moderar; **Group, hierarquia de curso e queries ficam no LMS** |
| `aculta_forum_lms/src/Hook/LmsDiscussionModerationHooks.php` | Adapta `field_aculta_rejected` ao acesso contextual `DiscussionContextResolver`, restrito ao tipo LMS | Evitar converter `administer comments` em permissão LMS; decisões compartilhadas via interface, não troca de grants |
| `aculta_user/aculta_user.services.yml` | `aculta_user.participation_rules` recebe `@config.factory` e `@entity_type.manager`; `participation_edit_window` recebe tagged iterator de isenções | Contratos de extensão em User devem preservar serviço público/DI e compatibilidade dos consumidores |
| `config/sync/user.role.email_confirmed.yml` e `workflows.workflow.wiki_editorial.yml` | Criação/edição de verbetes próprios, envio a revisão, sem permissão de publicar; estados draft/review/published | Wiki fornece histórico publicado; User avalia primeira participação; `content_editor` decide publicação |

## Matriz de reuso e economia de código

| Capacidade transversal | Dono futuro | Fórum público | Fórum LMS | Wiki | Condição |
| --- | --- | --- | --- | --- | --- |
| Primeira participação / confiança | **User** | adaptador de comentários/posts | política distinta/isenção contextual | adaptador de verbetes publicados | não misturar métricas nem cache |
| Lookup de contribuição publicada por contexto | **interface User** | query no Fórum | consulta própria se houver consumidor | query em Wiki | somente interface compartilhada, queries permanecem nos kernels |
| Metadados de decisão/cache | **User**, se segundo consumidor confirmar | adapter | adapter Group | adapter Domain/Revision | nenhuma decisão global que conceda acesso |
| Janela temporal de edição | **User**, infraestrutura existente | usa política atual | extensão `EditWindowExemptionInterface` | não aderir sem regra aprovada | preservar configuração por consumidor |
| Identidade e e-mail confirmado | **User / contrato de conta existente** | dependência de confirmação | Group + conta | gate email_confirmed | não duplicar verificador de e-mail |
| Moderação de primeira submissão | **User — regra** | workflow próprio | exceção/contexto LMS | primeiro verbete em review | never auto-publish |
| Fila de moderação e decisão editorial | **cada kernel** | fila ForumPost/Comment | fila Group | fila Node/propostas | apenas interface visual/contrato onde comprovadamente comum |
| Storage de sugestão/revisão | **cada kernel** | ForumPost/Comment | Comment+Group | Node / proposta Wiki futura | não criar entidade genérica sem necessidade |
| Avisos e mensagens | **aculta_messenger** | consumidor | consumidor | consumidor | não duplicar inbox |
| Cartões de autoria / identidade | **User + tema SDC** | consumidores Forum | LMS | Wiki conforme privacidade | cache/access no backend |
| Layout/estados/filas | **aculta420 SDC/UI Suite** | apresentação | apresentação | apresentação | nenhum CSS/HTML centralizado em User |

## Riscos descobertos

1. **Sinal de primeira participação difere:** `hasPublishedDiscussionComment()` considera Comment de discussões (inclusive LMS em um ramo) e posts revisionáveis do Fórum. Isso **não comprova** primeiro verbete Wiki publicado.
2. **LMS não compartilha simplesmente a regra pública:** `ParticipationRules::isNonLmsDiscussionComment()` explicitamente exclui `aculta_lms_topic`; professores obtêm moderação por Group. A migração não pode submeter automaticamente comentários LMS à primeira moderação do Fórum.
3. **Cacheability:** `ParticipationHooks` usa `comment_list` e contextos por usuário; a Wiki precisará de invalidação baseada em Node/revisions/publicações e contextos de purpose, sem copiar a tag `comment_list`.
4. **Segurança:** `ParticipationEditWindow` retorna neutral/forbidden, não concede edit; interfaces compartilhadas devem preservar `AccessResultInterface` e jamais usar `allowed` para contornar Node ou Group access.
5. **E-mail confirmado:** não basta `authenticated`; validação de estado real de confirmação precisa permanecer autoritativa, com testes para revogação/bloqueio.
6. **Primeiro verbete:** política de primeiro envio à revisão **não altera** a regra vigente de que somente content editors publicam qualquer revisão de Wiki.
7. **Propostas em verbetes de terceiros:** `edit own wiki_entry content` não é `edit any`. A estrutura de proposta moderável pertence à Wiki, não à participação genérica em User.
8. **Dependências:** classe `ParticipationRules` é `final`; extensão segura é por composição/interface/serviço, não subclasse ou mutação inesperada da DI.

## Direção de refatoração recomendada

**USER-PART 0.1-B:** introduzir desenho de contrato `PublishedParticipationLookupInterface` (nome provisório), com `contextId` explícito e provider por contexto. O serviço User decide a necessidade de primeira moderação, cada adapter retorna evidência de conteúdo publicado; não introduzir qualquer fallback que publique em caso de erro. Preservar os métodos públicos atuais de `ParticipationRules` e não converter chamadas do Fórum em nova API de uma vez.

**USER-PART 0.1-C:** primeiro adicionar testes de caracterização para Fórum/Comment/ForumPost/LMS exception e serviço DI. Somente depois implementar a interface e adapter Wiki com testes de status, revision default, autor, domínio, estado pendente e falhas de consulta. Adotar no Fórum aos poucos se realmente reduzir duplicação.

**USER-PART 0.1-D:** habilitar integração Wiki/WE SATIVA e moderação primeiro verbete. Conteúdo de terceiros requer ADR de proposta separado.

## Gates antes de qualquer mudança funcional

`php -l`, Composer (se disponível), Unit/Kernel, `drush cr`, access/cache, confirmações de e-mail, primeiro/segundo post, primeira/segunda participação LMS, primeira/segunda publicação Wiki, transições de revisão, grupos cruzados e Domain Wiki; homologação mobile-first posterior. **Nada disso foi executado nesta fase documental.**
