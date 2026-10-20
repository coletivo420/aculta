# Changelog — ACULTA Mail Confirmer

## 0.1.0-dev.2 — serviços por injeção, sem `\Drupal::` em src/ — 2026-10-20

- Estado, envio, hash e transições (`getStatus`, `isExpired`, `isPending`, `getHash`, `sendRequest`,
  `confirmWithHash`, `cancelConfirmation`) saíram da entidade e foram para `AcultaMailConfirmerManager`, que
  recebe os serviços por injeção. A entidade guarda só os dados.
- `uid`, `ip` e `created` do pedido novo são preenchidos por `createConfirmation()`; os callbacks estáticos
  de valor padrão foram removidos.
- Formulário de resposta, controller e worker da fila recebem o serviço por injeção.
- Comparação do hash com `hash_equals()`. A chave de assinatura continua a mesma (`private_key`), então links
  já enviados seguem válidos.
- Teste Kernel `AcultaMailConfirmerKernelTest` atualizado para a nova API, com asserção exata da fila
  (1 item). A contagem variável citada na 0.1.0-dev.1 não se reproduziu nesta execução; a causa não foi
  identificada.
- Alias `Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface` para o serviço.
- Expiração: `now - created >= hash_expiration`, a mesma fronteira da consulta `pending` e da
  `SingleUseLinkPolicy` do Portal. A classe `EmailConfirmationEntity` do Portal, que sobrescrevia `isExpired()`,
  foi retirada; a regra de 24 h passa a valer pelo `hash_expiration` (86400, verificado pelo gate de links de uso único).
- Testes: 5/5 neste módulo.

## 0.1.0-dev.1 — incorporação do email_confirmer (contrib) — 2026-10-20

- Código do contrib `drupal/email_confirmer` 1.0.0 incorporado como módulo próprio, com nomes do projeto
  (`aculta_mail_confirmer`, `AcultaMailConfirmer*`, `/aculta-mail-confirmer/`). Hooks em `src/Hook` com `#[Hook]`.
- Entidade `aculta_mail_confirmer` (mesmos campos e tabela `aculta_mail_confirmer`); os 28 pedidos do Runtime
  de teste foram copiados preservando `cid` e `uuid` (27 com o realm novo) e conferidos campo a campo.
- Textos da confirmação aprovados (pt-BR, prazo de 24 h).
- Desinstalado o contrib no Runtime de teste; removido do Composer (`drupal/email_confirmer`).
- Testes: Kernel do módulo 5/5 (pedido, texto aprovado, confirmação, cancelamento, expiração, reenvio na fila).
- Pendente: validação do fluxo no navegador (confirmação pelo link, troca de e-mail e login bloqueado).
