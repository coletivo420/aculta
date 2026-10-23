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

O provider declara o purpose canônico `magazine`, inclusive quando uma galeria
é alcançada por relação de curso. O Kernel do Index usa as entidades reais
`media_gallery` e Media e cobre backfill, relação `gallery_image`, atualização
e remoção (1 teste / 21 assertions). O harness não habilita o módulo owner
`aculta_gallery` completo nem substitui a homologação de acesso no Runtime.
