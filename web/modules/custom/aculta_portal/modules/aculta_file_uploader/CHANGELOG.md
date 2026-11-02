# Changelog — aculta_file_uploader

## 0.1.0-dev.26 — CSS do ponto focal por atributo

- `css/focal-point.css` seleciona `[data-aculta-focal]` e `[data-focal-marker]` em vez das classes.
- Unit uploader 47/104 PASS.

RUNTIME STATUS: DEFERRED. Mudança de marcação; visual não validado.

## 0.1.0-dev.25 — ponto focal atualiza o crop existente

- `FocalPointRecorder` atualiza o crop existente com `updateCropProperties` em vez de `updateCrop`. O `updateCrop` do image_widget_crop só atualiza crops ligados a um image style que usa o tipo; sem esse vínculo ele recriava o crop, duplicando recortes a cada gravação.
- Versão 0.1.0-dev.25.

RUNTIME STATUS: VALIDATED no Runtime `aculta_runtime` (2026-11-02), com os crop types importados:
`record(file, 25, 75)` criou `aculta_ponto_focal_16x9` (400x225) e `_1x1` (300x300);
`record(file, 80, 20)` atualizou os dois crops em vez de duplicar (2 crops no total).
Arquivo, crops e diretório de teste removidos depois.

## 0.1.0-dev.24 — wiring tolerante e fallback da política

- `aculta_file_uploader.deletion_owner` e `image_widget_crop.manager` passam a referências opcionais (`@?`): o módulo compila e é testável sem `aculta_image` ou `image_widget_crop` no container. Sem owner ou sem crop, a remoção e o ponto focal falham fechados.
- `ConsumerUploadPolicy::settings()` cai no catálogo central e no teto de 10 MiB quando a configuração está ausente ou vazia — inclusive depois de um `config:import` que apague `aculta_file_uploader.settings`.
- `FocalPointRecorderTest` usa um mock de `Image`; antes passava `NULL` ao construtor.
- Unit uploader 47/104 PASS.

RUNTIME STATUS: DEFERRED. Só wiring e testes; nada aplicado no Runtime.

## 0.1.0-dev.23 — estilo do editor de ponto focal

- Biblioteca focal-point ganha CSS: a caixa segue a imagem e o marcador fica sobre ela. Usa a variável `--aculta-focal-marker`, com fallback.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.22 — ponto focal gravado na Crop API (16:9 e 1:1)

- `FocalPointRecorder` grava o ponto focal como recortes 16:9 e 1:1 pelo image_widget_crop, com a mesma matemática de `FocalPoint`.
- Tipos de recorte `aculta_ponto_focal_16x9` e `aculta_ponto_focal_1x1` em config/sync (limites 0, sem recorte fixo).
- Dependências `crop` e `image_widget_crop` declaradas.
- Pendente: efeito nos estilos de imagem (todos os estilos) e validação no Runtime.

RUNTIME STATUS: DEFERRED.

# Changelog — aculta_file_uploader

## 0.1.0-dev.21 — limpeza de temporários vale para imagem e documento

- `ImageCleanup` apaga temporários sem uso (após o prazo do File API) também para documentos do catálogo (PDF, DOCX, XLS, XLSX), com a mesma regra das imagens.
- Envios do Studio sem uso seguem a mesma regra.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.20 — administradores veem os envios do Studio

- `RecentUploads::forUser()` e `isOwnedBy()` aceitam `$all` para administradores: veem e usam envios de todos os usuários, dentro da pasta da integração. Usuário comum continua só com os próprios.
- Teste Unit do caso de administrador.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.19 — envios recentes do Studio por integração e dono

- `RecentUploads` (`aculta_file_uploader.recent_uploads`): lista os envios do próprio usuário numa integração (`private://studio/envio/<integração>`) e confere se um arquivo é do dono e da pasta certa. Recusa caminho com `..` e nome de integração fora de letras minúsculas.
- Consulta com `accessCheck(FALSE)` declarado: o filtro por dono é explícito pela condição de uid.
- Teste Unit: `RecentUploadsTest`. A consulta ao banco não tem teste nesta entrega.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.18 — integrações galeria e usuário no painel

- `UploadSettingsForm::CONSUMERS` inclui `galeria` e `usuario`, para configurar cada uma no painel de uploads.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.17 — validação estrutural de docx e xlsx

