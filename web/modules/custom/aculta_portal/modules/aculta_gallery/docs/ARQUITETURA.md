# ACULTA Gallery — arquitetura e contratos de integração

Status: **normativo para a linha 0.1.x**. Dono: responsável pelo Portal. Revisão: a cada fase da linha 0.1.x e antes do RC.

**Ciclo vigente:** [Dev → RC → deploy/validação Alpha → produção](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md). O estado registrado no índice raiz é a fonte vigente. `preRC` foi aposentado; RC exige todas as pendências, testes, gates e revisão visual pré-deploy concluídos. A classificação `rc.1` e a versão `preRC.4` abaixo são registros históricos feitos sob o fluxo anterior e não qualificam o estado atual.

Este documento registra **por que** o submódulo é feito de certo modo. Ele não contém status de PR nem resultado de teste; esses dados ficam no `CHANGELOG.md` do módulo e, quando solicitado, na descrição da PR.

Escopo: álbuns fotográficos independentes, galerias menores incorporadas em artigos e blogs, visualização ampliada, catálogo e desempenho com galerias grandes, e integração multidomínio.

## 1. Fontes consultadas

Versões e compatibilidade foram conferidas no update feed do drupal.org (`updates.drupal.org/release-history/<projeto>/current`), no repositório de pacotes Drupal (`packages.drupal.org`) e no código dos tarballs oficiais, lidos em área de trabalho temporária (sem instalação no projeto).

| Módulo | Versão verificada | Compatibilidade Core | Dependências declaradas |
| --- | --- | --- | --- |
| `drupal/media_gallery` | 3.3.0 (publicado, 2026-08-19; release "Bug fixes") | `^10 \|\| ^11 \|\| ^12` | `drupal/photoswipe ^5.0` (info: `photoswipe:photoswipe (>= 5)`), `media_library`, `text` |
| `drupal/photoswipe` | 5.0.11 (publicado) | `^9.3 \|\| ^10 \|\| ^11` | biblioteca JS PhotoSwipe 5 (não é pacote PHP; ver D11) |
| `drupal/entity_embed` | 1.7 | `^10.3 \|\| ^11` | `drupal/embed ^1.10` |
| `drupal/media_library_bulk_upload` | 1.0.6 | `^10.3 \|\| ^11` | somente Core |

Core instalado: `drupal/core` 11.4.8 (`composer.lock`). Runtime de desenvolvimento: `aculta_runtime`, Drush conectado.

Pesquisa de melhores práticas: os resultados de busca na web não confirmaram todos os recursos anunciados nas páginas dos projetos. Por isso, cada capacidade listada abaixo foi confirmada no código do tarball (ou marcada como "não confirmado"). Fontes primárias consultadas:

