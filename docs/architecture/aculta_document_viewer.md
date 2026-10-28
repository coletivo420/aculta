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

## Fronteiras

- Media e File mantêm ownership, publicação e acesso; o viewer não duplica ACL.
- A entrega privada ocorre pela rota protegida do Core, conforme
  [aculta_pdf_viewer.md](aculta_pdf_viewer.md).
- A integração Group↔Media é opcional e as regras de audiência pertencem ao
  módulo que mantém a relação contextual.
