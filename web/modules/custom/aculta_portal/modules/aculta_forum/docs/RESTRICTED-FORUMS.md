# Fóruns restritos para associados ativos — F-GROUP 0.1 e 0.2

Documento da frente **F-GROUP 0.1** (inventário e fundação) e **F-GROUP 0.2**
(prova funcional em Kernel). Cobre o inventário (F-GROUP-A), as decisões de
contrato e a fundação implementada (F-GROUP-B), o limite do que foi provado
(F-GROUP-C) e a prova de grants reais em Kernel (F-GROUP-D). **Nenhum Runtime
foi alterado**: não houve import de configuração, criação de Group, rebuild de
`node_access` em ambiente nem migração de dados. O rebuild da F-GROUP 0.2
ocorreu apenas no banco isolado de testes Kernel. Os gates executados são
Unit/Kernel/PHPCS/PHPStan; o Runtime de testes segue **DEFERRED**.

## Escopo e limites

- Fórum restrito é um Group de um tipo configurado em
  `aculta_forum.settings.restricted_group_type`. Um tópico é restrito quando
  está relacionado a um Group desse tipo, por relação direta
  (`group_node:aculta_forum_topic`) ou pela categoria do tópico
  (`group_taxonomy_term:aculta_forum_categories`).
- A situação de associado **não** é um papel Drupal `associado` e não é
  duplicada no Group. A fonte da verdade continua em `aculta_user`
  (`aculta_associado`, serviço `aculta_user.associado_marker`), exposta aos
  consumidores por `aculta_user.active_associate`
  (`Drupal\aculta_user\Associado\ActiveAssociateInterface`).
- Group é consumidor da elegibilidade, nunca dono dela. O Fórum não escreve
  situação de associado; escreve apenas membership do Group conforme a situação
  lida.
- Os tipos de Group do LMS (`lms_course`, `lms_class`) são recusados: um Group
  de curso não pode, por configuração acidental, restringir fórum de associados.
- Sem permissão ampla para contornar falha de acesso, sem controle que dependa
  só de CSS/JS/link escondido, sem import destrutivo e sem migração de dados.

## F-GROUP-A — matriz de inventário

| Área | Componente atual | Comportamento antes da frente | Decisão F-GROUP 0.1 | Estado |
| --- | --- | --- | --- | --- |
| Situação de associado | `aculta_associado` (`AssociadoMarcador`), `AssociadoMarker` | Situações `pendente`, `convidado`, `ativo`, `suspenso`, `desligado`; sem contrato público para consumidores | `ActiveAssociateInterface`: `isActiveAssociate()` e `associateCacheTags()`; alias `aculta_user.active_associate` | Implementado, Unit PASS |
| Adapter Group | Group `^3.3`; só `lms_course` e `lms_class` existiam | Nenhum adaptador de fórum restrito | `RestrictedForumGroupResolver` lê o tipo configurado, valida existência e recusa LMS | Implementado, Unit PASS |
| Membership | Relações `group_membership` | Nenhuma sincronização automática | `RestrictedForumMembershipSync`: ativa/remove idempotente, lock, log, invalidação | Implementado, Unit PASS |
| Acesso ao tópico | `hook_entity_access` | Nenhuma regra do Fórum para tópicos restritos | `RestrictedForumAccessPolicy` + `RestrictedForumAccessHooks`; `neutral` fora do caso restrito | Implementado, Unit PASS |
| Listagem e contagem | `hook_node_grants`/`node_access` | Site em modo de grants padrão (`realm=all`) | Realm `aculta_forum_restricted`, `grant_view` por Group; exige rebuild | Implementado; ativação Runtime DEFERRED |
| Comments e posts revisionáveis | `ForumPostAccessHooks`, `aculta_forum_post`, `aculta_discussion_post` | Não conhecem Group | Revalidar no handler de post/comentário herdando a restrição do tópico | DEFERRED |
| Anexos e mídia | `ForumAttachmentAccessHooks`, `ForumAttachmentPublication` | Regra de referência pública anônima, sem Group | Tornar a referência e o download cientes do Group do tópico | DEFERRED |
| Busca | Search API, datasource `ContentEntity`, Views `aculta_forum_*` | Filtra por acesso do nó via node grants no índice | Confirmar que o índice e a contagem respeitam grants e cache | DEFERRED |
| Contadores/histórico | `history`, campos `aculta_forum_reply_count`/`last_activity` | Contadores por nó, sem recorte de acesso | Nenhum contador próprio; a listagem já depende de node grants | DEFERRED (validação Runtime) |
| Avisos/notificações | `aculta_messenger` | Fora do Fórum | Não duplicar inbox; aviso de novo tópico restrito só para membros | DEFERRED (frente futura) |
| URL direta | Rotas canônicas, revisões, `/forum-post-{id}` | Acesso pelo handler de entidade | `entity_access` revalida servidor a cada requisição; URL não concede nada | Implementado, Unit PASS; Runtime DEFERRED |
| Tema | `aculta420` | Nenhum elemento novo necessário | Sem alteração de tema nesta frente | Não aplicável |

