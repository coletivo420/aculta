# Changelog — aculta_file_uploader

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
