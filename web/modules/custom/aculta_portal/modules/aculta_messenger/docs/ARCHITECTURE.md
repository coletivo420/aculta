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
/ digest. Administradores do Messenger restringem por categoria quais modos
direto e resumo podem ser escolhidos; o opt-out individual permanece sempre
disponível. ACULTA Mailer pode suspender um tipo ou transporte, mas não define a
preferência nem a cadência do aviso. Opt-out de e-mail nunca remove o aviso no
site. Preferências aplicam-se por categoria/evento, e a entrega revalida política e acesso no
momento do envio. A sequência de integração é LMS → Wiki → Fórum → Loja → Doações, sem
duplicar o transporte nem manter fluxos de e-mail legados após migração.

O catálogo de categorias de e-mail é fechado no Messenger: respostas e atualizações do
Fórum, `aprendizagem`, `wiki`, `loja` e `doacao`. Categoria não registrada retorna somente
opt-out (`off`) e não pode persistir preferência. Um produtor novo precisa entrar no
contrato e nos testes antes de poder solicitar envio de e-mail.

O digest combina as origens por destinatário sob lock. Sua correlação UUID v5 usa uma
chave HMACada com `hash_salt` e o manifesto de IDs/tipos/quantidades, sem incluir títulos
ou conteúdo. Após o Mailer aceitar o e-mail, o reconhecimento de todos os adapters e a
gravação do intervalo rodam numa transação da conexão padrão Drupal; uma falha reverte
todo o lote. O Mailer suprime retries cuja correlação, tipo e destinatário já constem como
aceitos no Mail Log ativo. Antes do envio, o Messenger persiste em `user.data` somente a
correlação, instante e IDs/contadores do lote. Se uma confirmação falhar após o aceite, o
retry consulta a correlação e confirma exatamente o manifesto salvo antes de processar
eventos novos; o snapshot não guarda destinatário nem conteúdo. Um `accepted_at` persistido
no snapshot registra a prova de aceite e permite concluir a recuperação mesmo após a
expiração normal do Mail Log; snapshots antigos recebem esse marcador quando o Mailer ainda
confirma o aceite. Se o adapter opcional estiver indisponível, o lote aceito permanece
retido até ele voltar, sem novo envio duplicado. A pequena janela entre aceite externo e a
persistência local da prova continua dependente da retenção do Mail Log e não cria
atomicidade entre o transporte externo e o banco Drupal.

E-mails diretos de avisos do Fórum, LMS, Wiki, Loja e Doações usam correlação UUID v5
determinística a partir da identidade da fonte e do tipo de e-mail. O retry mantém a mesma
correlação, permitindo que o ACULTA Mailer serialize chamadas concorrentes e suprima um
aceite já registrado. Durante a migração, o Messenger consulta o UUID v4 usado pelos
workers antigos e conclui como enviado quando encontra o aceite legado para o mesmo tipo e
destinatário. Essa compatibilidade depende da retenção configurada do Mail Log; o intervalo
entre aceite externo e persistência do estado da entidade continua não transacional.

Novos avisos por e-mail de Mensagem Privada também usam um tipo registrado no Mailer,
conteúdo genérico sem corpo da conversa e correlação UUID v5 por mensagem de origem e
destinatário. O evento de fila marca a entrega como gerenciada pelo Mailer; a entidade
Message guarda esse ownership para que replays legados não a processem pelo caminho antigo.
Itens previamente enfileirados sem o marcador continuam temporariamente em Message Notify,
pois o caminho antigo não deixou correlação verificável para distinguir aceite de falha.
Depois que a fila legada for drenada em ambiente operacional, a dependência e o caminho
Message Notify serão removidos em uma atualização posterior. Update 10008 instala o campo
aditivo; não foi aplicado no Runtime.

Pedidos e aceites de amizade seguem o mesmo ownership central: o `aculta_user` mantém
entidade e workflow, enquanto o Messenger persiste o aviso no bundle `Message` existente e
envia novos e-mails pelo tipo `friendship_notice` do ACULTA Mailer. O campo
`field_aculta_mailer_managed` tem nome compatível com o limite de campo do Drupal e separa
itens novos de fila dos itens antigos. Update 10009 instala o campo somente no bundle de
amizade. Consulta Drush somente leitura ao Runtime de teste em 2026-10-24 encontrou 128
itens pendentes nessa fila; nenhum item foi processado ou alterado, portanto o caminho
compatível deve permanecer até a drenagem operacional ser confirmada.

