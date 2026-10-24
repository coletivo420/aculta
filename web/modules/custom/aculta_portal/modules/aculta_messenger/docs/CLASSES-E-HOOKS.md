# Classes e hooks — aculta_messenger

Mensagens privadas (conversas), avisos (Fórum, alertas do site, persistentes), central Avisos/Mensagens da conta, resumo diário e retenção. Avisos e mensagens são sistemas separados: ver `docs/AVISOS-E-MENSAGENS.md`.

`ForumTopicCounterHooks` e o hook `preprocess_forums` foram removidos em
0.1.1-dev.56: eram ligados à listagem do Forum contribuído. A listagem ACULTA
usa Views Core e consome os próprios dados neutros.

Gerado a partir do código em 2026-10-24 (`src/`). Cada classe aparece com a primeira frase do próprio docblock; a regra e o motivo completos ficam no código. Quando uma classe mudar, atualize esta tabela na mesma entrega.

## Hooks (`#[Hook]`)

| Hook | Implementação | Arquivo |
| --- | --- | --- |
| `aculta_portal_account_parent_sections_alter` | `AccountSectionsHooks::parentSectionsAlter` | `src/Hook/AccountSectionsHooks.php` |
| `aculta_portal_account_sections_alter` | `AccountSectionsHooks::sectionsAlter` | `src/Hook/AccountSectionsHooks.php` |
| `aculta_portal_settings_tabs_alter` | `SettingsTabsHooks::addCommunicationSettings` | `src/Hook/SettingsTabsHooks.php` |
| `aculta_user_public_profile_actions_alter` | `ProfileOptionsHooks::alterActions` | `src/Hook/ProfileOptionsHooks.php` |
| `comment_insert` | `ForumAvisoHooks::commentInsert` | `src/Hook/ForumAvisoHooks.php` |
| `comment_update` | `ForumAvisoHooks::commentUpdate` | `src/Hook/ForumAvisoHooks.php` |
| `aculta_forum_post_insert` | `ForumAvisoHooks::postInsert` | `src/Hook/ForumAvisoHooks.php` |
| `aculta_forum_post_update` | `ForumAvisoHooks::postUpdate` | `src/Hook/ForumAvisoHooks.php` |
| `cron` | `AvisoRetencaoHooks::expirarAvisos` | `src/Hook/AvisoRetencaoHooks.php` |
| `cron` | `AvisosResumoHooks::cron` | `src/Hook/AvisosResumoHooks.php` |
| `cron` | `PrivateMessageRetentionHooks::expireDormantThreads` | `src/Hook/PrivateMessageRetentionHooks.php` |
| `entity_base_field_info_alter` | `PrivateMessageReplyHooks::entityBaseFieldInfoAlter` | `src/Hook/PrivateMessageReplyHooks.php` |
| `entity_insert` | `MessageSubscribeEventHooks::entityInsert` | `src/Hook/MessageSubscribeEventHooks.php` |
| `entity_update` | `MessageSubscribeEventHooks::entityUpdate` | `src/Hook/MessageSubscribeEventHooks.php` |
| `entity_view` | `PrivateMessageReplyHooks::entityView` | `src/Hook/PrivateMessageReplyHooks.php` |
| `entity_view_alter` | `PrivateMessageReplyHooks::entityViewAlter` | `src/Hook/PrivateMessageReplyHooks.php` |
| `form_alter` | `AvisosPrivacyHooks::formAlter` | `src/Hook/AvisosPrivacyHooks.php` |
| `form_alter` | `PrivacyFormHooks::formAlter` | `src/Hook/PrivacyFormHooks.php` |
| `form_alter` | `PrivateMessageFormHooks::formAlter` | `src/Hook/PrivateMessageFormHooks.php` |
| `mail` | `AvisosResumoHooks::mail` | `src/Hook/AvisosResumoHooks.php` |
| `menu_links_discovered_alter` | `NoticeAdminHooks::moveSiteAlertMenuLink` | `src/Hook/NoticeAdminHooks.php` |
| `message_subscribe_get_subscribers_alter` | `MessageSubscribeHooks::suppressForumDelivery` | `src/Hook/MessageSubscribeHooks.php` |
| `module_preinstall` | `LifecycleHooks::preventPrivateBodyNotifierConflict` | `src/Hook/LifecycleHooks.php` |
| `module_preuninstall` | `LifecycleHooks::protectNotificationData` | `src/Hook/LifecycleHooks.php` |
| `node_access` | `MessengerBarHooks::nodeAccess` | `src/Hook/MessengerBarHooks.php` |
| `page_attachments` | `MessengerBarHooks::pageAttachments` | `src/Hook/MessengerBarHooks.php` |
| `preprocess_page` | `MessengerBarHooks::preprocessPage` | `src/Hook/MessengerBarHooks.php` |
| `preprocess_private_message` | `PrivateMessagePresentationHooks::preprocessPrivateMessage` | `src/Hook/PrivateMessagePresentationHooks.php` |
| `preprocess_private_message_thread` | `MessengerBarHooks::preprocessPrivateMessageThread` | `src/Hook/MessengerBarHooks.php` |
| `private_message_new_message` | `PrivateMessageNotificationHooks::newMessage` | `src/Hook/PrivateMessageNotificationHooks.php` |
| `toolbar_alter` | `NoticeAdminHooks::removeCommunityAnnouncementsTab` | `src/Hook/NoticeAdminHooks.php` |

