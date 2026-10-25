# ACULTA Gallery — hooks, classes e contrato com o tema

Status: referência vigente da linha 0.1.x. Complementa `ARQUITETURA.md` (decisões) e não repete o histórico de fases (ver `CHANGELOG.md`).

## 1. Hooks e serviços (código do submódulo)

| Ponto de extensão | Classe | Função |
| --- | --- | --- |
| — | — | Visualização e listagem não têm hooks de restrição por purpose; permissões e status publicados do Media Gallery controlam o acesso. |
| — | — | A listagem `/albuns` não aplica filtro de purpose; entidades publicadas ficam disponíveis em todos os purposes. |
| `media_gallery_view` (OOP `#[Hook]`) | `Hook/GalleryViewHooks` | Monta o palco do álbum (`album_stage`): slideshow VVJS da view `aculta_album_fotos` e painel de miniaturas. Roda em `default` e `full`. |
| `entity_type_alter` (OOP `#[Hook]`) | `Hook/GalleryListBuilderHooks` | Troca o list builder de `media_gallery` pela classe do submódulo. |
| Classe `ListBuilder/AcultaGalleryListBuilder` (estende `MediaGalleryListBuilder`) | `ListBuilder/AcultaGalleryListBuilder` | Coleção administrativa: o título usa o alias local em português. Edição e exclusão seguem no MAIN. |
| `media_gallery_presave` (OOP `#[Hook]`) | `Hook/GalleryCoverHooks` | Mantém a capa entre as imagens da própria galeria. Sem imagens, a capa fica vazia. |
| `KernelEvents::REQUEST` (subscriber, tag `event_subscriber`) | `EventSubscriber/CanonicalGalleryRouteSubscriber` | Responde 404 ao caminho técnico cru `/media_gallery/{id}`. O alias `/albuns/...` continua. |
| Plugin CKEditor 5 `aculta_gallery_create` | `Plugin/CKEditor5Plugin/CreateGallery` | Botão "Criar galeria" no formato `revista_editorial`. Abre a rota do diálogo. |
| Rota `aculta_gallery.ckeditor_dialog` | `Form/GalleryEmbedDialogForm` | Diálogo: título, imagens por autocompletar e upload de fotos novas. Salva a galeria e devolve a incorporação. |

Classes de marcação do palco (contrato `data-*` completo em [FRONTEIRA-MODULO-TEMA.md](FRONTEIRA-MODULO-TEMA.md)):

| Atributo / classe | Quem põe | Função |
| --- | --- | --- |
| `data-aculta-album-stage` | módulo (`GalleryViewHooks::mediaGalleryView`) | Contêiner do palco (slideshow + painel). |
| `data-aculta-album-thumbs` | módulo (`GalleryViewHooks::thumbs`) | Painel de miniaturas. |
| `data-aculta-album-thumb="N"` | módulo | Botão da miniatura N; corresponde ao slide N. |
| `data-aculta-album-toggle` | tema (`js/album-slideshow.js`) | Botão do celular que recolhe o painel. |
| `vvjs-slideshow`, `.vvjs-item`, `.vvjs-inner` | contrib (`views_vvjs`) | Marcação do VVJS, estilizada pelo tema dentro do palco. Miniaturas usam `goToSlide()` e o evento público `vvj:slideChanged`. |
| `photoswipe`, `photoswipe-gallery` | contrib (`photoswipe`) | Lightbox das fotos. |

Regras:
- Os hooks são OOP (`#[Hook]`) e recebem dependências por construtor (`#[Autowire]`). Não usar `\Drupal::` em `src/`.
- O diálogo não usa Media Library (modal aninhado: o Drupal tem um único `#drupal-modal`). Imagens entram por autocompletar ou upload do próprio formulário.
- O JavaScript do botão (`js/ckeditor5/acultagallery-create.js`) não tem etapa de compilação: usa o bundle do Core (`CKEditor5.dll`).

## 2. Marcação que a galeria entrega ao tema

**Palco do álbum (página `/albuns/...`):** o módulo entrega o contêiner `data-aculta-album-stage` com o slideshow VVJS e o painel `data-aculta-album-thumbs` (miniaturas `data-aculta-album-thumb="N"`). A aparência é do tema. Contrato completo, donos e pendências: [FRONTEIRA-MODULO-TEMA.md](FRONTEIRA-MODULO-TEMA.md).

Nas listagens e no corpo das notícias, a galeria usa o markup do PhotoSwipe (contrib) e as classes do Bootstrap que o tema já carrega:

No palco do álbum, `AlbumSlidesAccessHooks` filtra a View pelos IDs de Media com acesso `view` permitido e File de imagem
presente, ordenados pela ordem `delta` do campo `images`. `GalleryViewHooks` agrega metadados de cache do álbum, Media, File e resultado de
acesso no contêiner `album_stage`; o conjunto/índice de slides corresponde ao painel de miniaturas. A View de produto
(`galeria_produto`) usa VVJS com modo `produto_galeria`, enquanto a apresentação visual continua sendo responsabilidade
do ACULTA420.

| Classe | Onde aparece | Uso |
| --- | --- | --- |
| `photoswipe-gallery` | contêiner de cada campo `images` | Agrupa as fotos de uma galeria. Grupos ficam isolados por instância. |
| `photoswipe` | `<a>` de cada foto | Abre o lightbox. Traz `data-pswp-width` e `data-pswp-height` (dimensões reais). |
| `img-fluid`, `image-style-media-gallery-image` | `<img>` da miniatura | Miniatura 300×180 (estilo `media_gallery_image`), com `loading="lazy"`. Usada fora do palco. |
| `field--name-field-credits`, `field--name-field-event-date` | datas e crédito da galeria | Crédito e data do evento (Fase 5). |

Imagem grande do lightbox: estilo `media_gallery_lightbox` (1600 px de largura, sem ampliar).

## 3. Contrato com o tema (Editorial e Notícia)

- As publicações usam o bundle único `revista`; notícia, blog/opinião e entrevista são classificações editoriais. O gate `scripts/validate-content-visual-parity.php` verifica o bundle e seus displays.
- A página do editorial usa o padrão `aculta-revista-publication` (classes `__meta`, `__cover`, `__subtitle`, `__summary`, `__body`, `__authors`, `__footer`). Template: `components/patterns/revista-publication/revista-publication.twig`.
- Chamada (`field_subtitle`) e resumo (`field_summary`) são campos distintos: a chamada é uma linha, o resumo é uma ou duas frases.

## 4. Pendências visuais (não resolvidas)

- **Cartão da listagem com galeria incorporada:** o teaser renderiza o corpo, e o corpo com `<drupal-entity>` leva a galeria inteira para dentro do cartão. Corrigir no teaser (não exibir incorporações) ou no formatador de resumo.
- **Lightbox:** a legenda pelo `alt` da mídia foi configurada, mas a marcação não expôs o atributo de legenda na verificação pelo HTML. Confirmar no navegador.
- **SDCs do ACULTA420** (grade, item, capa, legenda e estado vazio): ainda não criados. Criar só com consumidor real, por componente, com schema de propriedades.
