# Arquitetura do aculta_pdf_viewer

`aculta_pdf_viewer` exibe arquivos PDF já pertencentes a File/Media e deixa a
autorização de arquivo sob Drupal Core e o domínio que detém a referência.

## Decisões vigentes

- **2026-10-17 — Arquivos privados usam a rota protegida do Core.**
  Situação: vigente. Motivo: a URL deve continuar verificando acesso do arquivo
  em cada requisição e respeitar a membership contextual. Origem:
  `0.1.0-dev.14`, [histórico arquivado](../history/changelog/aculta_pdf_viewer.md).
  O `PdfViewerBuilder` gera a URL pela rota `system.files` com o parâmetro
  `file`; `aculta_pdf_viewer_group` e o Kernel revalidam Group e membership.

## Fronteiras

- O módulo renderiza a visualização e gera links Core; não copia arquivos nem
  mantém ACL própria.
- O proprietário do Media/Group define a autorização contextual. A cada
  requisição ao arquivo, Core continua sendo a barreira final.
- Para decisões compartilhadas com o leitor de DOCX e planilhas, consulte
  [PDF-VIEWER-INTEGRATION.md](../portal/PDF-VIEWER-INTEGRATION.md).
