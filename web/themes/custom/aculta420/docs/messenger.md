# Messenger: guia de desenvolvimento do tema aculta420

Este guia é para quem mexe na **aparência** do Messenger. A parte funcional (rotas, regras, formulários, bloqueio, amizade) fica no módulo e está em [`docs/portal/MESSENGER.md`](../../../../../docs/portal/MESSENGER.md). A separação por camada está em [`docs/portal/MESSENGER-AUDIT.md`](../../../../../docs/portal/MESSENGER-AUDIT.md).

Regra central: **o tema não decide nada do Messenger.** Ele recebe dados prontos (URL, contagem, textos, render arrays, tokens) e escolhe classes, SDC e comportamento visual.

## 1. O que é do tema

| Item | Onde |
| --- | --- |
| Componentes SDC do Messenger | `components/patterns/messenger-*`, `components/content/messenger-*`, `components/feedback/messenger-state`, `components/feedback/unread-badge` |
| CSS da gaveta, da lista e do menu | `css/components/messenger-drawer.css` (biblioteca `messenger-drawers`) |
| CSS da bolha de mensagem | `components/content/messenger-item/messenger-item.css` |
| CSS dos outros componentes | a pasta de cada componente |
| JS da gaveta | `js/messenger-drawers.js` (library `messenger-drawers`) |
| JS dos contadores | `js/counters.js` |
| JS de "dispensar aviso" | `js/alerts-dismiss.js` (library global) |
| Tradução de tokens para classes | `src/Hook/PortalUiHooks.php` (`TOKENS`) |
| Mapeamento de rotas para SDC | `src/Hook/ThemeHooks.php` (`presentMessengerPage`, `presentMessengerTrigger`, `preprocessPrivateMessage`, `preprocessPrivateMessageThread`) |
| Marcação da barra | `templates/page.html.twig` (imprime `aculta_messenger_trigger`) |

## 2. O que não é do tema

- Rotas, controladores, formulários e validação (`aculta_messenger`).
- Regras de amizade, bloqueio e privacidade (`aculta_user` e `aculta_messenger`).
- Qualquer `\Drupal::` ou leitura de Domain/hostname. A barra recebe `domain_presentation` e os dados do Messenger já resolvidos.
- Texto de regra de negócio. Os textos que o usuário lê vêm do módulo (com `t()`); o tema só os apresenta.

## 3. Dados que o tema recebe

| Variável | Vinda de | Forma | Usado por |
| --- | --- | --- | --- |
| `aculta_messenger` | `aculta_messenger` → `MessengerBarHooks::preprocessPage()` no módulo | `{ url, unread_count, all_url }` | `ThemeHooks::presentMessengerTrigger()` |
| `aculta_messenger_trigger` | `ThemeHooks::presentMessengerTrigger()` | render array `messenger-trigger` | `page.html.twig` |
| `aculta_messenger_outgoing` | `PrivateMessagePresentationHooks` | bool | `messenger-item` (`outgoing`) |
| `aculta_messenger_sender_avatar` | `PrivateMessagePresentationHooks` | render array do avatar | `messenger-item` (`avatar`) |
| `aculta_messenger_read_status` | `PrivateMessagePresentationHooks` | estado nativo de leitura | `messenger-read-status` |
| `aculta_alerts` | `MessengerBarHooks::preprocessPage()` | `{ url, count, count_kind, all_url }` | `ThemeHooks::presentAlertsTrigger()` |

Regra: o tema **não** lê `private_message_thread`, `aculta_friendship`, `user.data` nem serviços. Se precisar de um dado novo, peça ao módulo uma variável neutra e documente aqui.

## 4. Componentes SDC

| Componente | Props | Slots | Usado em |
| --- | --- | --- | --- |
| `patterns/messenger-trigger` | `href` (obrigatório), `count` | — | Barra multidomínio (`presentMessengerTrigger`) |
| `patterns/messenger-list` | — | `content` | Lista de conversas (`aculta_messenger.preview`, `presentMessengerPage`) |
| `content/messenger-compose` | — | `form` | Compositor de primeira mensagem |
| `content/messenger-item` | `outgoing` | `avatar`, `content` | Bolha de mensagem (`preprocessPrivateMessage`) |
| `content/messenger-notification` | — | `content` | Notificação de mensagem para o dono |
| `feedback/messenger-state` | `state` (empty, error, loading) | `content` | Estados vazio, erro e carregando |
| `feedback/unread-badge` | `count` | — | Contador de não lidas |
| `patterns/messenger-read-status` | `unread` (obrigatório), `notice` | — | Rótulo acessível de leitura (sem uso atual pelo módulo; ver nota abaixo) |

