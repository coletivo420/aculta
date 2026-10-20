# Relatórios, registros e alertas (modelo WP Mail SMTP)

Referência: WP Mail SMTP Pro 4.10.2 (pacote analisado em 2026-10-20). Este documento mapeia cada função do modelo para o
que existe no ACULTA Mailer. O roadmap do módulo (`ROADMAP.md`) traz as fases.

## Mapa de funções

| Função no WP Mail SMTP | ACULTA Mailer | Estado |
|---|---|---|
| Log de e-mails (data, assunto, estado, transporte, código) | `aculta_mail_log` (sem corpo, destinatário em HMAC) | Pronto |
| Ativar registro | `aculta_mailer.log:enabled`, página Registro | Pronto (desligado para de contar a cota) |
| Período de retenção (6 meses no WP) | `aculta_mailer.log:retention_days` (30, 90, 180, 365; padrão 90) | Pronto |
| Registrar conteúdo do e-mail | Não se aplica: política de privacidade (`LOGGING-PRIVACY.md`) | Indisponível por política |
| Salvar anexos | Não se aplica: os e-mails do sistema não têm anexos | Indisponível |
| Rastreamento de abertura e cliques | Fase F11, desligada por padrão | Pendente (decisão de consentimento) |
| Status de webhooks (assinatura no provedor) | Endpoint F10; sem provedor ativo | Pendente |
| Alertas de falha (`failed_email`, `failed_primary_email`, `failed_backup_email`) | `MailAlerts::failure()`, janela de 5 min, fila | Pronto (desligado até informar destinatários) |
| Alerta de bounce (`email_hard_bounced`) | `MailAlerts::hardBounce()`, chamado pelo monitor IMAP | Pronto (depende do IMAP) |
| Resumo semanal por e-mail (Email Reports) | `MailSummary`, cron, 7 dias contra os 7 anteriores | Pronto (desligado até informar destinatários) |
| Endereço de retorno / bounce | `aculta_mailer.delivery:bounce_address`, troca do envelope no evento de envio (`ReturnPathSubscriber`), só SMTP | Pronto no código; vazio no runtime: o Hostinger exige envelope da conta SMTP |
| Failover para transporte de contingência | `TransportFailover` (smtp → smtp2go) e cota diária | Pronto (cota depende do registro) |
| Limite de envio (Rate Limiting, janelas hora, dia, semana e mês) | `TransportQuota`: `aculta_mailer.policy:rate_limit.<transporte>.<janela>`; sinal do provedor e contagem do registro | Pronto (limite do Brevo a confirmar) |
| Detecção de erro de cota pelo texto (assinaturas de erro) | `QuotaSignatures` (padrões em inglês e português) e `QuotaSignal` | Pronto (padrões podem crescer com os casos observados) |
| Failover e alerta de falha do principal (`failed_primary_email`) | Alerta de cota (`MailAlerts::quotaReached`), com janela de 5 minutos | Pronto |
| Chave geral "não enviar" (Control) | Suspensão por tipo (`skip`); sem chave global | Parcial |
| Teste de e-mail | Teste de modelo pelo caminho real | Pronto |
| Verificação de domínio (Email Detective, SPF/DKIM/DMARC) | Diagnóstico sem checagem de DNS | Pendente |
| Reenvio de falha (Resend) | Não há conteúdo no registro; o reenvio precisa da fila ou de dado guardado | Pendente (decisão) |
| Exportar registro em CSV | Não implementado | Pendente |
| Painel de estatísticas (Dashboard) | Painel de contagens por período | Pronto (sem gráfico) |
| Verificação de entrega pela API do provedor (Recheck) | Não implementado | Pendente (depende do webhook do Brevo) |

## Limites por transporte (copiado do WP Mail SMTP)

- O WP conta os envios processados em cada janela e usa o menor saldo entre elas. Aqui, a contagem vem do registro, por transporte e janela (hora, dia, semana, mês).
- O WP segura o excedente na fila. Aqui, ao atingir o limite, a mensagem segue pelo transporte de contingência, e o registro mostra o transporte usado.
- Texto de cota no erro de envio ou no bounce marca o transporte como esgotado por 24 horas, e gera alerta. Cota da caixa do destinatário ("mailbox quota") não conta: é do destinatário.
- Cota de envio é verificada no envio seguinte; a mensagem que recebeu o erro segue o caminho normal de retry e failover.
- Limite do Brevo: 300 por dia, conforme a API da conta (plano gratuito, `sendLimit` 300). Os textos de cota do Brevo ainda não foram observados.

## Decisões

- **Alertas com janela de 5 minutos**, como o padrão do WP Mail SMTP: evita uma caixa cheia de alertas durante uma
  queda. O número de alertas suprimidos segue no próximo alerta.
- **Alertas e resumos passam pela fila**, para não atrasar a requisição do usuário. Alertas não geram alertas: não há
  laço.
- **Endereço de retorno só em SMTP.** No Brevo (API), o envelope vira o remetente visível (`sender` do payload), então
  o endereço de retorno não se aplica. As devoluções do Brevo chegam pelo webhook transacional (eventos *Hard Bounced* e
  *Soft Bounced*, cadastrados pela API), que é o próximo passo da F10.
- **Registro desligado** deixa de gravar, e a cota diária por transporte deixa de valer, porque a cota conta o registro.
  A tela avisa.
- **Retenção de 90 dias**, gravada no runtime (decisão do responsável). O WP usa 6 meses; o prazo é configurável em
  Registro, e aumentar o prazo não apaga nada.
- **Destinatários:** alertas e resumo vão a todos os administradores do site (papel `administrator`, conta ativa) e,
  opcionalmente, a uma lista manual.
- **Brevo e IMAP** têm interruptor na página Entrega. Desligar o Brevo desvia as mensagens para a contingência.

## Fontes

- WP Mail SMTP Pro 4.10.2: `src/Pro/Alerts`, `src/Reports/Emails/Summary.php`, `src/Pro/Emails/Logs`,
  `src/Pro/Emails/RateLimiting`, `src/Pro/Emails/Control`, `src/Admin/EmailSendingErrors`.
- Brevo, webhooks transacionais: <https://developers.brevo.com/docs/transactional-webhooks>.
- Symfony Mailer: o envelope usa o cabeçalho `Sender` quando existe (`vendor/symfony/mailer/DelayedEnvelope.php`).
