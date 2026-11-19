# ACULTA Gallery — hooks, classes e contrato com o tema

Status: referência vigente da linha 0.1.x. Complementa `ARQUITETURA.md` (decisões) e não repete o histórico de fases (ver `CHANGELOG.md`).

## 1. Hooks e serviços (código do submódulo)

| Ponto de extensão | Classe | Função |
| --- | --- | --- |
| — | — | Visualização e listagem não têm hooks de restrição por purpose; permissões e status publicados do Media Gallery controlam o acesso. |
| — | — | A listagem `/albuns` não aplica filtro de purpose; entidades publicadas ficam disponíveis em todos os purposes. |
| `media_gallery_view` (OOP `#[Hook]`) | `Hook/GalleryViewHooks` | Monta a View UI Patterns/PhotoSwipe apropriada à finalidade editorial ou Fórum. Roda em `default` e `full`. |
| `entity_type_alter` (OOP `#[Hook]`) | `Hook/GalleryListBuilderHooks` | Troca o list builder de `media_gallery` pela classe do submódulo. |
| Classe `ListBuilder/AcultaGalleryListBuilder` (estende `MediaGalleryListBuilder`) | `ListBuilder/AcultaGalleryListBuilder` | Coleção administrativa: o título usa o alias local em português. Edição e exclusão seguem no MAIN. |
| `media_gallery_presave` (OOP `#[Hook]`) | `Hook/GalleryCoverHooks` | Mantém a capa entre as imagens da própria galeria. Sem imagens, a capa fica vazia. |
| `KernelEvents::REQUEST` (subscriber, tag `event_subscriber`) | `EventSubscriber/CanonicalGalleryRouteSubscriber` | Responde 404 ao caminho técnico cru `/media_gallery/{id}`. O alias `/albuns/...` continua. |
| Plugin CKEditor 5 `aculta_gallery_create` | `Plugin/CKEditor5Plugin/CreateGallery` | Botão "Criar galeria" no formato `revista_editorial`. Abre a rota do diálogo. |
| Rota `aculta_gallery.ckeditor_dialog` | `Form/GalleryEmbedDialogForm` | Diálogo: título, imagens por autocompletar e upload de fotos novas. Salva a galeria e devolve a incorporação. |

Classes de marcação do palco (contrato `data-*` completo em [FRONTEIRA-MODULO-TEMA.md](FRONTEIRA-MODULO-TEMA.md)):

| Atributo / classe | Quem põe | Função |
| --- | --- | --- |
| `photoswipe-gallery` | módulo (`GalleryViewHooks`) | Agrupa as imagens PhotoSwipe do álbum editorial; classe funcional exigida pelo contrib. |
| `layout-grid` | UI Patterns Views | SDC editorial `aculta420_v2:layout-grid`; recebe rows sem entidades em props. |

| `photoswipe`, `photoswipe-gallery` | contrib (`photoswipe`) | Lightbox das fotos. |

Regras:
- Os hooks são OOP (`#[Hook]`) e recebem dependências por construtor (`#[Autowire]`). Não usar `\Drupal::` em `src/`.
- O diálogo não usa Media Library (modal aninhado: o Drupal tem um único `#drupal-modal`). Imagens entram por autocompletar ou upload do próprio formulário.
- O JavaScript do botão (`js/ckeditor5/acultagallery-create.js`) não tem etapa de compilação: usa o bundle do Core (`CKEditor5.dll`).

## 2. Marcação que a galeria entrega ao tema

**Álbum editorial (página `/albuns/...`):** `GalleryViewHooks` monta wrapper `photoswipe-gallery`; a View `aculta_album_fotos` compõe suas rows com o SDC `layout-grid`, e o formatter PhotoSwipe fornece a imagem e o lightbox. Não há slideshow nem miniaturas VVJS nesse caminho.

**Galeria contextual do Fórum:** `aculta_forum_gallery_slides` agora compõe `layout-grid` com PhotoSwipe. O Runtime não tem galeria Fórum publicada; aceite visual com conteúdo real segue pendente. Contrato: [FRONTEIRA-MODULO-TEMA.md](FRONTEIRA-MODULO-TEMA.md).

Nas listagens e no corpo das notícias, a galeria usa o markup do PhotoSwipe (contrib) e as classes do Bootstrap que o tema já carrega:

Nas duas galerias, `AlbumSlidesAccessHooks` filtra a View pelos IDs de Media com acesso `view` permitido e File de imagem
presente, ordenados pela ordem `delta` do campo `images`. `GalleryViewHooks` agrega metadados de cache do álbum, Media,
File e resultado de acesso no contêiner pai. A View `galeria_produto` usa UI Patterns `layout-grid`; o hook de produto continua entregando o render array.

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
