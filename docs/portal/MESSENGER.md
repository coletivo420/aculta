# ACULTA Messenger: documentação do projeto

Versões verificadas nesta revisão: `aculta_messenger` 0.1.1-dev.219, `aculta_portal` 0.4.1-dev.29, `aculta_user` 0.4.0-dev.73, tema `aculta420` 0.6.1-dev.12.
Auditoria de fronteiras: [`MESSENGER-AUDIT.md`](MESSENGER-AUDIT.md).
Guia do tema: [`web/themes/custom/aculta420/docs/messenger.md`](../../web/themes/custom/aculta420/docs/messenger.md).
Implementação, contratos e roadmap atuais do submódulo: `web/modules/custom/aculta_portal/modules/aculta_messenger/docs/`.

A decisão normativa atual é [`aculta_messenger/docs/ARCHITECTURE.md`](../../web/modules/custom/aculta_portal/modules/aculta_messenger/docs/ARCHITECTURE.md): `aculta_messenger` centraliza mensagens privadas, avisos e a orquestração de e-mail; Mailer mantém transporte/templates/logs, enquanto os módulos de domínio publicam eventos e escolhem destinatários. Seguir conteúdo gera avisos conforme acesso e preferências, nunca conversas ou participação em threads. Tipos de conversa contextuais anteriores foram substituídos e preservados no [registro histórico](MESSENGER-CONVERSATION-KINDS-HISTORICAL.md).

## 1. Visão geral

O Messenger é a caixa de mensagens privadas do site, aberta pela barra multidomínio como uma **gaveta** (offcanvas).
Nela o membro lê conversas, responde, inicia conversa com outro membro e usa a caixa "Opções" para amizade, limpeza de histórico e bloqueio.
A mesma caixa "Opções" aparece no perfil público `/u/…`.

Princípios:

- **Ownership compartilhado:** `private_message` mantém conversas, mensagens e bloqueios; `aculta_user` mantém amizades; Messenger coordena avisos, integrações, preferências e canais. As entidades `Message`, `aculta_aviso`, Flags e Site Alert continuam como storages existentes conforme cada fluxo. `message_notify` permanece temporariamente para itens legados de fila ainda não drenados; Message Subscribe não entrega avisos de tópicos do Fórum.
- Pedidos e aceites de amizade também aparecem na central de Avisos sob a origem **Amizades**. O estado lido/não lido fica no `Message` existente e é sempre independente da preferência de e-mail; o resumo não expõe nomes de terceiros.
- **Submódulo compõe a experiência:** regras de envio, validação, rotas da gaveta e contadores.
- **Tema apresenta:** SDC, CSS, JS de comportamento visual. Não tem regra de negócio.

## 2. Camadas

| Camada | Local | Responsabilidade |
| --- | --- | --- |
| Core/contrib | `private_message`, `message*`, `flag`, `site_alert` | Threads, mensagens, bloqueio (`private_message.ban_manager`), notificações, avisos, leitura. |
| `aculta_user` | `web/modules/custom/aculta_portal/modules/aculta_user/` | `FriendshipManager`, `aculta_friendship`, página `/u/`, hook `aculta_user_public_profile_actions`. |
| `aculta_messenger` | `web/modules/custom/aculta_portal/modules/aculta_messenger/` | Núcleo de avisos, notificações e conversas privadas; consumidores/adapters de LMS, Wiki, Fórum, Loja e Doações; preferências, entrega, central e contratos públicos. A integração Magazine está em inventário e não ativa consumer antes da definição da audiência. |
| `aculta420` | `web/themes/custom/aculta420/` | SDC `messenger-*`, `messenger-drawers.js`, `counters.js`, tokens `data-portal-ui`, CSS do painel. |

Dependências declaradas do submódulo incluem `aculta_portal:aculta_user`, `aculta_portal:aculta_mailer`, `aculta_portal:aculta_forum`, `private_message`, `message`, `message_notify`, `message_subscribe`, `flag` e `site_alert`.

