# Arquitetura do aculta_forum

`aculta_forum` mantém tópicos, posts revisionáveis, comentários, categorias e
moderação do Growroom420. LMS, Wiki, Revista e Messenger conservam os dados dos
seus próprios contextos e consomem adapters do Fórum quando aplicável.

## Decisões vigentes

- **2026-10-19 — Participação pública é governada pela permissão do papel
  `email_confirmed`.**
  Situação: vigente. Motivo: criar ou editar conteúdo público não deve contornar
  a confirmação real de e-mail nem a permissão contextual. Origem:
  `0.1.0-dev.6` e `0.1.0-dev.7`, [histórico arquivado](../history/changelog/aculta_forum.md).
  Config Sync concede `create aculta forum posts` a `email_confirmed`; os hooks
  exigem essa permissão e revalidam tópico e contexto ao enviar. A confirmação
  herdada depende de `EmailConfirmationPolicy`; a atribuição direta do papel
  pelo administrador continua sendo uma concessão explícita.
- **2026-10-19 — Conteúdo legado marcado é imutável e sai das filas de
  moderação.** Situação: vigente. Motivo: preservar o acervo curado sem
  convertê-lo em conteúdo novo moderável nem criar conta Drupal para o autor.
  Origem: `0.1.0-dev.8`, [histórico arquivado](../history/changelog/aculta_forum.md).
  `field_autor_legado` identifica o conteúdo e os hooks de fila/movimentação
  barram operações administrativas sobre ele. A identidade do autor é
  especificada em [DECISOES.md](../../web/modules/custom/aculta_portal/modules/aculta_forum/docs/DECISOES.md)
  e no contrato de [aculta_user](aculta_user.md).
- **2026-10-20 — Apagar o acervo legado do Runtime como estado final.** Situação:
  superada por F-D43 em [DECISOES.md](../../web/modules/custom/aculta_portal/modules/aculta_forum/docs/DECISOES.md).
  Origem: `0.1.0-dev.45`, [histórico arquivado](../history/changelog/aculta_forum.md).
  F-D43 define a preparação de reimportação curada como estado-alvo do Runtime;
  não autoriza operação de importação sem pipeline, prévia, backup e rollback.

## Fronteiras

- A autorização contextual de turma está em
  [ADR-025](../decisions/ADR-025-acesso-e-moderacao-forum-lms.md).
- Avisos no site e e-mail não criam conversas privadas nem concedem acesso;
  ownership e canais estão em [ADR-018](../decisions/ADR-018-messenger-como-nucleo-de-mensagens.md).
- A fila de revisão do Fórum é independente da moderação de propostas da Wiki.