Os mesmos registros `aculta_msg_friendship` são adaptados pelo Messenger à central do site,
com origem **Amizades**, estado de leitura individual e contador integrado. Pedido e aceite
permanecem eventos de `aculta_user`; a apresentação na central não expõe nomes nem conteúdo
de terceiros. Avisos no site continuam obrigatórios independentemente da preferência de e-mail.

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

A API pública `MessengerNoticePublisherInterface` despacha `MessengerNoticeEvent` para consumidores registrados no EventDispatcher do Drupal. O envelope leva tipo, módulo/origem, entidade e UUID, revisão/versão opaca, UID do destinatário, propósito, instante, chave idempotente e metadados flat limitados. A validação rejeita chaves de payload sensível, dados aninhados, campos excessivos e caracteres de controle em UUID, revisão, chave idempotente e valores textuais de metadados. Nunca incluir corpo de mensagem privada, resposta/nota de avaliação, e-mail ou payload sensível. O produtor seleciona destinatários elegíveis; o consumidor revalida access antes de persistir/entregar/abrir e deduplica no storage existente. O envelope não define persistência. O Fórum já adapta Comments, ForumPosts e revisões de tópico; o LMS usa o mesmo contrato para os quatro eventos já integrados; a Doação publica pedido criado e cada pagamento aprovado separadamente. Os demais fluxos migram gradualmente após prova comparativa.

O Messenger revalida Entity Access, publicação, Domain/Purpose, membership e bloqueios no momento de entrega e ao abrir conteúdo. Deduplicação/retry não pode gerar segunda notificação. Opt-out de e-mail não remove aviso necessário no site. Falhas são registradas sem dados pessoais. Adapters opcionais ausentes não podem derrubar a central.

Rotas de membro pertencem ao purpose `account`; administração usa `main`. URLs cross-purpose passam por `DomainPurposeManager`. Métodos mutáveis não atravessam host. Twig, SDC e JavaScript recebem somente dados/render arrays já autorizados.

## Estado existente, objetivo e conclusão

**Existente (inventário estático):** Private Message, Message/Message Notify, Flags, Site Alert, `aculta_aviso`, preferências e workers atuais. Fórum, LMS, Wiki, Loja e Doações publicam eventos nos fluxos registrados; consumidores, workers, persistência/configuração Message e abertura segura pertencem ao núcleo Messenger. A Doação emite dois fatos distintos: pedido criado (que pode aguardar pagamento) e cada pagamento aprovado (incluindo cada cobrança recorrente). O pedido não é apresentado como recibo. Os antigos submódulos `*_messenger` não contêm mais código executável; manifests ocultos remanescentes servem apenas como identidade de compatibilidade até desinstalação operacional autorizada, sem manter fluxos legados.

**Objetivo:** API mínima comum e migração progressiva de produtores reais, mantendo storage e comportamento válidos; experiência integrada de avisos e conversas com permissões e preferências consistentes.

**Concluído nesta revisão:** decisão normativa unificada; contratos de chat contextual removidos do planejamento; validador restringe “só amigos” apenas à criação de conversa; adapters LMS, Wiki, Loja e Doações consolidados no núcleo Messenger e publishers mantidos nos módulos proprietários. Doação publica pedido criado e pagamento aprovado separadamente, uma vez por pagamento; o Messenger persiste ambos na entidade Message compartilhada, preserva o aviso obrigatório no site e permite e-mail off/direto/digest. O publisher/event envelope não cria storage paralelo. Migração aditiva de configuração instalada é provida por update; nenhuma operação foi aplicada no Runtime. Testes e demais fases só são considerados concluídos quando executados e registrados no roadmap/changelog. Homologação Runtime exige ambiente e autorização próprios.

## Histórico

ADRs e detalhes anteriores foram preservados em [ARCHITECTURE-HISTORY.md](ARCHITECTURE-HISTORY.md). Propostas antigas de `ConversationContext`, `ConversationMembership`, `ParticipationLedger`, chat automático de Fórum/curso/Wiki e relatórios baseados nesses chats estão **substituídas** por esta decisão. Preservam-se ADRs, changelogs e evidências como registro histórico; não devem orientar implementação futura.