- https://www.drupal.org/project/media_gallery (página do projeto; releases em https://www.drupal.org/project/media_gallery/releases)
- https://www.drupal.org/project/photoswipe (instalação via `bower-asset/photoswipe:^5`)
- https://www.drupal.org/project/entity_embed
- https://www.drupal.org/project/media_library_bulk_upload
- Core instalado: `web/core/modules/media_library/src/Form/FileUploadForm.php`, `web/core/modules/media_library/src/Plugin/Field/FieldWidget/MediaLibraryWidget.php`.
- Para a integração Views/cache desta revisão: [API do Views 11.x — `hook_views_query_alter`](https://api.drupal.org/api/drupal/core%21modules%21views%21views.api.php/11.x), [CacheableMetadata 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheableMetadata.php/class/CacheableMetadata/main), [Render API — cacheabilidade dos render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays), [VVJS 2.x](https://www.drupal.org/project/vvjs) e [releases VVJS](https://www.drupal.org/project/vvjs/releases). O Composer instalado fornece VVJS 2.0.2; a View foi verificada renderizando no Core 11.4.10.

## 2. Estado atual verificado

Inventário em `config/sync/` e no Runtime (sem alterar nada):

| Item | Estado | Consequência para a galeria |
| --- | --- | --- |
| Tipos de mídia | `image`, `document`, `remote_video` | Galerias aceitam somente `image`. `document` e `remote_video` ficam fora do campo de imagens. |
| Campo de imagem (`field_media_image`) | `alt` obrigatório (`alt_field_required: true`); extensões `png gif jpg jpeg webp`; sem limite de resolução | Revisão de alt e validação de tipo já existem na mídia. Galerias herdam essas regras. |
| Image styles | 10 estilos (`thumbnail`, `medium`, `large`, `wide`, `media_library`, `aculta_card_1x/2x`, `aculta_avatar`, `crop_thumbnail`, `produto_galeria`) | Faltam estilos de galeria (miniatura, lightbox). Criar na Fase 5 ou 6 por config. |
| Text formats | `full_html`, `basic_html`, `restricted_html`, `wiki`, `plain_text`, `email_html` | Inserção de galeria precisa respeitar filtros de `full_html`/`basic_html` (Fase 4). |
| CKEditor 5 (`full_html`) | Toolbar com `drupalInsertImage` e `drupalMedia` (Core) | Core já insere mídia única. Galeria exige plugin de entidade (Entity Embed). |
| Tipos de conteúdo de post | `article`, `blog` (publicações da Revista, `RevistaPublications::BUNDLES`) | Alvo editorial da inserção de galerias. |
| Workflow `editorial` | Content Moderation com estados `draft`, `published`, `archived`; aplicado a `blog`, `page`, `forum` via `moderation_state` | Conteúdo editorial tem moderação. Galeria não tem (ver L2). |
| Workflow `wiki_editorial` | Aplicado a `node.type.wiki_entry` | Fora do escopo da galeria. |
| Domínios | `DomainPurposeManager`: `main`, `account`, `support`, `magazine`, `wiki`, `shop`, `courses`, `forum`, `links` | Visibilidade por purpose (Fase 8). `domain_access` **não** está instalado; a Domain atual é `domain` + `domain_source`. |
| Campo de origem de domínio | `field_domain_source` em nodes | Modelo existente para a Fase 8 (a validar). |
| SEO | `metatag`, `schema_metatag`, `schema_image_object`, `schema_article`, `pathauto` | Reutilizar em vez de criar metadados próprios (Fase 6). `metatag_open_graph` não está habilitado. |
| Layout Builder | Habilitado | Media Gallery tem integração com Layout Builder (ver layout do módulo). Não usar como primeiro caminho. |
| Permissões | Sem permissão para galerias | Definir na Fase 1 (`aculta_gallery.permissions.yml`, sem duplicar permissões do Core). |
| Módulos de galeria | Nenhum instalado (`media_gallery`, `photoswipe`, `entity_embed`, `media_library_bulk_upload`) | Fase 1 instala apenas o aprovado. |

## 3. Avaliação dos módulos escolhidos

### Integração VVJS do álbum: acesso e cache (2026-10-18)

O palco usa a View `aculta_album_fotos` com estilo VVJS, uma linha por item de mídia. As miniaturas e os slides devem
representar exatamente o mesmo conjunto de Media com acesso `view` e arquivo de imagem presente, na ordem do campo
`images`. A checagem ocorre em `AlbumSlidesAccessHooks::filterAlbumSlides()` durante `views_query_alter`; consulta
indisponível ou sem itens permitidos falha fechada. A consulta ordena pela coluna `delta` da referência do álbum. Isso evita que o índice enviado por
`data-aculta-album-thumb` navegue para outra imagem ou revele um item que o painel de miniaturas omitiu.

`GalleryViewHooks` também agrega no render array do palco a entidade álbum, cada Media referenciada, o `AccessResult`
(inclusive quando negado) e o File da imagem. Esses metadados invalidam o HTML ao mudar publicação, arquivo ou
permissão e variam o cache conforme a decisão de acesso. A View não é tratada como fronteira de autorização isolada:
o filtro explícito é necessário porque o estilo de linha é Fields e a relação de campo, por si só, não garante a
verificação de acesso à entidade Media relacionada.

O gate `scripts/validate-aculta-image.php` agora lê IDs `image_style` e estilos do PhotoSwipe em Views e modos de
exibição exportados, exigindo que cada ID pertença ao catálogo do `aculta_image`. Core/contrib recebem o ID de estilo
que suas APIs exigem; o gate impede que a configuração exportada escape do catálogo controlado.

### Integração VVJS da galeria de produto

`galeria_produto` usa VVJS para apresentar as entidades Media uma por slide e mantém o modo de exibição
`produto_galeria`, o paginador sem limite, o CSS class `aculta-galeria-produto` e a finalidade de imagem existente.
Os slides têm autoplay desligado, navegação por setas, teclado e gesto de toque; VVJS é usado como formato de View,
sem duplicar a apresentação de imagem no tema. Os carrosséis editoriais e de projetos permanecem em VVJB; a lista
`aculta_albuns` continua grade paginada, pois é uma coleção, não uma sequência de slides. PhotoSwipe continua sendo o
lightbox; VVJL não o substitui.

### 3.1 Media Gallery 3.3.0 — atende, com limitações comprovadas

Confirmado no código do tarball 3.3.0:

- Entidade de conteúdo própria `media_gallery` (`@ContentEntityType`), com `translatable = TRUE`, `status` (EntityPublishedTrait), `changed`, `created` e `uid`.
- Campo `images`: referência a `media`, **cardinalidade ilimitada**, formatter `photoswipe_field_formatter`, widget `media_library_widget` (form display desabilitado por padrão: `setDisplayConfigurable('form', FALSE)`).
- Campos `title`, `description` (texto longo, traduzível), `use_pager`, `items_per_page` (padrão 12 na preprocess), `reverse`.
- Rotas: canônica `/media_gallery/{media_gallery}`; administração `/admin/content/media-gallery`.
- Controle de acesso: `view published media gallery entities`, `view unpublished media gallery entities`, `edit own media gallery entities`, `delete own media gallery entities`, `add media gallery entities`.
- Layouts (plugins `MediaGalleryLayout`): `Grid`, `Vertical`, `Horizontal`, `FeaturedImageGrid`, `Swiper`.
- Pathauto: plugin `MediaGalleryAliasType`.
- View de listagem `media_galleries` (config install) e bloco "latest gallery items".

Lacunas comprovadas (ver seção 6): revisões, moderação, capa, data de evento, categorias, créditos, direitos de uso, domínio, paginação real, slug em português.

### 3.2 PhotoSwipe 5.0.11 — atende

Formatter `photoswipe_field_formatter` (`src/Plugin/Field/FieldFormatter/PhotoswipeFieldFormatter.php`). Requer a biblioteca PhotoSwipe 5 provisionada fora do Composer PHP (D11). Recursos listados no mission (teclado, zoom, gestos, legenda) são do PhotoSwipe; **a verificação real de teclado e toque fica para a Fase 10** (Runtime).

### 3.3 Entity Embed 1.7 — atende para a inserção de galerias existentes

Pacote contém plugin CKEditor 5 `drupalentity` (`js/ckeditor5_plugins/drupalentity`) e testes `CKEditor5IntegrationTest`. Requer `drupal/embed ^1.10`.

Limitação: o plugin insere **entidades já existentes**. Não há criação de galeria dentro do diálogo do CKEditor. Isso segue a alternativa prevista no mission (seleção de galerias previamente criadas) e vira L5.

### 3.4 Media Library Bulk Upload 1.0.6 — não instalar por padrão

Core 11.4.8 já suporta upload múltiplo na Media Library: `FileUploadForm` define `'#multiple' => $slots != 1` (multiple quando há mais de uma vaga, o que ocorre com cardinalidade ilimitada). O módulo contrib não tem dependência adicional. A página do projeto indica manutenção mínima, então ele só entra se a Fase 3 medir uma lacuna real (D4).

### 3.5 Ponto não confirmado

A página do Media Gallery cita "inserir galerias no CKEditor". O código do tarball 3.3.0 **não contém** nenhum plugin ou referência a CKEditor (busca em `*.php`, `*.yml`, `*.js`). A integração com CKEditor, portanto, vem de Entity Embed (D3), não do Media Gallery.

## 4. Decisões

| ID | Decisão | Motivo |
| --- | --- | --- |
| **D1** | Usar a entidade `media_gallery` do Media Gallery como álbum e como galeria. **Não criar entidade `aculta_gallery`** nem Node Album. | O mission exige provar que o contrib não atende antes de criar entidade própria. A entidade atende os campos centrais (título, descrição, imagens ordenadas, publicação). Lacunas são cobertas por campos adicionais (D9), não por entidade nova. |
| **D2** | PhotoSwipe 5 como lightbox, via formatter do PhotoSwipe. | É a dependência do Media Gallery. Evita biblioteca paralela. |
| **D3** | Entity Embed (`drupalentity`) para inserir galerias existentes em artigos e blogs. | Mecanismo nativo de embedding de entidade no CKEditor 5. Não há shortcode textual nem editor JS próprio. |
| **D4** | Upload múltiplo pelo Core Media Library primeiro. `media_library_bulk_upload` somente se a Fase 3 comprovar lacuna (volume, validação ou ordenação). | Core já cobre o caso; o contrib tem manutenção mínima. Não instalar módulo redundante. |
| **D5** | Galerias **não são revisionadas**. Sem Content Moderation. Publicação é `status` (booleano). | A entidade não tem chaves de revisão (L2). Não se promete o que o tipo não suporta. A revisão do post preserva o token de embed; mudanças na galeria são ao vivo. Exige aceite do responsável (R3). |
| **D6** | URL pública da galeria é alias em português gerado pelo pathauto (ex.: `/galeria/<slug>`). A rota técnica `/media_gallery/{id}` não pode permanecer como caminho público. | AGENTS: slugs públicos em português, sem termo técnico de entidade e sem ID numérico. Implementação na Fase 2 (opções em L3). |
| **D7** | Paginação real **não** usa a paginação do Media Gallery. Usa consulta paginada por Views sobre `media_gallery__images` (faixa por `offset`/`limit`). | A paginação do contrib é aplicada depois que o campo inteiro foi renderizado (L4). Não se altera contrib. |
| **D8** | Visibilidade por domínio vem de um campo de purposes da própria galeria, validado por `DomainPurposeManager` no `hook_entity_access` (OOP `#[Hook]`). | Não há `domain_access` instalado. A Fase 8 usa o mesmo modelo do conteúdo (`field_domain_source`) e não usa hostname. |
| **D9** | Metadados adicionais (capa, autor/crédito, data do evento, categorias, direitos de uso, data de publicação) como **campos Drupal** na entidade `media_gallery`. | AGENTS: campos e Views antes de código estrutural. Os campos são config exportável. |
| **D10** | `aculta_gallery` é submódulo opcional do `aculta_portal`. Desabilitado, nada do Portal depende dele. | Mesma regra do `aculta_magazine` e do `aculta_deployer`. |
| **D11** | A biblioteca PhotoSwipe 5 precisa ser provisionada por **repositório de pacote** no `composer.json`, no padrão já usado por assets do projeto (`repositories` tipo `package`). Não é instalada por Composer PHP. | Pacote do módulo não traz a biblioteca JS. O projeto já usa esse padrão para bibliotecas jQuery/npm. Exige decisão explícita antes da Fase 1 (AGENTS, regra 14). |
| **D12** | Componentes visuais (grid, item, capa, legenda, estado vazio) ficam em SDC do ACULTA420, criados somente com consumidor real. | COMPONENT-DESIGN-SYSTEM: SDC para composição repetida com contrato estável. A Fase 5 altera o tema por autorização explícita da missão. |

### 4.1 Decisões do responsável (2026-10-14)

- **R5 (forma de entrega, registro histórico):** a execução original foi organizada na PR #268, com cada fase como commit. No ciclo vigente, continuar na branch autorizada; PR só quando solicitada.
- **Acesso às galerias (2026-10-14, supera R3):** segue o padrão do conteúdo da Revista. Só o administrador cria, edita e publica galerias. O editor de conteúdo não recebe as permissões de galeria, pois também não cria artigos nem blogs; continua usando o formato de texto da Revista e o fluxo de moderação, que não mudaram.
- **R3 (publicação, histórico):** aceito publicar sem revisão e sem Content Moderation (D5). **Só os papéis administrador e editor de conteúdo publicam galerias.** Mesmas permissões da Revista, aplicadas em `content_editor`: `access media gallery overview`, `add media gallery entities`, `edit any media gallery entities`, `delete any media gallery entities`, `view published media gallery entities`, `view unpublished media gallery entities`. O administrador já tem todas por `is_admin`. Não há papel de "autor" no site; autores que escrevem a Revista são o editor de conteúdo.
- **R4 (rota técnica):** não respondido. Adotada a recomendação (a): 404 na rota técnica quando a galeria tem alias. Confirmar na Fase 2.
- **R6 (PhotoSwipe):** autorizado. Instalado `photoswipe/photoswipe` 5.4.4 por repositório de pacote, em `web/libraries/photoswipe` (zip `v5.4.4` do GitHub, `dist/photoswipe.esm.js` confirmado).
- **R7 (tema):** autorizado alterar `web/themes/custom/aculta420/**` na Fase 5.
- **Galerias somente na Revista (D13):** toda publicação do tipo galeria fica vinculada à Revista, exclusivamente. Não é publicada em outros purposes (`main`, `shop`, `courses`, `wiki`, `forum`, `links`, `support`, `account`). A visibilidade por purpose (D8) é o purpose `magazine`. A inserção em posts fica restrita aos tipos da Revista (`article` e `blog`, `RevistaPublications::BUNDLES`). A Fase 8 implementa e testa essa restrição.

### 4.2 Criar galeria dentro do editor: pesquisa e decisão (2026-10-14)

Pergunta do responsável: é possível criar a galeria dentro do CKEditor 5? Quais alternativas?

| Alternativa | Fonte | O que faz | Avaliação |
| --- | --- | --- | --- |
| Entity Embed (escolhida) | código do tarball 1.7 (`drupalentity`) | Incorpora entidade **já existente** no texto, por `<drupal-entity>`. | Não cria galeria no diálogo. Entidade reutilizável, PhotoSwipe e D13 por entidade. **Adotada.** |
| Criar galeria no diálogo do Entity Embed | código do tarball 1.7 | Não há fluxo de criação. | Não suportado. |
| Inline Entity Form Dialog 1.0.6 | [drupal.org/project/inline_entity_form_dialog](https://www.drupal.org/project/inline_entity_form_dialog) | Widget de campo `entity_reference` que cria/edita a entidade referenciada num modal. | É widget de campo, não integração com o texto do CKEditor 5 (a página não declara integração). Não atende à incorporação no texto. Também pede Core ^11.2 e a página não reconcilia com ^10.3. |
| CKEditor Media Gallery 1.0.0-alpha1 (2026) | [drupal.org/node/3608354](https://www.drupal.org/node/3608354) | Botão de galeria no CKEditor 5; seleciona imagens da Media Library; galeria fica como elemento `drupal-gallery` no texto, **sem entidade**. | Alpha, "use at your own risk", fora da política de segurança, 10 sites. Sem entidade: sem reuso, sem publicação por purpose. **Não adotada.** |
| Upload de imagem dentro do diálogo de mídia do CKEditor | Core (`drupalMedia`) | Cria imagem (mídia), não galeria. | Útil para o editor subir fotos sem sair do texto. A verificar no navegador (Fase 10). |
| Plugin CKEditor próprio | — | Editor JS paralelo. | Proibido pela missão. |
| Modal dentro de modal | resumo de busca (drupal.org, issue 2741877 citada, não aberta) | CKEditor 5 dentro de diálogo do Drupal tem problemas com modais aninhados. | Qualquer criação de entidade a partir do diálogo do CKEditor depende desse problema do Core. Risco. |

Decisão: manter a galeria como **entidade criada no administrador** e incorporada pelo botão `galeria`. Complementar, na Fase 10: upload de fotos dentro do editor (Core) e um atalho "Criar galeria" na página de edição do artigo, que abre o formulário da galeria. Criar a galeria inteira dentro do CKEditor fica fora de escopo até o Core resolver modais aninhados.

Formato de texto dedicado da Revista (D13, implementado nesta fase): `revista_editorial` (copia o `full_html`, com `entity_embed` e sem `sourceEditing`, para que não se digite marcação de incorporação à mão). O botão `galeria` só existe nele. Permissão `use text format revista_editorial` concedida ao editor de conteúdo. Campos de texto fora de artigo e blog passam a aceitar a lista de formatos sem o da Revista; artigo e blog continuam aceitando todos (inclusive o `full_html` existente, para não quebrar os 11 conteúdos já gravados nele).

### 4.3 Criar galeria no editor: porte do CKEditor Media Gallery (2026-10-14)

Decisão do responsável: portar o fluxo do CKEditor Media Gallery para o ACULTA Gallery, com entidade `media_gallery` e PhotoSwipe, usando as ferramentas que o submódulo já tem.

- **Botão "Criar galeria"** (`acultaGalleryCreate`, plugin CKEditor 5 `CreateGallery` + `js/ckeditor5/acultagallery-create.js`), na toolbar do formato `revista_editorial`.
- **Diálogo** (`GalleryEmbedDialogForm`, rota `aculta_gallery.ckeditor_dialog`): título e imagens. As imagens usam o widget de imagens do display de formulário da galeria (`media_gallery.media_gallery.default`), sem widget novo. Permissão `add media gallery entities` e uso do formato.
- **Salvar**: cria a entidade `media_gallery` publicada e devolve a incorporação ao Entity Embed (`insertEntityEmbed`). Nada de lista de imagens no texto.
- **Reuso, sem duplicar**: inserção e renderização pelo Entity Embed; visualização pelo formatador PhotoSwipe da entidade; capa e publicação pelos hooks da Fase 3; restrição D13 pelo formato.
- **Não portado**: armazenamento em lista no texto, GLightbox, os quatro tipos de exibição do módulo, e o filtro próprio `drupal-gallery`. Origem e licença em `THIRD-PARTY.md`.
- **Verificado**: plugin descoberto e instanciado; formulário monta com título, widget de imagens e envio AJAX; envio sem imagem devolve o erro de validação e não cria galeria.
- **Ajuste de implementação (navegador, 2026-10-14)**: o widget de imagens do diálogo passou a ser o autocompletar por entidade do Core (`entity_reference_autocomplete`, múltiplo, mídias de imagem). A Media Library dentro do diálogo não funciona: o Drupal usa um único elemento de modal (`#drupal-modal`), e a Media Library substitui o diálogo da galeria. O formulário também guarda os serviços em propriedades comuns, como o Entity Embed, porque o objeto é restaurado nas requisições AJAX.
- **Verificado no navegador (Firefox, servidor de testes)**: login, formato da Revista, botão "Criar galeria", diálogo com título e duas imagens, criação da galeria publicada, incorporação no editor, artigo salvo e publicado em `revista420`, com o título da galeria e as duas imagens na página e sem tag `drupal-entity` sobrando. Dados de teste removidos depois.

## 5. Modelagem

### 5.1 Entidade

- **Entidade:** `media_gallery` (sem bundles). Campos de configuração ficam em `field.field.media_gallery.media_gallery.*` (a validar na Fase 1: entidade sem bundle no Field UI).
- **Galeria = álbum.** Mesma entidade serve álbum independente (página própria) e galeria menor (embed). A diferença está no uso: a galeria menor é incorporada por embed e não exige página pública (requisito da Fase 4).
- **Campos existentes:** `title`, `description`, `images`, `use_pager`, `items_per_page`, `reverse`, `uid`, `created`, `changed`, `status`.
- **Campos a adicionar (Fase 2):** capa (referência única a mídia de imagem), data do evento, data de publicação, créditos, direitos de uso, categorias (taxonomia existente), projeto (referência a `node` do tipo `project`), e campos de domínio (Fase 8). Nenhum campo duplica dados de Media ou de Node.

### 5.2 Número de imagens e reutilização

- **Ilimitado no modelo.** `images` tem cardinalidade ilimitada, então a restrição de tamanho é operacional, não de schema. Limite e paginação ficam em D7 e na Fase 7.
- **Reutilização:** a galeria guarda **referências** a entidades `media`. Uma mesma mídia pode aparecer em várias galerias. A exclusão de uma galeria não exclui as mídias (é referência, não composição). Remover uma imagem de uma galeria remove só a linha de referência. Comportamento a confirmar com teste na Fase 3.
- **Ordem:** o campo mantém a ordem dos deltas. Reordenação entra na Fase 3. A opção `reverse` do contrib inverte a exibição sem alterar os dados.
- **Formulário:** o widget `media_library_widget` carrega as referências no formulário. Com milhares de itens, isso é custo real. Medição e decisão ficam na Fase 2 (R2).

### 5.3 Entidades relacionadas (sem duplicação)

| Dado | Fonte de verdade |
| --- | --- |
| Imagem, metadados, alt, arquivo | Media (`image`) + File |
| Galeria, ordem, legenda, status | `media_gallery` |
| Post que contém a galeria | Node (`article`/`blog`) via Entity Embed |
| Listagem, filtros, paginação | Views |
| Lightbox | PhotoSwipe |
| Domínio de publicação | `DomainPurposeManager` + campo de purposes (Fase 8) |

## 6. Limitações comprovadas

| ID | Limitação | Evidência | Tratamento |
| --- | --- | --- | --- |
| **L1** | A entidade `media_gallery` não tem revisões. | Sem chaves de revisão nas `entity_keys` nem `revision_table` na anotação. | Aceita (D5). |
| **L2** | Sem Content Moderation nem estados de rascunho/revisão por galeria. | Sem chaves de revisão; `status` é o único estado. | Publicação booleana (D5). Pede aceite do responsável (R3). |
| **L3** | Rota canônica `/media_gallery/{media_gallery}` é em inglês e expõe ID numérico. | `links.canonical` na anotação da entidade. | Fase 2: alias pathauto em português e tratamento da rota técnica. Opções: (a) subscriber que responde 404 à rota técnica quando a galeria tem alias; (b) manter a rota e registrar exceção em `FRIENDLY-PORTUGUESE-SLUGS.md`. Recomendação: (a), sem redirect 301 de slug (regra do projeto). Decisão a confirmar antes da Fase 2. |
| **L4** | Paginação do contrib é aplicada **depois** da renderização do campo inteiro. | `media_gallery.module` chama `paginateMediaGallery()` sobre `$variables['content']['images']` já montado; o fatiamento acontece sobre itens já construídos. | D7. Nenhum patch no contrib. |
| **L5** | Entity Embed não cria galerias dentro do CKEditor. | Plugin `drupalentity` insere entidade existente. | Fase 4: seleção de galeria existente; registrar limitação. Sem editor JS próprio. |
| **L6** | Ordenação de `images` e `reverse` dependem de deltas. | Campo multivalorado padrão. | Reordenação na Fase 3. |
| **L7** | Galeria sem domínio. | Nenhum campo de domínio em `media_gallery`. | D8 e Fase 8. |
| **L8** | Arquivos derivados e originais são públicos por padrão. Restrição de entidade não restringe o arquivo. | Mídia `image` usa arquivo do File API; sem política de arquivo privado para mídia de galeria. | Fase 8 avalia; ver R1. |
| **L9** | Swiper depende de biblioteca externa não confirmada. | Layout `Swiper` existe; a dependência JS do layout não foi verificada no tarball. | Verificar antes de habilitar (Fase 5). Não habilitar sem comprovação. |
| **L10** | PHP do Runtime de desenvolvimento é 8.4.26 (`php -v`). O projeto declara PHP 8.5 em AGENTS. | `php -v`. `composer.json` exige `>=8.3`. | Verificar disponibilidade de PHP 8.5 no Homelab antes da Fase 10. Não declarar compatibilidade com 8.5 sem teste. |

## 7. Contratos

### 7.1 Publicação e acesso

- Acesso segue Entity API e as permissões do Media Gallery. Não há acesso paralelo por hostname.
- Galeria não publicada não aparece em Views, SEO, sitemap, cache ou outro domínio (Fase 8).
- Cacheability: a exibição de galeria declara contexts e tags da entidade e das mídias referenciadas. Não usar `max-age: 0` para esconder erro de cache (mission, Fase 9).
- Toda EntityQuery declara `accessCheck()` explicitamente.

### 7.2 Slugs e URLs

- Alias em português gerado pelo pathauto (`/galeria/...` como ponto de partida; o padrão final é definido na Fase 2).
- Nenhum slug com termo técnico (`media`, `gallery`, `node`, `user`).
- Slug publicado é estável. Mudança de slug responde 404 no caminho antigo, sem 301.
- Gate: `php scripts/validate-public-slugs.php`.

### 7.3 Fronteira Portal → tema

- Código no `aculta_gallery`: rotas, consultas, formulários, `#ajax`, validação, access, cache, SEO e presenter neutro.
- Design no ACULTA420: SDC de grid, item, capa, legenda e estado vazio, com schema de propriedades, criado somente com consumidor real.
- O módulo emite `data-aculta-*`/`data-portal-*` para comportamento. Não emite classes de design.
- O tema nunca recebe `DomainInterface`, storage ou purpose. Recebe dados prontos.
- Gate: `php scripts/validate-module-theme-boundary.php`.

### 7.4 Upload e arquivos

- Extensões: somente as do campo de imagem (`png gif jpg jpeg webp`).
- Tipo MIME e tamanho validados pelo File API. Nenhum upload de arquivo executável ou extensão fora da lista.
- Importação múltipla por Core (D4).

### 7.5 Segurança

- Texto de descrição e legenda: formato de texto explícito. Não usar `plain_text` com HTML esperado.
- Escapar qualquer atributo de `alt`, legenda e crédito. Não concatenar HTML.
- Sem segredos. Sem hostname fixo (AGENTS: `aculta.org` e `aculta.toca.net.br` só no `deploy.json`).

## 8. Riscos e perguntas ao responsável

| ID | Risco / pergunta | Quem decide | Quando |
| --- | --- | --- | --- |
| **R1** | Galerias de **acesso restrito** exigem arquivo privado para as imagens? Hoje a mídia é pública (L8). | Responsável | Antes da Fase 8 |
| **R2** | Tamanho esperado de álbuns (estimativa). Define se a Fase 7 precisa de Views paginada obrigatoriamente. | Responsável | Antes da Fase 7 |
| **R3** | Aceite de galerias sem revisão e sem Content Moderation (D5/L2). | Responsável | Antes da Fase 2 |
| **R4** | Decisão de L3: opção (a) (404 na rota técnica) ou (b) (exceção documentada). | Responsável | Antes da Fase 2 |
| **R5** | Conflito de forma de entrega: a missão pede **uma PR** com fases como commits; `AGENTS.md` pede **PR própria por fase**. Esta fase segue a missão. | Responsável | Antes da Fase 1 |
| **R6** | Provisionamento da biblioteca PhotoSwipe 5 (D11) por repositório de pacote. Novo item no `composer.json` exige explicação registrada. | Responsável | Fase 1 |
| **R7** | Autorização para alterar `web/themes/custom/aculta420/**` na Fase 5. A missão pede SDCs no tema. | Responsável | Fase 5 |
| **R8** | Evidência de desempenho com 50, 500 e 2.000 imagens depende de dados de teste **não públicos** (sem imagens de pessoas ou privadas como fixture). | Responsável | Fase 7 |

## 9. Plano de fases e versões

Versão alvo por fase, conforme `web/themes/custom/aculta420/docs/versioning.md` (linha `-dev.N`, classificação MINOR para feature nova; PATCH para documentação sem código):

| Fase | Versão alvo | Conteúdo | Tipo |
| --- | --- | --- | --- |
| 0 | 0.1.0-dev.1 | Este documento e `CHANGELOG.md` do módulo | PATCH (documentação, sem código) |
| 1 | 0.1.0-dev.2 | Fundação: `aculta_gallery.info.yml`, dependências aprovadas, config inicial e schema | MINOR |
| 2 | 0.1.0-dev.3 | Álbuns: campos adicionais, alias pt-BR, formulário editorial | MINOR |
| 3 | 0.1.0-dev.4 | Importação e organização: upload múltiplo, capa, ordem, desassociação | MINOR |
| 4 | 0.1.0-dev.5 | Galerias incorporadas em posts (Entity Embed) | MINOR |
| 5 | 0.1.0-dev.6 | PhotoSwipe e componentes SDC no ACULTA420 | MINOR |
| 6 | 0.1.0-dev.7 | Catálogo: Views, filtros, capas, SEO | MINOR |
| 7 | 0.1.0-dev.8 | Desempenho com galerias grandes | MINOR |
| 8 | 0.1.0-dev.9 | Integração multidomínio | MINOR |
| 9 | 0.1.0-dev.10 | Hardening e testes direcionados | PATCH |
| 10 | 0.1.0-rc.1 | Validação de release no Homelab | RC (declarada em 2026-10-15; duas rodadas de navegador no servidor de testes) |

A versão de cada fase é marcada no `aculta_gallery.info.yml` (a partir da Fase 1) e no `CHANGELOG.md` do módulo. PR só quando solicitada; tag Git exige pedido explícito do responsável.

## 10. Verificações previstas

- Gates do Portal: `validate-aculta-portal-drupal11.php`, `validate-module-theme-boundary.php`, `validate-public-slugs.php`, `validate-environment-addresses.php`.
- Drush: `php vendor/drush/drush/drush.php status`, `cr`, `updatedb:status`, `config:status`.
- Composer: `validate` e `audit` após a Fase 1.
- Testes: Kernel/Functional direcionados por fase; Runtime (Homelab) na Fase 10.
- Testes de navegador e teclado/toque: `docs/portal/TESTES-NAVEGADOR.md`.

## 11. Histórico

- 0.1.0-dev.1 (2026-10-14): modelagem e contratos definidos. Nenhuma dependência instalada. Nenhum código de runtime.
