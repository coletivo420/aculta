# Incorporação do email_confirmer (contrib) ao código do projeto

Estado em 2026-10-20: **em andamento (Dev)**. O contrib continua habilitado, com o Runtime de teste inalterado.
Objetivo: remover a dependência de `drupal/email_confirmer` (e do `email_confirmer_user`) com código próprio do
aculta_portal, sem perder confirmações pendentes nem quebrar links já enviados.

## O que o contrib entrega e o Portal usa

| Peça do contrib | Uso no Portal | Observação |
|---|---|---|
| Entidade `email_confirmer_confirmation` (campos: cid, email, realm, ip, data, confirm_url, cancel_url, error_url, uid, private, status, sent, confirmed, created) | `EmailConfirmationEntity` herda dela; o Portal lê/grava realm, hash, e-mail, data do último envio e dados adicionais | Pedidos ativos ficam no banco do Runtime |
| `EmailConfirmerManager::createConfirmation()` | `EmailConfirmationRequester` | Único método do gerenciador usado |
| Rota `/email-confirmer/reply/{confirmation}/{hash}` (formulário de resposta: confirmar ou cancelar) | Links de confirmação já enviados por e-mail | **Os links são de uso único, válidos por 24 h**; trocar o caminho quebra os enviados |
| Rota `/email-confirmer/resend/{confirmation}` (reenvio) | Controlada pelo Portal (`EmailResendForm`) | Reenvio pede novo pedido, conforme `LINKS-DE-USO-UNICO.md` |
| Configuração `email_confirmer.settings` (hash_expiration 86400, confirmation_lifetime 604800, textos, URLs de retorno) | Portal usa `hash_expiration` (24 h) e a limpeza | Textos já saem pela camada (`EmailConfirmationRequestMailType`) |
| Cron e fila `DelayedRequestDispatcher` | Reenvio atrasado e limpeza de pedidos antigos | Precisa de equivalente |
| `email_confirmer_user`: opções `user_email_change` e `user_login` (`email_confirmer_user.settings`), rota `/user/{user}/email-change/cancel`, formulário de cancelamento e acesso a troca pendente | Troca de e-mail e bloqueio de login de conta não confirmada | Muda o comportamento de entrada: precisa ser replicado exatamente |

## Estado do Runtime de teste (2026-10-20, somente leitura)

- Entidade `email_confirmer_confirmation`: **28 registros**.
- `email_confirmer_user.settings`: chaves `user_email_change` e `user_login` presentes.
- Os 28 registros e os links enviados dependem da mesma entidade e do mesmo caminho de resposta.

## Caminho escolhido (proposta, pendente de confirmação)

1. Criar o módulo `aculta_email_confirmation` com entidade própria (`aculta_email_confirmation`), gerenciador, formulário
   de resposta, reenvio, cron e fila, espelhando os campos e estados do contrib.
2. **Manter o caminho `/email-confirmer/reply/...`** no novo módulo, para os links já enviados continuarem válidos.
3. Copiar os 28 registros para a tabela nova **preservando o cid e o hash**, sem apagar o original (migração não destrutiva).
4. Trocar as referências do Portal (entidade base, gerenciador, controle de acesso e testes unitários).
5. Refazer os testes: Unit (requester, policy, regenerator, login bloqueado, troca de e-mail), Kernel (entidade, expiração, reenvio)
   e navegador (cadastro, confirmação pelo link, cancelamento, troca de e-mail e login bloqueado).
6. **Somente depois**, com autorização explícita: desinstalar o contrib, apagar a tabela antiga e tirar do composer.

## Pendências de decisão

- Confirmar que o caminho `/email-confirmer/reply/...` continua por tempo indeterminado (proposta), ou se migra para um
  caminho em português com período de transição.
- Autorizar a remoção (passo 6) quando as etapas 1 a 5 estiverem validadas.
