# Contrato de integração Studio ↔ Magazine

**Status:** contrato atualizado para a central administrativa e ACULTA Studio
Marley (MAG 0.5.0-dev.14, 2026-10-30). As seções históricas abaixo registram o
contrato Core anterior à fronteira ADR-038; as rotas atuais `/marley/revista/*`
delegam às mesmas APIs de entidade, Form API, Content Moderation, Comment e
`aculta_forum` no purpose `account`.

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
| Bundle e histórico | `src/Revista/RevistaPublications.php`, `docs/MIGRATION-ARTICLE-BLOG-TO-REVISTA.md` | Bundle canônico e registro histórico; o migrador foi removido | Entity API |
| Campos e displays | `config/sync/field.field.node.revista.*`, `core.entity_view_display.node.revista.*` | Modelo editorial, SEO, Pathauto, Metatag, listagens | Config Sync |

O Magazine **não** expõe um storage editorial paralelo. A implementação dev.14
adiciona adaptadores de apresentação/Forms no owner, que não substituem Entity
Access, Revision Access, Field Access nem o workflow. `RevistaPublications`
não é um contrato de listagem do Studio: ele lê o bundle e serve ao comando de
migração/inventário, não a telas.

O módulo oferece separadamente `MagazineContentIndexProvider` para o catálogo
opcional do Portal. Esse provider só descreve metadados e relações; ele não é
API de listagem do Studio e não altera a checagem de acesso/revisão do fluxo
editorial.

## 3. Rotas atuais no ACULTA Studio Marley (dev.14)

Marley é `account:/marley` conforme ADR-038. As rotas de escrita não chamam
POST, AJAX de persistência nem formulário em outro purpose:

| Ação | Rota | Owner e controles |
| --- | --- | --- |
| Lista e filtros | `/marley/revista/publicacoes` | `aculta_magazine`; query `accessCheck(TRUE)`, revisão visível e paginação limitada |
| Criar | `/marley/revista/nova` | Entity Form Builder de Node; capability Autoria + `create revista content` |
| Editar | `/marley/revista/{node}/editar` | Entity Form Builder; capability Autoria + `update` na publicação/revisão |
| Revisar/publicar/arquivar | `/marley/revista/{node}/transicao` | `StateTransitionValidationInterface`, capacidade Revisão + `update` + `view revision`; revalida transição no submit |
| Discussão e participação | `/marley/revista/{node}/discussao` | Renderiza o campo Comment do Node; `DiscussionContextResolver`/`PublicationContextProvider` avaliam host, campo, leitura e escrita |
| Moderação contextual | `/marley/revista/comentarios/{comment}/moderacao` | capacidade Comentários + `administer comments` + contexto Magazine e access do Comment/campo; aprovar, despublicar ou remover |

As rotas administrativas são `/painel-administrativo/revista`,
`/painel-administrativo/revista/configuracoes`,
`/painel-administrativo/revista/publicacoes` e
`/painel-administrativo/revista/comentarios`, todas no purpose `main` e restritas
a administradores. A View de publicações não usa mais o path Core
`/admin/content/revista` nem a aba genérica `system.admin_content`.

`aculta_forum` continua owner da resolução de contexto, integração e políticas
compartilhadas. A discussão usa Comment `aculta_discussion_post` associado a
`field_aculta_discussion`; não cria `ForumPost`, tópico público, nem fila
Growroom. `ForumModerationHooks` mantém comentários contextuais fora da fila
genérica Core. A fila administrativa da Revista é configurada na própria
central; a fila contextual do Studio filtra host `revista` e valida o provider.

## 4. Contratos consumidores no Studio

| Peça | Onde | Uso verificado |
| --- | --- | --- |
| Aba Revista | `aculta_studio/src/Plugin/MarleyTab/RevistaTab.php` | exige uma capacidade configurada de autoria, revisão ou comentários; separa publicações, criação e moderação contextual |
| Painel de publicações | `aculta_magazine/src/Controller/EditorialWorkspaceController.php` | `accessCheck(TRUE)`, verificação `view revision`, filtros de título/categoria/autoria/idioma/estado e paginação limitada |
| Criação e edição | rotas `aculta_magazine.studio_add` e `aculta_magazine.studio_edit` | Node Entity Form API e capacidade de autoria junto a permissões/Entity Access Drupal; no purpose `account` |
| Revisão e publicação | rota `aculta_magazine.studio_transition` | `StateTransitionValidationInterface`; opções vêm das transições válidas para a revisão e usuário atuais, revalidadas no submit |
| Discussão contextual | rota `aculta_magazine.studio_discussion` | Renderiza `field_aculta_discussion` do Node `revista`; `aculta_forum` resolve o contexto e valida publicação, campo, participação e moderação |
| Fila contextual | `aculta_studio/src/MarleyListingBuilder.php` | Query Comment access-checked por tipo/campo/host; apenas contexto `publication`; links e ações permanecem em Marley; Growroom segue separado |

## 4. Lacunas reais (0.5-B)