## Decisões arquiteturais (F-GROUP-B)

1. **Fonte da verdade única.** Ativo é `situacao === ativo` em
   `aculta_associado`, consultado por `isActiveAssociate()`. A mesma consulta
   alimenta grants, entity access e sincronização; não há cópia de estado no
   Group.
2. **Sincronização idempotente.** `RestrictedForumMembershipSync::syncAccount()`
   adiciona membership quando a conta é ativa e remove quando não é, usando
   apenas `GroupInterface::getMember()`, `addMember()` e `removeMember()` (API
   estável; `GroupMembershipLoaderInterface` foi removida no Group 4). Cada
   Group é isolado em `try/catch`; falha em um não impede os outros e vira
   entrada `error` no retorno, com log.
3. **Concorrência.** Lock `aculta_forum_membership_sync:<uid>` por 5 s evita
   corrida entre hooks; sem lock, a operação não prossegue e não há retry
   automático nesta fase. `syncAll()` existe para reconciliação manual.
4. **Gatilhos.** Hooks `aculta_associado_insert/update/delete` reconciliam o
   uid gravado na marca; ativação, suspensão, desligamento, readmissão e
   alteração administrativa passam pelo mesmo caminho. O uid só é lido depois de
   confirmar que a entidade é fieldable, do tipo esperado e com campo `uid`.
5. **Acesso de leitura.** `RestrictedForumAccessPolicy` devolve `forbidden` se a
   conta não é associada ativa ou não é membro de nenhum Group que restringe o
   tópico; `neutral` quando o tópico não é restrito, preservando o handler e as
   permissões existentes. Exceção do resolver vira `forbidden` com `max-age` 0 e
   log.
6. **Grants de listagem.** `hook_node_grants` devolve o realm
   `aculta_forum_restricted` com os gids em que a conta é membro, somente para
   `view`. `hook_node_access_records` grava um grant `grant_view` por Group que
   restringe o tópico; tópico público não recebe grant restrito e continua
   visível pela concessão `all` do core. Falha do resolver grava grant com
   `gid = 0` (fecha, não abre).
7. **Rebuild em mudanças de relação.** `RestrictedForumRelationshipHooks`
   regrava grants do tópico ao inserir/atualizar/remover relação do tópico e
   regrava todos os tópicos de uma categoria quando a relação da categoria muda.
   Relações de membership são ignoradas para não recursar.
8. **Cache.** `user` e `domain` como contextos; tags `user:<uid>`,
   `aculta_associado_list`, `node_list`, `group_relationship_list`,
   `group_relationship_list:plugin:group_membership:group:<gid>` quando a
   membership muda, e `node:<nid>` quando o grant é regravado.
9. **Desligado por padrão.** `restricted_group_type` nasce vazio: sem tipo
   configurado, nenhum Group é consultado, nenhum grant é emitido e o fórum
   público mantém o comportamento atual.
10. **Isolamento por construção.** Toda consulta de membership parte de
    `groupsOfType()`; membership em qualquer outro Group nunca vira grant nem
    acesso.

## Ativação (fora desta sessão)

A frente está implementada e desligada. Para ativar em um ambiente, nesta
ordem, com aprovação do responsável e backup do banco:

1. Criar o tipo de Group (por exemplo `forum_associados`), com os plugins de
   conteúdo de tópico e de categoria habilitados pelo administrador do Group.
   Nenhum `group.type.*` novo é distribuído pelo módulo nesta fase.
2. Conceder à role de membro do Group a permissão de grupo
   `view group_node:aculta_forum_topic entity`. O módulo Group **proíbe** a
   leitura de qualquer entidade agrupada sem uma permissão de grupo; sem esta
   permissão, nem o associado ativo com membership lê o tópico, mesmo com o
   grant de `node_access` correto. O teste Kernel da F-GROUP 0.2 reproduz a
   ausência desse passo para isolar a causa.
3. Configurar o tipo:
   `drush config:set aculta_forum.settings restricted_group_type forum_associados -y`
   (equivalente em `config/sync`; não executado aqui).
4. Criar o Group e relacionar tópicos ou categorias restritas.
5. Reconstruir os grants, porque ativar `hook_node_grants` coloca o site em modo
   de grants e a tabela `node_access` fica incompleta até o rebuild:
   `drush php:eval '\Drupal::service("Drupal\node\NodeAccessRebuild")->rebuild();'`
