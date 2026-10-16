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

Um registro por **pessoa, tipo e tópico**. Tipos: `forum_resposta` (comentário novo em tópico seguido) e
`forum_atualizacao` (revisão nova de tópico seguido). Cada campo:

| Campo | Função |
|---|---|
| `uid` | Pessoa que recebe o aviso (dona do registro) |
| `tipo` | Tipo do aviso (agrupamento: tipo + assunto) |
| `assunto_nid` | Tópico (assunto) |
| `titulo` | Título do tópico na época do aviso |
| `contador_site` | Respostas novas não lidas. Zera quando a pessoa lê o tópico |
| `contador_email` | Respostas ainda não enviadas no resumo por e-mail. Zera pelo resumo |
| `ultimo_comentario` | Último comentário contado (tipo resposta). Garante que cada comentário conta uma vez |
| `ultimo_evento` | Última revisão contada (tipo atualização). Garante que cada revisão conta uma vez |

Regras (implementadas em `Aviso\AvisoService`):

- Quem segue o tópico (flag `seguir_topico`) recebe o aviso. Quem escreveu o comentário não recebe aviso dele.
- Só comentário **publicado** conta. Comentário criado não publicado conta quando for aprovado (`comment_update`).
- Um comentário nunca conta duas vezes: se o identificador não for maior que `ultimo_comentario`, nada muda.
- Ler o tópico (requisição à rota canônica do nó, usuário autenticado) zera `contador_site` de **todos os tipos**
  daquele tópico.
- Atualização de tópico seguido (fila de publicação) cria aviso `forum_atualizacao`, sem mensagem.
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

## 5. Contador do ícone de avisos

O ícone de avisos da barra mostra: alertas do site + comunicados não lidos + **avisos por pessoa não lidos**
(`AvisoService::naoLidosTotal`). Cada entrada é um grupo tipo + assunto com contador > 0. O contador de conversas
(outro ícone) não inclui avisos.

## 6. Controles individuais (Minha conta > Privacidade, bloco "Avisos")

- Os avisos no site são **sempre exibidos**: não há opção para desligá-los.
- **Resumo diário por e-mail**: desligado por padrão. Quando ligado, dois sub-controles escolhem o que entra:
  respostas novas e atualizações de tópicos. Preferências em `user.data` (`aculta_messenger`).
- Desligar o resumo zera os contadores de e-mail pendentes, para não acumular um resumo antigo.

## 7. Resumo diário por e-mail

- Roda no cron (`AvisosResumoHooks::cron`) e chama `AvisosResumo::enviarResumos()`.
- Envia para contas **ativas, com e-mail e e-mail confirmado** (`EmailConfirmationPolicy`), com o resumo ligado.
- Um e-mail por conta por dia, no máximo (intervalo de 20 horas), e só quando há contador de e-mail > 0.
- Linhas agrupadas por tópico: tipo, título e quantidade. O contador de e-mail zera **somente após envio com sucesso**.
- Sistema de e-mail do Drupal, com o modelo `avisos_resumo` (`hook_mail`).

## 8. Funções do módulo, resumo dos novos componentes

- `src/Entity/Aviso.php`: entidade `aculta_aviso`. `AvisoAccessControlHandler`: a pessoa vê só os próprios avisos.
- `src/Aviso/AvisoService.php`: regras de contagem (`isNewComment`, `isNewRevision`), `registrarResposta`,
  `notificarAtualizacao`, `marcarLido`, `naoLidosTotal`.
- `src/Aviso/AvisoPreferencias.php`: preferências individuais de e-mail.
- `src/Aviso/AvisosResumo.php`: envio do resumo diário.
- `src/Form/AvisosPrivacyForm.php` + `src/Hook/AvisosPrivacyHooks.php`: bloco "Avisos" na privacidade da conta.
- `src/Hook/ForumAvisoHooks.php`: `comment_insert` e `comment_update` (aprovação de comentário).
- `src/EventSubscriber/ForumAvisoReadSubscriber.php`: leitura do tópico.
- `src/Plugin/QueueWorker/MessageSubscribeEventWorker.php`: atualização de tópico seguido vira aviso.
- `src/Hook/MessengerBarHooks.php`: soma os avisos por pessoa ao ícone de avisos.

## 8.1 Central "Avisos/Mensagens" em Minha conta

- Acesso à central: item **Avisos/Mensagens** na navegação de Minha conta (conta.aculta), com o contador de não lidos
  como badge (`AccountSectionsHooks`, pelos pontos `aculta_portal_account_sections` e `..._parent_sections`), e pelo link
  "Ver todos os avisos" da prévia. A barra multidomínio não mostra o rótulo textual: usa os ícones do aculta_messenger.
  A rota é `/avisos-mensagens` (`aculta_messenger.avisos_raiz`).