## Classes por pasta

### `Access`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `PersistentNoticeRouteAccess` | `src/Access/PersistentNoticeRouteAccess.php` | Restricts notice-specific administrative form routes to notice nodes. */ |

### `Aviso`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AvisoPreferencias` | `src/Aviso/AvisoPreferencias.php` | Controles individuais de avisos (ACULTA Messenger, F5.2). |
| `AvisoRetencao` | `src/Aviso/AvisoRetencao.php` | Retenção dos avisos por pessoa do Fórum (ACULTA Messenger): seis meses desde a criação. |
| `AvisoService` | `src/Aviso/AvisoService.php` | Revalida e agrega avisos de seguidores no `aculta_aviso` e no ledger existente (ACULTA Fórum, F5.2, F-D21). |
| `ForumNoticeEventProducer` | `src/Aviso/ForumNoticeEventProducer.php` | Publica eventos de Comment, ForumPost e revisão de tópico no contrato compartilhado. |
| `ForumNoticeEventProducerInterface` | `src/Aviso/ForumNoticeEventProducerInterface.php` | Contrato testável para produzir eventos de atividade do Fórum. |
| `ForumNoticeRegistrarInterface` | `src/Aviso/ForumNoticeRegistrarInterface.php` | Contrato de persistência/revalidação pelo agregador existente. |
| `AvisosResumo` | `src/Aviso/AvisosResumo.php` | Resumo diário dos avisos por e-mail (ACULTA Messenger, F5.2, política de 2026-10-16). |
| `AvisoDigestProviderInterface` | `src/Aviso/AvisoDigestProviderInterface.php` | Extensão para adicionar avisos atuais ao mesmo resumo diário combinado. |

### `Controller`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AvisosMensagensController` | `src/Controller/AvisosMensagensController.php` | Central "Avisos/Mensagens" dentro de Minha conta (ACULTA Messenger, F5.2). |
| `MessagesController` | `src/Controller/MessagesController.php` | Pages for the account communication center. */ |
| `NativePrivateMessageController` | `src/Controller/NativePrivateMessageController.php` | Native Private Message pages that no longer exist: messages are read and sent only in the multidomain bar. |
| `NewConversationController` | `src/Controller/NewConversationController.php` | "Iniciar nova conversa" in the multidomain bar drawer: find a member, then write the first message. |
| `NoticeAdminController` | `src/Controller/NoticeAdminController.php` | Administrative landing and listing pages for ACULTA notices. |
| `ProfileRelationshipController` | `src/Controller/ProfileRelationshipController.php` | An action from the "Opções" box of a public profile: processed here, then the member goes back to the profile. |

