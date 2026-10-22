# ACULTA Magazine — matriz de dependências

**MAG 0.5-A.2 · 2026-10-22**. Somente APIs e comportamentos **existentes** no código e na configuração atuais.
Nenhuma integração fictícia é listada; onde não existe API, a coluna diz “nenhuma”.

Legenda de estado: **Implementado** (código e consumo reais), **Parcial** (funciona com limitação conhecida),
**Ausente** (não existe), **Dependente** (depende de decisão/entrega de outro dono).

## 1. Consumidores e fornecedores

| Módulo | API/serviço real | Funcionalidade implementada | Consumo pelo Magazine | Testes | Integração ausente | Bloqueador | Dono | Próxima fase |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **Studio** (`aculta_studio`) | Plugin `#[StudioTab]` + `StudioTabInterface`/`StudioTabBase`; `StudioListingBuilder`; `StudioPresenter`; rotas `/studio/ganjalab[/{aba}[/{secao}[/{sub}]]]` | Área Revista com “Meu trabalho” (todos/rascunhos/em revisão/publicados/arquivados), listagem por autor com `accessCheck(TRUE)`, checagem por revisão, cursor `studio_cursor`, ação “Nova publicação” | Magazine é **fornecedor**: `RevistaTab` publica seções e estados; o builder lê nós do dono | Unit `RevistaMyWorkContractTest`, `StudioPresenterTest` (estáticos); Kernel/Functional ausentes | Link “Editar” e criação resolvem purpose `main` (corrigido em `7500090c`); consumo verificado em 0.5-B.9 | Não bloqueia publicação; Kernel/Functional do Studio seguem em E-B.2-T | Studio | E-B.2-T (Kernel/Functional/perf) |
| **User** (`aculta_user`) | `aculta_user.author_card_builder` (variante `author_editorial`); permissões Core de autoria; `node_co_authors` | Cartão de autor/coautor; feed de atividades | `ArticleAuthorsHooks` e `PostPresentation` compõem autoria no purpose `magazine` | Indireto em Unit do Magazine; sem Kernel de coautoria | Edição do campo `co_authors` negada a todo `content_editor` (A-03); feed consulta bundle `article` inexistente (A-12) | Coautoria pela interface exige decisão de política com `aculta_user` | User | 0.5-B.2 (matriz), depois política de coautoria |
| **Forum** (`aculta_forum`) | `PublicationContextProvider` (`HOST_BUNDLES = ['revista']`); Comments com `field_aculta_discussion`; `aculta_forum_topic` | Discussão contextual por publicação e moderação de comentários | `PublicationDiscussionHooks` aplica default; bloco administrativo de comentários | Unit `PublicationDiscussionHooksTest` (Magazine), `PublicationContextProviderTest` (Forum) | Nenhuma conhecida no fluxo editorial | Não | Forum | Nenhuma nesta fase |
| **Image** (`aculta_image`) | Filtro `aculta_imagem_modelo`; tipo de mídia imagem | Formatação de imagem no corpo e na capa | `revista_editorial` declara o filtro; `field_media_image` referencia mídia | Kernel `AcultaEditorFormatsKernelTest` valida o formato | Nenhuma | Não | Image/Editor | Nenhuma |
| **Gallery** (`aculta_gallery`) | CKEditor 5 + `media_gallery` + `entity_embed`; plugin de galeria | Galerias incorporadas ao corpo editorial | `aculta_magazine.info.yml` declara dependência dura; não há consumo em PHP | Nenhum teste específico do Magazine | O módulo se descreve como **opcional** para o Portal, mas é dependência obrigatória do Magazine | Impede desinstalar Gallery sem desinstalar Magazine | Gallery (Portal) | Reavaliar dependência em fase do Portal; não alterado aqui |
| **Document/PDF Viewer** | Nenhuma API consumida | — | Nenhum | — | — | Não | Portal (Entity Print) | Nenhuma |
| **Messenger** (`aculta_messenger`) | Nenhuma API consumida pelo Magazine | — | Nenhum | — | — | Não | Messenger | Nenhuma |
| **Statistics** (`aculta_statistics`) | Nenhuma API consumida pelo Magazine | Contagem de downloads de arquivos | Nenhum (o nome `revista.pdf` em teste é só fixture) | — | — | Não | Statistics | Nenhuma |
| **LMS Resources** (`aculta_lms_resources`) | `CourseResourceBuilder::NODE_PURPOSES` (`revista => magazine`, `wiki_entry => wiki`) | Links de recursos editoriais em cursos | Magazine é fornecedor passivo; o builder resolve URL no purpose `magazine` | Kernel `CourseResourceBuilderKernelTest` | Nenhuma | Não | LMS Resources | Nenhuma |
| **Portal/Domain** (`aculta_portal`) | `DomainPurposeManager` (purpose `magazine` → `revista420_aculta_org`); `routeUrl()`, `runInPurpose()`; `DomainRouteSubscriber`; `TokenHooks` | URL canônica por domínio; tokens `editorial-summary`, `author-type`, `author-name`, `session` | Metatag, Pathauto, `PostPresentation`, Views de sessões | Kernel `DomainPurposeManagerKernelTest`; smoke HTTP | Hostname difere por ambiente (esperado) | Não | Portal | 0.5-B.7 valida URLs de criação/edição (contrato de rotas) |
| **ACULTA420** (`aculta420`) | SDC `aculta420:revista-publication` (experimental) e componentes `card`, `content-grid`, `context-header` | Render da publicação e navegação | `node--revista--full.html.twig` | Nenhum teste automatizado de tema | Gate visual 320–1440, dark/light, Olivero pendente | Não para publicação | ACULTA420 | E-D/aceite visual |
| **node_co_authors** (contrib) | `node_co_authors_node_access()`, `node_co_authors_entity_field_access()` | Acesso de coautor a nó/revisão e campo | Campo `co_authors` no form display | Coberto por Kernel em 0.5-B | Período de edição do campo bloqueado por permissão ausente | Coautoria na UI | contrib + User | 0.5-B.2 |
| **entity_print** (contrib) | Extra fields virtuais | Impressão/PDF | Campos ocultos nos displays | Indireto | Dependência opcional não declarada | Não | Portal | Nenhuma |

