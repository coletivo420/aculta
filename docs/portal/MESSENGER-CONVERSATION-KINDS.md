# Tipos de conversas do ACULTA Messenger

**Substituído em 2026-10-24.** A proposta anterior foi preservada em [registro histórico](MESSENGER-CONVERSATION-KINDS-HISTORICAL.md). Chats por Fórum/curso/Wiki e entidades `ConversationContext`, `ConversationMembership` e `ParticipationLedger` foram descartados. Não implementar tais componentes.

A regra atual está em [ARCHITECTURE.md](../../web/modules/custom/aculta_portal/modules/aculta_messenger/docs/ARCHITECTURE.md): conversas são privadas e voluntárias, individuais ou em grupos criados por membros. Seguir conteúdo gera somente avisos conforme assinatura e preferências; matrícula não cria conversa; conteúdos não recebem chat próprio.

As conversas atuais são classificadas pelo número de participantes em diretas ou grupos voluntários. Aplicam-se as políticas do Private Message, bloqueios, limite configurável e retenção do Messenger. Mudança de amizade/preferência não impede respostas em threads existentes.

Evidências de implementação e planejamento atual estão em [MESSENGER.md](MESSENGER.md) e no [roadmap do módulo](../../web/modules/custom/aculta_portal/modules/aculta_messenger/docs/ROADMAP.md).