Os *stories* de cada componente ficam na mesma pasta (`*.story.yml`). Use-os para conferir visual antes de publicar.

Ao criar um componente novo, siga `docs/portal/COMPONENT-DESIGN-SYSTEM.md`: props simples e slots para render arrays; sem regra de negócio no Twig.

## 5. Tokens `data-portal-ui`

O módulo marca o elemento com um token semântico. `PortalUiHooks::TOKENS` troca o token por classes. Token sem entrada no mapa não gera classe.

| Token | Classes | Onde o módulo põe |
| --- | --- | --- |
| `conversation-action` | `aculta-messenger-options__action` | Botões do menu "Opções" e das janelas |
| `conversation-notice-action` | `aculta-messenger-notice__action` | Ação do aviso de bloqueio/privacidade |
| `messenger-back-link` | `aculta-messenger-back-link` | "← Todas as conversas" |
| `messenger-drawer-conversation` | `aculta-messenger-drawer-conversation` | Contêiner da conversa na gaveta |
| `messenger-conversation-toolbar` | `aculta-messenger-conversation-toolbar` | Barra da conversa (voltar e Opções) |
| `messenger-conversation-actions` | `aculta-messenger-conversation__actions` | Menu "Opções" de uma linha da lista |
| `messenger-options` | `aculta-messenger-options` | Caixa "Opções" (acordeão) |
| `messenger-options-summary` | `aculta-messenger-options__summary` | Cabeçalho do acordeão |
| `messenger-options-icon` | `bi bi-list` | Ícone do acordeão |
| `messenger-options-label` | `aculta-messenger-options__label` | Rótulo "Opções" (oculto em telas estreitas) |
| `messenger-options-panel` | `aculta-messenger-options__panel` | Painel de itens |
| `messenger-options-item` | `aculta-messenger-options__item` | Item "Iniciar conversa" do perfil |
| `messenger-options-status` | `aculta-messenger-options__status` | Linha de resultado ou pergunta no menu |
| `messenger-notice` | `aculta-messenger-notice` | Aviso no lugar da caixa de resposta |
| `messenger-sender` | `aculta-messenger-sender aculta-messenger-conversation` | Linha de conversa na lista |
| `messenger-sender-unread` | `is-unread` | Linha com mensagem não lida |
| `messenger-sender-link` | `aculta-messenger-sender__link` | Link que abre a conversa |
| `messenger-sender-date` / `messenger-sender-name` | `aculta-messenger-sender__date` / `__name` | Data e nome na linha |
| `messenger-sender-avatar` | `aculta-messenger-sender__avatar` | Avatar na linha |
| `notice-read-status` | `aculta-messenger-read-status` | Aviso persistente já lido |
| `notice-unread-status` | `aculta-messenger-read-status aculta-messenger-read-status--unread` | Aviso persistente não lido |
| `notice-admin-item` / `-intro` / `-list` / `-table` | `admin-item` / `aculta-notice-admin-intro` / `admin-list aculta-notice-admin-list` / `aculta-notice-admin-table` | Administração de avisos |
| `new-conversation-link` | `btn btn-primary aculta-messenger-new-conversation` | Botão "Iniciar nova conversa" |
| `new-conversation-links` | `d-flex flex-wrap gap-2 mt-2` | Linha de botões do compositor |
| `new-conversation-back` | `btn btn-outline-primary` | "Voltar para o início" |
| `new-conversation-cancel` | `btn btn-outline-secondary` | "Cancelar" |
| `new-conversation-submit` | `btn btn-primary` | "Enviar" |
| `new-conversation-message` | `form-control` | Campo da mensagem |
| `new-conversation-results` | `list-group` | Lista de resultados da busca |
| `new-conversation-result` | `list-group-item list-group-item-action` | Cada resultado |
| `search-preview`, `search-preview-input` | ver `docs/portal/TOKENS-E-ATRIBUTOS.md` | Busca de membros |

Os tokens `messenger-preview-inbox-link` e `messenger-read-status` foram removidos do mapa: nenhum código os emitia (auditoria A10).

### Quando o token não vira classe

O mapeamento só roda em elementos que têm **template de tema** (`preprocess_container`, `preprocess_html_tag`, `preprocess_item_list`, `preprocess_input`, `preprocess_form_element`, `preprocess_menu` e os próprios `aculta_portal_element`). Elementos que o Core monta como string no pré-render não passam por esse hook:

- `#type => link` (vira string no `LinkGenerator`): **não recebe classe**. Para links com token, o módulo usa `Drupal\aculta_portal\Render\PortalLinkElement::build()`, que gera o elemento `aculta_portal_element`.
- `#type => html_tag` (monta a string no pré-render): **não recebe classe** (o token fica no HTML). Ver a tabela de tokens acima; se precisar de classe, use um elemento com template.
- `#type => submit`, `#type => button` do Form API (template `input`): recebem classe.

Se um token "não pinta", confira primeiro o elemento que o módulo montou. O erro mais comum é tratar `link` como se tivesse template.

### Como adicionar um token

1. Peça ao módulo o nome semântico (ex.: `conversation-note`). O módulo marca `data-portal-ui="conversation-note"`.
2. Em `src/Hook/PortalUiHooks.php`, inclua `'conversation-note' => ['classe-do-tema', ...]` em `TOKENS`, na seção do Messenger.
3. Confirme que o elemento tem template (seção acima). Se não tiver, combine com o módulo outro elemento.
4. Atualize a tabela acima e o `docs/portal/TOKENS-E-ATRIBUTOS.md`.
5. Rode `php scripts/validate-module-theme-boundary.php`.

## 6. Atributos `data-aculta-*` e JS

`js/messenger-drawers.js` é o único arquivo de comportamento da gaveta. Ele usa `once()` e `Drupal.attachBehaviors`, então novo conteúdo carregado por `fetch` ganha comportamento sem recarregar a página.

| Atributo | Papel | Comportamento |
| --- | --- | --- |
| `data-aculta-drawer-url` | Gaveta (`messenger-trigger`) | URL carregada ao abrir |
| `data-aculta-drawer-count` | Gaveta | `messages` ou `alerts`: qual contador atualizar |
| `data-aculta-drawer-back` | "← Todas as conversas" | Volta à lista dentro da gaveta |
| `data-aculta-conversation-url` | Links e botões | Carrega a visão na gaveta por `fetch` |
| `data-aculta-conversation-view` | Conversa | Rola até a última mensagem |
| `data-aculta-drawer-action` | Formulário "Opções" | Envia por `fetch` e substitui a gaveta |
| `data-aculta-new-conversation` | Busca de membro | Impede Enter de enviar a busca |
| `data-aculta-new-conversation-form` | Primeira mensagem | Envia por `fetch` |
| `data-aculta-open-conversation` | Perfil: "Iniciar conversa" | Abre a gaveta na nova conversa |
| `data-aculta-dialog` | Janela de confirmação | Nome: `ban` ou `remove` |
| `data-aculta-dialog-open` | Botão do menu | Abre a janela com esse nome (`preventDefault`: não envia o formulário) |
| `data-aculta-dialog-close` | "Cancelar" da janela | Fecha a janela |
| `data-aculta-alert-dismiss` | Aviso | Dispensa o aviso e atualiza o contador (`alerts-dismiss.js`) |
| `data-aculta-reply-form` | Formulário de resposta | Seletor do CSS e do envio por `fetch` |

Pontos de atenção:

- O JS expõe `drawer.acultaNavigate(url)` no elemento da gaveta. É assim que o perfil abre a nova conversa. Não renomeie sem atualizar `data-aculta-open-conversation`.
- Os botões da janela de confirmação são `input type=submit` no Form API. O clique é cancelado no JS (`preventDefault`). Sem JS, a janela não abre.
- O diálogo é um `<dialog>` nativo dentro do formulário. A marcação é montada com `inline_template` no módulo (achado A2 da auditoria); o estilo fica em `messenger-item.css`.

## 7. CSS

| Arquivo | Escopo | Carregado por |
| --- | --- | --- |
| `css/components/messenger-drawer.css` | Gaveta, lista de conversas, menu "Opções", avisos e janela de confirmação | Biblioteca `messenger-drawers` |
| `components/content/messenger-item/messenger-item.css` | Bolha de mensagem | Componente (mesmo nome) e biblioteca `messenger-drawers` |
| `components/content/messenger-compose/messenger-compose.css` | Compositor | Componente |
| `components/content/messenger-notification/messenger-notification.css` | Notificação | Componente |
| `components/feedback/messenger-state/messenger-state.css` | Estados | Componente |
| `components/patterns/messenger-list/messenger-list.css` | Lista | Componente |
| `components/patterns/messenger-read-status/messenger-read-status.css` | Rótulo de leitura | Componente |
| `components/patterns/messenger-trigger/messenger-trigger.css` | Gatilho da barra | Componente |

Regras do projeto para o CSS do Messenger:

