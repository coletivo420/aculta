# Matriz ator × estado × ação — `revista`

**Status:** MAG 0.5-B.5 — inspeção do workflow real. Esta matriz descreve apenas
o que a configuração desta branch e o Core permitem hoje. Nenhum estado,
transição, papel ou permissão foi criado, removido ou reinterpretado.

**Data da verificação:** 2026-10-22, sobre a branch
`docs/aculta-forum-0.1.0-dev.1` (base `56d5dc94`).

## 1. Fonte verificada

| Fonte | O que foi lido |
| --- | --- |
| `config/sync/workflows.workflow.revista.yml` | estados, transições, `default_moderation_state`, `published` e `default_revision` de cada estado |
| `config/sync/user.role.{anonymous,authenticated,email_confirmed,content_editor,administrator}.yml` | permissões reais de cada papel |
| `web/core/modules/node/src/NodeAccessControlHandler.php` | `view`, `view revision`, `revert revision`, `create` |
| `web/core/modules/content_moderation/src/Hook/ContentModerationHooks.php` | veto de `update` sem transição válida |
| `web/core/modules/content_moderation/src/StateTransitionValidation.php` | filtro de transições por `use revista transition <id>` |
| `web/core/modules/content_moderation/src/Access/LatestRevisionCheck.php` | acesso à aba `latest-version` |
| `web/core/modules/content_moderation/src/Entity/Routing/EntityModerationRouteProvider.php` | requisitos da rota `latest-version` |
| `web/core/modules/content_moderation/src/EntityOperations.php` | condições do formulário `content_moderation_control` |
| `web/modules/contrib/node_co_authors/node_co_authors.module` | semântica de coautoria |

Nenhuma chamada de configuração foi executada para produzir esta matriz. A
leitura é estática e confrontada com a configuração versionada.

## 2. Estados que existem de fato

| Estado | Rótulo | `published` | `default_revision` | Peso |
| --- | --- | --- | --- | --- |
| `draft` | Rascunho | `false` | `false` | -5 |
| `review` | Em revisão | `false` | `false` | 1 |
| `published` | Publicado | `true` | `true` | 0 |
| `archived` | Arquivado | `false` | `true` | 5 |

- `default_moderation_state: draft`: todo Node novo nasce em `draft`.
- Revisões em `draft` e `review` **não** são revisões padrão. Revisões em
  `published` e `archived` **são**.
- Consequência: um Node com revisão padrão `published` e revisão mais recente
  `draft` continua visível ao público pela revisão padrão, enquanto o autor vê a
  revisão pendente por `latest-version`.
- **Não existe estado "rejeitado"**, "em aprovação", "aguardando" ou qualquer
  outro. O Studio não deve exibir nem oferecer tais estados.

## 3. Transições que existem de fato

| De | Transição | Rótulo | Para |
| --- | --- | --- | --- |
| `draft` | `create_new_draft` | Salvar rascunho | `draft` |
| `draft` | `draft_to_review` | Enviar para revisão | `review` |
| `draft` | `publish` | Publicar | `published` |
| `review` | `review_to_draft` | Devolver para correção | `draft` |
| `review` | `review_to_published` | Publicar | `published` |
| `published` | `create_new_draft` | Salvar rascunho | `draft` |
| `published` | `publish` | Publicar | `published` |
| `published` | `archive` | Arquivar | `archived` |
| `archived` | `archived_draft` | Restaurar rascunho | `draft` |
| `archived` | `archived_published` | Restaurar publicação | `published` |

Observações que valem para qualquer ator:

- A permissão de transição não substitui `update`. Para salvar uma mudança de
  estado, o ator precisa de `update` **e** `use revista transition <id>`.
- `getValidTransitions()` filtra somente por `use revista transition <id>` a
  partir do estado da revisão carregada. Não há filtro por autor, coautoria ou
  estado-alvo além dessa permissão.
- `ContentModerationHooks::entityAccess()` **nega `update`** de entidade
  moderada quando `getValidTransitions()` volta vazio. Portanto, um ator sem
  nenhuma permissão `use revista transition …` não consegue editar a publicação,
  mesmo que tenha `edit own/any revista content`.
- O formulário `content_moderation_control` descarta autotransições
  (`create_new_draft` a partir de `draft`, `publish` a partir de `published`).
  Ele não é renderizado para quem não tem nenhuma transição válida.

## 4. Atores e base de permissão

| Ator | Papel(is) | Permissões editoriais relevantes |
| --- | --- | --- |
| Anônimo | `anonymous` | `access content` |
| Autenticado | `authenticated` | `access content`, `access aculta portal` |
| E-mail confirmado | `email_confirmed` | `access content`, `view own unpublished content`, `view latest version`; nenhuma permissão de `revista`, nenhuma transição |
| Autor/editor | `content_editor` | `create revista content`, `edit own/any revista content`, `view any/own unpublished content`, `view all revisions`, `view revista revisions`, `revert all revisions`, `view latest version`, as oito `use revista transition …` |
| Coautor | o papel da conta **mais** estar em `co_authors` | a coautoria não acrescenta permissão: combina a lista com `view own unpublished content`, `edit own revista content` ou `delete own revista content` do papel |
| Administrador | `administrator` (`is_admin`) | todas as permissões e `bypass node access` |

