# Roadmap do ACULTA Mailer

Status atualizado em 2026-10-20. Cada fase vira commit(s) atômico(s) na PR aberta; não há PR nova por fase.
Fases com `DEFERRED` dependem de Runtime de teste autorizado e não podem ser declaradas validadas sem evidência.

| Fase | Entrega | Estado |
|---|---|---|
| F0 | Auditoria ([AUDIT](AUDIT.md)), pesquisa ([UPSTREAM](UPSTREAM.md)), arquitetura ([ARCHITECTURE](ARCHITECTURE.md)) | Concluída nesta PR |
| F1 | Fundação: `MailMessage`, `MailStatus`, `MessageIdFactory`, `RetrySchedule`, contrato `AcultaMailerInterface`, testes unitários | Em andamento nesta PR |
| F2 | Transporte: failover Hostinger → SMTP2GO e classificação SMTP (`TransportFailover`, `SmtpReplyClassifier`) concluídos e testados; adaptador do Symfony Mailer por DSN, diagnóstico e fallback no envio real | Parcial (lógica pronta) |
| F3 | Templates: registro de tipos por tag (`MailTypeInterface`) concluído; Config Entity `aculta_mailer_template`, Twig, variáveis controladas, preview e envio de teste pendentes | Parcial (tipos prontos) |
| F4 | Branding: `MailBrand` e `MailLayout`, herança, logo via `aculta_image` | Pendente |
| F5 | Policy: adjusters por plugin, reutilização de `mailer_policy` até equivalência | Pendente |
| F6 | Queue: fila `aculta_mailer_send`, worker com retry e failover (`MailSendWorker`), decisão testada; eventos de fila e classificação SMTP real (código de resposta) pendentes | Parcial (fila pronta) |
| F7 | Logging: entidade de log, retenção, Views, reenvio | Pendente |
| F8 | Dashboard: métricas e gráficos (sem framework JS novo) | Pendente |
| F9 | Usuário: criação, confirmação, troca de e-mail, reset, integração com `aculta_user` | Pendente |
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
