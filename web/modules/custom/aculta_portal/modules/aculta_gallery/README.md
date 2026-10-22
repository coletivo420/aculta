# ACULTA Gallery (`aculta_gallery`)

Submódulo do ACULTA Portal para integração de galerias com entidades e mídia do
Drupal. A arquitetura e os limites das dependências estão documentados em
[docs/ARQUITETURA.md](docs/ARQUITETURA.md); contratos com tema e hooks ficam nos
guias em `docs/`.

Consulte também [fronteira módulo–tema](../../../../../../docs/portal/FRONTEIRA-MODULO-TEMA.md),
[mapa módulo–tema](../../../../../../docs/portal/MAPA-MODULO-TEMA.md) e o
[CHANGELOG](CHANGELOG.md).

## Content Index

Quando `aculta_gallery` e `aculta_index` estiverem habilitados, o provider
`gallery` projeta metadados mínimos da entidade Gallery e relações de saída
`gallery_image`/`gallery_cover` somente para o bundle Media `image`. A resposta
do Index continua revalidando Entity Access da galeria e das imagens; File,
alt, direitos, descrição e URLs não entram no catálogo.

## Content Index

O provider `gallery` declara o purpose canônico `magazine` para revalidar Entity Access, inclusive quando uma galeria é alcançada por uma relação de curso. Relations `gallery_image` e `gallery_cover` apontam somente para `media:image`; o Index continua excluindo arquivos, URLs e dados de direitos.