Não existe papel de revisor. Revisar e publicar são permissões de transição do
próprio `content_editor`.

## 5. Matriz — ver (`node.view`) por estado da revisão pedida

Legenda: ✔ permitido; ✖ negado; **C** permitido sob a condição indicada.

A rota importa: a canônica carrega a **revisão padrão**; `latest-version` carrega
a **revisão mais recente**; a rota de revisão explícita carrega a revisão pedida
e exige `view revision`. A tabela considera o estado da revisão que se tenta
carregar.

| Estado da revisão | Anônimo | Autenticado | `email_confirmed` | Coautor com `email_confirmed` | `content_editor` | Coautor com `content_editor` | Administrador |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `draft` | ✖ | ✖ | **C** próprio | **C** coassinado, só se for a revisão padrão | ✔ qualquer | ✔ qualquer | ✔ |
| `review` | ✖ | ✖ | **C** próprio (via `latest-version`) | ✖ | ✔ qualquer | ✔ qualquer | ✔ |
| `archived` | ✖ | ✖ | **C** próprio | **C** coassinado | ✔ qualquer | ✔ qualquer | ✔ |
| `published` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |

Condições:

- **Próprio:** `view own unpublished content` + autoria (`uid`). Cobre a revisão
  padrão pela rota canônica e a revisão mais recente pela aba `latest-version`,
  que exige revisão pendente (ver §7).
- **Coassinado:** `node_co_authors_node_access()` devolve acesso a `view` de Node
  não publicado quando a conta tem `view own unpublished content` **e** está em
  `co_authors`. Sem a permissão, a lista de coautores não concede nada.
- **Qualquer:** `view any unpublished content`. `content_editor` possui.
- Um Node `published` na revisão padrão é visível a todos os atores com
  `access content`, mesmo que exista uma revisão `draft` pendente. A revisão
  pendente não é visível pela rota canônica; só por `latest-version`, com as
  condições da §7.
- `review` **nunca** é revisão padrão (`default_revision: false`). Um coautor com
  `email_confirmed` não tem `view revision` nem satisfaz o ramo de autoria de
  `latest-version`, então não alcança a revisão em `review` por rota alguma. A
  rota canônica lhe mostra a revisão padrão (`draft` inicial ou `published`
  anterior), não o conteúdo em revisão.
- O mesmo vale para uma revisão `draft` pendente criada depois de uma publicação:
  a canônica continua servindo a revisão `published`; o coautor não vê a
  pendente. A célula **C** de `draft` para coautor vale apenas quando o rascunho
  é a própria revisão padrão (Node novo, nunca publicado).

## 6. Matriz — editar e transicionar por estado

A célula indica `update` permitido e as transições que o ator pode executar
naquele estado. `update` exige, além da permissão de edição, pelo menos uma
transição válida.

| Ator | `draft` | `review` | `published` | `archived` |
| --- | --- | --- | --- | --- |
| Anônimo | ✖ | ✖ | ✖ | ✖ |
| Autenticado | ✖ | ✖ | ✖ | ✖ |
| `email_confirmed` | ✖ | ✖ | ✖ | ✖ |
| Coautor com `email_confirmed` | ✖ | ✖ | ✖ | ✖ |
| `content_editor` | ✔ — `create_new_draft`, `draft_to_review`, `publish` | ✔ — `review_to_draft`, `review_to_published` | ✔ — `create_new_draft`, `publish`, `archive` | ✔ — `archived_draft`, `archived_published` |
| Coautor com `content_editor` | ✔ — idêntico ao `content_editor` | ✔ — idem | ✔ — idem | ✔ — idem |
| Administrador | ✔ — todas | ✔ — todas | ✔ — todas | ✔ — todas |

Consequências práticas:

- `email_confirmed` e coautor com esse papel **não editam** publicação: falta
  `edit own revista content` e falta transição; qualquer um dos dois já basta
  para o Core negar `update`.
- `content_editor` edita em todos os quatro estados. O estado não restringe
  `update`; restringe apenas quais transições aparecem e são aceitas.
- A coautoria não acrescenta transição. Um coautor sem `content_editor` não
  move estado; um coautor com `content_editor` move exatamente como o papel.
- Não há transição a partir de `review` para `archived`, nem de `archived` para
  `review`, nem "rejeitar". Não inventar esses caminhos.

## 7. Demais ações editoriais

