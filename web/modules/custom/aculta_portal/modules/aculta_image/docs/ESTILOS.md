# Estilos de imagem do ACULTA (catálogo, uso e pesquisa)

Revisão: 2026-10-17. Fonte de verdade dos nomes: `src/ImageCatalog.php`. Os arquivos de estilo ficam em
`config/sync/image.style.*.yml` e todos os estilos usam o pipeline `aculta_derivados` (compressão sem perda).

## Pesquisa de referência

- Four Kitchens, boas práticas de imagens responsivas no Drupal: vários derivados por tipo de imagem, em vez de um
  único estilo para tudo. <https://www.fourkitchens.com/blog/development/responsive-image-best-practices-drupal/>
- Kalamuna (wiki): exportar até cerca de 2× o tamanho exibido para telas de alta densidade; manter a proporção da
  imagem próxima da proporção do recorte; poucos pontos de quebra. <https://kalamuna.atlassian.net/wiki/x/EYCLqg>
- UTexas Drupal Kit: o estilo de fallback deve ser o menor tamanho esperado no espaço.
  <https://drupalkit.its.utexas.edu/docs/development/responsive_images.html>
- Drupal, issue #3523451: com `srcset` e `sizes`, o Drupal 11 ordena os estilos pelo nome da máquina e usa o último
  para as dimensões renderizadas. Por isso o ACULTA não usa um conjunto de estilos do core em `responsive_image`;
  a única faixa 1x/2x (card) é montada por `ImageStyleHooks`, com dois estilos explícitos.
  <https://www.drupal.org/i/3523451>
- Drupal, issue #3516726: dimensões do fallback usadas no lugar das do estilo selecionado (corrigido por versão).
  <https://www.drupal.org/node/3516726>
- Chromatic: `srcset` com `sizes` para trocar resolução; `picture` só quando o recorte muda por ponto de quebra.
  <https://chromatichq.com/blog/responsive-images-drupal-8-using-srcset>

Decisão decorrente: recorte por finalidade, um estilo por uso, dois estilos explícitos para 1x/2x e nenhum conjunto
de estilos automático enquanto a issue #3523451 estiver aberta.

## Catálogo e uso (2026-10-17)

| Finalidade | Estilo | Dimensões | Recorte | Quem usa |
| --- | --- | --- | --- | --- |
| `avatar` | `aculta_avatar` | 256 × 256 | quadrado | painel da conta, perfil público, memorial, Magazine (autoria) |
| `avatar_pequeno` | `aculta_avatar_pequeno` | 96 × 96 | quadrado | **novo**: disponível; uso recomendado em respostas e listas (ver pendências) |
| `card` / `card_2x` | `aculta_card_1x` / `aculta_card_2x` | 256 / 512 | quadrado | card de projeto (teaser); 2x montado por `ImageStyleHooks` |
| `card_16x9` | `aculta_card_16x9` | 640 × 360 | 16:9 | **novo**: cards editoriais em proporção 16:9 |
| `destaque` | `aculta_destaque_16x9` | 1280 × 720 | 16:9 | **novo**: página completa do projeto; abertura de tópico do Fórum (Revista) |
| `grande` | `large` | 480 × 480 | sem recorte | mídias da Revista na exibição padrão (ver pendências) |
| `media` | `medium` | 220 × 220 | sem recorte | biblioteca de mídias |
| `miniatura` | `thumbnail` | 100 × 100 | sem recorte | campos de recorte do usuário |
| `compartilhamento` | `aculta_compartilhamento_1200x630` | 1200 × 630 | recorte central 1,91:1 | **novo**: `og:image` das publicações (Revista e projetos), via `metatags_alter` |
| `largo` | `wide` | 1090 px de largura | sem recorte | corpo de publicações |
| `recorte_quadrado` | `crop_thumbnail` | 400 px de largura | sem recorte | prévia do recorte de avatar |
| `galeria_miniatura` | `media_gallery_thumb` | 160 × 160 | recorte | grade de galerias |
| `galeria_card` | `media_gallery_image` | 300 × 180 | recorte | card de galeria |
| `galeria_visualizacao` | `media_gallery_stage` | 1000 px de largura | sem recorte | palco da galeria |
| `galeria_lightbox` | `media_gallery_lightbox` | 1600 px de largura | sem recorte | visualizador ampliado |
| `biblioteca_midia` | `media_library` | 220 × 220 | sem recorte | biblioteca de mídias |
| `produto_galeria` | `produto_galeria` | 960 × 1200 | sem recorte | galeria de produto da loja |

