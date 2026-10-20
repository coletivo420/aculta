# Changelog — ACULTA Mailer

## 0.1.0-dev.1 — 2026-10-20

- F0: auditoria do e-mail atual (`docs/AUDIT.md`), pesquisa upstream e decisões de porte (`docs/UPSTREAM.md`),
  arquitetura (`docs/ARCHITECTURE.md`) e roadmap (`docs/ROADMAP.md`).
- F1: contratos de mensagem (`MailMessage`, `MailStatus`, `MessageIdFactory`), política de retry (`RetrySchedule`) e
  interface pública `AcultaMailerInterface`, com testes unitários. Não habilitado e sem efeito em runtime.
- Decisão de transporte registrada: padrão Hostinger com fallback SMTP2GO (`docs/ARCHITECTURE.md` §4.1; implementação na F2).
