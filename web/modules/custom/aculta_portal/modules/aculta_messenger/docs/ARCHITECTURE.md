# Arquitetura do ACULTA Messenger

**Fonte normativa principal.** Revisão: 2026-10-24. O inventário de rotas e classes implementadas está em [MESSENGER.md](../../../../../../docs/portal/MESSENGER.md); o plano de entrega é [ROADMAP.md](ROADMAP.md).

## Decisão e limites

`aculta_messenger` é a infraestrutura unificada para avisos do site, notificações, assinaturas e conversas privadas voluntárias entre membros. Mensagens humanas continuam nas entidades e APIs do `private_message`; eventos persistentes usam Message/Message Notify, Flags e `aculta_aviso` nos casos em que esses storages já são a fonte apropriada. Site Alert mantém alertas temporários. Não há migração geral para nova entidade de notificação.

Seguir conteúdo expressa somente a intenção de receber avisos no site e, conforme políticas/preferências, por e-mail. Não cria conversa, não inclui seguidores em threads e não amplia acesso ao conteúdo. Fórum, Wiki, Revista, cursos e outros conteúdos não possuem chats exclusivos nem automáticos. Uma ação “Iniciar conversa” deve abrir o fluxo normal de mensagens privadas e aplicar todas as políticas compartilhadas.

## Ownership

| Componente | Responsabilidade |
| --- | --- |
| `aculta_messenger` | Contratos públicos, políticas compartilhadas de comunicação, agregação/deduplicação e integração aos mecanismos existentes. |
| `aculta_user` | Identidade, contas, amizades, preferências de identidade e elegibilidade dos relacionamentos. |
| `aculta_mailer` | Transporte, templates, cabeçalhos/rodapés, remetente, logs e métricas de e-mail. |
| `aculta_forum` | Conteúdo, moderação, assinaturas/eventos editoriais e elegibilidade dos destinatários do Fórum. |
| `aculta_lms_messenger` | Adapter dos eventos LMS, revalidação de permissões LMS e encaminhamento ao Messenger. |
| Wiki, Revista, Commerce e demais módulos | Conteúdo e workflows próprios; publicação de eventos por contratos comuns quando houver caso real. |
| `aculta420` | SDC, Twig, CSS, comportamento visual, responsividade e acessibilidade; sem autorização, destinatários ou persistência. |

## Fontes de verdade e armazenamento

| Dado | Fonte existente | Regra |
| --- | --- | --- |
| Threads, mensagens, participantes e leitura | Private Message | Preservar acesso por thread, bloqueios e estado nativo. |
| Amizades | `aculta_user` | Messenger consulta a API; não replica o grafo social. |
| Assinaturas | Flag/Message Subscribe ou mecanismo proprietário já adotado pelo produtor | Assinatura nunca concede acesso. A política F-D21 do Fórum permanece ativa; não reativar entrega duplicada de publicação via Message Subscribe. |
| Avisos por conteúdo do Fórum | `aculta_aviso` e contratos existentes | Manter histórico, agregação e marcadores; adaptar gradualmente. |
| Outros avisos persistentes | Message/Message Notify/Flag conforme o fluxo existente | Sem inbox paralela ou tabela de notificações redundante. |
| Alertas temporários | Site Alert | Agendamento e visibilidade seguem o mecanismo nativo. |
| E-mail | ACULTA Mailer / Drupal Mail | Produtores não enviam SMTP. Aplicar preferências, consentimento e template mínimo. |

## Contrato de evento e segurança

A API pública `MessengerNoticePublisherInterface` despacha `MessengerNoticeEvent` para consumidores registrados no EventDispatcher do Drupal. O envelope leva tipo, módulo/origem, entidade e UUID, revisão/versão opaca, UID do destinatário, propósito, instante, chave idempotente e metadados flat limitados. A validação rejeita chaves de payload sensível, dados aninhados e campos excessivos. Nunca incluir corpo de mensagem privada, resposta/nota de avaliação, e-mail ou payload sensível. O produtor seleciona destinatários elegíveis; o consumidor revalida access antes de persistir/entregar/abrir e deduplica no storage existente. O envelope não define persistência. Adapters atuais ainda precisam ser migrados gradualmente para este publisher.

O Messenger revalida Entity Access, publicação, Domain/Purpose, membership e bloqueios no momento de entrega e ao abrir conteúdo. Deduplicação/retry não pode gerar segunda notificação. Opt-out de e-mail não remove aviso necessário no site. Falhas são registradas sem dados pessoais. Adapters opcionais ausentes não podem derrubar a central.

Rotas de membro pertencem ao purpose `account`; administração usa `main`. URLs cross-purpose passam por `DomainPurposeManager`. Métodos mutáveis não atravessam host. Twig, SDC e JavaScript recebem somente dados/render arrays já autorizados.

## Estado existente, objetivo e conclusão

**Existente (inventário estático):** Private Message, Message/Message Notify, Flags, Site Alert, `aculta_aviso`, preferências e workers atuais; adapters de Fórum, amizades e LMS já produzem fluxos específicos. As evidências por fluxo estão em [MESSENGER.md](../../../../../../docs/portal/MESSENGER.md), [INTEGRATIONS.md](INTEGRATIONS.md), caracterizações locais e changelogs.

**Objetivo:** API mínima comum e migração progressiva de produtores reais, mantendo storage e comportamento válidos; experiência integrada de avisos e conversas com permissões e preferências consistentes.

**Concluído nesta revisão:** decisão normativa unificada; contratos de chat contextual removidos do planejamento; validador agora restringe “só amigos” somente à criação de conversa. O publisher/event envelope M2 foi adicionado sem storage; adapter LMS consome quatro tipos pelo contrato e mantém o Message/digest existente. Consumidores e integrações restantes continuam parciais. Testes e demais fases só são considerados concluídos quando executados e registrados no roadmap/changelog. Homologação Runtime exige ambiente e autorização próprios.

## Histórico

ADRs e detalhes anteriores foram preservados em [ARCHITECTURE-HISTORY.md](ARCHITECTURE-HISTORY.md). Propostas antigas de `ConversationContext`, `ConversationMembership`, `ParticipationLedger`, chat automático de Fórum/curso/Wiki e relatórios baseados nesses chats estão **substituídas** por esta decisão. Preservam-se ADRs, changelogs e evidências como registro histórico; não devem orientar implementação futura.
