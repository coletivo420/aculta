# Roadmap do ACULTA Mailer

Status atualizado em 2026-10-20. Cada fase vira commit(s) atômico(s) na PR aberta; não há PR nova por fase.
Fases com `DEFERRED` dependem de Runtime de teste autorizado e não podem ser declaradas validadas sem evidência.

| Fase | Entrega | Estado |
|---|---|---|
| F0 | Auditoria ([AUDIT](AUDIT.md)), pesquisa ([UPSTREAM](UPSTREAM.md)), arquitetura ([ARCHITECTURE](ARCHITECTURE.md)) | Concluída nesta PR |
| F1 | Fundação: `MailMessage`, `MailStatus`, `MessageIdFactory`, `RetrySchedule`, contrato `AcultaMailerInterface`, testes unitários | Em andamento nesta PR |
| F2 | Transporte: envio por transporte (`X-Transport`), código SMTP real, failover Hostinger → SMTP2GO, diagnóstico sem credenciais e validação com entrega confirmada pelo destinatário (`docs/TRANSPORTS.md`) | Concluída (2026-10-20) |
| F3 | Templates: tipos com variáveis declaradas; Config Entity `aculta_mailer_template` com editor, validação de placeholders, prévia e envio de teste pelo caminho real | Parcial (modelo e teste prontos; teste real aceito em 2026-10-20) |
| F4 | Branding: `aculta_mailer_brand` e `aculta_mailer_layout` com editores (texto); envelope HTML com identidade ACULTA no tema (`email-wrap`, logo do tema). Pendente: logo de marca via `aculta_image` e conferência visual pelo destinatário | Parcial (HTML no ar em teste) |
| F5 | Policy: hierarquia global → tipo, ajustes em plugins (`transport`, `reply_to`, `priority`, `skip`), suspensão sem retry (`docs/POLICIES.md`) | Parcial (hierarquia de global e tipo prontas; ajuste por instância e interface de edição pendentes) |
| F6 | Queue: fila `aculta_mailer_send`, worker com retry e failover (`MailSendWorker`), decisão testada; eventos de fila e classificação SMTP real (código de resposta) pendentes | Parcial (fila pronta) |
| F7 | Logging: entidade `aculta_mail_log` com texto completo da falha (relatório do admin), retenção de 90 dias e `views_data`; reenvio administrativo pendente | Parcial (registro e relatório prontos) |
| F8 | Dashboard: painel de contagens por período, estado, tipo e transporte concluído; gráficos e agregação no banco pendentes | Parcial (painel pronto) |
| F9 | Usuário: pedido de confirmação de e-mail enviado pelo aculta_mailer (token e entidade continuam no `email_confirmer`); troca de e-mail, reset e fila de reenvio ainda pelo contrib | Parcial (confirmação enviada pelo aculta_mailer) |
| F10 | Eventos de entrega: webhooks de provedor, bounce, complaint, supressão | Pendente |
| F11 | Tracking opcional (abertura e clique), desligado por padrão | Pendente |
| F12 | Integrações ACULTA: cadastro com e-mail já usado (DT-P06), conquista (`aculta_game`) e resumo de avisos (`aculta_messenger`) migrados; Forum, LMS, Magazine e Commerce pendentes | Parcial |
| F13 | Compatibilidade: interceptar e-mails remanescentes do Core | Pendente |
| F14 | Migração de configuração de Mailer Plus/Policy | Pendente |
| F15 | Retirada do contrib, somente com equivalência comprovada | Pendente |

## Runtime

- Runtime de teste (`aculta_runtime`): `aculta_mailer` habilitado (2026-10-20, sem updb). Consumidores migrados: cadastro com e-mail já usado, conquista e resumo de avisos. Confirmação de e-mail continua no `email_confirmer` (F9).
- Envio real pelo transporte não foi executado: os testes usam o coletor de e-mail do Core, sem SMTP.
- Validação de envio real (F2 em diante): **DEFERRED** até autorização explícita de ambiente de teste.

## Pendências de decisão

- Transporte padrão: **Hostinger** (`smtp`), com **fallback para SMTP2GO**, decisão do responsável (2026-10-20). A configuração versionada já aponta o padrão para `smtp`; o fallback é implementado na F2.
- Remetente: confirmar na F2 (ver `docs/portal/EMAIL-TRANSPORT.md` §4.2).
- Caminho administrativo: `/painel-administrativo/...`, conforme decisão do responsável.
- Local dos templates de layout: módulo, com estilos vindos do tema via dados e classes (decisão do responsável).
