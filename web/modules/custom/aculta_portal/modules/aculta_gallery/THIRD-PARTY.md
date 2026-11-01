# Código de terceiros adaptado no ACULTA Gallery

## CKEditor Media Gallery (drupal/ckeditor_media_gallery 1.0.0-alpha1)

- Origem: https://www.drupal.org/project/ckeditor_media_gallery (release 1.0.0-alpha1, 2026-07-04).
- Licença do original: GPL-2.0-or-later (`LICENSE.txt` do pacote, copiado na pesquisa e não distribuído aqui).
- Uso: a ideia do fluxo de galeria dentro do CKEditor 5 (botão, diálogo e inserção) foi portada para o ACULTA Gallery.
  Não há cópia literal do código do módulo; a implementação usa as ferramentas já existentes do submódulo.
- Adaptações, por decisão do responsável (2026-10-14):
  - armazenamento: entidade `media_gallery` (não lista de UUIDs no texto);
  - lightbox: PhotoSwipe (não GLightbox);
  - exibição: layouts da entidade `media_gallery` (não os quatro tipos próprios);
  - inserção e renderização: Entity Embed (`insertEntityEmbed`, filtro `entity_embed`), sem filtro próprio;
  - plugin CKEditor 5 sem etapa de compilação, sobre o bundle do Core (CKEditor 5 ~47.6), porque o plugin do módulo é compilado para CKEditor 5 v34.
- Não adotados: GLightbox (CDN e segunda biblioteca de lightbox), o filtro `drupal-gallery` e o `GalleryBuilder`.
- Se algum trecho for copiado literalmente no futuro, o aviso de copyright e a licença GPL-2.0-or-later do original devem acompanhar o trecho.
