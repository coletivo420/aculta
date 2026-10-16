# ACULTA Image (`aculta_image`)

Sistema único de imagens do site ACULTA. Todo uso de imagem passa por ele: catálogo de finalidades, política de upload,
renderização, leitura de dimensões, exclusão controlada, ingestão de origem externa, auditoria, quarentena e
comparação visual.

Versão: 0.1.0-dev.6. Planejamento e histórico das fases: [`docs/PLANO.md`](docs/PLANO.md).
Estilos e pesquisa: [`docs/ESTILOS.md`](docs/ESTILOS.md). Importação do Growroom: [`docs/IMPORTACAO-GROWROOM.md`](docs/IMPORTACAO-GROWROOM.md).

## Serviços

| Serviço | Classe | Função |
| --- | --- | --- |
| `aculta_image.catalog` | `ImageCatalog` | Finalidades e estilos (`STYLES`, `PURPOSES`). Única fonte de nome de estilo. |
| `aculta_image.upload_policy` | `UploadPolicy` | Extensões e tamanho máximo de upload. |
| `aculta_image.render` | `ImageRenderer` | `build()`, `styleId()`, `styleEntity()`, `displaySettings()`. |
| `aculta_image.inspector` | `ImageInspector` | Dimensões de arquivo ou de bytes. |
| `aculta_image.file_remover` | `FileRemover` | Exclusão que recusa arquivo com referência. |
| `aculta_image.ingester` | `ImageIngester` | Ingestão de imagem externa (SHA-256, reaproveitamento, origem). |
| `aculta_image.audit` | `ImageAudit` | Resumo, duplicatas, impressões digitais e candidatas. Somente leitura. |
| `aculta_image.quarantine` | `ImageQuarantine` | Envio, restauração e exclusão de vencidas à quarentena. |

## Rotas e permissões

- `/painel-administrativo/imagens`: relatório (resumo, duplicatas exatas, pares visuais, candidatas).
- `/painel-administrativo/imagens/quarentena`: enviar candidatas, restaurar e excluir vencidas.
- Permissão: `administer aculta images`.

## Dependências

- Core: `file`, `image`, `media`.
- Contrib (com justificativa no plano): `entity_usage` 5.0.1, `media_duplicates` 2.0.4, `imageapi_optimize` 4.2.0,
  `imageapi_optimize_binaries` 1.2.0-beta1 (beta; decisão do responsável).
- Binários no sistema: ImageMagick 7, Ghostscript, optipng, jpegoptim, gifsicle e cwebp.

## Regras de negócio

- Quarentena: 30 dias; exclusão só depois do prazo e com nova verificação de uso.
- Comparação visual: dHash de 64 bits com GD, impressões calculadas no cron em lotes de 200; pares com até 6 bits de
  diferença aparecem para revisão. Imagens lisas e não lidas são excluídas dos pares.
- Ingestão: um arquivo por conteúdo (SHA-256); origem registrada por arquivo e URL.

## Gates e testes

- `php scripts/validate-aculta-image.php`: tolerância zero (R1 a R8). Deve passar antes de qualquer PR.
- Testes unitários: `ImageCatalogTest`, `ImageIngesterTest`, `DHashTest`, `QuarantineRulesTest`.
  `vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_image/tests/src/Unit`

## Atualizações de banco

- `aculta_image_update_10001`: `aculta_image_origin`.
- `aculta_image_update_10002`: `aculta_image_quarantine`.
- `aculta_image_update_10003`: `aculta_image_fingerprint`.
