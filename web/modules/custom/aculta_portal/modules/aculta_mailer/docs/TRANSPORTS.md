# Transportes e failover (F2)

Status: concluída em 0.1.0-dev.9. Envio por transporte escolhido, código SMTP real, failover e diagnóstico (página
`.../envios-de-email/diagnostico`, sem usuário nem senha). Decisão do responsável (2026-10-20): padrão Hostinger, fallback SMTP2GO.

## Transportes

| ID do transporte | Servidor | Papel | Credencial |
|---|---|---|---|
| `smtp` | `smtp.hostinger.com:465` | Padrão (`mailer_transport.settings:default_transport`) | Senha no banco do Runtime (exceção de 2026-10-19), nunca em `config/sync` |
| `smtp2go` | `mail.smtp2go.com:465` | Fallback | Arquivo de segredos do servidor (`SMTP2GO_*`, `config/secrets-contract.json`) |

O ID é o nome da entidade `mailer_transport` do Mailer Plus. O `aculta_mailer` não define DSN nem senha.

## Como a mensagem escolhe o transporte

1. `AcultaMailer::deliver($message, $transport)` passa o ID em `params['transport']`.
2. `Hook\MailHooks` grava o cabeçalho `X-Transport` quando o ID é informado.
3. O `MultiTransport` do `mailer_transport` lê o cabeçalho, remove-o e envia pelo transporte indicado.
4. Sem cabeçalho, vale o padrão configurado.

## Código de resposta SMTP

- O Mailer Plus captura a exceção do transporte e devolve `FALSE`, sem o código.
- O Symfony Mailer dispara `FailedMessageEvent` no dispatcher do Drupal, com a exceção original (`UnexpectedResponseException`,
  cujo `getCode()` é o código SMTP).
- `Subscriber\MailFailureSubscriber` lê o código e o guarda por correlação (`SmtpFailureRegistry`, só na requisição).
- `AcultaMailer::deliver()` consome o código e o classifica com `SmtpReplyClassifier`.

## Classificação

| Resposta | Classe | Efeito |
|---|---|---|
| 5xx | `permanent` | Encerra em `failed`. Sem retry e sem troca de transporte. |
| 4xx | `temporary` | Reagenda com o atraso do `RetrySchedule`; troca de `smtp` para `smtp2go` no failover. |
| Sem código | `temporary` | Mesmo tratamento; o retry é limitado (5 tentativas). |
| Tipo não registrado | `permanent` | Não chega ao sistema de e-mail. |

## Failover

`TransportFailover('smtp', 'smtp2go')`: só erro temporário troca de transporte; falha no `smtp2go` não volta ao padrão.
A troca fica no item da fila (`transport`), e a tentativa seguinte usa esse transporte.

## Evidência de Runtime (2026-10-20)

Mensagens reais, para `destino@example.invalid` (domínio reservado, sem entrega), pelos dois transportes:

| Transporte | Resposta SMTP | Classe | Registro |
|---|---|---|---|
| `smtp` | 554 | permanent | `failed` / `smtp_554` |
| `smtp2go` | 550 | permanent | `failed` / `smtp_550` |

Os códigos diferentes confirmam que cada mensagem foi ao servidor pedido. Os dois registros de teste foram removidos.
O failover temporário foi validado no Kernel, com um transporte de teste que devolve 421
(`MailDeliveryKernelTest::testQueueFailsOverToFallbackAfterTemporaryFailure`). Não há teste real de 4xx no Runtime.

## Entrega confirmada

- 2026-10-20: envio de teste a um destinatário real (autorizado pelo responsável) foi aceito pelo Hostinger e
  recebido na caixa de entrada, com o assunto do modelo `registration_existing_mail`.

## Pendências

- Mensagem genérica do Mailer Plus aparece para o usuário (`Não foi possível enviar o email`), por padrão do contrib.
- Verificar em produção quando houver autorização (merge em `main` não publica).
