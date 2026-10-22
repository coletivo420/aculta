# Contrato de integração Studio ↔ Magazine

**Status:** inventário real (MAG 0.5-B.1). Este documento descreve somente
contratos que existem no código e na configuração desta branch. O que ainda não
existe está marcado como lacuna, não como API.

**Propriedade:** `aculta_magazine` é dono do conteúdo, campos, validações,
workflow e apresentação editorial. `aculta_studio` é dono da navegação,
composição das abas, listagens e das ações exibidas. O Core é a interface
canônica de criação, edição, revisão, preview e moderação; nenhum dos dois
módulos deve reimplementá-la.

## 1. Contratos do Core usados pelo fluxo editorial

Rotas verificadas em `web/core/modules/node/node.routing.yml`,
`web/core/lib/Drupal/Core/Entity/Routing/DefaultHtmlRouteProvider.php`,
`web/core/modules/node/src/Entity/NodeRouteProvider.php` e
`web/core/lib/Drupal/Core/Entity/EntityType.php`.

| Operação | Rota | Acesso declarado na rota |
| --- | --- | --- |
| Criar publicação | `node.add` (`/node/add/{node_type}`) | `_entity_create_access: node` (o `node_type` da URL define o bundle) |
| Editar publicação existente | `entity.node.edit_form` (`/node/{node}/edit`) | `_entity_access: node.update` |
| Ver publicação | `entity.node.canonical` (`/node/{node}`) | `_entity_access: node.view` |
| Ver uma revisão específica | `entity.node.revision` (`/node/{node}/revisions/{node_revision}/view`) | `_entity_access: node_revision.view revision` |
| Histórico de revisões | `entity.node.version_history` (`/node/{node}/revisions`) | `_entity_access: node.view all revisions` |
| Reverter revisão | `node.revision_revert_confirm` (`/node/{node}/revisions/{node_revision}/revert`) | `_entity_access: node_revision.revert revision` |
| Preview de rascunho | `entity.node.preview` (`/node/preview/{node_preview}/{view_mode_id}`) | `_node_preview_access` (createAccess para Node novo; `update` para Node existente); `no_cache: TRUE` |
| Excluir publicação | `entity.node.delete_form` | `_entity_access: node.delete` |

Observações verificadas:

- `NodeRouteProvider::getRoutes()` renomeia `entity.node.add_form` para
  `node.add` e `entity.node.add_page` para `node.add_page`; `entity.node.edit_form`
  mantém o nome. Qualquer link ou teste deve usar os nomes atuais (`node.add`),
  não os antigos.
- `NodePreviewAccessCheck` confirma o preview pelo mesmo critério de Entity
  Access: criar para Node novo, atualizar para Node existente. Não há permissão
  genérica de preview.
- O formulário de moderação não tem rota. `EntityModerationForm` é renderizado
  pelo extra field `content_moderation_control` (`EntityOperations::entityView()`)
  apenas quando o display define o componente, a entidade não está em preview e
  a revisão exibida é a mais recente e não é a revisão padrão publicada. As
  transições exibidas vêm de
  `StateTransitionValidationInterface::getValidTransitions()` para a conta atual
  e as autotransições são removidas. O formulário não substitui o acesso da
  entidade: só é alcançável com acesso de visualização ao Node.
- O único form mode configurado para `revista` é `default`
  (`config/sync/core.entity_form_display.node.revista.default.yml`). Não existe
  display de formulário próprio do Studio.
- `node.type.revista` tem `new_revision: true` e `preview_mode: 1`.

## 2. Contratos já oferecidos pelo Magazine

| Peça | Onde | Oferece | Consome |
| --- | --- | --- | --- |
| Workflow `revista` | `config/sync/workflows.workflow.revista.yml` | Estados `draft`, `review`, `published`, `archived`; oito transições (`create_new_draft`, `publish`, `archive`, `draft_to_review`, `archived_draft`, `review_to_draft`, `archived_published`, `review_to_published`); `default_moderation_state: draft` | `content_moderation`, `node.type.revista` |
| Permissões de transição | `config/sync/user.role.content_editor.yml` | `use revista transition <id>` para as oito transições, além de `create revista content`, `edit own/any revista content`, `view all revisions`, `view any/own unpublished content` | papel manual `content_editor` |
| Form alter | `src/Hook/RevistaFormHooks.php` | Agrupa os campos secundários do formulário nativo em `revista_secondary` (`#type: details`, fechado); anexa a library `aculta_magazine/revista-form` | `form_alter` em `node_revista_form` e `node_revista_edit_form` |
| Aviso de alterações não salvas | `js/revista-form.js` | Marca o formulário como alterado e usa `beforeunload` | Biblioteca anexada pelo form alter |
| Caixa de autores | `src/Hook/ArticleAuthorsHooks.php` + `src/Presentation/PostPresentation.php` | Autoria e coautoria na visualização | `node_view` |
| Discussão contextual | `src/Hook/PublicationDiscussionHooks.php` | Default do campo Comment em memória para Nodes anteriores ao campo | `node_load` |
| Bundle e migração | `src/Revista/RevistaPublications.php`, `src/Migration/PublicationBundleMigrator.php` | Inventário de publicações e migração histórica | Entity API |
| Campos e displays | `config/sync/field.field.node.revista.*`, `core.entity_view_display.node.revista.*` | Modelo editorial, SEO, Pathauto, Metatag, listagens | Config Sync |