### `Entity`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `Aviso` | `src/Entity/Aviso.php` | Aviso por pessoa (ACULTA Fórum, F5.2, F-D21 e política de avisos de 2026-10-16). |
| `AvisoAccessControlHandler` | `src/Entity/AvisoAccessControlHandler.php` | Aviso: a pessoa vê só os próprios avisos. Administração de avisos tem permissão própria. |

### `EventSubscriber`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `ForumAvisoReadSubscriber` | `src/EventSubscriber/ForumAvisoReadSubscriber.php` | Ler o tópico zera o contador do aviso do site (ACULTA Fórum, F5.2). |
| `ForumNoticeEventSubscriber` | `src/EventSubscriber/ForumNoticeEventSubscriber.php` | Revalida evento compartilhado por UUID/revisão e encaminha ao agregador do Fórum. |
| `NativePrivateMessagePagesSubscriber` | `src/EventSubscriber/NativePrivateMessagePagesSubscriber.php` | Removes the native Private Message pages from the member experience. |
| `NoticeAdminRouteSubscriber` | `src/EventSubscriber/NoticeAdminRouteSubscriber.php` | Assigns Portuguese admin paths to the native Site Alert management routes. */ |
| `PrivateMessageAccountRouteSubscriber` | `src/EventSubscriber/PrivateMessageAccountRouteSubscriber.php` | Assigns native Private Message routes to the single account purpose. */ |

### `Form`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AvisosPrivacyForm` | `src/Form/AvisosPrivacyForm.php` | Bloco "Avisos" da página de privacidade da conta (ACULTA Messenger, F5.2). |
| `CommunicationSettingsForm` | `src/Form/CommunicationSettingsForm.php` | Minha conta > Configurações > Notificações por e-mail (preferência de e-mail do Private Message). */ |
| `ConversationRelationshipForm` | `src/Form/ConversationRelationshipForm.php` | "Opções" of a conversation in the multidomain bar drawer, and of a public profile. |
| `FriendRequestsForm` | `src/Form/FriendRequestsForm.php` | Displays and handles the signed-in member's incoming friendship requests. */ |
| `MessagePrivacyForm` | `src/Form/MessagePrivacyForm.php` | Reads and saves who may start a conversation with the member (the "all" or "friends" preference). |
| `MessengerSettingsForm` | `src/Form/MessengerSettingsForm.php` | Controles administrativos do chat: grupos, limites de participantes e envios, e o tempo da limpeza. |
| `NewConversationForm` | `src/Form/NewConversationForm.php` | First message of a new conversation, written inside the multidomain bar drawer. |
| `PrivateMessageFormValidator` | `src/Form/PrivateMessageFormValidator.php` | Applies block and send-frequency rules to the native Private Message form. */ |