## 3. Fluxos

### 3.1 Abrir a gaveta

1. A barra (`aculta420:messenger-trigger`) recebe `url` e `unread_count` do módulo (`MessengerBarHooks::preprocessPage`).
2. Ao abrir, o JS carrega `aculta_messenger.drawer_conversations` (rota `/mensagens/conversas/painel`).
3. A lista vem de `MessagesController::buildConversationList()`. Cada linha tem o botão "Opções" (`optionsDisclosure`).

### 3.2 Conversa

- `aculta_messenger.drawer_conversation` (`/mensagens/conversa/{thread}/painel`) mostra as mensagens, a barra "← Todas as conversas" e o "Opções".
- A resposta é enviada por `fetch` para `aculta_messenger.drawer_reply` (POST). O token vai em `reply_token`. A rota valida o token e as regras de envio (`PrivateMessageFormValidator`) e grava a mensagem.
- Quando não é possível responder, `MessagesController::conversationResponse` mostra um aviso no lugar da caixa de resposta (`mode=notice`).

### 3.3 "Opções" de uma conversa ou de um perfil

Um único formulário, `ConversationRelationshipForm`, é usado nos dois lugares:

| Ação | Quando aparece | Confirmação |
| --- | --- | --- |
| Adicionar amigo | sem relação | não |
| Cancelar pedido de amizade | pedido enviado por mim | não |
| Aceitar / Rejeitar pedido de amizade | pedido recebido | não |
| Desfazer amizade | amigos | **sim** (diálogo "remove") |
| Limpar histórico | na conversa (não no perfil); em grupo, é a única ação | **sim**, em duas etapas no próprio menu |
| Bloquear / Desbloquear | sempre | **sim** no bloquear (diálogo "ban") |

No perfil, "Limpar histórico" é substituído por "Iniciar conversa", que abre a gaveta na etapa de nova conversa com o membro (`data-aculta-open-conversation`).

- Na gaveta, o formulário é enviado por `fetch` para `drawer_relationship` (conversa) e a resposta substitui a gaveta.
- No perfil, o formulário é enviado para `profile_relationship` (POST), que volta ao perfil (303).

### 3.4 Nova conversa

`NewConversationController` tem três passos, todos na gaveta:

1. `start` — campo de busca do membro (`aculta_messenger.new_conversation`).
2. `search` — resultados a partir de 3 caracteres (`aculta_messenger.new_conversation_search`). Só mostra membros que aceitam a conversa: ativos, não o próprio usuário, que não bloquearam a conta atual e que permitem a abertura pela preferência de privacidade.
3. `compose` — primeira mensagem (`aculta_messenger.new_conversation_compose`, `/mensagens/nova/{user}/painel`). Os botões "Cancelar" e "Voltar para o início" ficam acima de "Enviar".

### 3.5 Avisos

- Avisos ativos e persistentes: `alertsPreview` (`/avisos/resumo`) e `MessengerCounts` (fonte única das contagens).
- Administração: `NoticeAdminController` e rotas `/painel-administrativo/avisos…`, sob o item "ACULTA Avisos".
- Leitura individual: `flag` `aculta_notice_read` e `aculta_site_alert_dismissed`.

### 3.6 Bloqueio e privacidade

- `MessengerPrivateMessageMapper` substitui o `private_message.mapper`: bloquear não tira a conversa da lista de quem bloqueou. Quem bloqueou continua vendo o histórico, mas não envia.
- `ConversationMessagingState::stateFor()` retorna `open`, `blocked_by_me`, `blocked_by_them` ou `friends_only`. É ele que decide o aviso.
- Preferência "só amigos" (`aculta_messenger.message_privacy`, `user.data`): restringe apenas a abertura de conversa nova. Respostas em conversas existentes não são restringidas.

### 3.7 Conversa em grupo

