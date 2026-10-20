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
| `error_code` | Código curto: `smtp_<código>` (ex.: `smtp_554`) ou `sem_codigo_<classe>`. |
| `error_message` | Texto completo da falha do transporte, como o servidor devolveu. Visível só no relatório do admin (ver abaixo). |
| `sent`, `created`, `changed` | Datas. |

## O que nunca é registrado

- Corpo renderizado, assunto e contexto (links de login, recuperação, confirmação).
- Tokens, URLs secretas e credenciais.
- O endereço de e-mail em claro.
- Mensagem genérica ao usuário: quem envia (cadastro, conquista) vê só a mensagem genérica da camada de envio E-Mails ACULTA.

## Relatório de falhas

- Página `/painel-administrativo/configuracoes/aculta/envios-de-email/falhas`: falhas dos últimos 30 dias com o texto completo.
- O texto do servidor pode conter o endereço do destinatário (ex.: `<pessoa@dominio>`). Por isso o relatório é restrito e o aviso aparece na página.
- O usuário final nunca vê este texto.

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
