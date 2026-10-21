# Inventário de emissores de e-mail (F13)

Data: 2026-10-20. Base: busca no código do Core, dos contribs ativos e dos módulos ACULTA. Documentação apenas: nenhum
envio ou configuração foi alterado. Para migrar cada item, seguir a ordem da última coluna.

## Já migrados para o aculta_mailer

| Emissor | Chave | Observação |
|---|---|---|
| aculta_portal (cadastro com e-mail já usado) | `registration_existing_mail` | F12, DT-P06 |
| aculta_portal (pedido de confirmação) | `email_confirmation_request` | F9, texto em português |
| aculta_game (conquista) | `achievement_earned` | F12 |
| aculta_messenger (resumo de avisos) | `avisos_resumo` | F12 |

## Emissores ainda pelo Core ou por contribs

| Emissor | Chaves | Conteúdo sensível | Textos personalizados no projeto | Ordem |
|---|---|---|---|---|
| Core `user` (cadastro) | `register_admin_created`, `register_no_approval_required`, `register_pending_approval`, `register_pending_approval_admin` | Não | Não verificado | 2 |
| Core `user` (conta) | `status_activated`, `status_blocked`, `status_canceled` | Não | Não verificado | 2 |
| Core `user` (senha e cancelamento) | `password_reset`, `cancel_confirm` | **Sim**: link de uso único e de cancelamento | **Sim**: `config/sync/user.mail.yml` (texto em português) | 3 (por último, com revisão de segurança) |
| Core `contact` | `contact_page_mail`, `contact_page_copy`, `page_autoreply` | Dados do formulário | Não verificado | 4 |
| Core `update` | `update_status_notify` (admin) | Não | Não | 5 |
| Webform (`aculta_contact`) | envio do formulário | Dados do formulário | Ver `webform.webform.aculta_contact` | 4 (relacionado ao DT-P09) |
| Commerce (`commerce`) | recibos e notificações de pedido | Dados de pedido | Ver configuração do Commerce | 3 (fluxo de pagamento, com cuidado) |
| email_confirmer (contrib) | `notify_current` | Não | Não | 2 (com a F9 seguinte) |
| Consentimento de rastreamento (`aculta_user_agreement`) | `aculta_user_agreement_email_tracking` | Verificado via `AcultaUserAgreementTrackingConsent` | Ledger append-only / HMAC | — |
| message_notify (contrib) | notificador de e-mail | Conteúdo de avisos | Não verificado | 4 (com aculta_messenger) |

## Pontos que bloqueiam a migração sem cuidado

1. **Textos personalizados.** Migrar `user.mail` sem copiar os textos em português mudaria o que os usuários recebem.
2. **Links de uso único.** `password_reset` e `cancel_confirm` usam tokens. Pela regra do projeto, o token nunca vai para log
   nem para subject, e reenvio gera token novo, nunca reaproveita o antigo.
3. **Layout comum.** Todo e-mail da camada de envio E-Mails ACULTA já sai no envelope HTML do tema (`email-wrap`), inclusive os de Commerce e
   Webform. Migrar um emissor não muda o layout, mas muda o caminho de auditoria e o registro.
4. **Override do Core.** A camada de envio E-Mails ACULTA substitui o gerenciador de e-mail do Core. Mensagens do `user` passam por ele hoje,
   e a migração não pode quebrar esse fluxo para os demais módulos.

## Próximos passos sugeridos

- F13.1: migrar os emissores de conta de ordem 2 (cadastro e status), copiando o texto de `user.mail.yml` para os tipos.
- F13.2: `password_reset` e `cancel_confirm`, com revisão de segurança e teste de fluxo completo de recuperação de senha.
- F13.3: Commerce e Webform, em conjunto com a validação de pagamento e de DT-P09.