Uma conversa com mais de duas pessoas não tem amizade nem bloqueio para o menu: o menu "Opções" oferece só "Limpar histórico", com a mesma confirmação em duas etapas. A limpeza vale só para quem confirma (`PrivateMessageThread::clearAccountHistory`). `ConversationRelationshipForm` recebe o estado `group` e o `thread_id` (`MessagesController::threadFormState`).

### 3.8 Janelas de confirmação

"Bloquear" e "Desfazer amizade" abrem a janela "Você quer mesmo …?" com "Sim" e "Cancelar". A janela é montada por `Support\ConfirmDialog::add()`: um contêiner com a abertura e o fechamento do `<dialog>` (modelos inline), a pergunta e os botões. Os botões continuam filhos do formulário, então o Form API os registra. O "Sim" é um envio normal, com o nome da ação; "Cancelar" e o botão do menu não enviam o formulário (o script do tema cancela o clique).

## 4. Rotas

| Rota | Caminho | Método | Controlador | Uso |
| --- | --- | --- | --- | --- |
| `aculta_messenger.preview` | `/mensagens/resumo` | GET | `MessagesController::preview` | Lista da barra |
| `aculta_messenger.alerts_preview` | `/avisos/resumo` | GET | `MessagesController::alertsPreview` | Avisos |
| `aculta_messenger.drawer_conversations` | `/mensagens/conversas/painel` | GET | `drawerConversations` | Gaveta: lista |
| `aculta_messenger.drawer_conversation` | `/mensagens/conversa/{thread}/painel` | GET | `drawerConversation` | Gaveta: conversa |
| `aculta_messenger.drawer_reply` | `/mensagens/conversa/{thread}/responder` | POST | `drawerReply` | Enviar resposta |
| `aculta_messenger.drawer_relationship` | `/mensagens/conversa/{thread}/relacao` | POST | `drawerRelationship` | "Opções" da conversa |
| `aculta_messenger.profile_relationship` | `/mensagens/perfil/{user}/relacao` | POST | `ProfileRelationshipController::post` | "Opções" do perfil |
| `aculta_messenger.new_conversation` | `/mensagens/nova` | GET | `NewConversationController::start` | Nova conversa: busca |
| `aculta_messenger.new_conversation_search` | `/mensagens/nova/buscar` | GET | `NewConversationController::search` | Nova conversa: resultados |
| `aculta_messenger.new_conversation_compose` | `/mensagens/nova/{user}/painel` | GET, POST | `NewConversationController::compose` | Nova conversa: primeira mensagem |
| `aculta_messenger.communication_settings` | `/configuracoes/email` | GET | `CommunicationSettingsForm` | Preferências de e-mail |
| `aculta_messenger.admin_notices` | `/painel-administrativo/avisos` | GET | `NoticeAdminController::overview` | Administração |
| `aculta_messenger.admin_temporary_notices` | `/painel-administrativo/avisos/temporarios` | GET | `NoticeAdminController::temporary` | Administração |
| `aculta_messenger.admin_persistent_notices` | `/painel-administrativo/avisos/persistentes` | GET | `NoticeAdminController::persistent` | Administração |
| `aculta_messenger.admin_persistent_notice_add` | `/painel-administrativo/avisos/persistentes/criar` | GET | formulário `node.default` | Administração |

As rotas de gaveta são protegidas por `_entity_access: private_message_thread.view` e pela permissão `use private messaging system,access user profiles`. Os caminhos são públicos e em português (`docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md`). As rotas de POST não são cacheadas (`no_cache`).

## 5. Hooks

### 5.1 Hooks OOP do submódulo (`src/Hook/`)

Todos usam `#[Hook]`. Não há hook procedural nem arquivo `.module` no submódulo.

