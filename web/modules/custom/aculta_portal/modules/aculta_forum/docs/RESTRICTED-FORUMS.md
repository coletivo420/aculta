# Fóruns restritos para associados ativos — F-GROUP 0.1 a 0.4

Documento das frentes **F-GROUP 0.1** (inventário e fundação), **0.2** (prova de
grants em Kernel), **0.3** (herança em posts/comments e auditoria de anexos) e
**0.4** (anexos pela conta atual). Cobre o inventário (F-GROUP-A), as decisões
de contrato e a fundação (F-GROUP-B), o limite do que foi provado (F-GROUP-C) e
as provas em Kernel (F-GROUP-D) e em Runtime controlado (F-GROUP-E).

O Runtime de testes foi alterado apenas na janela controlada de 2026-10-23,
com backup verificado, e **revertido ao estado inicial**: a fixture foi
removida, `restricted_group_type` voltou a vazio e `node_access` voltou a
`realm=all` (21 linhas). Produção não foi tocada. Os gates executados são
Unit, Kernel, PHPCS e o roteiro de Runtime; busca/contagem e a matriz de cache
de página no Runtime seguem **DEFERRED**.

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
2. Criar a role de membro como **sincronizada insider** com a role global
   `authenticated` e a permissão `view group_node:aculta_forum_topic entity`.
   O módulo Group **proíbe** a leitura de qualquer entidade agrupada sem uma
   permissão de grupo; sem ela, nem o associado ativo com membership lê o
   tópico, mesmo com o grant de `node_access` correto. A role individual não é
   reatribuída quando o sync recria a membership após uma suspensão, então a
   reativação voltaria sem acesso; a role sincronizada não tem esse problema.
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

## Roteiro de Runtime controlado (não executado)

Este roteiro só deve ser executado no ambiente de testes
`*.aculta.toca.net.br`, com autorização explícita, janela reservada e backup
verificado. Produção `*.aculta.org` não participa desta fase.

1. **Backup e reversão.** Gerar dump do banco e da configuração antes de
   qualquer escrita; validar o dump com `gzip -t` e guardar o hash. Registrar o
   estado de `node_access` (`SELECT realm, COUNT(*) FROM node_access GROUP BY
   realm`) e a configuração `aculta_forum.settings`.
2. **Provisionamento.** Criar o tipo de Group, os plugins de conteúdo, a role
   de membro e a permissão `view group_node:aculta_forum_topic entity`; criar
   um Group de teste e relacionar um tópico de teste. Não usar conteúdo real.
3. **Ativação.** `config:set restricted_group_type`, rebuild de `node_access`,
   `drush cr`. Confirmar que a tabela tem grants por nó e nenhum `nid = 0`
   indevido.
4. **Matriz de contas.** Com anônimo, autenticado sem associação, associado
   `pendente`, `convidado`, `suspenso`, `desligado` e `ativo` com e sem
   membership, medir: listagem `/`, URL direta do tópico, busca, contadores,
   link de primeiro não lido e download de anexo. Esperado: só `ativo` com
   membership lê; todos os demais recebem 403/ausência, sem título na resposta.
5. **Cache e domínio.** Repetir após `drush cr` e após mudar a situação de um
   associado; confirmar revogação imediata e que o domínio `main` não serve o
   tópico restrito.
6. **Desligamento.** `config:set restricted_group_type ''`, rebuild e `drush cr`
   restauram o fórum público. O conteúdo e as relações não são apagados.
   Restaurar o backup apenas se a validação falhar.

