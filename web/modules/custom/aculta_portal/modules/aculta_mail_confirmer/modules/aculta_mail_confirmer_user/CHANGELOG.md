# Changelog — ACULTA Mail Confirmer User

## 0.1.0-dev.2 — hooks em classes com injeção; correção do guarda de recursão — 2026-10-20

- Hooks procedurais de `aculta_mail_confirmer_user.module` migrados para classes `#[Hook]` em `src/Hook`, com os
  serviços declarados em `aculta_mail_confirmer_user.services.yml`. O `.module` virou só cabeçalho.
- Correção: a confirmação da troca de e-mail gravava o guarda de recursão com `drupal_static($nome, TRUE)`, que não
  sobrescreve uma chave já inicializada por `user_presave` no mesmo pedido. Quando havia um `presave` antes da
  confirmação, a troca era reaberta e o e-mail antigo voltava. Agora o guarda é gravado por referência.
- `user_load_by_mail()` substituído por consulta de usuário com `loadByProperties()`; `$entity->original`
  substituído por `getOriginal()`.
- Formulário de cancelamento usa `cancelConfirmation()` do serviço.
- Teste Kernel `UserEmailChangeKernelTest` (2 testes): o e-mail só muda após a confirmação, e o cancelamento
  mantém o atual.

## 0.1.0-dev.1 — incorporação do email_confirmer_user — 2026-10-20

- Troca de e-mail e bloqueio de login de conta não confirmada, incorporados do contrib `email_confirmer_user`.
- Caminho de cancelamento da troca: `/usuario/{user}/troca-de-email/cancelar`.
- Configuração: `aculta_mail_confirmer_user.settings` (user_email_change, user_login), versionada.
- Pendente: validação no navegador (troca de e-mail e login bloqueado).
