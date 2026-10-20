# Roadmap do ACULTA Mailer

Status atualizado em 2026-10-20. Cada fase vira commit(s) atômico(s) na PR aberta; não há PR nova por fase.
Fases com `DEFERRED` dependem de Runtime de teste autorizado e não podem ser declaradas validadas sem evidência.

| Fase | Entrega | Estado |
|---|---|---|
| F0 | Auditoria ([AUDIT](AUDIT.md)), pesquisa ([UPSTREAM](UPSTREAM.md)), arquitetura ([ARCHITECTURE](ARCHITECTURE.md)) | Concluída nesta PR |
| F1 | Fundação: `MailMessage`, `MailStatus`, `MessageIdFactory`, `RetrySchedule`, contrato `AcultaMailerInterface`, testes unitários | Em andamento nesta PR |
| F2 | Transporte: adaptador do Symfony Mailer por DSN, segredo fora do Config Sync, diagnóstico | Pendente |
| F3 | Templates: Config Entity `aculta_mailer_template`, Twig, variáveis controladas, preview, envio de teste | Pendente |
| F4 | Branding: `MailBrand` e `MailLayout`, herança, logo via `aculta_image` | Pendente |
| F5 | Policy: adjusters por plugin, reutilização de `mailer_policy` até equivalência | Pendente |
| F6 | Queue: worker de envio, retry, tratamento de falha, eventos | Pendente |
| F7 | Logging: entidade de log, retenção, Views, reenvio | Pendente |
| F8 | Dashboard: métricas e gráficos (sem framework JS novo) | Pendente |
| F9 | Usuário: criação, confirmação, troca de e-mail, reset, integração com `aculta_user` | Pendente |
| F10 | Eventos de entrega: webhooks de provedor, bounce, complaint, supressão | Pendente |
| F11 | Tracking opcional (abertura e clique), desligado por padrão | Pendente |
| F12 | Integrações ACULTA: Forum, Game, LMS, Magazine, Commerce | Pendente |
| F13 | Compatibilidade: interceptar e-mails remanescentes do Core | Pendente |
| F14 | Migração de configuração de Mailer Plus/Policy | Pendente |
| F15 | Retirada do contrib, somente com equivalência comprovada | Pendente |

## Runtime

- F1 não tem efeito em runtime: o módulo não é habilitado e não altera envio. **Runtime: não aplicável nesta fase.**
- Validação de envio real (F2 em diante): **DEFERRED** até autorização explícita de ambiente de teste.

## Pendências de decisão

- Remetente e transporte padrão: a documentação ainda diverge (Hostinger x SMTP2GO). Resolver antes de F2.
- Caminho administrativo: `/painel-administrativo/...`, conforme decisão do responsável.
- Local dos templates de layout: módulo, com estilos vindos do tema via dados e classes (decisão do responsável).
