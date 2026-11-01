# ADR-019 — Política central de imagens no aculta_image

**Status:** vigente desde 2026-10-17.
**Escopo:** uploads e processamento de imagens nos módulos e no tema ACULTA.
**Origem:** `0.1.0-dev.1` e `0.1.0-dev.2`,
[histórico do aculta_image](../history/changelog/aculta_image.md).

## Contexto

Consumidores mantinham estilos, formatos e limites de imagem próprios. Isso
permitia divergência entre fluxos e duplicava processamento e apresentação.

## Decisão

1. `aculta_image` é o serviço compartilhado para catálogo de finalidades,
   validação, ingestão, inspeção, variantes, compressão e exclusão segura de
   imagens. Consumidores pedem finalidade e não implementam pipeline paralelo.
2. O `UploadPolicy` padrão aceita PNG, GIF, JPG, JPEG e WebP, com limite de 10
   MiB e sem limite padrão de dimensão. Uma política contextual pode usar apenas
   extensões do catálogo e definir explicitamente outro limite.
3. Drupal File/Media continuam donos do armazenamento. O consumidor define
   destino, vínculo ao conteúdo, autoria, acesso, retenção e apresentação.

## Consequências

- Novo campo ou formulário de imagem declara a política central e usa os
  validadores/serviços do `aculta_image`.
- Limpeza revalida uso e referências antes de remover arquivos; o consumidor
  não apaga entidades File diretamente.
- O contrato de upload de arquivos em geral permanece em
  [USER-UPLOADS.md](../portal/USER-UPLOADS.md).

## Verificação no código

`UploadPolicy` define extensões e limite padrão, restringe overrides ao
catálogo central e é injetado pelos consumidores; `ImageIngester` valida o
conteúdo real e `FileRemover` verifica uso antes da remoção.
