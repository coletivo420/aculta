# ACULTA Magazine (`aculta_magazine`)

Submódulo editorial do ACULTA Portal. Drupal continua como fonte de verdade para
conteúdo, publicação e mídia.

## Documentação

- [Índice documental](docs/README.md)
- [CHANGELOG](CHANGELOG.md)

Para políticas editoriais transversais, consulte a
[documentação de módulos](../../../../../../docs/modules/EDITORIAL-MEDIA.md)
e o [mapa módulo–tema](../../../../../../docs/portal/MAPA-MODULO-TEMA.md).

## Administração editorial

O bundle canônico de publicação é `revista`. Durante a migração, os bundles
legados `article` e `blog` continuam reconhecidos para preservar o conteúdo e
as referências existentes. Os três e seus comentários são organizados juntos em
`/painel-administrativo/conteudo/revista`. A View de comentários é renderizada
como bloco na mesma página e filtra por tipo, campo, entidade e bundles editoriais.
Não existe uma rota separada `/painel-administrativo/revista/comentarios`. A
colocação do bloco é configuração de site em Config Sync; a instalação do
módulo fornece as Views e o caminho editorial.