- Use **tokens semânticos** (`var(--aculta-surface-raised)`, `var(--aculta-text-primary)`, `var(--aculta-border-default)`…). Não use valores de paleta.
- Modo escuro é troca de tokens em `css/tokens.css`. Não crie seletor `dark` fora dali.
- Nada de cor em hexadecimal, `hwb`, `lab`, `lch`, `oklab`, `oklch`, `color()`, `color-mix()` ou `device-cmyk()` em declarações de cor (o gate rejeita).
- Layout igual nos dois modos e em telas de 390 px. Teste as duas larguras.
- Os seletores do Messenger são classes de token (`aculta-messenger-*`) ou atributos de comportamento (`data-aculta-*`, `data-aculta-reply-form`). Não crie classe de design no módulo.

## 8. Hooks PHP do tema que tocam o Messenger

O inventário de todos os hooks e classes do tema fica em
[`classes-hooks.md`](classes-hooks.md). Esta tabela registra somente o fluxo
específico do Messenger e seus pontos de apresentação.

| Classe | Hook | Método | Para que serve |
| --- | --- | --- | --- |
| `ThemeHooks` | `preprocess_page` | `preprocessPage` | Chama `presentMessengerPage` e `presentMessengerTrigger`. |
| `ThemeHooks` | — | `presentMessengerPage(array &$variables, ?string $routeName)` | Mapeia a rota `aculta_messenger.preview` → `messenger-list` e a criação nativa → `messenger-compose`. |
| `ThemeHooks` | — | `presentMessengerTrigger(array &$variables)` | `aculta_messenger` → render array `messenger-trigger`. |
| `ThemeHooks` | `preprocess_private_message_thread` | `preprocessPrivateMessageThread` | Estado de leitura acessível (`messenger-read-status`). |
| `ThemeHooks` | `preprocess_private_message` | `preprocessPrivateMessage` | Avatar e direção → `messenger-item`. |
| `InputHooks` / `PortalUiHooks` | `preprocess_input` (`InputHooks` delega a `PortalUiHooks::preprocess()`); `preprocess_container`, `preprocess_html_tag`, `preprocess_item_list`, `preprocess_form_element`, `preprocess_menu`, … | `preprocess` / `preprocessMenu` | Troca tokens por classes (`TOKENS`). |
| `PortalUiHooks` | `preprocess_details` (ordem última) | `preprocessDetails` | Mapeia a caixa "Opções" depois do acordeão do Bootstrap. |

Ordem de hooks: o módulo `aculta_messenger` prepara as variáveis neutras em
`preprocess_page`; o tema converte esses dados em componentes durante o mesmo
hook OOP. **Não crie** `template_preprocess_*` legado.

## 9. Receitas

### Mudar a cor do botão "Cancelar" da nova conversa

1. Em `PortalUiHooks::TOKENS`, altere `new-conversation-cancel`.
2. `php vendor/drush/drush/drush.php cr`.
3. Abra a nova conversa e confira no navegador (classes e cor).

### Mudar a aparência da janela de confirmação

1. Edite as regras `dialog[data-aculta-dialog]` em `messenger-item.css`.
2. Confira os dois casos: bloquear (`data-aculta-dialog="ban"`) e desfazer amizade (`data-aculta-dialog="remove"`).

### Criar um componente de estado novo

1. Crie `components/feedback/messenger-state/messenger-state.<nome>.story.yml` com o estado.
2. Se o estado for novo, combine com o módulo qual valor `state` ele envia.

## 10. Verificação antes de entregar

- `php scripts/validate-aculta420-foundation.php` (via `php vendor/drush/drush/drush.php scr`).
- `php scripts/validate-module-theme-boundary.php` (fronteira de classes e tokens).
- Navegador, em desktop e em 390 px: abrir a gaveta, "Opções", confirmação, nova conversa, perfil.
- Registre o resultado em `docs/portal/TESTES-NAVEGADOR.md`.

## 11. Pendências do tema para o Messenger

Ver `MESSENGER-AUDIT.md`, seção 3: A3 (separar o CSS do painel), A4 (regras mortas), A5 (mapeamento de rotas nativas), A6 (JS de alertas dentro da gaveta), A9 (nome do componente `messenger-item`).

## B4-02: consistência da gaveta AJAX

O JS do tema verifica se a resposta ainda corresponde ao `AbortController` ativo antes de substituir o conteúdo da gaveta. Assim, fechar a gaveta ou navegar para outra conversa não deve permitir que respostas antigas sobrescrevam o estado atual. Testes com AJAX e rede lenta ainda são exigidos no Runtime. Ver [B4-02](theme-ux-0.8-b4-02-ajax-drawer-race.md).
