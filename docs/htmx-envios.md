# Envios de formulário convertidos de `#ajax` para HTMX

Registro da conversão dos envios de formulário que usavam Drupal AJAX (`#ajax`)
para HTMX (`core/drupal.htmx`, ADR-050), no escopo de
`web/modules/custom/aculta_portal/**` (fora de `tests/`).

## Padrão usado

Seguimos o padrão já existente no projeto (`RegisterPopupForm`,
`LoginPopupForm`, `AccountBioForm`, `SearchPopupForm`):

- atributos gerados por `Drupal\Core\Htmx\Htmx` (`data-hx-post`,
  `data-hx-select`, `data-hx-target`, `data-hx-swap`);
- `onlyMainContent()` para receber só o conteúdo principal na requisição HTMX;
- o fragmento é recortado com `hx-select` do mesmo bloco que o callback AJAX
  devolvia;
- `core/drupal.htmx` é anexado por `Htmx::applyTo()`;
- `name`, `value`, `id` e `formnovalidate` dos botões foram preservados (o
  `formnovalidate` foi acrescentado onde o AJAX dispensava a validação do
  navegador);
- as validações e os submit handlers do Form API continuam os mesmos; só o
  canal de resposta mudou;
- o envio sem JavaScript permanece um POST nativo do formulário, que recarrega
  a página (progressão).

A rota usada no `hx-post` é a própria rota dedicada do formulário, que devolve o
formulário/fragmento quando a requisição vem do HTMX. O JS do Core acrescenta
`_wrapper_format=drupal_htmx` e `_triggering_element_name`, então o Form API
identifica o botão acionado exatamente como no AJAX.

**Nome único no botão.** O JS do Core envia só `_triggering_element_name`, sem
`_triggering_element_value`. Quando dois botões do mesmo formulário usam
`name="op"` (padrão do Form API), o Form API escolhe o **último** elemento com
esse nome — normalmente o "Salvar". Por isso os botões dinâmicos do ACULTA Links
têm `#name` próprio (`remove_social_button_N` e `add_social_button`). Formulários
novos com mais de um envio precisam do mesmo cuidado.

## Convertidos

| # | Arquivo | Envio | Rota do `hx-post` | Bloco atualizado |
| --- | --- | --- | --- | --- |
| 1 | `aculta_linkweed/src/Form/SocialLinksSettingsForm.php` | botão "Remover" de cada linha | `aculta_linkweed.social_links_settings` | `#aculta-social-links-settings` — wrapper do formulário inteiro, que contém o `buttons_wrapper` e o `form_build_id` renovado (`hx-select` + `hx-swap="outerHTML"`) |
| 2 | `aculta_linkweed/src/Form/SocialLinksSettingsForm.php` | botão "+ Adicionar botão" | `aculta_linkweed.social_links_settings` | `#aculta-social-links-settings` (`hx-select` + `hx-swap="outerHTML"`) |
| 3 | `aculta_paths/src/Form/SurfaceEditForm.php` | botão "Salvar" | `aculta_paths.editar` (com `purpose`) | `#aculta-paths-surface-edit` (erro de validação); `HX-Redirect` para `aculta_paths.form` no sucesso |
| 4 | `modules/aculta_events/src/Form/EventForm.php` | botão "Adicionar outra data" (`add_more_event_date`) | `aculta_events.event_add` ou `aculta_events.organizer_event_add`, conforme o purpose (`EventRouteContext::route`) | `#event-dates-wrapper` — contêiner `date_location.dates`, trocado por `outerHTML` (`hx-select` + `hx-swap`) |

Total: **4 envios convertidos**.

Observações:

- `SocialLinksSettingsForm` continua usando os callbacks
  `addButton` e `removeButton` de `SocialLinksFormCallbacks`. O callback
  `buttonsAjax`, que só existia para montar a resposta AJAX, foi removido.
- `SurfaceEditForm` reutiliza `validateForm()` e `submitForm()`; `ajaxSalvar`
  foi removido. O seletor de modo mantém o `#ajax` de `change` (não é envio de
  formulário) e o `validateForm()` continua ignorando a reconstrução
  intermediária.
- `EventForm` mantém o submit handler `addDateRow` (incrementa `date_delta` e
  marca rebuild) e o `#limit_validation_errors`; só o canal de resposta mudou. O
  callback `ajaxRebuildDates`, que só existia para devolver o fragmento ao AJAX,
  foi removido. O `#ajax` de campo dos botões "Remover local/organização" nos
  widgets de campo continua fora de escopo.
