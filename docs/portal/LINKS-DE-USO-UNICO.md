# Links de acesso único

Status: regra vigente desde 2026-10-20 (decisão do responsável). Substitui a validade infinita do link de confirmação
de e-mail, registrada antes em `EMAIL-CONFIRMATION-POLICY.md` §6.1.

## Regra

1. **Validade de 24 horas**, para todo link de acesso único do projeto, em todos os módulos. Depois de 24 horas, o link
   não funciona, mesmo que o token ainda exista.
2. **Pedido novo substitui o anterior.** Quando o usuário pede outro link (confirmação, recuperação, acesso), o link
   antigo deixa de valer e o conteúdo guardado para reenvio do pedido antigo é cancelado. O novo link é gerado no
   pedido novo, com conteúdo novo.
3. **Reenvio manual não reaproveita link de uso único.** O reenvio pela tela de falhas do `aculta_mailer` não aceita
   mensagens de link de uso único: o pedido precisa ser refeito.
4. **Exceção única: retentativa e failover automáticos da mesma mensagem.** A fila de envio e a troca de transporte
   (contingência) reenviam a mesma mensagem, com o mesmo conteúdo e o mesmo link, dentro das 24 horas. Não é um pedido
   novo: não cancela o conteúdo da própria mensagem e não gera link novo. Nenhum outro caminho automático pode reenviar
   link antigo.
5. **Conteúdo guardado para reenvio não contém link** (cada URL vira `[link removido]`) e sai do registro quando o
   envio é aceito, quando o provedor confirma a entrega, a devolução ou a reclamação, e quando o prazo de 24 horas
   vence.

## Inventário

| Link | Módulo | Mecanismo | Validade | Onde está aplicada |
|---|---|---|---|---|
| Confirmação de e-mail do cadastro | `aculta_portal` (realm `email_confirmer`) | `EmailConfirmationEntity::isExpired()` | 24 h | `src/Email/SingleUseLinkPolicy.php` |
| Troca de endereço de e-mail | `aculta_portal` (realm `email_confirmer_user`, mesma classe) | `EmailConfirmationEntity::isExpired()` | 24 h | `src/Email/SingleUseLinkPolicy.php` |
| Confirmação do `email_confirmer` | contrib `email_confirmer` | `hash_expiration` em `config/sync/email_confirmer.settings.yml` | 86400 s | gate |
| Recuperação de senha | Core `user` | `password_reset_timeout` em `config/sync/user.settings.yml` | 86400 s | gate |
| Login de uso único (cadastro pelo administrador, sem aprovação) | Core `user` | `password_reset_timeout` (mesmo prazo) | 86400 s | gate |
| Conta já existente (aviso no cadastro) | `aculta_portal` → `aculta_mailer` | tipo `registration_existing_mail` | 24 h (conteúdo) | `SingleUseLinkMail` |
| Confirmação de e-mail (mensagem) | `aculta_portal` → `aculta_mailer` | tipo `email_confirmation_request` | 24 h (conteúdo) | `SingleUseLinkMail` |

Os prazos de conteúdo guardado (coluna "Validade") valem para o reenvio de falha. O link em si segue a validade da
linha do Core ou do módulo.

## Como é garantido

- **Configuração versionada:** o gate `scripts/validate-single-use-links.php` falha se `password_reset_timeout` ou
  `hash_expiration` passar de 86400 segundos.
- **Código:** `SingleUseLinkPolicy::TTL` (Portal) e `SingleUseLinkMail::TTL` (aculta_mailer) têm o mesmo valor, conferido
  pelo gate. O aculta_mailer não importa a classe do Portal, porque o Portal depende dele.
- **Nenhum `isExpired()` do projeto pode devolver `FALSE` fixo.** O gate reprova a validade infinita.
- **Pedido novo cancela o anterior:** `AcultaMailer::deliver` chama `cancelPayloads` para o mesmo destinatário e tipo,
  excluindo a própria correlação (a exceção da retentativa).
- **Reenvio manual recusado:** `MailResend::canResend` não aceita mensagem de `SingleUseLinkMail`.
- **Conteúdo guardado com prazo:** `purgeExpiredPayloads` (cron) apaga o conteúdo vencido.

## Para quem altera

- Um e-mail novo com link de uso único entra em `SingleUseLinkMail::KEYS` e no inventário deste documento, no mesmo commit.
- Não aumente nenhum prazo acima de 24 horas. Se for preciso, a decisão muda aqui primeiro, com o responsável.
- Não reintroduza `isExpired()` com retorno fixo. Não reenvie link de uso único a partir de conteúdo antigo fora da
  exceção da seção 4.

Gate: `php scripts/validate-single-use-links.php` (tolerância zero).
