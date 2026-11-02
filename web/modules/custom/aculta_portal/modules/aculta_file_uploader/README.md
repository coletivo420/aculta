# ACULTA File Uploader

Pipeline central de upload do Portal. Define políticas de imagem e documento,
verifica MIME pelo conteúdo, resolve destinos por token e grava arquivos pela
File API do Drupal. O módulo oferece políticas globais e restrições por
integração; consumidores preservam suas regras de acesso e seus owners.

## Serviços principais

| Serviço | Responsabilidade |
| --- | --- |
| `aculta_file_uploader.upload_policy` | Catálogo e limite globais. |
| `aculta_file_uploader.consumer_policy` | Política por integração, que pode herdar ou restringir a global. |
| `aculta_file_uploader.mime_verifier` | Confirma MIME e estrutura suportada pelo conteúdo do arquivo. |
| `aculta_file_uploader.destination_resolver` | Resolve diretórios com tokens autorizados. |
| `aculta_file_uploader.ingestor` | Ingestão de arquivos usando o handler do Drupal. |
| `aculta_file_uploader.focal_point_recorder` | Persiste pontos focais como crops suportados pelo `image_widget_crop`. |
| `aculta_file_uploader.recent_uploads` | Lista uploads do usuário por integração e valida ownership. |
| `aculta_file_uploader.cleanup` / `quarantine` | Limpeza e quarentena de arquivos segundo os contratos do Portal. |

## Consumidores

O painel de configuração mantém políticas para `wiki`, `forum`, `revista`,
`lms`, `galeria` e `usuario`. Os consumidores integrados consultam o serviço
`consumer_policy`; a política não amplia o catálogo global nem o limite
configurado.

## Configuração administrativa

`/painel-administrativo/aculta-portal/uploads` permite administrar a política
global e as exceções por consumidor. A rota exige `administer site
configuration` e pertence ao domínio principal. `aculta_file_uploader.settings`
é configuração operacional; não a adicione ao Config Sync sem revisar os
overrides ativos de todos os consumidores.

## Atualização e validação

`aculta_file_uploader_update_10001()` migra a configuração antiga
`aculta_portal.user_upload` sem descartar as regras por consumidor. O changelog
registra testes e gates de cada versão. A validação de upload real nos
consumidores, incluindo imagem de destaque, anexo/galeria e resposta de LMS,
continua pendente de homologação autenticada no Runtime.

Consulte [`CHANGELOG.md`](CHANGELOG.md) e
[`docs/STUDIO-INTEGRATION.md`](docs/STUDIO-INTEGRATION.md) para histórico e
integração de uploads no Studio.