- `MimeTypeVerifier::matches()` exige as entradas do pacote OOXML nos bytes lidos: docx precisa de `[Content_Types].xml` e `word/document.xml`; xlsx, de `[Content_Types].xml` e `xl/workbook.xml`. Um ZIP comum com extensão docx ou xlsx é recusado.
- Limitação: a verificação procura os nomes nos cabeçalhos locais do ZIP. Não decodifica o XML.
- Testes Unit: `testDocxNeedsWordPackageEntries` e `testXlsxNeedsWorkbookEntry`.

RUNTIME STATUS: DEFERRED. Teste não executado (sem vendor/ nem Runtime). Confirmar com um .docx e um .xlsx reais no Runtime.

## 0.1.0-dev.16 — correção da política por integração

- Testes: o teste de PDF agora remove o arquivo temporário (a alteração anterior tinha apagado a chamada) e o docblock de `testRejectsFileAboveLimit` voltou a ficar junto do método.
- `ConsumerUploadPolicy` valida dimensões de imagem pelo `ImageFactory` do Core, como a política antiga fazia. Novos: `restrictValidators()` e `acceptedExtensions()`, usados pelo LMS.

RUNTIME STATUS: DEFERRED. Sem PHPUnit executado (sem vendor/ nem Runtime); apenas `php -l`.

## 0.1.0-dev.15 — política por integração com documentos

- `ConsumerUploadPolicy` separa imagens (`imageExtensions`, `isValidImage`, `imageValidators`) e documentos (`documentExtensions`, `isValidDocument`, `documentValidators`). Ambas herdam ou restringem a política global e nunca ampliam o catálogo central.
- `UploadPolicy::documentValidators()` monta validadores de documento dentro de `DOCUMENT_EXTENSIONS`.
- Painel `/painel-administrativo/aculta-portal/uploads` oferece imagens e documentos por integração.
- Configuração padrão `config/install` passa a incluir pdf, docx, xls e xlsx, com a mesma paridade do painel antigo. `update_10001` não foi alterado.
- Testes Unit: `ConsumerUploadPolicyTest` cobre separação de catálogos e PDF com conteúdo compatível.

RUNTIME STATUS: DEFERRED. Testes PHPUnit ainda não executados (sem `vendor/` e sem Runtime); apenas `php -l`.

## 0.1.0-dev.14 — catálogo de documentos (pdf, docx, xls, xlsx)

- `UploadPolicy::DOCUMENT_EXTENSIONS` define os anexos. O catálogo de imagens (`EXTENSIONS`) e o limite de 10 MB não mudam; o aculta_image continua só com imagens.
- `MimeTypeVerifier` aceita pdf (application/pdf), docx e xlsx (OOXML ou zip) e xls (OLE). Zip é aceito para docx/xlsx: validação estrutural do pacote fica como pendência.
- Teste Unit: PDF com conteúdo compatível.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.13 — painel de uploads por integração

- Nova rota `aculta_file_uploader.settings` em `/painel-administrativo/aculta-portal/uploads`, só no domínio principal (`_admin_route`), com permissão `administer site configuration`.
- `UploadSettingsForm`: política global e uma aba por integração (wiki, fórum, revista, LMS). Herança ou restrição, nunca ampliação do catálogo nem do limite de 10 MB. Lista vazia é recusada.
- Link no menu administrativo do aculta_portal.
- O painel antigo (`/admin/config/aculta/uploads-usuarios`, `aculta_portal.user_upload`) permanece até fórum e LMS migrarem.

RUNTIME STATUS: DEFERRED. Validar no Runtime: rota, permissão, gate de rotas administrativas e salvamento.

## 0.1.0-dev.12 — política de imagem por integração (configuração do uploader)

- Nova configuração `aculta_file_uploader.settings` (padrão em `config/install`, schema próprio) com política global e `consumers.<integração>` (herança ou restrição).
- `ConsumerUploadPolicy` (`aculta_file_uploader.consumer_policy`): extensões de imagem restritas ao catálogo central, limite nunca acima de 10 MB e MIME pelo conteúdo. Lista vazia fecha a política.
- `aculta_file_uploader_update_10001()` copia `aculta_portal.user_upload` para a nova configuração sem apagar a original. Fórum e LMS continuam na política antiga até migrarem.
- Decisão: a configuração NÃO é exportada para `config/sync`. Uma importação de configuração sobrescreveria consumidores já migrados. Exportar só após o RC.
- Testes Unit: `ConsumerUploadPolicyTest`.

RUNTIME STATUS: DEFERRED. Validar no Runtime: update_10001 em base com `aculta_portal.user_upload` e instalação limpa.

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