6. `drush cr` e validar listagem, busca, URL direta, anexos e contadores com
   conta ativa, suspensa, desligada, pendente e visitante.

Sem o passo 2, o `entity_access` do Fórum deixa de ser o único veto: o próprio
Group nega. Sem o passo 5, listagens e busca podem ficar inconsistentes mesmo
com o acesso direto corretamente bloqueado pelo `entity_access`.

## Matriz de testes

Resultado da F-GROUP 0.1 (2026-10-22): `tests/src/Unit/RestrictedForum` — **60
testes, 167 asserções, OK**; PHPCS limpo no diretório de testes e nos arquivos
de produção tocados; PHPStan nível 5 OK.

Resultado da F-GROUP 0.2 (2026-10-23), no banco isolado `aculta_simpletest`:

- `RestrictedForumGroupAclKernelTest` — 12 testes, 261 asserções, OK.
- `RestrictedForumNodeAccessRebuildKernelTest` — **4 testes, 92 asserções, OK**:
  hooks de produção registrados no fixture, `NodeAccessRebuild` do Core,
  linhas reais em `node_access`, `NodeAccessControlHandler::access()` e consulta
  com a tag `node_access`.
- Suíte Kernel completa do Fórum — **50 testes, 1.112 asserções, OK**.
- PHPCS Drupal/DrupalPractice limpo nos arquivos novos; PHPUnit reporta apenas
  deprecations de Core/Group já existentes no ambiente.

Nenhum teste Runtime, Functional ou de navegador foi executado nesta frente.

| Cenário obrigatório | Cobertura | Resultado |
| --- | --- | --- |
| Associado ativo com membership | `RestrictedForumAccessHooksTest` (grant por Group), `MembershipSyncTest` (não adiciona de novo) | PASS (Unit) |
| Associado ativo sem membership | Grant ausente; `MembershipSyncTest` adiciona | PASS (Unit) |
| Suspenso com membership antiga | `testInactiveAssociateGetsNoGrants` | PASS (Unit) |
| Desligado | Mesma regra de situação não ativa | PASS (Unit) |
| Pendente/convidado | Mesma regra de situação não ativa | PASS (Unit) |
| Usuário comum com e-mail confirmado | Sem situação ativa, logo sem grant nem acesso | PASS (Unit) |
| URL direta | Política `forbidden` delegada por `entity_access` | PASS (Unit) e PASS (Kernel, handler real); Runtime DEFERRED |
| Grant real por Group | `RestrictedForumNodeAccessRebuildKernelTest::testRebuildWritesRealmGrantOnlyForRestrictedTopic` | PASS (Kernel) |
| Membership no acesso de entidade | `testActiveMemberReadsRestrictedTopicThroughEntityAccess` (ativo+membro lê; ativo sem membership, suspenso com membership antiga e anônimo não leem; tópico público lê para todos) | PASS (Kernel) |
| Filtragem de listagem | `testNodeAccessQueryFiltersListing` (query com tag `node_access` por conta) | PASS (Kernel) |
| Rebuild de um tópico | `testGrantRebuilderUpdatesSingleTopicWithoutFullRebuild` (público → restrito → público) | PASS (Kernel) |
| Permissão de grupo ausente | Group proíbe a leitura de conteúdo agrupado sem `view group_node:aculta_forum_topic entity`; documentado como passo de ativação | Confirmado (Kernel) |
| Conteúdo restrito em busca | Depende de Search API e node grants no Runtime | DEFERRED — índice não provisionado |
| Anexos e mídia | Hooks de anexo ainda não conhecem Group | DEFERRED — implementação pendente |
| Invalidação de cache | Asserções de tags na sincronização e no rebuild | PASS (Unit) |
| Isolamento de Group | Consultas só pelo tipo configurado; relação de outro tipo ignorada | PASS (Unit) |
| Regressão do fórum público | Sem tipo configurado e tópico público não recebem grant restrito; outros bundles/operações seguem `neutral` | PASS (Unit); Runtime DEFERRED |
| Regressão do Group LMS | `lms_course`/`lms_class` recusados com log de erro | PASS (Unit) |

**Motivo dos DEFERRED restantes:** busca exige Search API indexado; anexos e
posts revisionáveis ainda não herdam o Group; e a validação Runtime exige janela
aprovada, backup e rebuild da tabela `node_access` do ambiente de testes. O
rebuild executado na F-GROUP 0.2 foi somente no banco Kernel isolado.

## Riscos residuais

