# Matriz de acesso editorial — `revista`

**Status:** levantamento de contratos reais (MAG 0.5-B.2). A matriz descreve o
que o Core, o `node_co_authors` e a configuração desta branch efetivamente
permitem hoje. Ela não propõe novas regras editoriais: estados, transições e
papéis permanecem inalterados.

**Método:** leitura de `NodeAccessControlHandler`
(`web/core/modules/node/src/NodeAccessControlHandler.php`), `NodeEntityHooks`
(`web/core/modules/node/src/Hook/NodeEntityHooks.php`),
`node_co_authors.module`, `StateTransitionValidation`,
`ModerationStateWidget`, `LatestRevisionCheck`, `EntityModerationForm` e
`EntityOperations`, confrontados com
`config/sync/workflows.workflow.revista.yml` e com os papéis
`anonymous`, `authenticated`, `email_confirmed`, `content_editor` e
`administrator`. Nenhuma permissão foi inferida: todas constam do Config Sync ou
são concedidas pelo Core a `is_admin`.

**Matriz ator × estado × ação:** `EDITORIAL-ACTOR-STATE-MATRIX.md` (MAG 0.5-B.5)
consolida a mesma verificação em tabelas de leitura, edição, transições e
demais ações por estado. Este documento mantém o detalhamento dos contratos.

## 1. Atores

| Ator | Como existe hoje | Permissões editoriais relevantes | Evidência |
| --- | --- | --- | --- |
| Anônimo | visitante sem sessão | `access content` | `user.role.anonymous.yml` |
| Autenticado sem papel editorial | conta com `authenticated` apenas | `access content`, `access aculta portal` | `user.role.authenticated.yml` |
| Conta confirmada | conta com `email_confirmed` | `access content`, `view own unpublished content`, `view latest version`, `view wiki_entry revisions` | `user.role.email_confirmed.yml` |
| Autor/editor (`content_editor`) | papel concedido manualmente | `create revista content`; `edit own`/`edit any revista content`; `view own`/`view any unpublished content`; `view all revisions`; `view revista revisions`; `revert all revisions`; as oito `use revista transition …` | `user.role.content_editor.yml` |
| Coautor | conta listada em `co_authors` | nada além do que o papel dela já concede; o contrib acrescenta `view`/`update`/`delete` **condicionados** (ver §5) | `node_co_authors_node_access()` |
| Administrador | `administrator`, `is_admin: true` | todas as permissões; `bypass node access` | `user.role.administrator.yml` |

Observações:

- `access content` é pré-requisito de qualquer operação de Node, inclusive
  `update` e `create` (`NodeAccessControlHandler::access()` e `createAccess()`
  negam sem ele). Todos os atores acima o possuem.
- Não existe papel de "revisor" separado. Revisão e publicação são permissões de
  transição do mesmo `content_editor`. Não há estado nem papel de aprovação
  dedicado.
- Nenhum papel possui `delete own revista content` ou `delete any revista
  content`. Excluir publicação, hoje, é operação de administrador.
- Nenhum papel possui `edit co-authors of own/co-authored/all content`. O campo
  `co_authors` fica somente leitura para `content_editor` (ver §5).
- A concessão de `content_editor` é manual; `email_confirmed` é papel de conta.
  A matriz descreve a combinação de papéis, não a política de atribuição.

## 2. Operações de entidade

Legenda: ✔ permitido; ✖ negado; **C** permitido sob condição indicada.

