# Arquitetura do aculta_document_viewer

`aculta_document_viewer` seleciona e apresenta documentos File/Media pelo
viewer compartilhado. O dono da entidade, do campo e do arquivo continua sendo
a autoridade de acesso.

## Decisões vigentes

- **2026-10-16 — Um mapa de bundle para campo de documento.** Situação: vigente.
  Motivo: builder e formatter devem aceitar o mesmo campo para cada bundle.
  Origem: `0.1.0-dev.12`, [histórico arquivado](../history/changelog/aculta_document_viewer.md).
  `DocumentFieldMap::fieldForBundle()` é usado pelo builder e pelo formatter.
- **2026-10-17 — Edição de Media editorial limitada à própria autoria.**
  Situação: vigente. Motivo: permitir corrigir Media recém-criada sem conceder
  edição sobre documentos de outras pessoas. Origem: `0.1.0-dev.20`, [histórico
  arquivado](../history/changelog/aculta_document_viewer.md). Config Sync concede
  `edit own document media` ao papel editorial, sem `edit any document media`.
- **2026-10-17 — Arquivos privados usam a rota protegida do Core.**
  Situação: vigente. Motivo: a URL deve continuar verificando acesso do arquivo
  em cada requisição e respeitar a membership contextual. O `PdfViewerBuilder`
  (`aculta_document_viewer.pdf_builder`), `DocxViewerBuilder`
  (`aculta_document_viewer.doc_builder`) e `SpreadsheetViewerBuilder`
  (`aculta_document_viewer.sheet_builder`) geram a URL pela rota `system.files`
  com o parâmetro `file`; `aculta_document_viewer_group` e o Kernel revalidam
  Group e membership.
- **2026-10-16 — Relação Group somente para documentos de curso.** Situação:
  vigente. Motivo: o vínculo de controle de acesso pertence a `lms_course`,
  sem instalar relações de Media em todos os tipos de Group.
  `MediaDocument` (`aculta_document_viewer_group`) usa `enforced: FALSE`, deriva
  somente `course_document` e a configuração sincronizada declara a relação em
  `lms_course`.
- **2026-10-29 — Sufixos canônicos por tipo e subabas administrativas.**
  Situação: vigente. Bibliotecas, serviços, FieldFormatters e seções do painel
  usam `aculta_document_viewer_pdf`, `aculta_document_viewer_doc` e
  `aculta_document_viewer_sheet`. O painel administrativo único fica em
  `/painel-administrativo/aculta/leitor-documentos`, com subabas horizontais
  nativas do Drupal (`Geral` para opções conjuntas do módulo, e `PDF`,
  `Documentos` e `Planilhas` para opções específicas de cada leitor).

## Fronteiras

- Media e File mantêm ownership, publicação e acesso; o viewer não duplica ACL.
- A entrega privada ocorre pela rota protegida do Core (`system.files`).
- A integração Group↔Media (`aculta_document_viewer_group`) é opcional e as
  regras de audiência pertencem ao módulo que mantém a relação contextual.
