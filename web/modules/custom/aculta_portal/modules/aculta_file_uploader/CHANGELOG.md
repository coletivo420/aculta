# Changelog — aculta_file_uploader

## 0.1.0-dev.11 — motor da quarentena movido para o aculta_file_uploader

- `Quarantine\ImageQuarantine` sai do aculta_image. Serviço: `aculta_file_uploader.quarantine`. Tabela `aculta_image_quarantine` e regras de prazo inalteradas.
- Formulário e rotas da quarentena permanecem no aculta_image.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.10 — corrigir instalação e resolução de tokens

- A instalação detecta a tabela de quarentena já criada por `aculta_image` e
  não tenta recriá-la. Instalações novas ainda criam a tabela pela definição
  de schema.
- `DestinationResolver` passa dados e opções na assinatura Drupal 11 de
  `Token::replace()`, evitando `TypeError` antes da sanitização.
- Uploader Unit: 27 testes / 48 assertions PASS. No Runtime `toca`, o módulo
  foi habilitado sem recriar a tabela existente e `drush cr` passou; os serviços
  `aculta_file_uploader.ingestor` e `aculta_lms_h5p.editor_upload_manager`
  foram resolvidos pelo container. O envio real de arquivo H5P ainda não foi
  exercitado.

## 0.1.0-dev.9 — motor da fila de limpeza movido para o aculta_file_uploader

- `Cleanup\ImageCleanup` (fila `aculta_image_replaced_files`, `run()` e a limpeza de temporários) sai do aculta_image. Serviço: `aculta_file_uploader.cleanup`.
- Os hooks de entidade e o cron continuam no aculta_image, para não enfileirar sem o módulo de imagem. Teste Unit movido com o motor.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.8 — limpeza de File movida do aculta_portal, com contratos

- Movidos do aculta_portal: `Cleanup\UnusedFileCleaner`, `FileReferenceInspector` (e interface), `FileCleanupStatus`, `FileReferenceStatus` e `FileCleanupAssessorInterface`. Serviços: `aculta_file_uploader.unused_file_cleaner`, `aculta_file_uploader.file_reference_inspector`, `aculta_file_uploader.cleanup.logger` (mesmo canal `aculta_portal_cleanup`).
- Contrato `Cleanup\FileDeletionOwnerInterface`: quem apaga o arquivo. O aculta_image implementa por `FileRemoverInterface` e publica o alias `aculta_file_uploader.deletion_owner`.
- Dimensões de imagem lidas pelo `ImageFactory` do Core, sem `ImageInspector`. Sem dependência de aculta_image ou aculta_portal.

RUNTIME STATUS: DEFERRED. Validar no Runtime: limpeza de File e exclusão via alias.

## 0.1.0-dev.7 — schema da quarentena passa ao aculta_file_uploader

- `aculta_file_uploader.install` declara `hook_schema()` para `aculta_image_quarantine`. O nome da tabela não muda.
- Em instalações existentes a tabela já existe e não é recriada. Em instalações novas, ela é criada por este módulo.
- Ao desinstalar o aculta_file_uploader, a tabela será removida. Ao desinstalar o aculta_image, ela não é mais tocada.

RUNTIME STATUS: DEFERRED. Validar no Runtime antes do RC (update do aculta_image e instalação limpa).

## 0.1.0-dev.6 — motor da auditoria movido do aculta_image

- `Audit\ImageAudit` e `Visual\DHash` (com teste Unit) saem do aculta_image e passam para este módulo. Serviço: `aculta_file_uploader.audit`.
- A auditoria continua somente leitura. Rotas, abas, controller e painel `/painel-administrativo/imagens` seguem no aculta_image (decisão do responsável: só o motor migra).

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.5 — GeneratedImageFileManager movido do aculta_image (migração dos derivados)

- `GeneratedImageFileManager` e o serviço `aculta_file_uploader.generated_file` passam a viver aqui; `aculta_image.generated_file` sai do `aculta_image`.
- Dimensões são lidas pelo `ImageFactory` do Core, sem `ImageInspector`, para não criar dependência circular (o `aculta_image` já depende deste módulo).
- Dependência nova: `drupal:image`.
- Consumidores atualizados: `aculta_qrcode`, `aculta_document_viewer` e o teste Kernel do `aculta_index`.

RUNTIME STATUS: DEFERRED. Testes ainda não executados no Runtime nem com o `vendor/` completo.

## 0.1.0-dev.4 — migração da limpeza do aculta_image (passo 1: regras de quarentena)

- `Quarantine\QuarantineRules` movida do aculta_image (M7), com teste Unit.
- Passos seguintes da migração da limpeza: quarentena com tabela, fila de
  substituição, auditoria, derivados, hooks e cron.

## 0.1.0-dev.3 — Fases 1 (fechamento), 2 e 3 (parte pura e UI)

- Fase 1: testes Unit do `DestinationResolver` com `Token` simulado.
- Fase 2: elemento `aculta_upload_dropzone`, que estende o `managed_file` do Core, e drop zone em JavaScript nativo (`js/upload-dropzone.js`). Sem JavaScript, o campo comum continua.
- Fase 3: `FocalPoint` (matemática do recorte centrado, sem dependência do Drupal), testes Unit e editor de ponto focal em JavaScript (`js/focal-point.js`), operável por teclado.
- Pendente na Fase 3: gravação do ponto focal na Crop API e efeito de imagem. Dependem de validação no Runtime.
- Estilos: nenhuma folha de estilo no módulo. A apresentação fica no tema ACULTA420, que exige autorização.

Fases 4 (migração dos consumidores) e 5 (limpeza de órfãos) não foram iniciadas.

RUNTIME STATUS: DEFERRED. Testes PHPUnit, Kernel e comportamento no navegador ainda não executados.

## 0.1.0-dev.1 — Fase 1 (núcleo, sem consumidores migrados)

- `UploadPolicy`: catálogo central e limite do aculta_image, para que a migração não mude comportamento.
- `MimeTypeVerifier`: verificação de MIME pelo conteúdo (fileinfo), conceito portado do File Upload Secure Validator, sem dependência do módulo.
- `DestinationResolver`: destinos com tokens pelo serviço `Token` do Core, com sanitização por segmento e só `public://` e `private://`.
- `FileUploadIngestor`: ingestão única (política, conteúdo raster, MIME e destino) sobre `file.upload_handler`.
- Testes Unit de `UploadPolicy` e `MimeTypeVerifier`. Pendentes: testes do `DestinationResolver` e Kernel de ingestão.
- Nenhum consumidor migrado. `aculta_image` mantém a sua cópia até a Fase 4.

RUNTIME STATUS: DEFERRED. Testes ainda não executados no Runtime nem com o `vendor/` completo.
