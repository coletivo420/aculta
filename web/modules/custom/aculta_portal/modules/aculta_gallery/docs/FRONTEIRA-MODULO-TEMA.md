# ACULTA Gallery — fronteira entre submódulo, tema e configuração

Documento de dono. A regra geral do Portal está em `docs/portal/FRONTEIRA-MODULO-TEMA.md`;
esta aplicação acompanha `aculta_gallery` 0.1.0-dev.24.

## 1. Três donos

| Dono | Onde está | O que faz |
| --- | --- | --- |
| **Submódulo `aculta_gallery`** | `web/modules/custom/aculta_portal/modules/aculta_gallery/` | Dados, acesso, rotas, views de conteúdo, hooks, formulário de incorporação, coleção administrativa, *tamanho* das imagens (estilos) e *quais* fotos aparecem em que ordem. |
| **Tema `aculta420_v2`** | `web/themes/custom/aculta420_v2/` | Fornece o SDC `layout-grid`; UI Patterns Views compõe a apresentação editorial. Não conhece entidade nem regra de acesso. |
| **Configuração do site** | `config/sync/` | Views, estilos de imagem e campos exportados. Cada item tem um dono nomeado abaixo. |

Contrib (`media_gallery`, `photoswipe`, `entity_embed`, `embed`) é dependência
de terceiros: o submódulo a configura, o tema estiliza o markup dela, e nenhum dos dois o altera.

## 2. Quem é dono de cada peça

| Peça | Dono | Arquivo | Motivo |
| --- | --- | --- | --- |
| Entidade `media_gallery`, campos, pathauto `/albuns/[title]` | submódulo | `config/install/*.yml` | Dados e URL pública pertencem ao domínio da galeria. |
| Acesso público em todos os purposes | Core `media_gallery` + submódulo | permissões Core e publicação da entidade | Galerias são conteúdo transversal; consumidores controlam anexos contextuais por seus próprios fluxos. |
| Coleção administrativa com alias em português | submódulo | `src/ListBuilder/AcultaGalleryListBuilder.php`, `src/Hook/GalleryListBuilderHooks.php` | Link usa o alias local; a edição continua no MAIN (política administrativa). |
| View `aculta_album_fotos` (fotos do álbum, paginação, UI Patterns, PhotoSwipe) | submódulo (config) | `config/sync/views.view.aculta_album_fotos.yml` | `layout-grid` apresenta os render arrays de foto; o formatter PhotoSwipe mantém o lightbox. |
| View `aculta_forum_gallery_slides` (imagens contextuais do Fórum) | submódulo (config) | `config/sync/views.view.aculta_forum_gallery_slides.yml` | UI Patterns `layout-grid` compõe as rows PhotoSwipe; Runtime sem galeria Fórum publicada, portanto sem aceite visual de conteúdo real. |
| View `aculta_albuns` (listagem `/albuns`) | submódulo (config) | `config/sync/views.view.aculta_albuns.yml` | Lista de álbuns; filtro de purpose está no hook de listagem. |
| Wrapper PhotoSwipe editorial | submódulo | `src/Hook/GalleryViewHooks.php` | Agrupa os links acessíveis do formatter em `photoswipe-gallery` e propaga cache metadata. |
| Wrapper PhotoSwipe das galerias | submódulo | `src/Hook/GalleryViewHooks.php` | Encapsula a saída das duas Views SDC e agrega cache metadata de álbum, Media, File e acesso. |
| Estilo da imagem do lightbox | submódulo (config) | `config/sync/image.style.media_gallery_lightbox.yml` | Ampliação em 1600 px. |
| Grade responsiva editorial | tema/UI Patterns | `components/layout-grid/` | SDC local; `view_rows` contém saída Drupal/PhotoSwipe. |

## 3. Contrato entre módulo e tema

O módulo entrega marcação com atributos `data-*` (comportamento). O tema escolhe a aparência.

| Atributo | Quem põe | Quem usa | Significado |
| --- | --- | --- | --- |
| `photoswipe-gallery` | módulo (`GalleryViewHooks`) | PhotoSwipe contrib | Wrapper do álbum editorial e grupo do lightbox. |
| `layout-grid` | UI Patterns Views | SDC `aculta420_v2:layout-grid` | Grade de fotos; não consulta entidades. |


Regra: o módulo não põe classe de design (`bi-`, `btn`, `col-`, etc.) nem CSS/JS. O tema não consulta
entidade, view, purpose nem nome de view do módulo.

## 4. Pontos de atenção (estado)

- **Álbum editorial migrado.** Runtime/Sync concordam; 63 imagens, ordenação, filtro de acesso, pager,
  PhotoSwipe e cache foram preservados. Firefox ESR antes/depois passou cinco larguras e dois modos;
  PhotoSwipe abre e fecha com Escape. Zoom 200%, cache-hit e perfis autenticados seguem pendentes.
- **VVJS ainda tem uma configuração de exemplo upstream fora das galerias ACULTA.** O Fórum já usa UI Patterns, mas ainda não há
  conteúdo real para aceite visual. A View de produto também foi migrada; não desinstalar VVJS sem prova de zero consumidores
  (incluindo exemplos upstream) e autorização operacional.
- **Gate de contrato.** `scripts/validate-album-stage-contract.php` valida UI Patterns/layout-grid,
  formatter PhotoSwipe, pager, acesso/cache no hook e a separação da View do Fórum; não inspeciona contrib.

## 5. Versionamento

- Módulo: `0.1.0-dev.24` (ver `aculta_gallery.info.yml`).
- Tema: `aculta420_v2`; a versão segue o registro em `aculta420_v2.info.yml` e o fluxo de versão do tema.
