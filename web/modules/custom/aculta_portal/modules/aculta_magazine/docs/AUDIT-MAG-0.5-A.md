# MAG 0.5-A — Auditoria arquitetural, Config Sync e contratos editoriais

**Data:** 2026-10-22 · **Branch:** `docs/aculta-forum-0.1.0-dev.1` · **HEAD inicial:** `6653ab93`
**Ambiente:** homelab de desenvolvimento (`toca`), Drupal 11.4.10, PHP 8.4.26, Drush 13.8.0.0, banco `aculta_runtime`.
**Escopo:** `aculta_magazine` e contratos consumidos pelo ACULTA Studio. Não é produção.
**Estado:** auditoria. Nenhuma configuração foi importada (`drush cim` não executado), nenhum conteúdo foi migrado ou excluído.

Este documento registra evidência para MAG 0.5-A e a base factual de MAG 0.5-B. Itens não testados em Runtime são
classificados como tal; ausência de teste não é tratada como defeito.

## 1. Ambiente e estado do repositório (A.1)

| Item | Evidência |
| --- | --- |
| Repositório | `https://github.com/coletivo420/aculta` |
| Branch autorizada | `docs/aculta-forum-0.1.0-dev.1` |
| HEAD | `6653ab93` (prova ACL de Group e revogação do fórum restrito) |
| Working tree | limpo exceto `node_modules/`, `package-lock.json`, `package.json` não rastreados, pré-existentes |
| PR | a mesma da branch; nenhuma PR nova criada, nenhum merge executado |
| Drupal | 11.4.10, bootstrap Successful, site URI `http://default` |
| Banco | MySQL, `aculta_runtime` (homelab, não produção) |
| Conteúdo | 12 Nodes `revista` (NID 90, 92, 97, 101, 102, 104, 106, 109, 111, 113, 115, 123), todos publicados, `co_authors` vazio |

Alterações de outras frentes no working tree não foram tocadas. O drift de configuração de outros módulos (ver §4)
não foi importado nem corrigido.

## 2. Inventário do bundle `revista` (A.3)

### 2.1 Entidade, campos e displays

| Elemento | Estado | Evidência |
| --- | --- | --- |
| Bundle `revista` único | Implementado e homologado | `src/Revista/RevistaPublications.php` (`CANONICAL_BUNDLE`, `BUNDLES`); `config/sync/node.type.revista.yml`; migração 0.3.0 |
| `new_revision: TRUE`, `preview_mode: 1`, `display_submitted: FALSE` | Implementado | `node.type.revista.yml` |
| Campos editoriais: `field_subtitle`, `field_summary`, `field_credits`, `field_references`, `field_related_activity`, `field_editorial_author`, `field_editorial_category`, `field_media_image`, `field_tags`, `field_published_at`, `field_meta_tags`, `field_domain_source`, `field_aculta_discussion` | Implementado e homologado | definições de campo ativas e em `config/sync`; form display default |
| `body` com formato `revista_editorial` | Implementado | form display default; permissão `use text format revista_editorial` em `content_editor` |
| Display `default` | Implementado | `core.entity_view_display.node.revista.default.yml` |
| Display `full` | Implementado; o tema usa `aculta420:revista-publication` | `node--revista--full.html.twig` consome `post_meta` e `article_authors` |
| Display `teaser` | Implementado | conteúdo restrito a `body` + `links` |
| `co_authors` no formulário (peso 17) | **Parcial / inoperante para o papel atual** | ver §3, achado A-03 |
| `field_coautores` oculto no form display | **Legado** | campo inexistente no Runtime e sem config; entrada `hidden` remanescente |
| Campos virtuais Entity Print ocultos | Implementado | displays default/full/teaser; CHANGELOG 0.4.0 |
| `field_aculta_discussion` oculto no form, visível em `full` | Implementado | display + `PublicationDiscussionHooks` |

### 2.2 Autoria e coautoria

| Elemento | Estado | Evidência |
| --- | --- | --- |
| Autor principal exibido como `author` e coautores como `coauthor` | Implementado | `src/Hook/ArticleAuthorsHooks.php`, variante `author_editorial` de `aculta_user.author_card_builder` |
| Coautoria por `node_co_authors` (`co_authors`) | Implementado (contrib) | `node_co_authors_node_access()` exige também `edit own revista content`; `node_co_authors_entity_field_access()` proíbe edição do campo sem uma das quatro permissões `edit co-authors …` |
| Edição do campo `co_authors` por `content_editor` | **Bloqueada** | `content_editor` não possui nenhuma permissão `edit co-authors …` (confirmado no Runtime) |
| Regra editorial de quem pode editar coautoria | **Dependente de decisão com `aculta_user`** | a política é compartilhada; não alterada nesta fase |