O Magazine **não** expõe hoje um serviço de consulta editorial, um serviço de
ações permitidas nem um adaptador de acesso para o Studio. `RevistaPublications`
não é um contrato de listagem do Studio: ele lê o bundle e serve ao comando de
migração/inventário, não a telas.

O módulo oferece separadamente `MagazineContentIndexProvider` para o catálogo
opcional do Portal. Esse provider só descreve metadados e relações; ele não é
API de listagem do Studio e não altera a checagem de acesso/revisão do fluxo
editorial.

## 3. Contratos consumidores no Studio

| Peça | Onde | Uso verificado |
| --- | --- | --- |
| Aba Revista | `src/Plugin/StudioTab/RevistaTab.php` | `isAvailable()` exige `aculta_magazine`; `access()` exige `create revista content` **ou** `edit any revista content`; seções `publicacoes` (todos, rascunhos, em revisão, publicados, arquivados) e `criar` |
| Ação de criação | `StudioTabBase::routeAction()` | Resolve `node.add` com `node_type => revista` no purpose `magazine`, com `Url::fromRoute(...)->access($account)` |
| Listagem | `src/StudioListingBuilder.php` | `scope: mine` (uid da conta), `accessCheck(TRUE)`, paginação por cursor, `canViewRevision()` = `view` **e** `view revision`; cache contexts `user`, `user.permissions`, `url.query_args:studio_cursor`, tag `node_list:revista` |
| Apresentação | `src/StudioPresenter.php`, `src/Controller/StudioController.php` | Filtra abas/seções por acesso e por ações/listagens disponíveis; renderiza com cache contexts `user.permissions`, `url.path` |
| Rota do Studio | `aculta_studio.routing.yml` | `/studio/ganjalab/...` canônica, aliases legadas, `_permission: access aculta portal`, `_aculta_domain_purpose: account` |

## 4. Lacunas reais (0.5-B)

| ID | Lacuna | Evidência | Dono da correção |
| --- | --- | --- | --- |
| B-01 | O link de edição da listagem usa o purpose `main`, não `magazine` | `StudioListingBuilder::editLink()` chama `routeUrl('main', 'entity.node.edit_form', ...)` | `aculta_studio` (A-07), commit próprio |
| B-02 | Coautoria não concede acesso além do que o contrib concede | `node_co_authors_node_access()` só permite `view` de não publicado e `update`/`delete` com `edit/delete own revista content`, e só se a conta estiver em `co_authors`; nenhum papel recebe `edit co-authors of ...` no Config Sync | `aculta_user`/`aculta_magazine`, decisão documentada; sem mudança de regra nesta fase |
| B-03 | A listagem "Meus conteúdos" é somente por `uid`; não inclui conteúdo coassinado | `StudioListingBuilder` aplica `uid` da conta e `accessCheck(TRUE)` | `aculta_studio`, após decisão de produto; não alterar agora |
| B-04 | Não há link de preview nem de histórico de revisões na aba Revista | `RevistaTab` só declara listagem e criação; as rotas Core existem | `aculta_studio`, 0.5-B.9 mínimo |
| B-05 | O grupo de campos secundários é sempre criado fechado e não abre quando contém erro de validação; o foco não é levado ao primeiro campo inválido | `RevistaFormHooks::alter()` grava `#open => FALSE` e não inspeciona erros; o grupo só existe quando ao menos um campo de `SECONDARY` foi movido (`count($secondary) > 6`, contando as seis chaves de definição) | `aculta_magazine`, 0.5-B.3 |
| B-06 | O aviso de alterações não salvas não é reiniciado no retorno por bfcache | `revista-form.js` não trata `pageshow` | `aculta_magazine`, 0.5-B.3 |

## 5. Decisão sobre `MagazineEditorialAdapter`

Não será criado nesta fase. O inventário mostra que o Studio consome o fluxo
editorial pelos contratos do Core (rotas, Entity Access, Revision Access,
`content_moderation_control`) e pela listagem que ele mesmo constrói com
`accessCheck(TRUE)`. Um adaptador só se justifica quando existir um consumidor
real que precise, em uma única chamada, de:

1. URL autorizada de criação/edição **e** a razão de negação;
2. lista de transições permitidas para uma conta **sem** depender do formulário
   renderizado;
3. metadados de cacheability calculados fora de um `Url`/entidade.

Nenhum desses consumidores existe hoje. Criar o serviço agora seria uma camada
sem uso, contra a diretiva de evitar wrappers onde o Core basta. A decisão será
revisada em 0.5-B.9 se a integração mínima provar a necessidade; qualquer
serviço futuro pertence a `aculta_magazine` e não pode conter HTML, CSS,
armazenamento paralelo nem lógica própria de publicação.

## 6. Limites deste contrato

- O Studio não administra permissões de conteúdo: cada ação exibida deve chamar
  Form API/Entity Access/Workflows do dono.
- Listagens nunca usam `accessCheck(FALSE)`; revisões exigem `view` e
  `view revision`.
- O Magazine não cria uma segunda interface GanjaLab, não registra novos
  `StudioTab` providers e não altera estados ou transições nesta fase.
- Operações condicionadas a fases posteriores: E-B.3 (ações editoriais
  explícitas), E-C (editor), E-D (revisão/aprovação) não fazem parte de 0.5-B.
