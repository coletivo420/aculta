# Changelog — ACULTA Gallery

Submódulo opcional do `aculta_portal`. Linha 0.1.x em `-dev.N` até o RC.

## 0.1.0-dev.1 — modelagem e contratos de integração — 2026-10-14

Classificação: PATCH (documentação, sem código, sem dependência instalada).

- Novo `docs/ARQUITETURA.md`: avaliação de Media Gallery 3.3.0, PhotoSwipe 5.0.11, Entity Embed 1.7 e Media Library Bulk Upload 1.0.6 com evidência do código do tarball e do Core instalado (11.4.8); decisões D1–D12; limitações L1–L10; riscos e perguntas R1–R8; plano de fases e versões.
- Principais constatações:
  - Media Gallery traz entidade `media_gallery` própria (álbum e galeria). Não se cria entidade nova.
  - A entidade não tem revisões nem Content Moderation (publicação só por `status`).
  - A paginação do contrib é aplicada depois da renderização do campo inteiro; não atende a galeria grande sem Views paginada.
  - A rota canônica `/media_gallery/{id}` é em inglês e numérica; o alias em português é obrigatório.
  - O Core 11.4.8 já oferece upload múltiplo na Media Library; `media_library_bulk_upload` não entra por padrão.
- Nenhum arquivo de runtime. Nenhum `info.yml` ainda (entra na 0.1.0-dev.2).
- RUNTIME STATUS: não aplicável (sem código).
- Próxima fase: 0.1.0-dev.2 (fundação), após aceite das decisões R3, R4, R5 e R6.
