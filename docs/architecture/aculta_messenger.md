# Arquitetura do aculta_messenger

`aculta_messenger` centraliza mensagens e avisos. Os módulos de domínio são
donos dos eventos e dos dados de origem; os adapters do Messenger revalidam
acesso ao consumir e exibir cada aviso.

## Decisões vigentes

- **2026-10-17 — Avisos e contagens não são apresentados a visitantes anônimos.**
  Situação: vigente. Motivo: mensagens e avisos são privados e vinculados a uma
  conta autenticada. Origem: `0.1.1-dev.45`, [histórico arquivado](../history/changelog/aculta_messenger.md).
  `MessengerBarHooks` omite o contador para anônimos e as rotas de consulta
  exigem conta.
- **2026-10-24 — Messenger é o núcleo de mensagens e avisos.** Situação:
  vigente. Origem: `0.1.1-dev.97`, [histórico arquivado](../history/changelog/aculta_messenger.md);
  decisão transversal em [ADR-018](../decisions/ADR-018-messenger-como-nucleo-de-mensagens.md).

## Fronteiras

- Drupal Message/Private Message são a persistência; não há inbox paralelo.
- `aculta_mailer` transporta e-mails; não recebe ownership de preferências ou
  avisos do site.
