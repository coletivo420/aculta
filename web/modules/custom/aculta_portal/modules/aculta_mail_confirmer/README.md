# ACULTA Mail Confirmer

Confirmação de endereço de e-mail do ACULTA. Código incorporado do módulo contrib `drupal/email_confirmer`
(GPL-2.0-or-later, ver `LICENSE.txt`), com nomes próprios para o projeto.

## O que faz

- Entidade `aculta_mail_confirmer` (pedidos de confirmação), com estados pendente, confirmado, cancelado e expirado.
- Prazo do link: 24 horas (`hash_expiration`), conforme `docs/portal/LINKS-DE-USO-UNICO.md`.
- Caminhos públicos do módulo: `/aculta-mail-confirmer/responder/{confirmação}/{hash}` (resposta) e
  `/aculta-mail-confirmer/reenviar/{confirmação}` (reenvio). No domínio de Conta, o Portal publica
  `/confirmar-email/...`.
- Configuração em `/painel-administrativo/aculta/confirmacao-de-email`.
- Tokens `[aculta-mail-confirmer:confirmation-url]` e `[aculta-mail-confirmer:confirmation-mail]`.
- Limpeza periódica (cron) dos pedidos mais antigos que `confirmation_lifetime`.

## Submódulo `aculta_mail_confirmer_user`

Troca de e-mail da conta e bloqueio de login de conta não confirmada (`user_email_change`, `user_login`).
Caminho de cancelamento: `/usuario/{user}/troca-de-email/cancelar`.

## Permissões

- `access aculta mail confirmer`: usar o serviço (concedida a `authenticated`).
- `administer email confirmations`: administrar todos os pedidos.

## Origem e licença

Ver `LICENSE.txt`. Código derivado do contrib `email_confirmer` 1.0.0, removido do Composer depois de
migrar os 28 pedidos do Runtime de teste (2026-10-20). Histórico e decisões: `docs/portal/EMAIL-CONFIRMER-INCORPORACAO.md`.