- Duas abas no padrão de Atividade e Meus Dados (`account-data-nav`/`account-data-link`), com a aba atual marcada:
  - **Avisos** (`/avisos-mensagens/avisos`): a **mesma lista da gaveta da barra** (`MessagesController::alertsBuild`):
    comunicados, avisos da pessoa e alertas do site, com os mesmos itens, links e ações de dispensa. Só lê os avisos da
    conta logada; a página não recebe parâmetro de usuário. Dentro da aba não há o link "Ver todos", porque a aba já é a
    lista completa; a gaveta da barra mantém o link (`alertsBuild(FALSE)` na aba).
  - **Mensagens** (`/avisos-mensagens/mensagens`): a **mesma lista de conversas da gaveta da barra**
    (`MessagesController::conversasBuild`), com o mesmo botão de nova conversa e os mesmos links. As conversas continuam
    no sistema de mensagens; a aba não cria nem altera conversas.
- Clique em um aviso ou em uma conversa dentro da central abre o item completo **no mesmo lugar**, como na gaveta da barra:
  a área das abas é um contêiner `data-aculta-drawer-url` com `data-aculta-drawer-content`, e o script da gaveta
  (`messenger-drawers.js`) busca o fragmento e o mostra ali. O botão Voltar retorna à lista da gaveta. A biblioteca do
  script é anexada pelos gatilhos da barra; o módulo não anexa biblioteca do tema.
- Lista de conversas na conta: caixa proporcional (largura máxima de 42 rem, linha compacta). A regra fica no tema, no
  atributo de comportamento `data-aculta-account-list`, e não vale para a gaveta da barra.
- Acesso: `access aculta portal`, no propósito `account`. Abas inexistentes respondem 404.
- A prévia da barra multidomínio mostra até 5 avisos da pessoa, seguidos do link **Ver todos os avisos**, que abre a
  aba Avisos. O contador do ícone de avisos soma esses avisos (seção 5).
- Visitante não vê a central (403).

## 8.2 Rótulo Lido / Não lido e retenção

Regra de apresentação e de vida útil, por tipo de aviso (decisão do responsável, 2026-10-16):

| Tipo | Rótulo Lido / Não lido | Regra temporal |
| --- | --- | --- |
| Alertas do site (`site_alert`, não persistentes) | sim: flag `aculta_site_alert_read`, marcado ao abrir o alerta | nenhuma |
| Avisos persistentes (`aculta_notice`) | não | nenhuma |
| Avisos do Fórum (`aculta_aviso`, respostas e atualizações) | sim: contador zerado ao ler o tópico | excluídos 6 meses após a criação (cron, `AvisoRetencaoHooks`) |
| Avisos de cursos (LMS) | sim, quando existirem | nenhuma |
| Conversas | sim, pelo estado de não lida da conversa | política de conversas (`ConversationPolicy`) |

- O rótulo fica à direita da caixa, em texto pequeno (`read-label` e `read-label-unread`), com fundo só atrás do texto.
- Avisos de cursos ainda não têm código neste repositório: quando existirem, devem usar o mesmo rótulo e não entram na exclusão.

## 9. Mudanças desta etapa e pendências

**Feito (2026-10-16):**

- Respostas e atualizações de tópico seguido viram avisos por pessoa, agrupados por tipo + assunto. Nenhuma
  mensagem é criada (conferido).
- Entrega de atualização por mensagem substituída por aviso (worker reescrito).
- Contador do ícone de avisos soma os avisos por pessoa.
- Bloco de controles individuais na privacidade da conta, e resumo diário por e-mail com as regras da seção 7.
- Testes unitários das regras de deduplicação e do intervalo. Testes no Runtime de teste: resposta, atualização
  (revisão repetida não conta), soma no ícone, leitura por todos os tipos, preferências, envio do resumo e zeragem
  ao desligar.

**Verificado com limite:**

- O envio real de e-mail não foi confirmado: o domínio de teste foi recusado pelo servidor SMTP (esperado). A
  entrega real precisa de teste com endereço real no Homelab.

**Pendente:**

- Contador de não lidos no item do menu da conta (o rótulo do menu é estático).
- Teste do worker de atualização com alias real (exige tópico com alias publicado).
- Limpeza do tipo antigo de mensagem `aculta_msg_forum_published` (configuração e registros antigos).
