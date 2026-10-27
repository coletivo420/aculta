# Changelog — ACULTA Mail Confirmer User

## 0.1.0-dev.3 — documentar o contrato atual de acesso — 2026-10-26

- A descrição do submódulo foi corrigida: `user_login` registra confirmações no primeiro acesso ou em login por link de uso único; não bloqueia contas sem confirmação. O login é permitido e a política do Portal restringe as operações protegidas.
- O changelog histórico dev.1 documenta o comportamento do contrib de origem; essa frase não descrevia o contrato ACULTA já adotado pela `EMAIL-CONFIRMATION-POLICY.md`.
- Testes Kernel focalizados do Confirmador e troca de e-mail: 7/53 PASS no MariaDB dedicado; 1 depreciação em `token_requirements` de Core/contrib. Sem SMTP externo ou Runtime write.

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

- O texto “bloqueio de login” descreve o comportamento do módulo contrib original, não o comportamento adotado pelo ACULTA. O ACULTA permite login e restringe operações até confirmação; ver dev.3 e `EMAIL-CONFIRMATION-POLICY.md`.
- Caminho de cancelamento da troca: `/usuario/{user}/troca-de-email/cancelar`.
- Configuração: `aculta_mail_confirmer_user.settings` (user_email_change, user_login), versionada.
- Pendente: validação no navegador de confirmação e troca de e-mail; o teste não deve esperar bloqueio de login.