### 2.3 Publicação, agendamento, SEO e URLs

| Elemento | Estado | Evidência |
| --- | --- | --- |
| Workflow `revista` (Content Moderation) | Implementado e homologado | `config/sync/workflows.workflow.revista.yml`; 4 estados, 8 transições |
| Estados | `draft` (Rascunho, −5, não publicado), `review` (Em revisão, 1, não publicado), `published` (Publicado, 0, publicado, revisão padrão), `archived` (Arquivado, 5, não publicado, revisão padrão) | idem |
| Transições | `create_new_draft`, `draft_to_review`, `review_to_draft`, `publish`, `review_to_published`, `archive`, `archived_draft`, `archived_published` | idem; **não existe** estado/transição “rejeitado”; não existe `review → archived` |
| Permissões de transição | Somente `content_editor` | `user.role.content_editor.yml`; `authenticated` não possui nenhuma |
| Agendamento | `field_published_at` (datetime) exposto no formulário | não há scheduler; publicação é transição manual |
| Metatag/OG/Schema | Implementado; depende de tokens de `aculta_portal` (`[node:editorial-summary]`, `[node:author-name]`, `[node:author-type]`, `[node:session]`) | `metatag.metatag_defaults.node__revista.yml`; `TokenHooks.php` |
| Pathauto | Implementado; padrão `/[node:session]/[node:title]` | `pathauto.pattern.aculta_article.yml` — id/label legados, seleção correta (`revista`) |
| Simple Sitemap | Implementado | `simple_sitemap.sitemap.magazine.yml` (tipo `aculta_purpose`, purpose `magazine`) |
| Busca | `revista` indexado | `search_api.index.aculta_conteudo.yml` |
| Alias `/topico/` legado | Implementado em update hook do Magazine | `aculta_magazine.install:10003` — comportamento de Fórum em hook do Magazine (achado A-07) |
| Traduções | Não há configuração específica de tradução no bundle; `langcode` presente no form display | dependente de `language`/content translation global |
| Revisões e status de publicação | `new_revision: TRUE`; `status` no form; moderação controla publicação | form display + workflow |

### 2.4 Código e serviços

| Elemento | Estado | Observação |
| --- | --- | --- |
| `RevistaPublications` | Implementado, testado (Unit) | `isPublication()` estrito; sem referências a bundles aposentados |
| `PostPresentation` | Implementado | header (autor/data/hora) e compartilhamento; cache tags + `theme`, `url.path`, `url.site` |
| `ArticleAuthorsHooks` | Implementado | `node_view`, purpose `magazine`, somente `full` publicado |
| `PublicationDiscussionHooks` | Implementado, testado (Unit) | aplica default de discussão em memória quando `status` ausente |
| `RevistaFormHooks` | Implementado, **sem teste** | agrupa propriedades secundárias em `details` fechado; não abre o grupo quando há erro |
| `js/revista-form.js` | Implementado, **sem teste** | aviso `beforeunload`; desliga ao submeter |
| `RevistaPublications`/migrador | Implementado, testado (Kernel) | `PublicationBundleMigratorKernelTest` |
| Views administrativas | Implementado, testado (Unit estático) | `/painel-administrativo/conteudo/revista` (rota `admin/content/revista`), acesso `access content overview`; bloco de comentários com `administer comments` |
| `aculta_magazine.install` 10001/10002 | **Obsoleto, inofensivo** | no-ops documentados sobre campos de Fórum aposentados |
| `aculta_magazine.install` 10003 | **Dependente de Fórum** | cria padrão Pathauto e reescreve aliases `/topico/` de Nodes `forum` |
| DI | Correto | dois serviços, dependências explícitas; nenhum `\Drupal::` em `src/` |
| `CacheableMetadata` | Correto nos hooks que compõem render arrays | `PostPresentation` e `ArticleAuthorsHooks` declaram tags/contextos |

### 2.5 Integração com tema e Studio

| Elemento | Estado | Observação |
| --- | --- | --- |
| SDC `aculta420:revista-publication` | Implementado (experimental) | recebe `meta`, `cover`, `subtitle`, `summary`, `body`, `footer`, `authors`, `discussion` |
| `RevistaTab` (`aculta_studio`) | Implementado, não homologado | área “Revista”, seção “Meu trabalho”, filtros por estado, ação “Nova publicação” no purpose `magazine` |
| Link “Editar” da listagem | **Inconsistência de purpose** | `StudioListingBuilder::editLink()` resolve no purpose `main`; a criação usa `magazine`; a rota `entity.node.edit_form` não tem purpose obrigatório |
| Consulta auxiliar de Group no Studio | `accessCheck(FALSE)` em `group_relationship` | resolve IDs de turmas para um curso; não alimenta listagem de conteúdo; documentado, não alterado |
| Templates `node--article--full.html.twig` / `node--blog--full.html.twig` | **Obsoleto** | bundles aposentados; pertencem ao tema, não ao Magazine |
| Header multidomínio | Mantido | nenhuma duplicação introduzida |

