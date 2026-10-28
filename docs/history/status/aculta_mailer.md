# Histórico de status — aculta_mailer

Texto de status retirado de `docs/portal/EMAIL-TRANSPORT.md`; a fonte
canônica é `docs/operations/status/aculta_mailer.yml`.

> Status: **M0/M1/M2 e R3 concluídas; R4 alinha o template administrativo e registra a política atual do Webform.** Documento normativo da arquitetura de transporte.


## Snapshot removido de EMAIL-TRANSPORT.md

## 0. Estado atual do envio (2026-10-19)

Envio pela camada de envio E-Mails ACULTA. O **transporte padrão é a Hostinger** (`smtp`, `smtp.hostinger.com:465`), versionado em
`config/sync/mailer_transport.mailer_transport.smtp.yml` (sem credenciais) e apontado por `config/sync/mailer_transport.settings.yml`.
O SMTP2GO (`mail.smtp2go.com:465`, `config/sync/mailer_transport.mailer_transport.smtp2go.yml`) fica disponível para troca,
mas sua cota está esgotada.

Remetente: `naoresponda@aculta.org`, o mesmo em teste e em produção, que é também a conta SMTP da Hostinger. A Hostinger
aceita esse remetente: o teste de envio passa de `MAIL FROM` e falha apenas no destinatário de teste reservado (`example.invalid`).

**Credenciais.** Usuário e senha da Hostinger ficam no próprio transporte da camada de envio E-Mails ACULTA, no banco do Runtime de teste, e não
são versionados. O `config/sync` traz o transporte sem credenciais; o `config:status` aponta essa diferença, que é esperada.

**Incidente de 2026-10-18 a 2026-10-19 (resolvido).** Os envios falhavam com
`553 5.7.1 : Sender address rejected: not owned by user naoresponda@aculta.org`, porque o remetente de teste era
`naoresponda@toca.net.br`. Com o remetente igual à conta autenticada, a Hostinger aceita o envio.

**Regra.** A credencial de envio pode ficar no banco do Runtime (exceção aprovada em 2026-10-19 e registrada no `AGENTS.md`).
Nunca vai para `config/sync`, para o repositório ou para exportação. O gate do Portal (`transactionalMailReady()`) e os validadores
seguem o transporte padrão e verificam que a versão versionada não tem credenciais.

**Tradução da camada de envio E-Mails ACULTA (2026-10-19).** Os 85 textos do `symfony_mailer` 2.0.2 estão traduzidos para pt-br no
Runtime de teste (conferidos um a um contra o `.po` oficial). A tradução é estado do banco, não do repositório:
**Tradução da camada de envio E-Mails ACULTA (2026-10-19).** Os textos do `symfony_mailer` 2.0.2 que estão no catálogo oficial e os que
aparecem nas telas do módulo (formulários de transporte SMTP, DSN e Sendmail, substituição e política) estão em pt-br.
Os textos extras estão em `web/modules/custom/aculta_portal/translations/symfony_mailer.pt-br.po`, importados como
tradução customizada (`drush locale:import --type=customized --override=none pt-br <arquivo>`). Em cada ambiente, importar
o catálogo oficial com `drush locale:update` e depois o arquivo do projeto. Nenhum arquivo de contrib é editado.
Pendência: o prefixo "Warning:" da tela de substituição é texto fixo no código do módulo (`MailerOverrideController`, sem
`t()`); só pode ser traduzido por correção no módulo ou por sobrescrita de tema, decisão a tomar.