| Classe | Hook | Método | O que faz |
| --- | --- | --- | --- |
| `LifecycleHooks` | `module_preinstall` | `preventPrivateBodyNotifierConflict` | Impede ativar o notificador do Core com o template de corpo privado. |
| `LifecycleHooks` | `module_preuninstall` | `protectNotificationData` | Impede remover campos ou configuração que registros de notificação ainda usam; a limpeza é explícita. |
| `MessageSubscribeEventHooks` | `entity_insert` / `entity_update` | `entityInsert` / `entityUpdate` | Enfileira publicação ou revisão de tópico para o producer central de avisos; não cria conversa. |
| `MessageSubscribeHooks` | `message_subscribe_get_subscribers_alter` | `suppressForumDelivery` | Suprime a entrega direta do Message Subscribe para tópicos e comentários do Fórum; `seguir_topico` alimenta o fluxo central de avisos. |
| `MessengerBarHooks` | `preprocess_page` | `preprocessPage` | Entrega `aculta_messenger` (URL e contagem) ao tema, só para contas que podem enviar mensagens. |
| `MessengerBarHooks` | `page_attachments` | `pageAttachments` | Anexa o script da busca de nova conversa a todas as páginas, porque é carregado por `fetch`. |
| `MessengerBarHooks` | `preprocess_private_message_thread` | `preprocessPrivateMessageThread` | Estado de leitura por membro, como valor neutro para o tema. |
| `MessengerBarHooks` | `node_access` | `nodeAccess` | Aplica janela de publicação e público por papel aos avisos persistentes. |
| `NoticeAdminHooks` | `menu_links_discovered_alter` | `moveSiteAlertMenuLink` | Move o link de avisos do site para a área ACULTA Avisos. |
| `NoticeAdminHooks` | `toolbar_alter` | `removeCommunityAnnouncementsTab` | Remove a aba de comunicados da comunidade da barra de administração. |
| `PrivacyFormHooks` | `form_alter` | `formAlter` | Adiciona a privacidade de mensagens em Minha conta > Configurações > Privacidade, com seu próprio submit. |
| `PrivateMessageFormHooks` | `form_alter` | `formAlter` | Aplica texto simples e as políticas de envio nos formulários nativos do Private Message. |
| `PrivateMessageNotificationHooks` | `private_message_new_message` | `newMessage` | Enfileira só o ID da mensagem; o worker resolve a conversa salva. |
| `PrivateMessagePresentationHooks` | `preprocess_private_message` | `preprocessPrivateMessage` | Adiciona avatar do remetente e direção da mensagem, sem alterar os dados. |
| `PrivateMessageReplyHooks` | `entity_view` | `entityView` | Adiciona o compositor nativo quando a exibição da conversa omitiu o campo. |
| `PrivateMessageReplyHooks` | `entity_view_alter` | `entityViewAlter` | Ajusta a conversa que a gaveta mostra; remove o link nativo de limpar histórico. |
| `PrivateMessageReplyHooks` | `entity_base_field_info_alter` | `entityBaseFieldInfoAlter` | Define o rótulo "Participantes" no campo base, para a conversa e seus formulários. |
| `PrivateMessageRetentionHooks` | `cron` | `expireDormantThreads` | Apaga conversas inativas pelo ciclo de vida da entidade do Private Message. |
| `ProfileOptionsHooks` | `aculta_user_public_profile_actions_alter` | `alterActions` | Adiciona a caixa "Opções" ao perfil `/u/`. |
| `SettingsTabsHooks` | `aculta_portal_settings_tabs_alter` | `addCommunicationSettings` | Adiciona a aba "Notificações por e-mail" em Minha conta > Configurações. |

Hook de `aculta_user` que o Messenger depende: `FriendshipLifecycleHooks::removeRelationshipsForDeletedMember` (`user_delete`) remove as amizades de uma conta excluída. O hook de alter do perfil é invocado em `PublicProfileController::view()`.

### 5.2 Hooks do tema que tocam o Messenger