### 2.6 Segurança editorial (A.3)

| Verificação | Resultado | Evidência |
| --- | --- | --- |
| Quem pode criar | `content_editor` (`create revista content`) | `user.role.content_editor.yml`; 15 contas no Runtime |
| Quem pode editar conteúdo de terceiros | `content_editor` (`edit any revista content`) | idem |
| Quem pode editar o próprio | `content_editor` (`edit own revista content`) | idem |
| Coautor pode editar o Node | Sim, se também tiver `edit own revista content` | `node_co_authors_node_access()` |
| Coautor pode ver rascunho de terceiro | Sim, se tiver `view own unpublished content` | idem |
| Coautor pode editar lista de coautores | Não | `node_co_authors_entity_field_access()` |
| Acesso anônimo a publicação publicada | Sim | smoke HTTP |
| Acesso anônimo a rascunho/revisão não padrão | Negado | Node/Revision access; verificado em Kernel (0.5-B) |
| Acesso a revisões | `content_editor`: `view revista revisions`, `view all revisions`, `view latest version`, `revert all revisions` | `user.role.content_editor.yml` |
| Transições | somente `content_editor`, por transição explícita | idem; sem permissão genérica `administer content moderation` |
| Listagens Studio | `accessCheck(TRUE)` + `view` + `view revision` por item | `StudioListingBuilder`; sem `accessCheck(FALSE)` em conteúdo |
| Cache por usuário | contextos `user`, `user.permissions`, `url.query_args:studio_cursor` | idem |
| Pré-visualização | rota Core de preview; exige acesso de edição | não alterada nesta fase |
| URLs absolutas/cross-domain | `DomainPurposeManager::routeUrl()`; hostname por ambiente | `domain.record.revista420_aculta_org.yml` difere entre ambientes por desenho |
| CSRF/authz | Form API/rotas Core | nenhum formulário próprio criado |

**Conclusão de segurança:** não foi encontrada permissão genérica substituindo Entity/Revision Access, nem consulta
de conteúdo privado com `accessCheck(FALSE)` em listagens do Studio. O ponto aberto é a **edição do campo
`co_authors`**, bloqueada para todos os `content_editor` — não é falha de segurança (é negação), mas impede a
coautoria pela interface.

## 3. Achados da auditoria (A.6)

| ID | Achado | Classificação | Ação nesta fase |
| --- | --- | --- | --- |
| A-01 | Form display `revista` não declara `workflows.workflow.revista` em `dependencies.config` (o Runtime declara) | Divergência Config Sync | Corrigido em `config/sync` (0.5-A.3), sem import |
| A-02 | Displays de visualização `default`, `full`, `teaser` não declaram o componente `content_moderation_control` (o Runtime declara) | Divergência Config Sync | Corrigido em `config/sync` (0.5-A.3), sem import |
| A-03 | Edição do campo `co_authors` negada a todo `content_editor`; o widget permanece no formulário | Parcial, regra editorial compartilhada | Documentado; testes em 0.5-B; **sem alterar permissões** |
| A-04 | `field_coautores` consta em `hidden` do form display sem definição de campo | Legado inofensivo | Documentado; não removido para não criar drift adicional |
| A-05 | `pathauto.pattern.aculta_article` mantém id/label legados para `revista` | Legado cosmético | Documentado; renomear exigiria reimportação/limpeza de config |
| A-06 | `aculta_magazine.install:10003` executa comportamento de Fórum | Ownership cruzado | Documentado; **não removido** (recuperação/atualização não confirmadas) |
| A-07 | `StudioListingBuilder::editLink()` usa purpose `main` para editar Revista, enquanto a criação usa `magazine` | Inconsistência de URL canônica | Corrigido em 0.5-B.3 (consumidor Studio, commit próprio) |
| A-08 | `RevistaFormHooks` não abre o grupo “Propriedades secundárias” quando há erro em campo agrupado | Integração Form API | Corrigido em 0.5-B.3 |
| A-09 | Aviso de alterações não salvas pode ficar permanentemente desligado após restauração por bfcache | Integração JS | Corrigido em 0.5-B.3 |
| A-10 | Templates de tema para `article`/`blog` permanecem | Obsoleto (tema) | Documentado; pertence ao ACULTA420 |
| A-11 | `node.type.revista`, `workflows.workflow.revista` e os demais configs `revista` estão idênticos entre Sync e Runtime | Sem divergência | Preservado |
| A-12 | Aba “Revista” do feed de atividades de `aculta_user` consulta `type = article` com `accessCheck(FALSE)`; o bundle não existe mais, então a aba fica sempre vazia | **Bug de consumidor legado** | Não corrigido: ownership é de `aculta_user`; registrado em `DEPENDENCY-MATRIX.md` |

