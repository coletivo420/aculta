# Arquitetura do aculta_user

`aculta_user` mantém identidade, papéis, perfis e regras de participação. Os
módulos consumidores consultam esses contratos, sem criar hierarquias paralelas.

## Decisões vigentes

- **2026-10-28 — Lista administrativa mínima de pessoas (D-22).** Situação:
  vigente. A lista exibe somente nome de exibição, apelido e situação da conta;
  não inclui e-mail, documento ou endereço. Origem:
  [decisão D-22](../architecture/DECISOES-RESPONSAVEL-2026-10-28.md#d-22-pessoas-no-painel-administrativo-adm2).
- **2026-10-21 — Associado é situação da conta, não papel Drupal.** Situação:
  vigente. Motivo: adesão associativa é estado administrativo, não permissão
  herdável. Origem: `0.4.0-dev.239`, [histórico arquivado do Portal](../history/changelog/aculta_portal.md).
  A decisão transversal está em [ADR-023](../decisions/ADR-023-associado-como-situacao.md).
- **2026-10-20 — Fallback por inicial removido.**
  Situação: vigente conforme [architecture/avatar.md](avatar.md): o avatar
  selecionado ou o fallback estável do acervo são fornecidos pelo
  `AvatarResolver`; o tema não inventa placeholder, inicial ou campo paralelo.
  Origem: `0.4.0-dev.46`,
  [histórico arquivado de aculta_user](../history/changelog/aculta_user.md).

## Fronteiras

- `aculta_user` mantém a fonte de verdade dos perfis, papéis e participação.
- Fórum, LMS, Portal e tema consultam os contratos compartilhados; não persistem
  uma segunda situação de associado nem inferem status por papel.
