# Auditoria do ACULTA Messenger: módulo, submódulo e tema

Data: 2026-10-15. Branch: `chore/messenger-pendencias` (HEAD `d60b9a2`).
Versões auditadas: `aculta_portal` 0.4.0-dev.31, `aculta_messenger` 0.1.1-dev.30, `aculta420` 0.6.0-dev.52.

Este documento separa, por arquivo, hook e classe, o que pertence a cada camada do Messenger.
A regra de referência é `docs/portal/FRONTEIRA-MODULO-TEMA.md` ("Código no Aculta Portal, Design no ACULTA420").
A documentação do projeto e a referência de hooks e classes estão em:

- [`docs/portal/MESSENGER.md`](MESSENGER.md): o projeto inteiro (módulo, submódulo e contratos).
- [`web/themes/custom/aculta420/docs/messenger.md`](../../web/themes/custom/aculta420/docs/messenger.md): guia de desenvolvimento do tema.

## 1. Escopo e limpeza de dados de teste

Limpeza feita antes da auditoria, pela API de entidades (sem SQL direto):

| Dado | Ação | Motivo |
| --- | --- | --- |
| Conta `teste.perfil` (uid 32) | Removida | Conta de teste criada para a suíte de navegador |
| Perfis 37 e 38 (de `teste.perfil`) | Removidos | Pertencentes à conta removida |
| Amizade `5:32` (`teste.comum` ↔ `teste.perfil`, aceita) | Removida | Criada pelos testes |
| Amizade `1:5` (`piradopirata` ↔ `teste.comum`, cancelada) | Removida | Criada pelos testes; registro cancelado, sem efeito |
| Conversa 1 (`teste.comum` ↔ `piradopirata`) e 6 mensagens | Removidas | Mensagens simuladas e de teste (textos "Mensagem simulada…" e "Teste") |

Mantidos, de propósito:

- `piradopirata` (uid 1), `coletivo420` (uid 15) e `hawalari.coxini` (uid 19): contas reais, não tocadas.
- `teste.comum` (uid 5): conta de login usada pelos testes de navegador. Se quiser removê-la, é uma decisão separada.

Estado após a limpeza: 0 conversas, 0 mensagens, 0 amizades `aculta_friendship`.

## 2. Classificação por camada

Três camadas de código do Messenger, mais o núcleo que o Messenger usa:

| Camada | Local | Responsabilidade |
| --- | --- | --- |
| **Módulo** `aculta_portal` | `web/modules/custom/aculta_portal/` | Conceitos compartilhados do Portal: contratos de Domain, presenters e a fronteira de tema (`data-portal-ui`). |
| **Submódulo** `aculta_messenger` | `web/modules/custom/aculta_portal/modules/aculta_messenger/` | Funções do Messenger: rotas, controladores, formulários, serviços, hooks, validação, contadores, avisos. Sem design. |
| **Submódulo** `aculta_user` | `web/modules/custom/aculta_portal/modules/aculta_user/` | Amizade (`FriendshipManager`, entidade `aculta_friendship`) e a página pública `/u/`. O Messenger consome este serviço. |
| **Tema** `aculta420` | `web/themes/custom/aculta420/` | Apresentação: Twig/SDC, CSS, JS de comportamento visual, mapeamento de tokens. |
| **Contrib / Core** | `private_message`, `message`, `message_notify`, `message_subscribe`, `flag`, `site_alert`, Core | Fonte de verdade de threads, bloqueios, mensagens, notificações, avisos e leitura. |

### 2.1 Submódulo `aculta_messenger` (36 arquivos PHP em `src/`)

Função do Messenger, sem design:

| Grupo | Arquivos |
| --- | --- |
| Controladores | `MessagesController`, `NewConversationController`, `ProfileRelationshipController`, `NoticeAdminController`, `NativePrivateMessageController` |
| Formulários | `ConversationRelationshipForm`, `NewConversationForm`, `PrivateMessageFormValidator`, `FriendRequestsForm`, `MessagePrivacyForm`, `CommunicationSettingsForm` |
| Serviços | `ConversationMessagingState`, `MessengerCounts`, `UnreadThreadCounter` |
| Eventos e acesso | `NativePrivateMessagePagesSubscriber`, `NoticeAdminRouteSubscriber`, `PrivateMessageAccountRouteSubscriber`, `PersistentNoticeRouteAccess` |
| Mapper e provider | `MessengerPrivateMessageMapper` (troca a classe do `private_message.mapper`), `AcultaMessengerServiceProvider` |
| Filas | `MessageSubscribeEventWorker`, `PrivateMessageNotificationWorker` |
| Hooks OOP | 13 classes em `src/Hook/` (ver `MESSENGER.md`, seção 5) |
| Utilitário | `Support/StorageDateTime` |

