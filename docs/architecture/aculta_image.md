# Arquitetura do aculta_image

`aculta_image` é o módulo dono do catálogo e do processamento comum de imagens.
Os consumidores declaram uma finalidade e reutilizam os serviços do módulo; não
mantêm políticas de imagem paralelas.

## Decisões vigentes

- **2026-10-17 — Política central de imagem e limite padrão de 10 MiB.**
  Situação: vigente. Motivo: consumidores compartilham allowlist e validação,
  enquanto limites específicos permanecem explícitos e restritos ao contexto.
  Origem: `0.1.0-dev.1`, [histórico arquivado](../history/changelog/aculta_image.md).
  O `UploadPolicy` atual define PNG/GIF/JPG/JPEG/WebP, 10 MiB por padrão e
  nenhum limite de dimensão; overrides só aceitam extensões do catálogo e são
  explícitos. Contrato maior: [uploads do Portal](../portal/USER-UPLOADS.md).

## Fronteiras

- `aculta_image` é dono de inspeção, variantes, compressão, ingestão e remoção
  segura de imagens.
- Drupal File/Media continuam sendo os armazenamentos; o consumidor mantém
  finalidade, vínculo, autoria, acesso, retenção e apresentação.
- A política geral de arquivos de usuários pertence ao contrato do Portal;
  este módulo acrescenta as validações e o ciclo de vida específicos de imagem.
