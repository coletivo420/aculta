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
# Verificação técnica — 2026-10-29

- Gallery Unit: 2 testes / 13 assertions PASS.
- Kernel Content Index Gallery: 2 testes / 51 assertions PASS; 9 depreciações
  Core/contrib. O teste cobre magazine, controle de acesso e relações de imagem.
- PHP lint: 11 arquivos PASS.
- PHPCS Drupal: PASS sem erros ou avisos após correções de estilo e comentários
  de documentação; nenhuma mudança de comportamento foi intencional.
- Firefox ESR abriu o CKEditor e o diálogo com fixture válida; diálogo e álbuns
  autenticados passaram 15/15 checks em 1280/390/320 px com o CSS candidato do
  tema injetado. Repetir depois de servir o CSS e fazer cache rebuild; manter
  Dev até essa validação e a auditoria final.


## Revisão visual — 2026-10-29

- Firefox ESR autenticado abriu um rascunho da Revista com autor, categoria,
  imagem e campo de discussão válidos; o CKEditor e o diálogo "Criar galeria"
  apareceram com título, imagens, upload e ação. Nenhum formulário foi enviado.
- O diálogo e os álbuns públicos existentes no host principal e no Fórum
  passaram 15/15 verificações em 1280/390/320 px, sem overflow e com ações da
  top-bar visíveis. O teste injetou o reflow candidato do tema, que empilha as
  regiões da top-bar em uma coluna; o Runtime ainda usa a regra anterior.
- O rascunho e sua revisão temporários foram removidos, sem galeria criada. As
  capturas e o resumo ficam em `$SUITE_OUT/gallery-dialog-20261029-588` fora do
  repositório. Repetir com o CSS servido após cache rebuild antes de avaliar RC.
