# Arquitetura do aculta_user

`aculta_user` mantém identidade, papéis, perfis e regras de participação. Os
módulos consumidores consultam esses contratos, sem criar hierarquias paralelas.

## Decisões vigentes

- **2026-10-21 — Associado é situação da conta, não papel Drupal.** Situação:
  vigente. Motivo: adesão associativa é estado administrativo, não permissão
  herdável. Origem: `0.4.0-dev.239`, [histórico arquivado do Portal](../history/changelog/aculta_portal.md).
  A decisão transversal está em [ADR-023](../decisions/ADR-023-associado-como-situacao.md).
- **2026-10-20 — Fallback antigo de avatar por inicial foi substituído.**
  Situação: superada por [architecture/avatar.md](avatar.md): quando não há
  imagem válida, usa-se o placeholder do tema. Origem: `0.4.0-dev.46`,
  [histórico arquivado de aculta_user](../history/changelog/aculta_user.md).

## Fronteiras

- `aculta_user` mantém a fonte de verdade dos perfis, papéis e participação.
- Fórum, LMS, Portal e tema consultam os contratos compartilhados; não persistem
  uma segunda situação de associado nem inferem status por papel.