| Classe (tema) | Hook | Método | O que faz |
| --- | --- | --- | --- |
| `ThemeHooks` | `preprocess_page` | `preprocessPage` | Chama `presentMessengerPage` e `presentMessengerTrigger`. |
| `ThemeHooks` | — | `presentMessengerPage` | Mapeia rotas do Messenger a SDCs (`messenger-list`, `messenger-compose`). Ver A5. |
| `ThemeHooks` | — | `presentMessengerTrigger` | Transforma `aculta_messenger` em `messenger-trigger` (SDC). |
| `ThemeHooks` | `preprocess_private_message_thread` | `preprocessPrivateMessageThread` | Expõe o estado de leitura nativo da conversa de forma acessível. |
| `ThemeHooks` | `preprocess_private_message` | `preprocessPrivateMessage` | Entrega avatar do remetente e `outgoing` à `messenger-item`. |
| `PortalUiHooks` | `preprocess_container`, `preprocess_html_tag`, `preprocess_item_list`, `preprocess_input`, `preprocess_form_element` (e outros) | `preprocess` | Troca `data-portal-ui` por classes (mapa `TOKENS`). |
| `PortalUiHooks` | `preprocess_menu` | `preprocessMenu` | Mesmo mapa, para links de menu. |
| `PortalUiHooks` | `preprocess_details` (ordem última) | `preprocessDetails` | Mapeia a caixa "Opções" depois do acordeão do Bootstrap, que escreve os atributos do `details`. |

## 6. Classes do submódulo

| Classe | Pasta | Papel |
| --- | --- | --- |
| `AcultaMessengerServiceProvider` | raiz | Troca a classe de `private_message.mapper`. |
| `MessagesController` | `Controller/` | Lista, conversa, resposta, "Opções" da conversa, aviso de bloqueio, contadores da gaveta. |
| `NewConversationController` | `Controller/` | Nova conversa: busca, resultados, primeira mensagem. |
| `ProfileRelationshipController` | `Controller/` | Recebe o "Opções" do perfil e volta ao perfil. |
| `NoticeAdminController` | `Controller/` | Páginas administrativas de avisos. |
| `NativePrivateMessageController` | `Controller/` | Respostas às páginas nativas removidas: redireciona para a conta ou responde com a página de conteúdo removido. |
| `ConversationRelationshipForm` | `Form/` | "Opções" (amizade, limpar, bloquear) e o modo de grupo (só limpar). |
| `ConfirmDialog` | `Support/` | Monta a janela de confirmação de uma ação (abertura, pergunta, Sim e Cancelar). |
| `OptionsDisclosure` | `Support/` | Monta a caixa "Opções" (acordeão) das conversas e do perfil. |
| `NewConversationForm` | `Form/` | Primeira mensagem de uma conversa nova. |
| `PrivateMessageFormValidator` | `Form/` | Regras de envio: bloqueio, só amigos, limite (flood). |
| `FriendRequestsForm` | `Form/` | Pedidos de amizade pendentes. |
| `MessagePrivacyForm` | `Form/` | Preferência "todos" ou "amigos". |
| `CommunicationSettingsForm` | `Form/` | Preferências de e-mail. |
| `ConversationMessagingState` | `Service/` | Estado de envio da conversa (`open`, `blocked_by_*`, `friends_only`). |
| `MessengerCounts` | `Service/` | Contagens de avisos e comunicados (definição única). |
| `UnreadThreadCounter` | `Service/` | Conversas não lidas. |
| `MessengerPrivateMessageMapper` | `Mapper/` | Lista de conversas que mantém bloqueios visíveis a quem bloqueou. |
| `NativePrivateMessagePagesSubscriber` | `EventSubscriber/` | Remove as páginas nativas do Private Message. |
| `NoticeAdminRouteSubscriber` | `EventSubscriber/` | Rotas de avisos em área administrativa. |
| `PrivateMessageAccountRouteSubscriber` | `EventSubscriber/` | Rotas de conta do Private Message. |
| `PersistentNoticeRouteAccess` | `Access/` | Acesso às rotas de avisos persistentes. |
| `MessageSubscribeEventWorker`, `PrivateMessageNotificationWorker` | `Plugin/QueueWorker/` | Filas de notificação. |
| `StorageDateTime` | `Support/` | Leitura de datas em UTC do armazenamento. |
| Classes de `Hook/` | `Hook/` | Ver seção 5.1. |