Critérios de interrupção: qualquer resposta 200 com título de tópico restrito
para conta não elegível, qualquer grant `all:0` em tópico restrito, ou rebuild
incompleto. Nesses casos, desligar a frente e restaurar o backup antes de
investigar.

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
| URL direta | Política `forbidden` delegada por `entity_access` | PASS (Unit), PASS (Kernel) e PASS (Runtime: HTTP 403 anônimo, 200 só para membro ativo) |
| Grant real por Group | `RestrictedForumNodeAccessRebuildKernelTest::testRebuildWritesRealmGrantOnlyForRestrictedTopic` | PASS (Kernel) |
| Membership no acesso de entidade | `testActiveMemberReadsRestrictedTopicThroughEntityAccess` (ativo+membro lê; ativo sem membership, suspenso com membership antiga e anônimo não leem; tópico público lê para todos) | PASS (Kernel) |
| Filtragem de listagem | `testNodeAccessQueryFiltersListing` (query com tag `node_access` por conta) | PASS (Kernel) |
| Rebuild de um tópico | `testGrantRebuilderUpdatesSingleTopicWithoutFullRebuild` (público → restrito → público) | PASS (Kernel) |
| Permissão de grupo ausente | Group proíbe a leitura de conteúdo agrupado sem `view group_node:aculta_forum_topic entity`; documentado como passo de ativação | Confirmado (Kernel) |
| Conteúdo restrito em busca | Depende de Search API e node grants no Runtime | DEFERRED — índice não provisionado |
| Anexos e mídia | Media publicada pela referência; `media_access` e File revalidam a conta | PASS (Kernel) e PASS (Runtime: Media e download só para membro ativo) |
| Invalidação de cache | Asserções de tags na sincronização e no rebuild | PASS (Unit) |
| Isolamento de Group | Consultas só pelo tipo configurado; relação de outro tipo ignorada | PASS (Unit) |
| Regressão do fórum público | Sem tipo configurado e tópico público não recebem grant restrito; outros bundles/operações seguem `neutral` | PASS (Unit); Runtime DEFERRED |
| Regressão do Group LMS | `lms_course`/`lms_class` recusados com log de erro | PASS (Unit) |

**Motivo dos DEFERRED restantes:** busca e contagem exigem Search API indexado
no Runtime; a matriz de cache de página e a regressão do fórum público no
Runtime ainda não foram medidas. Anexos, posts e Comments foram validados em
Kernel e no Runtime controlado de 2026-10-23 (ver F-GROUP-E).

## Riscos residuais

- **Busca e contagem.** Enquanto o datasource `ContentEntity` e a contagem de
  resultados não forem validados com grants, um índice desatualizado pode expor
  total ou trecho de tópico restrito. Risco aceito apenas com a frente desligada
  (padrão).
- **Anexos publicados.** A Media é publicada quando há referência ativa em
  post/comment publicado de tópico publicado, inclusive restrito, e o acesso é
  revalidado pela conta em `media_access` e no download privado do File. Anexo
  de tópico restrito continua negado a anônimo, suspenso e sem membership. O
  ponto de atenção mudou de "vazamento" para "mídia publicada": views,
  limpeza e outros consumidores que tratam `status = 1` como público precisam
  ser revistos antes de ampliar o uso.
- **Posts e comentários.** A herança existe por composição: o hook do post
  revalida `$topic->access()` e o Core Comment revalida o host. Falta a prova
  Kernel com o módulo completo e a matriz de cache sob o purpose `forum`.
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

- Drush no checkout do servidor de testes `toca` (Runtime `aculta_runtime`,
  banco `aculta_runtime`), confirmado com `drush status` em 2026-10-23.
- Módulo Group `^3.3` e banco de testes com tipo de Group provisionado.
- Search API com índice do Fórum para a validação de busca.
- Firefox ESR visível em `DISPLAY=:0` via geckodriver para a validação
  autenticada de navegador; o login centraliza em
  `conta.aculta.toca.net.br` e o cookie de sessão usa
  `Domain=.aculta.toca.net.br`.

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

## F-GROUP 0.3 — auditoria de herança (posts, comentários e anexos)

Auditoria de código em 2026-10-23, sem alteração de produção:

- **Posts revisionáveis — herdado.** `ForumPostAccessHooks::publicAccess()`
  compõe `$topic->access('view', $account, TRUE)` antes de decidir `view` e
  `update`. Um tópico restrito que nega a conta nega o post, inclusive ao dono.
  `tests/src/Unit/Hook/ForumPostAccessHooksTest::testRestrictedTopicAccessPropagatesToPost`
  cobre `view` e `update` com tópico `forbidden`.
- **Comments — herdado pelo Core.** `CommentAccessControlHandler::checkAccess()`
  aplica `andIf($comment->getCommentedEntity()->access('view', $account, TRUE))`.
  O Comment de tópico restrito nega a conta, e não há hook do Fórum a duplicar.
- **Anexos — resolvido na 0.4.** Na época da 0.3 a referência era sempre
  anônima e o anexo ficava negado a todos. A 0.4 separou publicação de acesso:
  a Media é publicada por referência ativa em host publicado e o acesso é
  revalidado pela conta. Ver a seção seguinte.

