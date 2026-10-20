# Changelog — ACULTA Mail Confirmer

## 0.1.0-dev.1 — incorporação do email_confirmer (contrib) — 2026-10-20

- Código do contrib `drupal/email_confirmer` 1.0.0 incorporado como módulo próprio, com nomes do projeto
  (`aculta_mail_confirmer`, `AcultaMailConfirmer*`, `/aculta-mail-confirmer/`). Hooks em `src/Hook` com `#[Hook]`.
- Entidade `aculta_mail_confirmer` (mesmos campos e tabela `aculta_mail_confirmer`); os 28 pedidos do Runtime
  de teste foram copiados preservando `cid` e `uuid` (27 com o realm novo) e conferidos campo a campo.
- Textos da confirmação aprovados (pt-BR, prazo de 24 h).
- Desinstalado o contrib no Runtime de teste; removido do Composer (`drupal/email_confirmer`).
- Testes: Kernel do módulo 5/5 (pedido, texto aprovado, confirmação, cancelamento, expiração, reenvio na fila).
- Pendente: validação do fluxo no navegador (confirmação pelo link, troca de e-mail e login bloqueado).