- Os formulários sem JavaScript passam a seguir o redirect nativo no sucesso
  (`aculta_paths.form`), o mesmo destino que o AJAX fechava; nos botões
  dinâmicos do ACULTA Links, o POST nativo devolve a página inteira.

## Não convertidos

| Arquivo | Envio com `#ajax` | Motivo |
| --- | --- | --- |
| `src/Form/RegisterPopupForm.php` | `actions.submit` | O HTMX já está implementado para a V2. O `#ajax` restante é o fallback deliberado da V1 (tema `aculta420`), registrado no docblock e no ADR-050. Removê-lo mudaria o comportamento da V1. |
| `src/Form/LoginPopupForm.php` | `actions.submit` | Mesmo caso do cadastro: HTMX na V2 e `#ajax` como fallback da V1. |
| `src/Hook/FormHooks.php` (`accountAjaxSubmit`) | `actions.submit` de todos os formulários de conta | Altera muitos formulários de uma vez no host `account`; a resposta AJAX mantém o formulário e as mensagens na mesma área e desliga os redirects. A conversão é a F2 do ADR-050 e precisa de validação em navegador no Runtime (foco/erros/redirect), que não está disponível nesta tarefa sem SIMPLETEST_DB. |
| `src/Cart/AddToCartAjax.php` | `actions.submit` do formulário de adicionar ao carrinho (Commerce) | Um único envio atualiza três alvos (caixa de status, corpo do painel do carrinho e contador/rótulo do gatilho) com comandos distintos. A conversão exige contrato de vários fragmentos/OOB e autorização da loja (F4 do ADR-050). |
| `modules/aculta_events/src/Form/EventDateOrderForm.php` | "Salvar ordem" | O callback fecha o diálogo jQuery e substitui `#event-dates-list` no formulário pai. HTMX não tem comando para fechar o diálogo jQuery, e um `HX-Redirect` perderia os campos não salvos do formulário pai. |
| `modules/aculta_events/src/Form/EventDateCreateForm.php` | "Criar data" | Mesmo caso: fecha o diálogo e atualiza a lista de datas do evento pai; exige coordenação de dois blocos e do diálogo. |
| `modules/aculta_events/src/Form/EventDateEditForm.php` | "Salvar data" | Idem: fecha o diálogo e substitui `#event-dates-list`. |
| `modules/aculta_events/src/Form/EventDateDeleteForm.php` | "Remover data" | Idem: fecha o diálogo e substitui `#event-dates-list`. |
| `modules/aculta_events/src/Form/EventLocationModalForm.php` | `ajaxCreateLocation` (submit) e demais `#ajax` de botões/campos | Modal aberto por `use-ajax`; o submit cria o local, fecha o diálogo e reconstrói o widget do campo no formulário pai. Depende do diálogo jQuery e de vários alvos. |
| `modules/aculta_events/src/Form/EventOrganizationModalForm.php` | `ajaxCreateOrganization` (submit) e demais `#ajax` de botões/campos | Mesmo caso do formulário de local. |
| `modules/aculta_gallery/src/Form/GalleryEmbedDialogForm.php` | "Criar e inserir" | O callback devolve HTML para o diálogo e insere o código no CKEditor por comandos AJAX (`HtmlCommand` + fechamento do diálogo). HTMX não expressa a inserção no editor. |
| `modules/aculta_forum/src/Form/ForumGalleryUploadDialogForm.php` | "Criar galeria e inserir" | Idem: upload + inserção no CKEditor por comandos AJAX; requer a migração do diálogo para `<dialog>` (F3). |
| `modules/aculta_forum/src/Form/ForumAttachmentUploadDialogForm.php` | "Enviar e inserir" | Idem: upload de anexo e inserção no CKEditor por comandos AJAX. |
| `modules/aculta_user/src/Form/UserPictureForm.php` | "Sortear novo avatar" | O callback substitui a prévia dentro do diálogo jQuery (`.ui-dialog [data-aculta-user-avatar]`). O popup de foto inteiro está na F2 do ADR-050 (migração para `<dialog>`); converter só o botão misturaria os dois mecanismos e exigiria token CSRF na rota `aculta_user.avatar_reroll`. |
| `modules/aculta_secrets_manager/src/Form/SecretEditForm.php` | "Salvar" | O sucesso só fecha o diálogo jQuery (`CloseModalDialogCommand`), sem destino de página; não há comando HTMX equivalente sem antes migrar o diálogo. Área sensível: não alterar sem validação no Runtime. |
| `modules/aculta_secrets_manager/src/Form/SecretClearForm.php` | "Apagar valor" | Mesmo caso do SecretEditForm. |
| `modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php` | botão "Remover local" | É `#type => button` dentro de um widget de campo, não um envio de formulário; a troca é do próprio widget e o formulário depende do diálogo jQuery do local. |
| `modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php` | botões de limpar/remover | Mesmo caso: botões de widget, não envio de formulário. |