## F-GROUP 0.4 — anexos pela conta atual (concluída)

Implementação escolhida: **publicar a Media do tópico restrito** (opção B), com
acesso revalidado pela conta.

- `ForumAttachmentPublication::isPubliclyReferenced()` ganhou `$account` e
  `$check_access`. A publicação (`syncMedia`) usa `check_access: FALSE` e passa
  a considerar referência em post/comment de tópico publicado, inclusive
  restrito. `hasCurrentPublicReference($media, $account)` avalia o host para a
  conta, caindo no anônimo quando nenhuma é dada.
- `ForumAttachmentAccessHooks::mediaAccess()` decide para a Media publicada:
  `forbidden` para conta sem referência acessível e `allowed` para a conta que
  lê o post/comment. Mídia não publicada continua neutra (dono/moderador).
- O download privado do File já revalida a Media via `FileReferenceResolver`;
  com o hook decisivo, a cadeia File → Media → tópico fecha sem permissão
  ampla. Nenhum hook de `file_access` foi adicionado.
- Teste Kernel novo em `ForumAttachmentPublicationKernelTest`: com a permissão
  de comentário anônimo revogada (host não legível anonimamente), a Media é
  publicada, o anônimo é negado e a conta autorizada é liberada.
- **Galerias.** `ForumGalleryPublication::syncGallery()` publica pela referência
  ativa (mesma regra) e `ForumAttachmentAccessHooks::entityAccess()` decide a
  galeria `forum` por conta, com `hasCurrentPublicGalleryReference()`. O
  slideshow continua filtrando cada imagem por `media->access()`. Teste Kernel
  próprio: galeria publicada, anônimo negado, membro liberado.
- **Documentos e Media embutida.** O formato `forum` só permite
  `media_embed` com o bundle `forum_attachment`; não há botão `entity_embed`
  configurado. Os visualizadores de PDF e documento revalidam
  `media->access('view')` e `file->access('download')`. A revisão não encontrou
  embed de outro bundle no Fórum.
- **Consumidores de Media publicada.** `UnusedMediaCleaner` recusa Media
  publicada (`OwnerRejected`); `ForumAttachmentCleanup` só remove `status=0` e
  recheca antes de apagar; listagens administrativas exigem permissão; o Index
  revalida Entity Access. Nenhum consumidor público trata `status=1` como
  acesso irrestrito.
- **Cache de acesso.** `RestrictedForumAccessPolicy::result()` agora aplica
  `setCacheMaxAge(0)`. Sem isso, o `cache.access` mantinha o 403 depois da
  reativação do associado. A revalidação passa a ocorrer em toda requisição,
  como o contrato já prometia.
- **Role de ativação.** A fixture e a documentação passam a usar role
  **sincronizada insider** com `authenticated` (`global_role`), não individual.
  O `RestrictedForumMembershipSync` recria a membership sem roles; com role
  individual, a reativação voltava sem a permissão
  `view group_node:aculta_forum_topic entity` e o Group negava o tópico.
- Suíte: Unit 169/992 OK; Kernel do Fórum 52/1.170 OK; PHPCS limpo.

## F-GROUP-E — Runtime controlado de 2026-10-23 (executado e revertido)

Ambiente: host `toca`, Runtime `aculta_runtime`, banco `aculta_runtime`,
Drupal 11.4.10, PHP 8.4.26, branch `docs/aculta-forum-0.1.0-dev.1`.

- **Backup:** `aculta_runtime-pre-fgroup-20261023.sql.gz`, 4.773.934 bytes,
  `gzip -t` OK, SHA-256
  `72714d90d807e88219a770567c2bcd92ba3f8745a4a2da3739b085fe6fc36dd0`.
- **Estado inicial:** `restricted_group_type` vazio, `node_access` só
  `realm=all` (21 linhas), zero tópicos do Fórum, Group types apenas
  `lms_class`/`lms_course`.
- **Fixture:** Group type `forum_associados`, role de membro **sincronizada
  insider** com `authenticated` e `view group_node:aculta_forum_topic entity`,
  um Group, um tópico restrito e um tópico público (sem Group), quatro contas
  (ativo com membership, ativo sem, suspenso com, comum) e as situações
  correspondentes em `aculta_associado`.