### `Hook`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AccountSectionsHooks` | `src/Hook/AccountSectionsHooks.php` | Acrescenta "Avisos/Mensagens" à navegação de Minha conta (conta.aculta) (ACULTA Messenger, F5.2). |
| `AvisoRetencaoHooks` | `src/Hook/AvisoRetencaoHooks.php` | Exclui automaticamente os avisos do Fórum com mais de seis meses (ACULTA Messenger, regra de retenção). |
| `AvisosPrivacyHooks` | `src/Hook/AvisosPrivacyHooks.php` | Acrescenta o bloco "Avisos" à página de privacidade da conta (ACULTA Messenger, F5.2). |
| `AvisosResumoHooks` | `src/Hook/AvisosResumoHooks.php` | Cron e mensagem do resumo diário de avisos (ACULTA Messenger, F5.2). |
| `ForumAvisoHooks` | `src/Hook/ForumAvisoHooks.php` | Publica eventos compartilhados para respostas e primeira publicação de ForumPost (ACULTA Fórum, F-D21). |
| `LifecycleHooks` | `src/Hook/LifecycleHooks.php` | Protects Messenger-owned notification records during module removal. */ |
| `MessageSubscribeEventHooks` | `src/Hook/MessageSubscribeEventHooks.php` | Queues publication events for the forum's existing follow flag. */ |
| `MessageSubscribeHooks` | `src/Hook/MessageSubscribeHooks.php` | Enforces F-D21 for ACULTA Forum topics and hosted Comments while preserving other contexts. */ |
| `MessengerBarHooks` | `src/Hook/MessengerBarHooks.php` | Supplies the neutral, per-member Messenger action to the global shell. */ |
| `NoticeAdminHooks` | `src/Hook/NoticeAdminHooks.php` | Consolidates administrative notice entry points under ACULTA Avisos. |
| `PrivacyFormHooks` | `src/Hook/PrivacyFormHooks.php` | Adds the Messenger's messaging privacy to Minha conta > Configurações > Privacidade. |
| `PrivateMessageFormHooks` | `src/Hook/PrivateMessageFormHooks.php` | Integrates safe ACULTA policies with the native Private Message form. */ |
| `PrivateMessageNotificationHooks` | `src/Hook/PrivateMessageNotificationHooks.php` | Queues private-message notices after the upstream event is raised. */ |
| `PrivateMessagePresentationHooks` | `src/Hook/PrivateMessagePresentationHooks.php` | Prepares privacy-aware, theme-neutral sender data for message rendering. */ |
| `PrivateMessageReplyHooks` | `src/Hook/PrivateMessageReplyHooks.php` | Keeps the native Private Message reply form available in thread views. */ |
| `PrivateMessageRetentionHooks` | `src/Hook/PrivateMessageRetentionHooks.php` | Applies the six-month inactivity retention policy to private conversations. */ |
| `ProfileOptionsHooks` | `src/Hook/ProfileOptionsHooks.php` | The "Opções" box of a public profile (/u/): the same box as in a conversation, with "Iniciar conversa" in place of |
| `SettingsTabsHooks` | `src/Hook/SettingsTabsHooks.php` | Aba "Notificações por e-mail" em Minha conta > Configurações (preferência de e-mail da central de mensagens). */ |

### `Mapper`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `MessengerPrivateMessageMapper` | `src/Mapper/MessengerPrivateMessageMapper.php` | The Private Message mapper with the ACULTA rule for blocks: blocking a member does not hide the conversation. |

### `Plugin`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `MessageSubscribeEventWorker` | `src/Plugin/QueueWorker/MessageSubscribeEventWorker.php` | Atualização de tópico seguido: vira aviso por pessoa, não mensagem (ACULTA Fórum, F5.2, F-D21). |
| `PrivateMessageNotificationWorker` | `src/Plugin/QueueWorker/PrivateMessageNotificationWorker.php` | Sends minimal, deduplicated private-message email notices. */ |

### `Service`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `ConversationMessagingState` | `src/Service/ConversationMessagingState.php` | Whether the current member can send in a one-to-one conversation, and why not. |
| `ConversationPolicy` | `src/Service/ConversationPolicy.php` | The rules of the chat: how many people a conversation may have, how long it is kept and how much is sent. |
| `MessengerCounts` | `src/Service/MessengerCounts.php` | The single definition of the alert and persistent-notice counts shown in the Messenger surfaces. |
| `UnreadThreadCounter` | `src/Service/UnreadThreadCounter.php` | Counts unread conversations using Private Message's per-thread read state. */ |

### `Support`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `ConfirmDialog` | `src/Support/ConfirmDialog.php` | An action that asks first: a menu button opens a native dialog, and its "Sim" button runs the action. |
| `NoticeType` | `src/Support/NoticeType.php` | The types of notice and the colour each one has in the drawer. A persistent notice chooses its type in the admin form |
| `OptionsDisclosure` | `src/Support/OptionsDisclosure.php` | The "Opções" accordion: a native details element whose summary is the label with the menu icon. |
| `StorageDateTime` | `src/Support/StorageDateTime.php` | Reads datetime field values that Drupal stores in UTC. |

### `src`

| Classe | Arquivo | Responsabilidade |
| --- | --- | --- |
| `AcultaMessengerServiceProvider` | `src/AcultaMessengerServiceProvider.php` | Replaces the Private Message mapper class: a block keeps the conversation listed for the blocker. |