| Operação | Anônimo | Autenticado | `email_confirmed` | `content_editor` | Coautor | Administrador |
| --- | --- | --- | --- | --- | --- | --- |
| Ver publicação em `published` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Ver `draft`/`review`/`archived` próprio | ✖ | ✖ | ✔ | ✔ | ✔ | ✔ |
| Ver `draft`/`review`/`archived` de terceiro | ✖ | ✖ | ✖ | ✔ | ✖ | ✔ |
| Ver `draft`/`review`/`archived` coassinado | ✖ | ✖ | ✔ | ✔ | ✔ | ✔ |
| Criar (`node.add`) | ✖ | ✖ | ✖ | ✔ | ✔ (com o papel) | ✔ |
| Editar próprio | ✖ | ✖ | ✖ | ✔ | ✔ | ✔ |
| Editar de terceiro | ✖ | ✖ | ✖ | ✔ | ✔ | ✔ |
| Excluir | ✖ | ✖ | ✖ | ✖ | ✖ | ✔ |
| Ver revisão padrão | — | — | ✖ | ✔ | ✖ | ✔ |
| Ver revisão não padrão | ✖ | ✖ | ✖ | ✔ | ✖ | ✔ |
| Histórico de revisões | ✖ | ✖ | ✖ | ✔ | ✖ | ✔ |
| Reverter revisão | ✖ | ✖ | ✖ | ✔ | ✖ | ✔ |
| Preview de Node novo | ✖ | ✖ | ✖ | ✔ | ✔ (com o papel) | ✔ |
| Preview de Node existente | ✖ | ✖ | ✖ | ✔ | ✔ | ✔ |

Condições verificadas:

- **Ver não publicado.** `checkViewAccess()` devolve acesso quando o Node está
  publicado; caso contrário exige `view any unpublished content` **ou**
  (`view own unpublished content` **e** autoria). `email_confirmed` só alcança o
  que possui ou coassina; `content_editor` alcança tudo.
- **Ver revisão.** A operação `view revision` exige uma permissão de revisão
  (`view all revisions`, `view revista revisions` ou `administer nodes`) **e**
  `view` na revisão padrão e, para revisão não padrão, `view` também naquela
  revisão. `content_editor` tem `view all revisions` e `view any unpublished
  content`, portanto vê qualquer revisão. `email_confirmed` não tem permissão de
  revisão: não vê histórico nem revisões, mesmo das próprias publicações.
- **Histórico.** A rota `entity.node.version_history` usa `view all revisions`;
  `content_editor` passa direto. Sem essa permissão a rota nega.
- **Reverter.** `revert revision` exige `revert all revisions` (ou
  `revert revista revisions`), não pode ser a revisão padrão e ainda exige
  `update` na revisão padrão e na revisão revertida. `content_editor` atende.
- **Preview.** `NodePreviewAccessCheck` usa `createAccess('revista')` para Node
  novo e `update` para Node existente. Não existe permissão de preview. A rota
  declara `no_cache: TRUE`.
- **Editar.** `NodeEntityHooks::nodeAccess()` concede `update` com `edit any
  revista content` ou (`edit own revista content` **e** autoria). Não há
  distinção por estado: `draft`, `review`, `published` e `archived` têm o mesmo
  acesso de edição. O que muda por estado é a transição disponível (§3).
- **Excluir.** Idêntico, com `delete own/any revista content`; nenhum papel tem
  essas permissões. Consequência prática: a rota `entity.node.delete_form` nega
  para `content_editor`.

## 3. Workflow `revista` — estado × transição

Estados configurados (`workflows.workflow.revista.yml`): `draft` (padrão),
`review`, `published` e `archived`. Não há outros estados. A permissão de
transição é a única checagem de Workflows; ela **não substitui** o acesso de
edição: para salvar, o ator precisa de `update` **e** da permissão
`use revista transition <id>`.

| De | Transição | Para | Permissão | `content_editor` hoje |
| --- | --- | --- | --- | --- |
| `draft` | `create_new_draft` | `draft` | `use revista transition create_new_draft` | ✔ |
| `draft` | `publish` | `published` | `use revista transition publish` | ✔ |
| `draft` | `draft_to_review` | `review` | `use revista transition draft_to_review` | ✔ |
| `review` | `review_to_draft` | `draft` | `use revista transition review_to_draft` | ✔ |
| `review` | `review_to_published` | `published` | `use revista transition review_to_published` | ✔ |
| `published` | `create_new_draft` | `draft` | `use revista transition create_new_draft` | ✔ |
| `published` | `publish` | `published` | `use revista transition publish` | ✔ |
| `published` | `archive` | `archived` | `use revista transition archive` | ✔ |
| `archived` | `archived_draft` | `draft` | `use revista transition archived_draft` | ✔ |
| `archived` | `archived_published` | `published` | `use revista transition archived_published` | ✔ |

