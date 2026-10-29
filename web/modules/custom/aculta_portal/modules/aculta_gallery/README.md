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
`media_gallery`, Media e Domain, cobrindo backfill, relação `gallery_image`,
atualização, remoção e Entity Access no purpose `magazine`, com falha fechada
quando esse Domain falta (2 testes / 51 assertions; 9 depreciações em 2026-10-28). O harness não habilita o
módulo owner `aculta_gallery` completo nem substitui a homologação no Runtime.
# Verificação para RC — 2026-10-28

- Gallery Unit: 2 testes / 13 assertions PASS.
- Kernel Content Index Gallery: 2 testes / 51 assertions PASS; 9 depreciações
  Core/contrib. O teste cobre `magazine`, controle de acesso e relações de
  imagem.
- PHP lint: 11 arquivos PASS.
- PHPCS Drupal falha: 11 erros e 12 avisos em 6 arquivos PHP. A revisão
  interativa do diálogo e das galerias do Fórum no Firefox ESR/aparelho real
  continua pendente. Manter `Dev` até resolver os gates e concluir essa revisão.