Em `aculta_user`: `FriendshipManager` (`Social/`), `AcultaFriendship` (`Entity/`), `FriendshipStatus` (`Social/`), `FriendshipActionForm` (`Form/`).

## 7. Contratos com o tema

### 7.1 Variáveis entregues ao tema

| Variável | Origem | Conteúdo |
| --- | --- | --- |
| `aculta_messenger` | `MessengerBarHooks::preprocessPage` | `url`, `unread_count` |
| `aculta_messenger_trigger` | `ThemeHooks::presentMessengerTrigger` | render array `messenger-trigger` |
| `aculta_messenger_outgoing` | `PrivateMessagePresentationHooks` | bool: mensagem enviada por mim |
| `aculta_messenger_sender_avatar` | `PrivateMessagePresentationHooks` | avatar do remetente |
| `aculta_messenger_read_status` | `PrivateMessagePresentationHooks` | estado de leitura |
| `aculta_alerts` | `MessengerBarHooks` | avisos ativos |

### 7.2 Tokens `data-portal-ui` do Messenger

O módulo marca o elemento com o token. O tema troca por classes em `PortalUiHooks::TOKENS`. Não há classe de design no módulo.

| Token | Emitido por | Classes no tema |
| --- | --- | --- |
| `conversation-action` | `ConversationRelationshipForm::button` | `aculta-messenger-options__action` |
| `conversation-notice-action` | `ConversationRelationshipForm` (aviso) | `aculta-messenger-notice__action` |
| `messenger-preview` | `MessagesController` | `aculta-messenger-preview` |
| `notice-read-status` / `notice-unread-status` | `MessagesController` (avisos persistentes) | `aculta-messenger-read-status` (+ `--unread`) |
| `new-conversation-link` | `NewConversationController` | `btn btn-primary aculta-messenger-new-conversation` |
| `new-conversation-links` | `NewConversationForm` | `d-flex flex-wrap gap-2 mt-2` |
| `new-conversation-back` | `NewConversationForm` | `btn btn-outline-primary` |
| `new-conversation-cancel` | `NewConversationForm` | `btn btn-outline-secondary` |
| `new-conversation-submit` | `NewConversationForm` | `btn btn-primary` |
| `new-conversation-message` | `NewConversationForm` | `form-control` |
| `new-conversation-results` | `NewConversationController` | `list-group` |
| `new-conversation-result` | `NewConversationController` | `list-group-item list-group-item-action` |
| `search-preview` / `search-preview-input` | `NewConversationController` | ver `TOKENS-E-ATRIBUTOS.md` |

Um token emitido em `html_tag`, `link` ou `container` só é trocado se o elemento tiver template de tema. Para links, usar `Drupal\aculta_portal\Render\PortalLinkElement` (ver guia do tema).

### 7.3 Atributos `data-aculta-*`

