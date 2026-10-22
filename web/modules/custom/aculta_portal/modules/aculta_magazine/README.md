# ACULTA Magazine (`aculta_magazine`)

Submódulo editorial do ACULTA Portal. Drupal continua como fonte de verdade para
conteúdo, publicação e mídia.

## Documentação

- [Índice documental](docs/README.md)
- [Arquitetura normativa](docs/ARCHITECTURE.md)
- [Matriz de dependências](docs/DEPENDENCY-MATRIX.md)
- [Auditoria MAG 0.5-A](docs/AUDIT-MAG-0.5-A.md)
- [Contrato de integração Studio ↔ Magazine](docs/STUDIO-INTEGRATION-CONTRACT.md)
- [Migração `article`/`blog` → `revista`](docs/MIGRATION-ARTICLE-BLOG-TO-REVISTA.md)
- [CHANGELOG](CHANGELOG.md)

Para políticas editoriais transversais, consulte a
[documentação de módulos](../../../../../../docs/modules/EDITORIAL-MEDIA.md)
e o [mapa módulo–tema](../../../../../../docs/portal/MAPA-MODULO-TEMA.md).

## Administração editorial

O único bundle de publicação é `revista`. Os Nodes legados foram migrados com
preservação de NID, UUID, revisão e aliases; os bundles `article` e `blog` foram
aposentados. Publicações e comentários são organizados juntos em
`/painel-administrativo/conteudo/revista`. A View de comentários é renderizada
como bloco na mesma página e filtra por tipo, campo, entidade e bundles editoriais.
Não existe uma rota separada `/painel-administrativo/revista/comentarios`. A
colocação do bloco é configuração de site em Config Sync; a instalação do
módulo fornece as Views e o caminho editorial.

Comentários contextuais continuam como entidades Comment no Node `revista`.
`aculta_forum` fornece o contexto compartilhado de leitura, resposta e moderação;
essa integração não cria tópico público. Galerias são entidades `media_gallery`
propriedade de `aculta_gallery`, incorporadas no corpo editorial pelo CKEditor 5
e Entity Embed; imagens continuam sob a política de `aculta_image`.