## 4. Relatório de Config Sync (A.4)

Comparação entre `config/sync`, configuração ativa e código consumidor, feita por leitura recursiva de arrays no
Runtime. **Nenhum `drush cim` foi executado.** `drush config:status` acusa 140 configurações divergentes no site,
majoritariamente de outras frentes; nenhuma delas é `revista` além das listadas.

| Configuração | Diferença | Causa provável | Risco | Dono | Correção sugerida |
| --- | --- | --- | --- | --- | --- |
| `core.entity_form_display.node.revista.default` | `dependencies.config` contém `workflows.workflow.revista` apenas no ativo | exportação anterior à associação do workflow ao bundle | baixo (dependência ausente no pacote) | Magazine | adicionar em `config/sync` |
| `core.entity_view_display.node.revista.default` | componente `content_moderation_control` (peso −20) apenas no ativo | o extra field do Content Moderation foi instalado no bundle depois da última exportação | baixo/médio (controle de moderação some se o config for importado) | Magazine | adicionar em `config/sync` |
| `core.entity_view_display.node.revista.full` | idem | idem | idem | Magazine | idem |
| `core.entity_view_display.node.revista.teaser` | idem | idem | idem | Magazine | idem |
| `domain.record.revista420_aculta_org` | `hostname`: Sync `revista420.aculta.org`, ativo `revista420.aculta.toca.net.br` | hostname por ambiente | nenhum (esperado) | Portal | **não alterar**; documentado como dependente de ambiente |
| `node.type.revista` | nenhuma | — | — | Magazine | preservado |
| `workflows.workflow.revista` | nenhuma | — | — | Magazine | preservado |
| `user.role.content_editor` | nenhuma diferença relevante ao Magazine | — | — | User | preservado |
| `views.view.aculta_magazine_admin_*` | iguais ao `config/install` (teste Unit) | — | — | Magazine | preservado |
| `metatag.metatag_defaults.node__revista` | nenhuma | — | — | Magazine | preservado |
| `pathauto.pattern.aculta_article` | nenhuma | — | — | Magazine | preservado |
| `simple_sitemap.sitemap.magazine` | nenhuma | — | — | Portal/Magazine | preservado |
| `search_api.index.aculta_conteudo` | divergente em outras frentes; bundle `revista` correto | concorrente | não avaliado aqui | Busca | nenhuma ação |

As duas primeiras linhas foram corrigidas **no repositório** (0.5-A.3). Como a importação global está bloqueada, a
convergência do Runtime ocorrerá na janela de importação da frente responsável.

## 5. Dependências externas (resumo; detalhe em `DEPENDENCY-MATRIX.md`)

Studio, User, Forum, Image, Gallery, Document/PDF Viewer, Messenger, Statistics, LMS e Portal possuem contratos
reais consumidos pelo Magazine. O Magazine **não** depende de `aculta_messenger`, `aculta_statistics`, LMS ou
Document/PDF Viewer para publicar; essas integrações são de apresentação ou de outros fluxos. `entity_print` é
opcional e não é dependência declarada do módulo.

## 6. Itens diferidos

- Concessão de `edit co-authors of own/co-authored content`: decisão de política editorial com `aculta_user`.
- Renomeação do pattern Pathauto legado: exige reimportação e limpeza de aliases; não é necessária para 0.5-B.
- Remoção dos update hooks 10001/10002 e do comportamento de Fórum em 10003: exige confirmação de que nenhum
  ambiente ainda precisa executá-los.
- Templates de tema para bundles aposentados: frente ACULTA420.
- Homologação de paginação por cursor do Studio: E-B.2-T, fora do escopo de 0.5-B.

## 7. Veredito da fase 0.5-A

O Magazine está coerente com o bundle único `revista`, com workflow, permissões e SEO implementados. As
divergências de Config Sync são pequenas, isoladas e agora refletidas no repositório sem importação. Não há
duplicação de storage, permissão genérica atuando como access handler, nem listagem privada sem Entity Access.
Os riscos remanescentes são de integração (coautoria na interface, purpose do link de edição do Studio, grupo de
erros do formulário) e estão endereçados em 0.5-B.