| Atributo | Quem põe | Quem usa | Função |
| --- | --- | --- | --- |
| `data-aculta-drawer-url` | tema (componente `messenger-trigger`) | `messenger-drawers.js` | URL de carregamento da gaveta |
| `data-aculta-drawer-count` | tema (componente `messenger-trigger`) | `messenger-drawers.js` | `messages` ou `alerts` |
| `data-aculta-drawer-back` | módulo (link "← Todas as conversas") | JS | Volta à lista dentro da gaveta |
| `data-aculta-conversation-url` | módulo | JS | Carrega uma visão na gaveta por `fetch` |
| `data-aculta-conversation-view` | módulo | JS | Rola a conversa até a última mensagem |
| `data-aculta-drawer-action` | módulo (formulário "Opções") | JS, CSS | Marca o formulário do menu; envia por `fetch` |
| `data-aculta-new-conversation` | módulo (busca) | JS | Impede o Enter de enviar a busca |
| `data-aculta-new-conversation-form` | módulo | JS | Envia a primeira mensagem por `fetch` |
| `data-aculta-open-conversation` | módulo (perfil) | JS | Abre a gaveta na nova conversa |
| `data-aculta-dialog` | módulo (`ConfirmDialog`) | JS, CSS | Nome da confirmação: `ban` ou `remove` |
| `data-aculta-dialog-open` | módulo (botão do menu) | JS | Abre o diálogo com esse nome |
| `data-aculta-dialog-close` | módulo (Cancelar) | JS | Fecha o diálogo |
| `data-aculta-alert-dismiss` | módulo (avisos) | `alerts-dismiss.js` | Dispensa um aviso e mantém a contagem |
| `data-aculta-reply-form` | módulo (resposta) | `messenger-drawers.js`, CSS | Marca o formulário de resposta |
| `data-aculta-thread-id`, `data-aculta-thread-unread`, `data-aculta-sent-date` | módulo (`MessagesController`) | JS | Dados da lista (conversa, não lida, data de envio) |

## 8. Dados

- Conversas e mensagens: entidades `private_message_thread` e `private_message` (contrib). Os membros vêm de `getMembers()`.
- Bloqueios: `private_message.ban_manager` (contrib).
- Amizade: `aculta_friendship` (`aculta_user`), com `member_low`, `member_high`, `requester`, `recipient` e `status`. Acesso pelo `FriendshipManager`, não por SQL.
- Preferência de privacidade: `user.data`, módulo `aculta_messenger`, chave `message_privacy`.
- Limpeza de histórico: `PrivateMessageThread::clearAccountHistory()`, por membro. Não apaga a conversa do outro membro.
- Retenção: `PrivateMessageRetentionHooks::expireDormantThreads` (cron).

Os testes de navegador criam dados. Remover contas e conversas de teste pela API de entidades (ver `MESSENGER-AUDIT.md`, seção 1).

## 9. Testes e verificação

| Tipo | Onde | Como |
| --- | --- | --- |
| Unitários (PHPUnit) | `aculta_messenger/tests/src/Unit/` (57 arquivos de teste) | `php vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_messenger/tests/src/Unit` |
| Gates | `scripts/validate-module-theme-boundary.php`, `validate-turnstile-exceptions.php`, `validate-public-slugs.php`, `validate-aculta-portal-drupal11.php`, `validate-aculta420-foundation.php` (Drush: `drush scr`) | Ver `docs/portal/GUARDRAILS.md` |
| Navegador | `tests/browser/aculta-messenger/`; suíte completa registrada 76/76 e smoke da central 4/4. | Firefox ESR visível (`DISPLAY=:0`) no Runtime de teste (`*.aculta.toca.net.br`) |

## 10. Pendências conhecidas

- A lista global ainda não tem uma paginação cronológica única. Cada origem implementa paginação própria após revalidar acesso. A decisão entre manter os paginadores por origem ou construir uma sequência global está em avaliação; ver [roadmap M6](../../web/modules/custom/aculta_portal/modules/aculta_messenger/docs/ROADMAP.md).
- A dependência do caminho Message Notify para registros antigos de e-mail de amizade e Mensagem Privada só será removida depois da drenagem operacional das filas, verificada no Runtime. Nenhuma fila real foi limpa ou alterada nesta revisão.
- O aviso de bloqueio do Private Message ainda usa texto em inglês (configuração não exportada; pendente).
- Não há validação em dispositivos de toque reais nem com leitor de tela.
- Os testes visuais registrados não substituem uma homologação completa do Runtime após as próximas alterações da central.
- A regra vigente para conversas está em [`MESSENGER-CONVERSATION-KINDS.md`](MESSENGER-CONVERSATION-KINDS.md): conversas individuais e grupos voluntários pelo mesmo sistema; Fórum, LMS, Wiki e outros conteúdos não criam chats ou threads automáticos.
