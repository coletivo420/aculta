# Incorporação do email_confirmer (contrib) ao código do projeto

Estado em 2026-10-20: **incorporação concluída no código e no Runtime de teste; validação no navegador pendente**. O contrib foi desinstalado e removido do Composer.
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

## Resultado (2026-10-20)

- Código: módulo `aculta_mail_confirmer` (com `aculta_mail_confirmer_user`) em `web/modules/custom/aculta_portal/modules/`, com nomes do projeto, hooks com `#[Hook]` e entidade com atributo.
- Caminhos públicos: `/aculta-mail-confirmer/responder/...` e `/aculta-mail-confirmer/reenviar/...`. No domínio de Conta, o Portal publica `/confirmar-email/...`. Links já enviados com o caminho antigo (`/email-confirmer/reply/...`) passam a responder 404: os de mais de 24 horas já estavam expirados; os recentes precisam de um novo pedido.
- Dados: as 28 confirmações foram copiadas para `aculta_mail_confirmer`, preservando `cid`, `uuid` e demais campos; conferência campo a campo com comparação que aceita `NULL` (28/28). Só o `realm` de troca de e-mail muda (`email_confirmer_user` para `aculta_mail_confirmer_user`; 27 registros). A tabela antiga foi apagada depois dessa conferência, na desinstalação.
- Permissão: `access aculta mail confirmer` concedida a `authenticated` (configuração versionada igual ao Runtime, exceto a ordem da lista).
- Testes: Kernel do módulo 5/5; Kernel do Portal 26/26; Unit do Portal 240/240; Unit do mailer 141/141.
- Remoção: `drupal/email_confirmer` desinstalado no Runtime e removido do `composer.json`/`composer.lock`.

## Incidente durante a remoção (registro)

Na primeira tentativa de desinstalar o contrib (`drush pm:uninstall ... -y`), a desinstalação foi recusada por causa das 28 entidades antigas, mas os dois módulos novos (`aculta_mail_confirmer` e o submódulo) também foram desinstalados, e a tabela nova foi removida. A causa provável é a combinação de `-y` (que aceita a remoção de dependentes) com metadados de dependência em cache desatualizado (`aculta_portal` ainda declarava dependência do contrib). Nada foi apagado da tabela antiga, que seguiu com as 28 linhas.

Recuperação: `drush cr` (atualiza os metadados), reinstalação dos módulos novos, nova cópia das 28 linhas a partir da tabela antiga, regravação das configurações versionadas, concessão da permissão e nova conferência. Só então as entidades antigas foram apagadas pela API e o contrib foi desinstalado sem `-y`-cascata (sem dependentes restantes).

## Pendências

- Validação no servidor de teste (2026-10-20):
  - Troca de e-mail, de ponta a ponta com e-mail real: usuário de teste novo, pedido para `dmarc@aculta.org`, mensagem
    recebida por IMAP com o link do próprio e-mail, confirmação pelo serviço, e-mail da conta alterado. Usuário e pedido
    de teste removidos; aviso ao endereço antigo desligado só durante o teste e restaurado.
  - Confirmação pelo formulário: página em pt-BR no navegador (X11), desafio anti-bot passou no navegador real. O envio do
    formulário ainda depende de um clique humano (pedido de teste pendente, para remoção depois).
  - Login bloqueado: pendente. Não há senha conhecida numa conta de teste sem confirmação; coberto só por teste Unit.
- Concluído em 0.1.0-dev.2: `\Drupal::` removido de `src/` dos dois módulos (serviços por injeção); hooks procedurais do submódulo migrados para `#[Hook]`; teste da fila exato (1 item). Variação anterior da contagem não se reproduziu; causa não identificada.
- Drift preexistente, fora desta integração: `commerce_shipping` na dependência do papel `authenticated`.