## 2. Dependências declaradas pelo módulo

`aculta_magazine.info.yml` declara: `aculta_portal:aculta_image`, `aculta_portal:aculta_user`,
`aculta_portal:aculta_forum`, `aculta_portal:aculta_gallery`, `node`, `metatag`, `pathauto`, `views`,
`node_co_authors`.

Observações:

- `aculta_gallery` é dependência dura apesar de o próprio módulo se declarar opcional no Portal (ver §1).
- `entity_print` e `ckeditor5` não estão declarados; o formato `revista_editorial` e os campos virtuais dependem
  deles em runtime.
- O campo `field_related_activity` referencia o bundle `activity`, que **não é fornecido por `config/install` de
  nenhum módulo custom** — é configuração de site. Se o bundle for removido, o campo fica inválido.

## 3. Contratos que o Magazine oferece

| Consumidor | Contrato | Estabilidade |
| --- | --- | --- |
| Studio | bundle `revista`, estados `draft`/`review`/`published`/`archived`, transições da tabela de `ARCHITECTURE.md` §3 | estável no workflow atual |
| Studio | `create revista content`, `edit own revista content`, `edit any revista content` | permissões Core, estáveis |
| Forum | `field_aculta_discussion` + bundle `revista` no `PublicationContextProvider` | estável |
| LMS Resources | purpose `magazine` da URL canônica | estável |
| Portal | tokens de nó `editorial-summary`, `author-type`, `author-name`, `session` | estável |
| Tema | SDC `aculta420:revista-publication` com slots `meta`/`authors`/`discussion` | experimental no tema |

## 4. Bloqueadores e diferimentos

1. **Coautoria pela interface** — bloqueada pela ausência de permissões `edit co-authors …` em `content_editor`.
   Depende de decisão com `aculta_user`; não é falha de segurança.
2. **Feed de atividades do User (A-12)** — consulta bundle `article` com `accessCheck(FALSE)`; aba vazia. Correção
   é do dono `aculta_user`.
3. **Purpose do link de edição** — Studio usa `main` na listagem e na criação.
   Corrigido no consumidor em 0.5-B.9 (`7500090c`), conforme o contrato de
   rotas e domínios.
4. **Homologação Runtime do Studio** — E-B.2-T (Kernel/Functional/perf) permanece fora de 0.5-B.
5. **Dependência de Gallery** — reavaliação pertence ao Portal; o Magazine não altera `.info.yml` nesta fase sem
   consumidor demonstrado.