- **Ativação:** `restricted_group_type=forum_associados`, rebuild de
  `node_access`, `drush cr`. O tópico recebeu uma linha
  `aculta_forum_restricted`/gid do Group e nenhum `all:0`.
- **Medição por conta (Drush):** `node->access('view')` e query com tag
  `node_access` — anônimo, ativo sem membership, suspenso com membership e
  comum: FALSE/vazio; ativo com membership: TRUE e único NID na listagem.
- **HTTP anônimo:** `https://growroom420.aculta.toca.net.br/node/<nid>` → 403,
  sem título; a home → 200, sem o título restrito.
- **Firefox ESR/X11:** sessões isoladas por conta (o cookie
  `Domain=.aculta.toca.net.br` é compartilhado, então cada conta exige um
  perfil limpo). Ativo com membership: título visível e presente na listagem.
  Suspenso com membership: 403, sem título e ausente da listagem. Capturas
  privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-fgroup-20261023/`.
- **Anexos, antes de B:** Media não publicada, referência anônima falsa e
  `media->access('view')` falso para anônimo, membro ativo e suspenso — fail
  closed, sem acesso nem para o membro.
- **Anexos, depois de B:** Media publicada, referência anônima falsa;
  `media->access('view')` e `file->access('download')` permitidos só ao
  associado ativo com membership. O `File` existe no banco e o acesso de
  download foi medido, mas o arquivo físico **não** foi gravado: o diretório
  `private://forum/anexos` não existia e não foi criado nesta sessão
  (`file_exists: false`). O download HTTP real do arquivo fica pendente.
- **Galeria, depois de B:** galeria `forum` publicada, referência anônima falsa
  e `gallery->access('view')` liberado só ao associado ativo com membership;
  anônimo e suspenso negados.
- **Regressão pública:** o tópico público (grant `all:0`) responde 200 e é
  legível por anônimo, ativo, suspenso e comum; a home anônima mostra o título
  público e não o restrito. O ativo vê os dois; o suspenso vê só o público.
- **Cache de página:** o 403 anônimo do restrito é cacheado (`X-Drupal-Cache`
  MISS e depois HIT), sem título; a home anônima é HIT e só contém o público.
  Respostas autenticadas são `UNCACHEABLE`; não há vazamento entre contas.
- **Revogação e reativação:** com a role sincronizada e a política sem cache,
  suspender o associado leva de 200 a 403 na requisição seguinte e reativar
  volta a 200, **sem** `drush cr` entre as etapas.
- **Reversão:** `restricted_group_type=''`, rebuild, remoção de Group, tópicos,
  galerias, Media/Files da fixture, contas, associações, role e Group type.
  Estado final conferido: `realm=all:21`, zero tópicos, zero contas
  `fgroup_teste_*`, Group type, galerias, Media e Files da fixture ausentes. O
  backup permanece como garantia.
- **Limitações:** busca e contagem não foram medidas (índice não provisionado);
  o download HTTP do arquivo físico e a matriz de cache de busca continuam
  pendentes.

## Próxima microfase recomendada

**F-GROUP 0.5 — busca e contagem sob grants.** Validar o datasource
`ContentEntity` e a paginação/contagem com `node_access` no Runtime, incluindo
o caso de índice atrasado; cobrir em Kernel o processador de busca que já
revalida a publicação e os grants do tópico. O índice `aculta_forum` já tem
`content_access` e o processor `forum_post_search_access` configurados, mas a
medição real depende de reindexar a fixture. O download HTTP do arquivo físico
também fica para essa janela. Só depois avaliar a ativação em produção.

## Referências

- [`../README.md`](../README.md) · [`ROADMAP.md`](ROADMAP.md) ·
  [`POST-ACCESS-AND-PERMISSIONS.md`](POST-ACCESS-AND-PERMISSIONS.md) ·
  [`TEST-MATRIX.md`](TEST-MATRIX.md) · [`DECISOES.md`](DECISOES.md)
- [`../../aculta_user/docs/ASSOCIADO.md`](../../aculta_user/docs/ASSOCIADO.md)
- [Contratos compartilhados XK 0.1-B](../../../../../../../docs/architecture/ACULTA-XK-0.1-B-SHARED-CONTRACTS.md)
- [Auditoria transversal XK 0.1](../../../../../../../docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md)