Não há `\Drupal::` em `src/` (zero ocorrências). Os serviços são injetados. Isso segue a regra de DI do Portal.

### 2.2 Submódulo `aculta_user` (parte do Messenger)

| Arquivo | Papel para o Messenger |
| --- | --- |
| `Social/FriendshipManager` e `FriendshipManagerInterface` | Regras de amizade (`request`, `accept`, `decline`, `cancel`, `remove`, `areFriendsWith`, `getRelationshipWith`, `getIncomingRequests`). |
| `Entity/AcultaFriendship` e `AcultaFriendshipAccessControlHandler` | Registro da amizade e seu acesso. |
| `Social/FriendshipStatus` | Estados (`pending`, `accepted`, `declined`, `cancelled`, `removed`). |
| `Controller/PublicProfileController` | Oferece o hook `aculta_user_public_profile_actions` (alter) que o Messenger usa para a caixa "Opções" do perfil. |

### 2.3 Tema `aculta420` (apresentação do Messenger)

| Arquivo | Papel |
| --- | --- |
| `components/patterns/messenger-trigger`, `messenger-list`, `messenger-read-status` | SDC: botão da barra, lista, estado de leitura. |
| `components/content/messenger-item`, `messenger-compose`, `messenger-notification` | SDC: item/bolha de mensagem, compositor, notificação. |
| `components/feedback/messenger-state`, `unread-badge` | SDC: estados vazio/erro/carregando e contador. |
| `components/content/messenger-item/messenger-item.css` | CSS de **todo** o painel (ver achado A3). |
| `js/messenger-drawers.js` | Comportamento da gaveta (carregamento, formulários por `fetch`, diálogo, abertura de conversa). |
| `js/counters.js` | Contadores da barra (mensagens e avisos). |
| `src/Hook/PortalUiHooks.php` | Mapa de tokens `data-portal-ui` do Messenger para classes. |
| `src/Hook/ThemeHooks.php` | Mapeia as rotas do Messenger para SDCs (`presentMessengerPage`, `presentMessengerTrigger`) e lê `aculta_messenger`, `aculta_messenger_outgoing`, `aculta_messenger_sender_avatar`, `aculta_messenger_read_status`. |
| `templates/page.html.twig` | Imprime o gatilho `aculta_messenger_trigger`. |

Tudo o que o tema usa do Messenger chega como dado neutro (URL, contagem, textos, render arrays). O tema não lê Domain, hostname nem regra funcional. Isso está correto.

## 3. Achados

Severidade: **alta** = viola regra do projeto; **média** = dívida que contraria a fronteira ou gera risco de manutenção; **baixa** = limpeza.