Atores sem nenhuma `use revista transition …` (anônimo, autenticado,
`email_confirmed`) não têm transições disponíveis. Como `email_confirmed`
também não tem `update`, não chegam ao formulário de edição.

Leitura de estado × ação para `content_editor`:

| Estado atual | Ações editoriais permitidas |
| --- | --- |
| `draft` | salvar rascunho, enviar para revisão, publicar |
| `review` | voltar a rascunho, publicar |
| `published` | criar nova revisão em rascunho, republicar, arquivar |
| `archived` | voltar a rascunho, republicar |

Nenhuma transição leva a estado "rejeitado": esse estado não existe nesta
configuração e não deve ser exibido pelo Studio.

O widget de moderação (`ModerationStateWidget::formElement()`) oferece ao
usuário apenas as transições devolvidas por
`StateTransitionValidation::getValidTransitions()`, filtradas por
`use revista transition <id>` a partir do estado da revisão carregada. Em Node
novo, o estado de partida é o `default_moderation_state` (`draft`); o seletor
"Salvar como" só oferece os destinos permitidos a partir dele.

## 4. Formulário `content_moderation_control`

Não existe rota. O extra field só é renderizado por
`EntityOperations::entityView()` quando **todas** as condições valem:

1. o display de visualização declara o componente (os displays `default`,
   `full` e `teaser` de `revista` declaram, desde 0.5-A);
2. a entidade não está em preview;
3. não é simultaneamente revisão padrão e publicada;
4. a revisão exibida é a mais recente (ou a mais recente afetada por tradução).

Consequências: um Node `draft`, `review` ou `archived` na revisão mais recente
mostra o formulário para quem tem acesso de `view`; um Node `published` na
revisão padrão não mostra, porque o próximo passo é criar nova revisão pelo
formulário de edição. O formulário filtra as transições pela conta atual e
descarta autotransições. Ele não concede acesso: é alcançável apenas dentro de
uma página que a conta já pode ver.

A rota `latest-version` (`/node/{node}/latest`) exige `node.view` **e**
`_content_moderation_latest_version`, que só permite quando existe revisão
pendente e a conta tem `view latest version` **e** `view any unpublished
content`, ou `view latest version` **e** `view own unpublished content` com
autoria. Resultado prático nesta configuração: `content_editor` tem
`view latest version` **e** `view any unpublished content` → alcança qualquer
Node; `email_confirmed` também tem `view latest version`, mas com
`view own unpublished content` → alcança **somente o próprio Node** com revisão
pendente, porque o teste do ramo `own` é a autoria (`uid`), não a coautoria.
Anônimo e autenticado sem papel editorial não têm `view latest version` → não
alcançam. Um coautor com `email_confirmed` que não seja o autor vê a revisão pela
rota canônica, quando ela lhe é acessível, e não pela aba `latest-version`.

## 5. Coautoria — semântica exata

`node_co_authors_node_access()` só opina se o Node tem o campo `co_authors` e a
conta está listada nele. O contrib **não** cria acesso próprio; ele combina a
participação na lista com uma permissão `own` já existente:

| Operação | Condição do contrib | Efeito real hoje |
| --- | --- | --- |
| `view` de Node não publicado | `view own unpublished content` **e** listado | Coautor com `email_confirmed` vê o rascunho/revisão/arquivado coassinado |
| `update` | `edit own revista content` **e** listado | Só relevante para papel que tenha `edit own` sem `edit any`; `content_editor` já tem `edit any` |
| `delete` | `delete own revista content` **e** listado | Nunca ocorre: nenhum papel tem a permissão |

Limites verificados:

- O contrib **não** concede acesso a revisões. Coautor sem `view revista
  revisions`/`view all revisions` não vê histórico nem revisão específica.
- O contrib **não** concede transições. Coautor sem `use revista transition …`
  não move estado.
- O contrib **não** concede edição a conta sem `edit own revista content`.
  Coautor com apenas `email_confirmed` lê o rascunho, mas não edita.