| Ação | Quem pode | Base verificada |
| --- | --- | --- |
| Criar `node.add` de `revista` | `content_editor`, administrador | `createAccess()` exige `create revista content` e `access content` |
| Excluir publicação | administrador | nenhum papel tem `delete own/any revista content`; a rota nega ao `content_editor` |
| Ver histórico (`/node/{node}/revisions`) | `content_editor`, administrador | rota exige `node.view all revisions`; `content_editor` tem `view all revisions` |
| Ver revisão específica | `content_editor`, administrador | `view revision` exige `view all revisions` ou `view revista revisions`, e `view` na revisão padrão e na revisão pedida |
| Reverter revisão | `content_editor`, administrador | `revert all revisions`, revisão não padrão, `update` nas duas revisões |
| Preview de Node novo | `content_editor`, administrador | `NodePreviewAccessCheck` usa `createAccess('revista')` |
| Preview de Node existente | `content_editor`, coautor com `content_editor`, administrador | `NodePreviewAccessCheck` usa `update`; coautoria não amplia |
| Abrir `latest-version` | `content_editor`, administrador, e `email_confirmed` **somente** no próprio Node com revisão pendente | rota exige `node.view`, existência de revisão pendente e (`view latest version` + `view any unpublished content`) ou (`view latest version` + `view own unpublished content` + autoria) |
| Ver o formulário `content_moderation_control` | qualquer conta que já vê a revisão mais recente não publicada **e** tem ao menos uma transição não autotransição | renderizado por `EntityOperations::entityView()`; na prática só `content_editor` e administrador recebem o formulário |
| Editar `co_authors` | administrador | `edit co-authors of own/co-authored/all content` não é concedida a nenhum papel; o campo aparece, mas o Field Access nega (B-02) |

Sobre a aba `latest-version`: `email_confirmed` possui `view latest version` e
`view own unpublished content`, portanto alcança a aba **do próprio Node** com
revisão pendente. A coautoria não conta para o teste de autoria desse acesso.
Um coautor com `email_confirmed` que não seja o autor vê a revisão **padrão**
pela rota canônica, quando ela lhe é acessível, e não a revisão pendente: a aba
`latest-version` exige autoria no ramo `own`, e a coautoria não conta.

## 8. Revisão padrão × revisão mais recente

A mesma publicação pode ter estados diferentes em duas revisões:

| Situação | Revisão padrão | Revisão mais recente | Efeito |
| --- | --- | --- | --- |
| Rascunho novo | `draft` | `draft` | invisível ao público; editor vê e edita |
| Enviado para revisão | `draft` | `review` | público continua sem acesso; editor vê `review` |
| Publicado | `published` | `published` | público vê; editor pode criar nova revisão |
| Publicado com alteração pendente | `published` | `draft` (ou `review`) | público continua vendo a versão publicada; editor vê a pendente e edita por `latest-version` |
| Arquivado | `archived` | `archived` | público perde a visão; editor pode restaurar |

Regras para listagens e links do Studio:

- A situação exibida de uma publicação é a da revisão mais recente **somente**
  quando a conta pode ver aquela revisão (`view` + `view revision`). Caso
  contrário, a listagem deve omitir a publicação, não rotulá-la com estado
  privado (fail closed).
- O link de edição leva ao formulário, que carrega a revisão mais recente por
  `load_latest_revision`. O acesso continua sendo o do Core.
- A rota canônica serve a revisão padrão. Não usar a canônica para inferir o
  estado editorial corrente de uma publicação.

## 9. Cacheability das decisões

| Decisão | Contextos | Dependências |
| --- | --- | --- |
| `view` publicado | — | Node |
| `view` não publicado | `user.permissions`, `user` (autoria) | Node |
| `view` por coautoria | `user`, `user.permissions` | Node |
| `update`/`delete` | `user.permissions`, `user` | Node |
| `create` | `user.permissions` | — |
| Operações de revisão | `user.permissions` | Node + revisão padrão |
| Transições válidas | `user.permissions` | Workflow + Node |
| `latest-version` | `user.permissions`, `user` | Node |

O Studio deve consumir a decisão de Entity Access/Revision Access e propagar a
`CacheableMetadata`. Não recalcular estado ou permissão por conta própria.

## 10. O que esta matriz não altera e não autoriza concluir

- Não altera estados, transições, papéis, permissões nem o Config Sync.
- Não cria o estado "rejeitado" nem qualquer estado de aprovação.
- Não transforma coautoria em autoridade editorial: coautor continua sem
  transições e sem acesso a revisões além do papel.
- Não concede exclusão ao `content_editor`.
- Não substitui os testes de autorização de 0.5-B.4; a matriz é documentação
  derivada da configuração, não uma nova regra de produto.
- Correção registrada nesta inspeção: `email_confirmed` **possui**
  `view latest version`. A afirmação anterior de que nenhum outro papel além de
  `content_editor` tinha essa permissão estava incorreta e foi corrigida em
  `EDITORIAL-ACCESS-MATRIX.md`.