Estilos removidos do Runtime em 2026-10-17: `max_325x325`, `max_650x650`, `max_1300x1300` e `max_2600x2600`
(sem consumidor; existiam só no Runtime).

## Capa e compartilhamento

- Capa do site: `aculta_destaque_16x9` (1280×720). Compartilhamento: `aculta_compartilhamento_1200x630`, usado no
  `og:image` (decisão de 2026-10-17). O Guardian publica o `og:image` em 1200×630 com recorte por enquadramento
  (`fit=crop`) e declara `og:image:width`; a BBC publica a imagem sem recorte social. Adotamos o modelo do Guardian.
  Recomendação de implementação: declarar `og:image:width` e `og:image:height` no metatag. A dimensão 1200×630 é o padrão de compartilhamento de Facebook, LinkedIn e
  WhatsApp, e funciona no X (que recorta para 16:9). Mantenha o assunto principal na faixa central de 1080×600.
- Peso: mantenha o derivado abaixo de cerca de 300 KB (fonte secundária indica que o WhatsApp reduz imagens acima
  disso). O pipeline `aculta_derivados` aplica compressão sem perda.
- Google Discover: pede largura de pelo menos 1200 px e proporção 16:9 para imagem grande; o destaque 1280×720 atende.
  Pedir `max-image-preview:large` é responsabilidade do metatag.

## Imagens dentro de publicações e posts

O CKEditor 5 do core não oferece escolha de estilo para imagens inseridas no texto. A padronização adotada:

- `image_upload.max_size` de 10 MB nos editores que aceitam upload (`basic_html`, `full_html`, `revista_editorial` e
  `usuario_aculta`). **Dimensão:** 1200 px de largura. O core deixou de recusar imagens maiores (`max_dimensions` nulo);
  o `aculta_image` reduz para 1200 px no upload (`InlineImageHooks` e `ImageCompressor::redimensionar`). Aceita e converte.
  - Coluna de leitura da Revista: `68ch` (`revista-post.css`), cerca de 580 px no tamanho de fonte do tema. Uma imagem
    do corpo precisa de cerca de 1160 px para ficar nítida em tela retina; 1200 px cobre isso.
  - Jornais, medidos no HTML público: BBC, variantes de 240 a 1536 px, com 1024 px para o corpo; Guardian, de 465 a
    1920 px; Folha, de 320 a 2400 px. Nenhum grande veículo serve imagem de corpo acima de cerca de 1536 px. Por isso
    1600 px foi descartado.
  - Antes de 1600, a referência de 1200 foi confirmada por esses números; o valor pode subir se a coluna mudar.
- Compressão sem perda do upload: `ImageCompressor` (código próprio) roda no hook `file_insert` para arquivos de
  `public://inline-images/`, com `optipng`, `jpegoptim` e `gifsicle`. Medido: 0,7% em JPEG; o ganho maior vem de
  derivados de PNG (13,6%). Em produção depende de `shell_exec` liberado no painel.
- Módulos da comunidade avaliados e não adotados no corpo: `ckeditor_media_resize` (exige migrar as imagens para
  mídias), `editor_image_styles` e `ckeditor_media_image_style` (sem release estável e fora da cobertura de segurança).

## Pendências de aplicação (não feitas nesta revisão)

Atualização 2026-10-17 (handoff): o modelo de imagem no texto está parcialmente implementado; ver `MODELOS-DE-IMAGEM.md`. Os itens abaixo continuam pendentes.

- Respostas do Fórum usam `aculta_avatar` (256 px) para avatares de 36 px. Trocar para `avatar_pequeno` exige mudar
  a exibição de usuário usada nos comentários, o que altera o design de comentários; ficou para decisão.
- Mídias da Revista (`media.image.default`) usam `large`. A capa de destaque pode passar a `destaque`, mas muda a
  proporção de todas as capas: decisão de design.
- Capa de curso do LMS (previsão de 960 × 540): criar o estilo quando o campo existir.
