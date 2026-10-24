# Registro de envios e privacidade (F7)

Status: implementado em 0.1.0-dev.4; reenvio com conteúdo cifrado em 0.1.0-dev.38 (`aculta_mailer_update_10004`); prazo de 24 horas para links de uso único em 0.1.0-dev.39 (`aculta_mailer_update_10005`).

## O que é registrado

Entidade `aculta_mail_log` (uma por mensagem, identificada pelo correlation ID):

| Campo | Conteúdo |
|---|---|
| `correlation_id` | UUID v4 aleatório ou UUID v5 estável da entrega lógica (também em `X-Aculta-Mail-ID`). |
| `type` | Tipo do e-mail (`account_created`, `achievement_earned`, ...). |
| `recipient_hash` | HMAC-SHA256 do endereço normalizado (minúsculas, sem espaços), com `hash_salt` do ambiente. |
| `langcode`, `transport`, `attempts` | Idioma, transporte usado e número da tentativa. |
| `status` | Estado (`sent`, `deferred`, `failed`). `sent` é "aceito pelo sistema de e-mail", não "entregue". |
| `error_code` | Código curto: `smtp_<código>` (ex.: `smtp_554`) ou `sem_codigo_<classe>`. |
| `error_message` | Texto completo da falha do transporte, como o servidor devolveu. Visível só no relatório do admin (ver abaixo). |
| `sent`, `created`, `changed` | Datas. |
| `payload_cipher` | Só nas mensagens com falha: conteúdo para reenvio, cifrado (destinatário, assunto, corpo renderizado e cabeçalhos). Apagado quando a mensagem é aceita, e junto com o registro pela retenção. Ver *Reenvio*. |

## O que nunca é registrado

- Corpo renderizado, assunto e contexto em claro (links de login, recuperação, confirmação). Para mensagens com falha, o conteúdo vai ao campo `payload_cipher`, cifrado (ver *Reenvio*).
- Tokens, URLs secretas e credenciais.
- O endereço de e-mail em claro.
- Mensagem genérica ao usuário: quem envia (cadastro, conquista) vê só a mensagem genérica da camada de envio E-Mails ACULTA.

## Relatório de falhas

- Página `/painel-administrativo/aculta/mail/falhas`: falhas dos últimos 30 dias com o texto completo.
- O texto do servidor pode conter o endereço do destinatário (ex.: `<pessoa@dominio>`). Por isso o relatório é restrito e o aviso aparece na página.
- O usuário final nunca vê este texto.

## Acesso

- Leitura: permissão `administer aculta_mailer` (restrita).
- Escrita: somente pelo módulo; o controle de acesso recusa criação por outros caminhos.
- Views: a entidade expõe `views_data` padrão, para uma listagem administrativa em fase própria (F8).

## Retenção

- `MailCronHooks` (via `MailLogRetention`) apaga, no cron, registros mais antigos que o prazo configurado (padrão 90 dias), em lotes de 500.
- O prazo é constante de código nesta versão. Configuração por ambiente fica para fase própria.

## Fila: dados em trânsito

A fila `aculta_mailer_send` guarda destinatário e contexto até o envio, sem corpo renderizado. Itens são apagados ao
serem processados. Quando o envio falha de forma permanente ou esgota as tentativas, o item é removido e só o registro
permanece. Pendência: a fila não tem prazo próprio de expiração; itens presos ficam até o worker processá-los.

## Limites

- `sent` não prova entrega. Entrega depende de webhooks de provedor (F10).
- Uma correlação v5 repetida não é enviada novamente quando este registro ativo mostra o mesmo tipo, destinatário HMAC e estado `sent`. Chaves devem ser HMACadas com `hash_salt` e não podem conter texto pessoal. A proteção não opera com logging desligado e não cobre a falha simultânea da aceitação externa e da gravação local.
- Aberturas e cliques não são registrados (F11, opt-in).

## Reenvio (0.1.0-dev.38)

- Mensagem com falha guarda o conteúdo para reenvio em `payload_cipher`: destinatário, assunto, corpo renderizado, HTML e cabeçalhos. Anexos não entram (os e-mails do sistema não têm anexos).
- Cifra: `sodium_crypto_secretbox` (XSalsa20 e Poly1305), nonce aleatório por mensagem, chave `MAILER_PAYLOAD_KEY` (32 bytes, arquivo de segredos). Sem chave válida, nada é guardado e o reenvio fica indisponível: falha fechada.
- Mensagem aceita não guarda conteúdo: o campo é apagado no envio que dá certo, inclusive no reenvio.
- Retenção: o conteúdo cifrado sai com o registro, pelo prazo de retenção (padrão 90 dias).
- Reenvio: página da falha, link "Reenviar" (permissão `administer aculta_mailer`, confirmação com token CSRF). Respeita a chave geral de envio e a lista de supressão. A nova tentativa atualiza o próprio registro.
- Conteúdo de e-mail com link de uso único, guardado para reenvio, não contém link: cada URL vira `[link removido]`. O conteúdo sai do registro quando o envio é aceito, quando o provedor confirma a entrega, a devolução ou a reclamação, e quando expira.
- E-mails com link de uso único (`SingleUseLinkMail`: confirmação de e-mail, conta já existente, recuperação de senha, acesso de cadastro e confirmação do `email_confirmer`) têm prazo de 24 horas para o conteúdo guardado. Se não forem enviados nesse prazo, o cron (`MailLogRetention::purgeExpiredPayloads`) apaga o conteúdo cifrado; o registro continua, sem conteúdo. Os demais e-mails seguem a retenção do registro (90 dias).
- Chave `MAILER_PAYLOAD_KEY` criada e verificada no Runtime de teste (32 bytes). Sem ela, nada é guardado.

## Rastreamento (opcional, 0.1.0-dev.46)

- Desligado por padrão. Só rastreia destinatário com consentimento no acordo `aculta_rastreamento`.
- Campos: `opens`, `clicks`, `first_opened`, `first_clicked`. Nenhum IP, user agent ou dado de navegador.
- Limite: clientes que pré-carregam imagens (como o proxy de imagens do Gmail) geram aberturas sem leitura; a contagem de aberturas é superestimada nesses casos.