| ID | Sev. | Camada | Achado | Evidência | Ação recomendada |
| --- | --- | --- | --- | --- | --- |
| A1 | alta | Submódulo | O módulo emite classes de **apresentação** (BEM do Messenger e classes de admin) em 23 atributos `class` de 6 arquivos. | `ConversationRelationshipForm`, `ProfileOptionsHooks`, `PrivateMessageReplyHooks`, `MessagesController`, `NewConversationController`, `NoticeAdminController`. Ex.: `aculta-messenger-options__summary`, `aculta-messenger-conversation__actions`, `admin-list`. | Trocar por `data-portal-ui` com token novo e mapear em `PortalUiHooks`. A regra diz "não emite classes de design". O gate `validate-module-theme-boundary` passa porque conta apenas classes de design conhecidas (`btn`, `alert`, `col-`, `bi-`…). Ampliar o gate para o prefixo `aculta-messenger`. |
| A2 | média | Submódulo | Marcação estrutural montada em PHP: a caixa de confirmação usa `inline_template` com as tags `<dialog>` e `<p>` em `ConversationRelationshipForm`. | `ConversationRelationshipForm::confirmedAction()`. | Mover a caixa para um SDC do tema (ex.: `messenger-confirm`) que recebe `{ name, question, confirm_label, cancel_label }`. O módulo passa só dados. |
| A3 | média | Tema | `messenger-item.css` (112 linhas) estilizam o painel inteiro: a caixa "Opções", avisos, barra de conversa, relação e diálogo. Está dentro da pasta do componente `messenger-item`, que é só a bolha da mensagem. | `components/content/messenger-item/messenger-item.css`. | Separar em arquivos por componente (ou `css/components/messenger-drawer.css`) e carregar pela biblioteca `messenger-drawers`. |
| A4 | média | Tema | Regras mortas do menu antigo de linha: `.aculta-messenger-conversation__menu-trigger` e variantes (7 linhas de CSS). Nenhum JS, componente ou módulo usa esses seletores. | `messenger-item.css` linhas 36–38 e vizinhas. | Remover (estilo morto). |
| A5 | média | Tema | `ThemeHooks::presentMessengerPage` mapeia `private_message.private_message_page` e `private_message.private_message_create` para SDCs. O submódulo remove essas páginas nativas (`NativePrivateMessagePagesSubscriber`, `NativePrivateMessageController`). | `ThemeHooks.php` linhas ~284–290; `NativePrivateMessagePagesSubscriber`. | Confirmar se essas rotas ainda são renderizadas. Se não forem, remover o mapeamento. |
| A6 | média | Tema | `messenger-drawers.js` mistura a gaveta do Messenger com o comportamento de alertas (`aculta-alert-dismiss`) e contadores. | `js/messenger-drawers.js`, bloco `aculta-alert-dismiss`. | Mover o fechamento de alertas para o arquivo de avisos (`counters.js` ou um `alerts.js`). |
| A7 | média | Testes | A suíte de navegador (52 verificações) e os scripts de depuração ficam em `scratchpad/`, fora do repositório. Não são versionados. | `docs/portal/TESTES-NAVEGADOR.md`, seção U, cita o resultado, mas não o código. | Versionar a suíte em `tests/browser/aculta-messenger/` com instruções de execução, ou referenciar o local versionado. |
| A8 | baixa | Documentação | `docs/ROADMAP.md` e `docs/ARCHITECTURE.md` do submódulo não citam a gaveta multidomínio, "Opções", a confirmação de bloqueio nem "Nova conversa". `ARCHITECTURE.md` descreve "dois modais Drupal" no menu global, que foi substituído pela gaveta. | Busca por esses termos nos dois arquivos, sem resultado. | Atualizar com a referência de `MESSENGER.md` (ou apontar para ela). |
| A10 | baixa | Tema | Mapeados em `PortalUiHooks` sem emissor no módulo: `messenger-preview-inbox-link` e `messenger-read-status`. | Busca em todo `web/modules/custom`: nenhuma ocorrência fora do mapa. | Remover do mapa após confirmar. |
| A9 | baixa | Tema | Nome de componente ambíguo: `messenger-item` é a bolha de mensagem e também o arquivo de CSS da gaveta (A3). | `components/content/messenger-item/`. | Resolve-se com A3. |

### 3.1 O que está correto (sem achado)

- O submódulo não usa `\Drupal::` em `src/` e injeta serviços por construtor ou `#[Autowire]`.
- Todos os tokens `data-portal-ui` que o módulo emite (12 tokens) têm mapeamento em `PortalUiHooks`.
- O tema não contém rota, serviço, lógica de Domain nem `#component: aculta420:*` no módulo.
- A caixa "Opções" do perfil é oferecida ao tema pelo módulo `aculta_user` (hook de alter), não pelo tema.
- Os gates passam: `validate-module-theme-boundary` (1028 verificações), `validate-turnstile-exceptions`, `validate-public-slugs`, `validate-aculta-portal-drupal11` e `validate-aculta420-foundation` (344 verificações).

## 4. Ordem sugerida de correção

1. A4 (remoção de CSS morto) e A5 (confirmar e remover mapeamento morto): baixo risco, feito no tema.
2. A1 (classes de apresentação no módulo): trocar por tokens e ampliar o gate. É a maior dívida de fronteira.
3. A2 e A3 (SDC da confirmação e separação do CSS): depois de A1, porque tocam os mesmos elementos.
4. A6 e A7 (JS de alertas e suíte versionada).
5. A8 (documentação do submódulo): feito junto com a documentação entregue neste trabalho.

As correções A1 a A7 são código e exigem versão e PR próprios. Este documento não altera código do Messenger.
