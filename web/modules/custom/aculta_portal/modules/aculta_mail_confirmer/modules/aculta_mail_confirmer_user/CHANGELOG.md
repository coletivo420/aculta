# Changelog — ACULTA Mail Confirmer User

## 0.1.0-dev.1 — incorporação do email_confirmer_user — 2026-10-20

- Troca de e-mail e bloqueio de login de conta não confirmada, incorporados do contrib `email_confirmer_user`.
- Caminho de cancelamento da troca: `/usuario/{user}/troca-de-email/cancelar`.
- Configuração: `aculta_mail_confirmer_user.settings` (user_email_change, user_login), versionada.
- Pendente: validação no navegador (troca de e-mail e login bloqueado).
