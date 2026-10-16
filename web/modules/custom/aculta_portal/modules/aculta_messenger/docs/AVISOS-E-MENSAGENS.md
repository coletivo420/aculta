# Avisos e mensagens no ACULTA Messenger

Status: documento de referência, atualizado em 2026-10-16. Descreve o que o módulo faz hoje e o que está pendente.
Decisões de origem: F-D21 (política em `docs/portal/AVISOS-E-NOTIFICACOES.md`, no repositório).

## 1. Dois sistemas, dois destinos

Este módulo contém dois sistemas que **não se misturam**. Nenhuma notificação de aviso usa a caixa de mensagens, e
nenhuma conversa usa o aviso.

| | Mensagens | Avisos |
|---|---|---|
| Para que serve | Conversa entre pessoas | Comunicar algo ao sistema, à pessoa ou ao site |
| Onde aparece | Caixa de conversas (Private Message) | Barra do site, lista de avisos e contadores |
| Quem envia | Pessoas | O sistema (comunicados, respostas em tópico seguido) |
| Entidade | `private_message` (nativa) | Comunicados `aculta_notice` (nó); avisos por pessoa `aculta_aviso` (entidade própria) |
| E-mail | Pelo Message Notify, quando a pessoa ativa | Resumo diário opt-in, pela fila de envio do módulo (pendente) |
| Contadores | Não lidas por conversa (nativo) | `contador_site` por tópico; `MessengerCounts` |

Regra: uma resposta em tópico seguido é um **aviso**, não uma mensagem. A publicação de tópico também não gera mais
mensagem (ver seção 5).

## 2. Avisos por pessoa (`aculta_aviso`)

Um registro por **pessoa e por tópico** (tipo `forum_resposta`). Cada campo:

| Campo | Função |
|---|---|
| `uid` | Pessoa que recebe o aviso (dona do registro) |
| `tipo` | Tipo do aviso (agrupamento: tipo + assunto) |
| `assunto_nid` | Tópico (assunto) |
| `titulo` | Título do tópico na época do aviso |
| `contador_site` | Respostas novas não lidas. Zera quando a pessoa lê o tópico |
| `contador_email` | Respostas ainda não enviadas no resumo por e-mail. Zera pelo resumo |
| `ultimo_comentario` | Último comentário contado. Garante que cada comentário conta uma vez |

Regras (implementadas em `Aviso\AvisoService`):

- Quem segue o tópico (flag `seguir_topico`) recebe o aviso. Quem escreveu o comentário não recebe aviso dele.
- Só comentário **publicado** conta. Comentário criado não publicado conta quando for aprovado (`comment_update`).
- Um comentário nunca conta duas vezes: se o identificador não for maior que `ultimo_comentario`, nada muda.
- Ler o tópico (requisição à rota canônica do nó, usuário autenticado) zera `contador_site` daquele aviso.
- O aviso é visível só para a própria pessoa (`AvisoAccessControlHandler`). Administrar avisos exige
  `administer aculta avisos`.

## 3. Funções do módulo por camada

Todas as classes ficam em `src/`. O módulo não cria engine de mensagens nem transporte próprio.

**Mensagens (conversas e entrega de mensagens)**

- `Controller/MessagesController`, `NativePrivateMessageController`, `NewConversationController`: telas de conversa.
- `Form/NewConversationForm`, `PrivateMessageFormValidator`, `FriendRequestsForm`: formulários de conversa e pedidos.
- `Service/ConversationPolicy`, `ConversationMessagingState`: quem pode abrir conversa (privacidade e amizade).
- `Service/UnreadThreadCounter`: contagem de conversas não lidas.
- `Hook/PrivateMessage*Hooks`: formulários, respostas, retenção e apresentação das mensagens.
- `Plugin/QueueWorker/PrivateMessageNotificationWorker`: entrega, por e-mail, de mensagens privadas.
- `Mapper/MessengerPrivateMessageMapper`: mapeamento de dados para o tema.

**Avisos (comunicados, alertas e avisos por pessoa)**

- `Controller/NoticeAdminController`, `Support/NoticeType`, `Access/PersistentNoticeRouteAccess`: comunicados
  `aculta_notice` (segmentados por papel, com validade).
- `Service/MessengerCounts`: contagens de alertas e comunicados não lidos, lidas pela barra do site.
- `Hook/NoticeAdminHooks`, `EventSubscriber/NoticeAdminRouteSubscriber`: administração dos comunicados.
- `Entity/Aviso`, `Entity/AvisoAccessControlHandler`: entidade do aviso por pessoa.
- `Aviso/AvisoService`: regra de contagem, deduplicação e leitura dos avisos por pessoa.
- `Hook/ForumAvisoHooks`: `comment_insert` e `comment_update` chamam o serviço.
- `EventSubscriber/ForumAvisoReadSubscriber`: leitura do tópico zera o contador do site.

**Assinaturas e eventos**

- `Hook/MessageSubscribeHooks`: destinatários de assinatura. **Hoje retorna vazio para tópicos de fórum** (seção 5).
- `Hook/MessageSubscribeEventHooks`, `Plugin/QueueWorker/MessageSubscribeEventWorker`: fila do evento de publicação
  de tópico. Ainda existe; sua entrega por mensagem foi desligada.

**Integração com o Portal e o tema**

- `Hook/MessengerBarHooks`, `Hook/PrivacyFormHooks`, `Hook/ProfileOptionsHooks`, `Hook/SettingsTabsHooks`: barra,
  privacidade e abas de configuração.
- `EventSubscriber/PrivateMessageAccountRouteSubscriber`, `NativePrivateMessagePagesSubscriber`: rotas nativas.
- `Form/MessengerSettingsForm`, `CommunicationSettingsForm`, `MessagePrivacyForm`: configurações do módulo.
- `Support/ConfirmDialog`, `OptionsDisclosure`, `StorageDateTime`: suporte de interface e datas.

## 4. Permissões

- `administer aculta messenger`: limites, limpeza e grupos do chat.
- `administer aculta avisos`: administrar avisos por pessoa (sem conteúdo de mensagens).

## 5. Mudanças nesta etapa e pendências

**Feito (2026-10-16):**

- Avisos por pessoa (`aculta_aviso`), com contadores, deduplicação e leitura, conforme a seção 2.
- Respostas em tópico seguido viram aviso. Nenhuma mensagem é criada por isso.
- `MessageSubscribeHooks::getSubscribers` passou a retornar vazio. Motivo: a regra de F-D21 proíbe notificação de
  fórum como mensagem. Antes, a publicação de tópico seguido era entregue como mensagem.

**Pendente (F5.2):**

- Aviso de **atualização** de tópico seguido (antes entregue como mensagem) deve migrar para `aculta_aviso` com
  tipo próprio. Até lá, essas notificações não são entregues.
- Exibição do contador de avisos na barra e na lista de avisos da pessoa.
- Resumo diário por e-mail, só quando houve atualização (`contador_email` > 0), opt-in, pela fila do módulo.
- Testes de integração no Runtime de teste (já testado: contagem, deduplicação, aprovação, autor, leitura, nenhuma
  mensagem criada). Testes de página e de e-mail ainda não.