### `#ajax` fora do escopo (não são envios de formulário)

Continuam como estão, por serem gatilhos de campo (change/blur/click) e não
envios: `AccountAddressForm` (país), `AcultaCheckoutLogin` (blur do e-mail),
`DonationCheckoutPane` e `CheckoutPro` (change; F5, pagamento) e o seletor
`modo` de `SurfaceEditForm`.

## Links `use-ajax` mantidos

Estes links continuam abrindo ou fechando o diálogo jQuery do Core, ou
disparam resposta AJAX de múltiplos alvos. HTMX não tem comando equivalente sem
antes migrar o diálogo para `<dialog>` (F2/F3 do ADR-050), então ficam como
exceção registrada:

| Arquivo | Link | Motivo |
| --- | --- | --- |
| `aculta_paths/src/Controller/SurfaceListController.php` | Editar superfície (agora SDC `size: small`) | Abre o formulário em `data-dialog-type="modal"` (jQuery). O botão é SDC; só o modal permanece no diálogo do Core. |
| `aculta_user/src/Hook/PhotoEditorHooks.php` | Avatar / Alterar foto | Abre o diálogo jQuery e depende de upload/resposta AJAX. |
| `aculta_user/src/Social/SocialLinksCard.php` | Editar rede social | Abre o diálogo jQuery. |
| `aculta_user/src/Controller/AvatarRerollController.php` | Sortear novo avatar | Resposta AJAX troca todos os avatares do painel. |
| `aculta_secrets_manager/src/Controller/SecretsController.php` | Ações do cofre | Diálogo jQuery e área sensível. |
| `aculta_messenger/src/Support/ConfirmDialog.php` | Ações de conversa | Abre/fecha `<dialog>` nativo controlado por JS. |
| `aculta_portal/src/Search/SearchUtility.php` e `PortalHooks.php` | Buscar / Entrar | Fallback deliberado da V1; a V2 usa HTMX nos popups equivalentes. |
| `aculta_events/.../EventDateListBuilder.php`, `EventLocationModalWidget`, `EventOrganizationModalWidget` | Datas, locais e organizações | Abrem diálogos jQuery e atualizam mais de um alvo. |

## Validação funcional (2026-11-08)

- `php -l` nos arquivos PHP alterados.
- `php scripts/validate-aculta420-ui-styles.php`, `validate-aculta420-v2-foundations.php` e `validate-aculta420-palette-schemes.php`: PASS.
- Teste de navegador em Firefox ESR visível (`DISPLAY=:0`), com conta de teste por `drush uli`: `tests/browser/transversal/aculta-htmx-envios/submit-forms.py`.
  - **SocialLinksSettingsForm**: "+ Adicionar botão" responde 200, troca o wrapper `#aculta-social-links-settings` e acrescenta os campos da nova linha sem recarregar a página.
  - **SurfaceEditForm**: "Salvar" com o título obrigatório vazio responde 200 e devolve o formulário com o estado de erro no fragmento, sem navegar nem gravar configuração.
- Evidência: `tests/browser/capturas/htmx-envios/2026-11-08/manifest.json`.
- Correção encontrada nesta validação: o botão "+ Adicionar botão" passou a ter `#name => add_social_button`. Sem isso, o `_triggering_element_name=op` compartilhado fazia o "Salvar" virar o disparador e o envio salvava a configuração em vez de adicionar a linha.

### `EventForm` (envio #4)

- `php -l web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventForm.php`: sem erros.
- `php scripts/validate-aculta420-ui-styles.php`: PASS.
- `php scripts/validate-aculta420-v2-foundations.php`: PASS.
- Sem teste de navegador nesta rodada: o botão "Adicionar outra data" depende de conta autenticada com permissão de criar Evento e do formulário completo de entidade. O `#ajax` era um bloco único (`#event-dates-wrapper`), sem diálogo nem CKEditor; a rota do `hx-post` é a mesma do próprio formulário (`event_add`/`organizer_event_add`).
