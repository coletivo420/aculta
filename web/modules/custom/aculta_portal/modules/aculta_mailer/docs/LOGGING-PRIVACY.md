# Registro de envios e privacidade (F7)

Status: implementado em 0.1.0-dev.4. Runtime de teste: tabela criada por `aculta_mailer_update_10001` (2026-10-20).

## O que é registrado

Entidade `aculta_mail_log` (uma por mensagem, identificada pelo correlation ID):

| Campo | Conteúdo |
|---|---|
| `correlation_id` | UUID v4 da mensagem (também em `X-Aculta-Mail-ID`). |
| `type` | Tipo do e-mail (`account_created`, `achievement_earned`, ...). |
| `recipient_hash` | HMAC-SHA256 do endereço normalizado (minúsculas, sem espaços), com `hash_salt` do ambiente. |
| `langcode`, `transport`, `attempts` | Idioma, transporte usado e número da tentativa. |
| `status` | Estado (`sent`, `deferred`, `failed`). `sent` é "aceito pelo sistema de e-mail", não "entregue". |
| `error_code` | Código curto controlado pelo módulo: `send_refused`, `unregistered_type`, `retries_exhausted`. |
| `sent`, `created`, `changed` | Datas. |

## O que nunca é registrado

- Corpo renderizado, assunto e contexto (links de login, recuperação, confirmação).
- Tokens, URLs secretas e credenciais.
- O endereço de e-mail em claro.
- Texto livre de erro do provedor (o código é controlado pelo módulo).

## Acesso

- Leitura: permissão `administer aculta_mailer` (restrita).
- Escrita: somente pelo módulo; o controle de acesso recusa criação por outros caminhos.
- Views: a entidade expõe `views_data` padrão, para uma listagem administrativa em fase própria (F8).

## Retenção

- `MailLogRetentionHooks` apaga, no cron, registros com mais de 90 dias, em lotes de 500.
- O prazo é constante de código nesta versão. Configuração por ambiente fica para fase própria.

## Fila: dados em trânsito

A fila `aculta_mailer_send` guarda destinatário e contexto até o envio, sem corpo renderizado. Itens são apagados ao
serem processados. Quando o envio falha de forma permanente ou esgota as tentativas, o item é removido e só o registro
permanece. Pendência: a fila não tem prazo próprio de expiração; itens presos ficam até o worker processá-los.

## Limites

- `sent` não prova entrega. Entrega depende de webhooks de provedor (F10).
- Aberturas e cliques não são registrados (F11, opt-in).
