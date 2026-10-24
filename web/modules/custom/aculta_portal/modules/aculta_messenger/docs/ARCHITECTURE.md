# Arquitetura do ACULTA Messenger

**Fonte normativa principal.** Revisão: 2026-10-24. O inventário de rotas e classes implementadas está em [MESSENGER.md](../../../../../../docs/portal/MESSENGER.md); o plano de entrega é [ROADMAP.md](ROADMAP.md).

## Decisão e limites

`aculta_messenger` é o ponto central de mensagens privadas, avisos no site e coordenação de e-mail: mantém consumidores/adapters, agregação, deduplicação, preferências, políticas de entrega, filas e apresentação segura. Os módulos proprietários publicam eventos e escolhem destinatários conforme suas regras. O `aculta_mailer` continua responsável pelo transporte SMTP/Mail, templates, remetente, cabeçalhos/rodapés, logs e métricas; o Messenger não duplica essa infraestrutura. Mensagens humanas continuam nas entidades e APIs do `private_message`; eventos persistentes usam Message/Message Notify, Flags e `aculta_aviso` nos casos em que esses storages já são a fonte apropriada. Site Alert mantém alertas temporários. Não há migração geral para nova entidade de notificação.

Seguir conteúdo expressa somente a intenção de receber avisos no site e, conforme políticas/preferências, por e-mail. Não cria conversa, não inclui seguidores em threads e não amplia acesso ao conteúdo. Fórum, Wiki, Revista, cursos e outros conteúdos não possuem chats exclusivos nem automáticos. Uma ação “Iniciar conversa” deve abrir o fluxo normal de mensagens privadas e aplicar todas as políticas compartilhadas.

## Ownership

| Componente | Responsabilidade |
| --- | --- |
| `aculta_messenger` | Núcleo central de mensagens privadas, avisos, consumers/adapters LMS/Wiki/Fórum/Loja/Doações, agregação, deduplicação, preferências e orquestração dos canais. |
| `aculta_user` | Identidade, contas, amizades, preferências de identidade e elegibilidade dos relacionamentos. |
| `aculta_mailer` | Transporte, templates, cabeçalhos/rodapés, remetente, logs e métricas de e-mail. |
| `aculta_forum`, `aculta_lms`, `aculta_wiki`, `aculta_commerce` | Conteúdo e workflows; publishers e seleção de destinatários segundo regras proprietárias. Não implementam consumers, armazenamento de avisos, filas de entrega ou transporte de e-mail. |
| `aculta_messenger` (integrações) | Persistência nos storages existentes, central, contagem, workers, abertura segura e regras uniformes de canal. Integrações opcionais só são registradas quando seu produtor está ativo. |
| Revista e demais módulos | Conteúdo e workflows próprios; publicam pelo contrato compartilhado quando houver caso real. |
| `aculta420` | SDC, Twig, CSS, comportamento visual, responsividade e acessibilidade; sem autorização, destinatários ou persistência. |

## Política comum de canais

Fórum, LMS, Loja, Wiki e Doações seguem o mesmo contrato: o aviso na central do site é
obrigatório; a pessoa pode escolher e-mail desativado, entrega direta ou resumo
/ digest, e administradores podem restringir tipos e canais pela política do
ACULTA Mailer. Opt-out de e-mail nunca remove o aviso do site. Preferências
aplicam-se por categoria/evento, e a entrega revalida política e acesso no
momento do envio. A sequência de integração é LMS → Wiki → Fórum → Loja → Doações, sem
duplicar o transporte nem manter fluxos de e-mail legados após migração.

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

A API pública `MessengerNoticePublisherInterface` despacha `MessengerNoticeEvent` para consumidores registrados no EventDispatcher do Drupal. O envelope leva tipo, módulo/origem, entidade e UUID, revisão/versão opaca, UID do destinatário, propósito, instante, chave idempotente e metadados flat limitados. A validação rejeita chaves de payload sensível, dados aninhados e campos excessivos. Nunca incluir corpo de mensagem privada, resposta/nota de avaliação, e-mail ou payload sensível. O produtor seleciona destinatários elegíveis; o consumidor revalida access antes de persistir/entregar/abrir e deduplica no storage existente. O envelope não define persistência. O Fórum já adapta Comments, ForumPosts e revisões de tópico; o LMS usa o mesmo contrato para os quatro eventos já integrados; a Doação publica pedido criado e cada pagamento aprovado separadamente. Os demais fluxos migram gradualmente após prova comparativa.

O Messenger revalida Entity Access, publicação, Domain/Purpose, membership e bloqueios no momento de entrega e ao abrir conteúdo. Deduplicação/retry não pode gerar segunda notificação. Opt-out de e-mail não remove aviso necessário no site. Falhas são registradas sem dados pessoais. Adapters opcionais ausentes não podem derrubar a central.

Rotas de membro pertencem ao purpose `account`; administração usa `main`. URLs cross-purpose passam por `DomainPurposeManager`. Métodos mutáveis não atravessam host. Twig, SDC e JavaScript recebem somente dados/render arrays já autorizados.

## Estado existente, objetivo e conclusão

**Existente (inventário estático):** Private Message, Message/Message Notify, Flags, Site Alert, `aculta_aviso`, preferências e workers atuais. Fórum, LMS, Wiki, Loja e Doações publicam eventos nos fluxos registrados; consumidores, workers, persistência/configuração Message e abertura segura pertencem ao núcleo Messenger. A Doação emite dois fatos distintos: pedido criado (que pode aguardar pagamento) e cada pagamento aprovado (incluindo cada cobrança recorrente). O pedido não é apresentado como recibo. Os antigos submódulos `*_messenger` não contêm mais código executável; manifests ocultos remanescentes servem apenas como identidade de compatibilidade até desinstalação operacional autorizada, sem manter fluxos legados.

**Objetivo:** API mínima comum e migração progressiva de produtores reais, mantendo storage e comportamento válidos; experiência integrada de avisos e conversas com permissões e preferências consistentes.

**Concluído nesta revisão:** decisão normativa unificada; contratos de chat contextual removidos do planejamento; validador restringe “só amigos” apenas à criação de conversa; adapters LMS, Wiki, Loja e Doações consolidados no núcleo Messenger e publishers mantidos nos módulos proprietários. Doação publica pedido criado e pagamento aprovado separadamente, uma vez por pagamento; o Messenger persiste ambos na entidade Message compartilhada, preserva o aviso obrigatório no site e permite e-mail off/direto/digest. O publisher/event envelope não cria storage paralelo. Migração aditiva de configuração instalada é provida por update; nenhuma operação foi aplicada no Runtime. Testes e demais fases só são considerados concluídos quando executados e registrados no roadmap/changelog. Homologação Runtime exige ambiente e autorização próprios.

## Histórico

ADRs e detalhes anteriores foram preservados em [ARCHITECTURE-HISTORY.md](ARCHITECTURE-HISTORY.md). Propostas antigas de `ConversationContext`, `ConversationMembership`, `ParticipationLedger`, chat automático de Fórum/curso/Wiki e relatórios baseados nesses chats estão **substituídas** por esta decisão. Preservam-se ADRs, changelogs e evidências como registro histórico; não devem orientar implementação futura.
