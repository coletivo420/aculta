# Arquitetura do aculta_wiki

O módulo é dono das entidades Wiki, do workflow editorial, das propostas de
contribuição e das decisões editoriais. O Studio apresenta as tarefas
autorizadas; ele não substitui o armazenamento nem o controle de acesso da Wiki.

## Decisões vigentes

- **2026-10-23 — Decisões de proposta são privadas e revisionáveis.**
  Situação: vigente. Motivo: cada decisão editorial deve preservar o estado
  anterior e permitir detectar submissões concorrentes/obsoletas. Origem:
  `0.1.0-dev.18`, [histórico arquivado](../history/changelog/aculta_wiki.md).
  `WikiProposal` é uma entidade revisionável e `WikiProposalDecision` grava
  nova revisão sob permissão, estado e revisão-base verificados.
- **2026-10-22 — A moderação editorial ocorre no ACULTA Studio.**
  Situação: vigente. Motivo: a área do colaborador mostra suas contribuições e
  devolutivas, enquanto a fila de revisão permanece na experiência editorial.
  Origem: `0.1.0-dev.19`, [histórico arquivado](../history/changelog/aculta_wiki.md),
  commit de origem `cd163afb3` (2026-10-23). Contrato atual:
  [MARLEY-WIKI-MODERATION.md](../portal/MARLEY-WIKI-MODERATION.md) e
  [contrato de moderação compartilhada](ACULTA-SHARED-MODERATION-AND-KERNEL-POLICY.md).
  A fronteira de ownership Wiki–Studio é consolidada em
  [ADR-026](../decisions/ADR-026-ownership-moderacao-wiki-studio.md).

## Fronteiras

- Wiki mantém entidades, revisões, grants, propostas e transições editoriais.
- `aculta_user` pode prover política conjunta de participação; não recebe o
  armazenamento da Wiki.
- A decisão sobre quem modera é aplicada pelo access/workflow da Wiki e
  revalidada nos adapters do Studio.
