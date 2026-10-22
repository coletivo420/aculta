# FORUM-PART 0.1-C3 — caracterização da ACL de Group e da revogação

**Data:** 2026-10-22 · **Estado:** caracterização concluída; nenhuma mudança
funcional, de DI, de schema ou de Runtime.
**Base:** [ADR-XK-03](../../../../../../../docs/architecture/ACULTA-XK-0.1-B-SHARED-CONTRACTS.md)
e [fóruns restritos](RESTRICTED-FORUMS.md).

O contrato XK-C3 pede prova de ACL de Group e revogação **antes** da migração.
Até aqui o subsistema `RestrictedForum*` só tinha testes Unit com mocks; não
havia prova de que Group real, grants de nó e a marca real de associado compõem
o resultado documentado no ADR-XK-03. Esta fase fixa esse contrato por um teste
Kernel com Group e `aculta_associado` reais, sem alterar nenhum caminho de
produção.

## Contratos caracterizados

### Serviços

| Serviço | Papel na prova |
| --- | --- |
| `RestrictedForumGroupResolver` | resolve o tipo de Group configurado, os Groups de um tópico e os Groups do tipo |
| `RestrictedForumAccessPolicy` | decide `view` de tópico: associado ativo **e** membro do Group |
| `RestrictedForumAccessHooks` | liga a decisão a `entity_access`, `node_grants` e `node_access_records` |
| `RestrictedForumMembershipSync` | reconciliação idempotente da membership conforme a situação |
| `AssociadoMarker` | leitura autoritativa da situação (`aculta_associado`) |

Todas as instâncias usam os serviços reais do container; o módulo `aculta_forum`
não é habilitado no Kernel, e `aculta_user` também não.

### Matriz conjunta de acesso

| Situação | Membership no Group do tópico | `entityAccess('view')` | `nodeGrants()` | `nodeAccessRecords()` |
| --- | --- | --- | --- | --- |
| `ativo` | sim | `neutral` | `[realm => [gid]]` | 1 registro por Group do tópico |
| `ativo` | não (ou outro Group) | `forbidden` | `[]` ou só os gids próprios | registros do tópico (independem da conta) |
| `pendente`, `convidado`, `suspenso`, `desligado` | sim | `forbidden` | `[]` | registros do tópico |
| sem linha em `aculta_associado` | sim | `forbidden` | `[]` | registros do tópico |
| anônimo | — | `forbidden` | `[]` | registros do tópico |
| `ativo` | tópico sem Group restrito | `neutral` | `[]` | `[]` |
| `ativo` | Group de outro tipo | `neutral` | `[]` | `[]` |
| `ativo` | tipo configurado é `lms_course` | `neutral` | `[]` | `[]` |
| — | falha do resolvedor | `forbidden`, max-age 0 | `[]` | grant fechado de `gid` 0 |

Regras observadas:

- `entity_access` só intervém em `NodeInterface` do bundle
  `aculta_forum_topic` e na operação `view`; as demais operações e bundles
  devolvem `NULL` e seguem o Core.
- `node_grants` nunca responde para anônimo nem para operação diferente de
  `view`, e ordena os gids (`ksort`) antes de devolver.
- A decisão é **revogada na checagem**: a situação é relida a cada chamada e não
  há cache estático em `AssociadoMarker::marcaDe()`, portanto suspender,
  desligar ou remover a linha nega o acesso mesmo com a membership intacta.
- Readmissão (`suspenso` → `ativo`) devolve `neutral` e os grants sem novo
  registro.
- O fail-closed do resolvedor produz `forbidden` com `setCacheMaxAge(0)` e um
  registro de `gid` 0, exatamente o caminho já existente.

### Cacheabilidade

`AccessResult` (permitido ou negado pela política) carrega:

- dependência do tópico;
- contextos `user` e `domain`;
- tags de `AssociadoMarker::associateCacheTags()` (`aculta_associado_list`,
  `user:<uid>` ou `user:0`), `config:aculta_forum.settings`,
  `group_relationship_list` e `node_list`.

### Membership e expiração de cache (verificado, sem defeito)

