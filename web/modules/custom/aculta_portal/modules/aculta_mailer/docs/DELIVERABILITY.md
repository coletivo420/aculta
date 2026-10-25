# Entregabilidade: eventos de provedor (F10)

Status: camada neutra implementada em 0.1.0-dev.12. **Nenhum provedor está ativo.** A escolha do provedor e a chave de
assinatura são decisão do responsável, pendentes. Sem provedor registrado, o endpoint responde 404.

## O que a camada faz

1. `ProviderWebhookInterface`: cada provedor é um serviço com a tag `aculta_mailer.webhook_provider`. Ele valida a
   assinatura (`verify`) e normaliza o corpo em `ProviderEvent` (`parse`).
2. Rota `POST /aculta-mailer/webhook/{provider}` (técnica, sem sessão): sem provedor, 404; assinatura inválida, 403 (nada é
   lido nem gravado); corpo malformado, 400.
3. `DeliveryEventApplier`: encontra o registro pelo correlation ID (ou pelo ID do provedor) e aplica o estado do evento
   somente quando `MailStatus::canTransitionTo()` permite. Evento fora de ordem ou repetido não altera o estado.
4. Eventos: `delivered`, `deferred`, `bounced`, `complained` (`DeliveryEventType`). Código do provedor vai para
   `error_code` como `provedor_<código>`, sem texto livre.

## Regras de segurança

- A assinatura é validada antes de qualquer leitura de evento (comparação em tempo constante no provedor de teste).
- O endpoint não exige sessão, e a autenticação é só a assinatura do provedor.
- Segredos do provedor: arquivo de segredos do servidor (`aculta_portal.secrets_manager`), nunca em `config/sync` nem no
  código. O provedor real exige entrada no ACULTA Secrets Contract (ver `AGENTS.md`).
- Nada do corpo do evento é registrado em log; só tipo, correlação e código.

## Provedor de teste

`TestWebhookProvider` (somente no módulo de teste): assinatura por cabeçalho fixo, corpo JSON. Serve para os testes Kernel,
não para produção.

## Pendências

- Escolher o provedor (ex.: Amazon SES, Brevo, SendGrid, Mailgun, Postmark, Resend) e obter a chave de assinatura.
- Implementar `ProviderWebhookInterface` do provedor escolhido, com a validação dele.
- Configurar o endereço do webhook no painel do provedor, por ambiente (`aculta_deployer`, `environment_bound`).
- Supressão (`aculta_mail_suppression`) e aplicação antes do envio: não implementados nesta versão.
- Abertura e clique: fase F11, opt-in.