- `node_co_authors_entity_field_access()` exige `edit co-authors of own content`
  (autor), `edit co-authors of co-authored content` (coautor),
  `edit co-authors of all content` ou `administer nodes` para alterar
  `co_authors`. Nenhum papel comum tem essas permissões; na prática o campo é
  editável somente por administrador. O widget aparece no formulário, mas a
  alteração é negada por Field Access — comportamento do contrib, registrado
  como B-02 no contrato de integração.
- Coautor não é autor: `getOwnerId()` continua sendo o `uid`. Listagens
  "minhas publicações" por `uid` não incluem coautoria (B-03).

## 6. Cacheability

| Checagem | Contextos | Dependências |
| --- | --- | --- |
| `view` publicado | — | Node |
| `view` não publicado | `user.permissions` | Node |
| `view` de coautor | `user` (`cachePerUser()`) + `user.permissions` | Node |
| `update`/`delete` por autoria | `user.permissions`, `user` | Node |
| `create` | `user.permissions` | — |
| Operações de revisão | `user.permissions` | Node + revisão padrão |
| `latest-version` | `user.permissions`, `user` | Node |
| `content_moderation_control` | herda da página | Node, revisão |

Regras para o Studio:

- Toda decisão de acesso deve ser tomada por Entity Access ou Revision Access e
  a `CacheableMetadata` resultante deve subir para o render array/consulta. Não
  recalcular permissão por conta própria.
- Listagens usam `accessCheck(TRUE)`. `accessCheck(FALSE)` é proibido para
  conteúdo editorial, mesmo quando o filtro é por `uid` ou por `co_authors`.
- O cache do Studio declara `user`, `user.permissions` e
  `node_list:revista`; a listagem atual já o faz. Qualquer nova consulta deve
  preservar esses contextos e a tag.
- `getValidTransitions()` depende da conta, não do formulário renderizado. Um
  consumidor que precise listar ações deve chamá-lo (ou o
  `StateTransitionValidationInterface`) e combinar com `access('update')`; não
  deve deduzir ações de permissão genérica nem de estado apenas.

## 7. Cenários obrigatórios para 0.5-B.4

Derivados diretamente desta matriz; cada um deve virar teste Kernel ou
Functional, não apenas teste estático:

1. Anônimo vê `published` e recebe 403 para `draft`, revisão, histórico,
   preview e `node.add`.
2. Autor A (`content_editor`) cria e edita o próprio rascunho.
3. Autor A edita o Node do Autor B (`edit any`); Autor B sem papel editorial
   recebe 403.
4. Coautor com `email_confirmed` vê o rascunho coassinado e recebe 403 para
   editar, ver revisão e executar transição.
5. Coautor com `content_editor` edita; a coautoria não acrescenta revisão,
   exclusão nem transição além do papel.
6. Revisão não padrão: `view revision` permitido só com permissão de revisão e
   `view` na revisão.
7. `latest-version` alcançável por `content_editor`, negado a autor sem
   permissão de revisão.
8. Transição permitida (`draft_to_review`) aceita; transição não permitida no
   estado (`review_to_published` a partir de `draft`) recusada mesmo com a
   permissão.
9. `content_editor` recebe 403 em `entity.node.delete_form`.
10. Preview de Node novo e existente segue `createAccess`/`update`.
11. Cache entre contas: a mesma listagem/hot page não reaproveita decisão de
    outra conta (contextos `user`, `user.permissions`).
12. Formulário de edição sem JavaScript preserva o campo de moderação e o grupo
    de campos secundários.

## 8. O que esta matriz não altera

- Estados, transições, permissões e papéis permanecem como no Config Sync.
- Nenhuma importação de configuração, nenhuma migração e nenhuma alteração
  persistente no Runtime foram feitas para produzir este documento.
- Correções de produto continuam fora desta fase: B-02 (coautoria na interface)
  e B-03 (listagem de coassinados) dependem de decisão com `aculta_user` e
  `aculta_studio`; B-01 (purpose `main`) e B-04 (links de preview/histórico) são
  do Studio, em commit próprio.