A dúvida era se `Group::addMember()`/`removeMember()` invalidariam o item de
cache de `GroupMembership::loadByUser()`, que é tagueado
`group_relationship_list:plugin:group_membership:entity:<uid>`. A invalidação
de tags do Core é por string exata, sem hierarquia
(`DatabaseCacheTagsChecksum::doInvalidateTags()` apenas incrementa a linha
correspondente em `cachetags`), então invalidar `group_relationship_list` não
limparia a tag específica. **Mas** `GroupRelationship::getListCacheTagsToInvalidate()`
(`web/modules/contrib/group/src/Entity/GroupRelationship.php`) inclui
`group_relationship_list:plugin:<plugin_id>:entity:<entity_id>` e
`EntityBase::invalidateTagsOnSave()` invalida toda a lista em cada
create/update. Ou seja, a própria relação criada/removida por
`addMember()`/`removeMember()` invalida o item por usuário. A tag extra do
sincronizador é redundante, mas correta — **não há defeito de membership
obsoleta**. O teste comprova o efeito observável: depois de `syncAccount()`, o
`getMember()` do Group reflete a mudança.

## Teste entregue

`tests/src/Kernel/RestrictedForumGroupAclKernelTest.php` — Kernel, MariaDB do
`secrets/simpletest-db.env` (SQLite é proibido pela política do projeto),
12 testes / 261 assertions.

| Teste | Célula |
| --- | --- |
| `testActiveMembershipKeepsNeutralAccessAndGrantsTheGroup` | ativo + membro: `neutral`, grants, registro, tags e contextos |
| `testActiveAssociateWithoutMembershipIsForbidden` | ativo sem membership: `forbidden`, sem grants |
| `testMembershipInAnotherRestrictedGroupIsForbidden` | membro de outro Group: isolamento entre fóruns |
| `testRevocationIsImmediateWhileMembershipRemains` | pendente, convidado, suspenso, desligado e sem linha: membership intacta, acesso negado |
| `testReadmissionRestoresAccess` | readmissão devolve acesso e grants |
| `testAnonymousAccountIsForbidden` | anônimo negado, tag `user:0`, sem grants |
| `testTopicWithoutRestrictedGroupIsNeutral` | tópico público: `neutral`, sem grants, sem registros |
| `testGroupOfAnotherTypeDoesNotRestrict` | Group de outro tipo não restringe |
| `testLmsGroupTypeIsNeverAccepted` | `lms_course` recusado pelo resolvedor |
| `testResolverFailureFailsClosed` | fail-closed com max-age 0 e `gid` 0 |
| `testHooksOnlyHandleViewOfTopics` | escopo de bundle e operação |
| `testMembershipSyncFollowsAssociateSituation` | `unchanged` → `added` → `removed` → `added`, uid 0 ignorado |

### Ambiente do teste

- Novos arquivos de teste: o próprio Kernel e o fixture
  `tests/modules/aculta_forum_restricted_test`.
- O fixture registra a entidade de produção `AssociadoMarcador` pelo atributo
  `ContentEntityType` (Reflection, provider do fixture) e declara o schema de
  `aculta_forum.settings` usado pelo teste. É o único código novo fora do
  teste; não entra em produção.
- Group real: tipos `associado_forum`, `outro_forum` e `lms_course`, relação
  `group_node:aculta_forum_topic`, memberships criadas por `addMember()`.
- A situação é gravada diretamente no campo `situacao`, sem usar
  `definir()`/`aceitarConvite()`, que exigem `profile`.
- Depreciações (17) vêm das anotações `@EntityType`/`@EntityReferenceSelection`
  do contrib Group e de `group_module_implements_alter`; nenhuma é do Fórum.

## Fora desta fase

- URL direta, Search API, anexos, comentários, contadores e notificações de
  fórum restrito não têm prova Runtime neste incremento; a prova é da camada de
  acesso comum a todos esses consumidores.
- A regressão LMS Group permanece coberta por
  `aculta_forum_lms/tests/src/Kernel/LmsGroupNodeAccessKernelTest.php` (suíte
  verde, exceto um erro pré-existente e alheio em
  `Lmsaculta_H5PDiscussionResolverKernelTest`, classe
  `aculta_studio.ganjalab420_controller` ausente).
- Migração/backfill, configuração real de Group em produção, Wiki e LMS fora do
  Fórum não foram tocados.
