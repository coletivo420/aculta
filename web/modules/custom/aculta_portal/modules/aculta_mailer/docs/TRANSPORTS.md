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


## Transporte Brevo e cota diária (2026-10-20)

- **Fábrica:** `BrevoSecretTransportFactory`, esquema `aculta-brevo://default`. A chave vem de `BREVO_API_KEY` pela interface `SecretProviderInterface` (implementada pelo aculta_portal). A DSN não tem segredo, então a configuração pode ser exportada.
- **Biblioteca:** `symfony/http-client` 7.4 instalado (autorizado em 2026-10-20).
- **Transporte `brevo`:** criado na configuração do Mailer Plus (plugin `dsn`, DSN `aculta-brevo://default`, sem segredo). Não é o padrão: só é usado quando escolhido.
- **Evidência:** envio real pela API do Brevo para o destinatário autorizado, aceito (`sent`), com transporte `brevo` no registro. Entrega na caixa de entrada depende de conferência do destinatário.
- **Cota diária:** `aculta_mailer.policy:quota` (ex.: `brevo: 200`). A cota é contada no nosso próprio registro, mensagens aceitas nas últimas 24 horas. Ao atingi-la, o envio segue pelo transporte de contingência, antes da falha.

## Comparação com o WP Mail SMTP Pro (código-fonte lido)

- **Falha → backup:** o Pro tenta a conexão de backup só depois que o envio primário falha, e envia alertas (`failed_email`, `failed_primary_email`, `failed_backup_email`).
- **Cota de provedor:** detectada por código de erro específico de cada provedor (ex.: SendLayer, HTTP 429, código 17). O plugin marca um aviso e não troca de conexão por antecipação.
- **Contagem diária:** não encontrei contador de envios por dia no código do Pro. O throttle existente limita as chamadas de diagnóstico, não o envio.
- **Logs:** tabela própria de e-mails enviados, com retenção configurável.
- **Nosso diferencial:** cota diária contada no registro e troca antecipada para a contingência. A detecção por código do provedor continua como complemento (a ser implementada com o código real do Brevo).

## Autenticação do domínio (2026-10-20)

- **Brevo:** `aculta.org` verificado e autenticado. DKIM `brevo1._domainkey` e `brevo2._domainkey` publicados (CNAME).
- **SPF:** sem alteração. SPF não é exigido pelo Brevo com IP compartilhado; `include` do Brevo só com IP dedicado.
- **DMARC:** `p=none`, relatórios agora para `dmarc@aculta.org`. A caixa precisa existir na Hostinger para receber os relatórios.
- **DNS:** zona `aculta.org` gerenciada na Cloudflare. Alterações feitas pela API da Cloudflare com token de DNS.