| ID | Lacuna | Evidência | Dono da correção | Situação em 0.5-B |
| --- | --- | --- | --- | --- |
| B-01 | O link de edição da listagem usava o purpose `main`, enquanto a criação e a própria listagem usavam `magazine` | `StudioListingBuilder::editLink()` chama `routeUrl('main', 'entity.node.edit_form', ...)`; `RevistaTab` passava `purpose: magazine` à listagem e `magazine` à criação | `aculta_studio` (A-07), commit próprio | Resolvida em `7500090c` (0.5-B.9): listagem e criação passam a usar `main`, conforme `ROUTES-DOMAIN-CONTRACT.md` §5 |
| B-02 | Coautoria não concede acesso além do que o contrib concede | `node_co_authors_node_access()` só permite `view` de não publicado e `update`/`delete` com `edit/delete own revista content`, e só se a conta estiver em `co_authors`; nenhum papel recebe `edit co-authors of ...` no Config Sync | `aculta_user`/`aculta_magazine`, decisão documentada; sem mudança de regra nesta fase | Adiada: nenhuma permissão de coautoria foi adicionada; a regra do contrib permanece a fonte de verdade |
| B-03 | A listagem "Meus conteúdos" é somente por `uid`; não inclui conteúdo coassinado | `StudioListingBuilder` aplica `uid` da conta e `accessCheck(TRUE)` | `aculta_studio`, após decisão de produto; não alterar agora | Adiada: listagem continua por `uid`; mudança exige decisão de produto |
| B-04 | Não há link de preview nem de histórico de revisões na aba Revista | `RevistaTab` só declara listagem e criação; as rotas Core existem | `aculta_studio` | Adiada: o histórico é alcançável pelo formulário nativo; o preview depende do fluxo do formulário (`POST`/token) e não ganhou link próprio |
| B-05 | O grupo de campos secundários é sempre criado fechado e não abre quando contém erro de validação; o foco não é levado ao primeiro campo inválido | `RevistaFormHooks::alter()` grava `#open => FALSE` e não inspeciona erros; o grupo só existe quando ao menos um campo de `SECONDARY` foi movido (`count($secondary) > 6`, contando as seis chaves de definição) | `aculta_magazine`, 0.5-B.3 | Resolvida em `d9c3a81f` (0.5-B.3): o grupo é criado aberto quando há erro em campo agrupado e o foco segue o primeiro erro |
| B-06 | O aviso de alterações não salvas não é reiniciado no retorno por bfcache | `revista-form.js` não trata `pageshow` | `aculta_magazine`, 0.5-B.3 | Resolvida em `d9c3a81f` (0.5-B.3): `pageshow` restaura o estado do aviso |

### 4.1. Operações consumíveis pelo GanjaLab ao fim de 0.5-B

O Studio consome os contratos abaixo sem wrapper próprio. Cada operação vale
para a conta atual e é decidida por Entity Access/Revision Access do Core.

| Operação | Contrato | Situação |
| --- | --- | --- |
| Criar publicação | `node.add` com `node_type: revista` no purpose `main` | Consumível; `createAccess` decide |
| Abrir o formulário nativo de edição | `entity.node.edit_form` no purpose `main`, exibido só com `$node->access('update')` | Consumível |
| Ver a publicação | `entity.node.canonical` | Consumível |
| Ver uma revisão específica | `entity.node.revision` (`view revision`) | Consumível |
| Seguir o histórico de revisões | `entity.node.version_history` (`view all revisions`) | Consumível |
| Descobrir e executar transições | `content_moderation_control` no display + `StateTransitionValidation::getValidTransitions()` | Consumível pelo formulário nativo; o Studio não reimplementa a lista |
| Preview autorizado | `entity.node.preview` (`createAccess`/`update`, `no_cache`) | Alcançável pelo fluxo do formulário; sem link próprio no Studio (B-04) |
| Listar publicações do autor por estado | `StudioListingBuilder` com `accessCheck(TRUE)`, `uid` e checagem de revisão | Consumível, somente por `uid` (B-03) |

Condicionadas a fases posteriores, fora de 0.5-B:

- **E-B.3** — ações editoriais explícitas no Studio, caso apareça um consumidor
  que justifique o adaptador de §5.
- **E-C** — editor dedicado.
- **E-D** — revisão/aprovação com fluxo próprio.

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
sem uso, contra a diretiva de evitar wrappers onde o Core basta. A decisão foi
revisada na integração mínima de 0.5-B.9: o Studio consome criação, edição,
listagem e moderação pelos contratos do Core e não pediu nenhuma chamada
agregada. O adaptador continua sem consumidor real. Qualquer serviço futuro
pertence a `aculta_magazine` e não pode conter HTML, CSS, armazenamento
paralelo nem lógica própria de publicação.

## 6. Limites deste contrato

- O Studio não administra permissões de conteúdo: cada ação exibida deve chamar
  Form API/Entity Access/Workflows do dono.
- Listagens nunca usam `accessCheck(FALSE)`; revisões exigem `view` e
  `view revision`.
- O Magazine não cria uma segunda interface GanjaLab, não registra novos
  `StudioTab` providers e não altera estados ou transições nesta fase.
- Operações condicionadas a fases posteriores: E-B.3 (ações editoriais
  explícitas), E-C (editor), E-D (revisão/aprovação) não fazem parte de 0.5-B.
- A integração mínima de 0.5-B.9 verificou o consumidor real no Studio
  (`RevistaTab`, `StudioTabBase`, `StudioListingBuilder`) e não alterou
  permissões, estados, transições nem configuração. A única correção de
  consumidor foi o purpose das operações (`7500090c`), em commit separado do
  Magazine.