- **Busca e contagem.** Enquanto o datasource `ContentEntity` e a contagem de
  resultados não forem validados com grants, um índice desatualizado pode expor
  total ou trecho de tópico restrito. Risco aceito apenas com a frente desligada
  (padrão).
- **Anexos.** `ForumAttachmentPublication::hasCurrentPublicReference()` e os
  hooks de acesso de anexo não consideram o Group do tópico; um anexo de tópico
  restrito pode ser servido por URL de mídia/arquivo. Bloqueia a ativação em
  produção.
- **Posts e comentários.** O acesso ao tópico não se propaga automaticamente ao
  post revisionável nem ao Comment; é preciso revalidar no handler do post.
- **Permissão de grupo.** Sem `view group_node:aculta_forum_topic entity` na
  role de membro, o módulo Group nega a leitura de todos os tópicos agrupados,
  inclusive para o associado ativo. É um veto independente do Fórum e precisa
  ser verificado na ativação e após qualquer alteração de roles do Group.
- **Modo de grants global.** Ativar `hook_node_grants` afeta todo o site até o
  rebuild terminar; executar em janela controlada, com backup.
- **Lock sem retry.** Uma corrida perdida no lock deixa a membership
  desatualizada até o próximo evento ou `syncAll()`; não há fila.
- **Auditoria.** O registro é log de canal do Fórum; não há trilha consultável
  de quem alterou membership por quê.
- **Dependência do Group 3.3.** A API usada é a estável (`getMember`,
  `addMember`, `removeMember`); revisar na atualização para Group 4.
- **Dados legados.** Tópicos antigos publicados antes da restrição não têm
  relação de Group e permanecem públicos; qualquer recorte retroativo é decisão
  de produto, não efeito colateral desta frente.

## Dependências de homelab

- Drush com acesso a `config:set`, `php:eval` e `cache:rebuild` no ambiente de
  testes (`*.aculta.toca.net.br`); nada foi executado nesta sessão.
- Módulo Group `^3.3` e banco de testes com tipo de Group provisionado.
- Search API com índice do Fórum para a validação de busca.
- Firefox ESR visível em `DISPLAY=:0` via geckodriver para a validação
  autenticada de navegador.

## F-GROUP-D — prova funcional em Kernel (concluída)

`RestrictedForumNodeAccessRebuildKernelTest` registra os hooks de produção no
fixture `aculta_forum_restricted_test` (adaptador fino
`RestrictedForumAccessTestHooks`, que só delega) e exercita o caminho real:

- `NodeAccessRebuild::rebuild()` grava `realm=aculta_forum_restricted` com o gid
  do Group no tópico restrito e o grant público `all:0` no tópico sem Group.
- `NodeAccessControlHandler::access('view')` libera apenas o associado `ativo`
  com membership e role de grupo; nega ativo sem membership, suspenso com
  membership antiga e anônimo, sem afetar o tópico público.
- Consulta `SELECT nid FROM node` com tag `node_access` e
  `addMetaData('account', …)` devolve a mesma visão, provando a filtragem de
  listagem.
- `RestrictedForumGrantRebuilder::rebuildForTopic()` acompanha a inclusão e a
  remoção da relação de Group sem rebuild global.

A prova revelou uma dependência de ativação que não estava documentada: o
módulo Group nega a leitura de conteúdo agrupado sem a permissão de grupo
`view group_node:aculta_forum_topic entity` na role de membro. O passo foi
incorporado à seção Ativação.

## Próxima microfase recomendada

**F-GROUP 0.3 — herança da restrição em posts revisionáveis e anexos.** O
acesso ao tópico não se propaga hoje ao `aculta_forum_post` nem ao Comment, e a
regra de anexo público ignora o Group. Implementar e testar em Kernel a
revalidação por tópico nos handlers de post, de comentário e de mídia, com
download privado revalidando a mesma condição. Em paralelo, preparar o roteiro
de Runtime controlado (backup, janela aprovada, rebuild de `node_access`,
listagem, URL direta e busca) para executar quando autorizado. Só depois ativar
em produção.

## Referências

- [`../README.md`](../README.md) · [`ROADMAP.md`](ROADMAP.md) ·
  [`POST-ACCESS-AND-PERMISSIONS.md`](POST-ACCESS-AND-PERMISSIONS.md) ·
  [`TEST-MATRIX.md`](TEST-MATRIX.md) · [`DECISOES.md`](DECISOES.md)
- [`../../aculta_user/docs/ASSOCIADO.md`](../../aculta_user/docs/ASSOCIADO.md)
- [Contratos compartilhados XK 0.1-B](../../../../../../../docs/architecture/ACULTA-XK-0.1-B-SHARED-CONTRACTS.md)
- [Auditoria transversal XK 0.1](../../../../../../../docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md)
