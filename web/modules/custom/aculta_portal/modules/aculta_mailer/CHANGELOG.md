# Changelog — ACULTA Mailer

## 0.1.0-dev.5 — painel de envios (F8) — 2026-10-20

- Painel `/painel-administrativo/configuracoes/aculta/envios-de-email` (permissão `administer aculta_mailer`): contagens de 1, 7 e 30 dias por estado, tipo e transporte.
- Só contagens: sem destinatário, sem conteúdo. Sem gráfico: tabelas; gráfico e agregação no banco ficam para depois.
- Limite: o painel carrega os registros do período para contar; em volume alto, trocar por agregação no banco.
- Mapa de rotas atualizado (`docs/architecture/ROUTES-SITEMAP.md`). Runtime de teste: rota e página renderizam.

## 0.1.0-dev.4 — registro de envios e retenção (F7) — 2026-10-20

- Entidade `aculta_mail_log` (uma por correlation ID): estado, tentativas, transporte, código de erro e destinatário como HMAC. Sem corpo, sem token e sem endereço em claro (`docs/LOGGING-PRIVACY.md`).
- `MailLogRecorder` grava o estado no envio direto e nas transições do worker.
- Permissão `administer aculta_mailer`; controle de acesso somente leitura.
- Retenção de 90 dias pelo cron, em lotes de 500.
- `aculta_mailer_update_10001` instala a tabela. Runtime de teste: aplicado com `drush updb -y`, sem outras pendências.

## 0.1.0-dev.3 — fila de envio com retry — 2026-10-20

- `MailQueue::enqueue()` coloca a mensagem na fila `aculta_mailer_send` (guarda destinatário e contexto, nunca o corpo).
- `MailSendWorker` envia pela API; falha reagenda pelo `DeliveryPlanner` com o atraso de `RetrySchedule`; tipo não registrado é descartado; tentativa com horário futuro volta para a fila sem envio.
- `MailMessage::fromArray()` e `toArray()`, validados de novo ao sair da fila.
- Limite conhecido: o envio ainda não informa o código SMTP, então toda falha é tratada como temporária; o fallback por transporte chega na F2.
- Testes: 56 unitários e Kernel; Kernel da fila com 3 testes (envio, descarte, horário futuro).

## 0.1.0-dev.2 — API de envio e registro de tipos — 2026-10-20

- `AcultaMailerInterface::send()` retorna `bool` (aceite do sistema de e-mail) e usa o `plugin.manager.mail` do Core.
- Tipos de e-mail são serviços com a tag `aculta_mailer.type` (`MailTypeInterface`), reunidos por `MailTypeRegistry`.
  Tipo não registrado não envia e registra erro sem destinatário nem conteúdo.
- `hook_mail` (`Hook\MailHooks`) renderiza assunto e corpo do tipo e grava o cabeçalho `X-Aculta-Mail-ID`.
- Testes Kernel (`AcultaMailerKernelTest`, módulo `aculta_mailer_test`): envio de tipo registrado com cabeçalho de
  correlação e recusa de tipo desconhecido. Runtime de teste: módulo habilitado, sem atualização de banco.

## 0.1.0-dev.1 — 2026-10-20

- F0: auditoria do e-mail atual (`docs/AUDIT.md`), pesquisa upstream e decisões de porte (`docs/UPSTREAM.md`),
  arquitetura (`docs/ARCHITECTURE.md`) e roadmap (`docs/ROADMAP.md`).
- F1: contratos de mensagem (`MailMessage`, `MailStatus`, `MessageIdFactory`), política de retry (`RetrySchedule`) e
  interface pública `AcultaMailerInterface`, com testes unitários. Não habilitado e sem efeito em runtime.
- Decisão de transporte registrada: padrão Hostinger com fallback SMTP2GO (`docs/ARCHITECTURE.md` §4.1; implementação na F2).
- F2 (parcial): `TransportFailover` (padrão Hostinger, fallback SMTP2GO, só para erro temporário) e `SmtpReplyClassifier` (4xx temporário, 5xx permanente).
- F6 (parcial): `DeliveryPlanner` combina retry e failover em `DeliveryDecision`. Sem worker de fila nesta versão.
- Contexto de `MailMessage`: passa a aceitar listas e linhas de escalares com até dois níveis; objetos e entidades continuam recusados (`docs/ARCHITECTURE.md` §4).
