# Envios de formulário convertidos de `#ajax` para HTMX

Registro da conversão dos envios de formulário que usavam Drupal AJAX (`#ajax`)
para HTMX (`core/drupal.htmx`, ADR-050), no escopo de
`web/modules/custom/aculta_portal/**` (fora de `tests/`).

## Padrão usado

- atributos gerados por `Drupal\Core\Htmx\Htmx` (`data-hx-post`,
  `data-hx-select`, `data-hx-target`, `data-hx-swap`);
- `onlyMainContent()` para receber só o conteúdo principal na requisição HTMX;
- o fragmento é recortado com `hx-select` do mesmo bloco que o callback AJAX
  devolvia;
- `core/drupal.htmx` é anexado por `Htmx::applyTo()`;
- `name`, `value`, `id` e `formnovalidate` dos botões foram preservados;
- as validações e os submit handlers do Form API continuam os mesmos; só o
  canal de resposta mudou;
- o envio sem JavaScript permanece um POST nativo do formulário.

**Nome único no botão.** O JS do Core envia só `_triggering_element_name`, sem
`_triggering_element_value`. Quando dois botões do mesmo formulário usam
`name="op"`, o Form API escolhe o **último** elemento com esse nome. Por isso os
botões com envio próprio têm `#name` próprio (`add_social_button`,
`remove_social_button_N`, `sortear`).

**Diálogos e vários alvos.** Quando o callback AJAX fechava o diálogo jQuery e
atualizava outro bloco (o formulário pai), a resposta HTMX devolve um fragmento
`hx-swap-oob` com o bloco a substituir e envia `HX-Trigger:
aculta:close-dialog`. O listener mínimo no tema
(`web/themes/custom/aculta420_v2/js/htmx-dialogs.js`) fecha o diálogo nativo ou
jQuery UI clicando no botão de fechar existente — sem jQuery novo. O mesmo JS
trata `aculta:cart-updated` (contador e rótulo do carrinho). A biblioteca
`aculta420_v2/htmx-dialogs` é carregada globalmente pelo tema.

## Convertidos

| # | Arquivo | Envio | Rota do `hx-post` | Fragmento | Teste |
| --- | --- | --- | --- | --- | --- |
| 1 | `aculta_linkweed/src/Form/SocialLinksSettingsForm.php` | "Remover" e "+ Adicionar botão" | `aculta_linkweed.social_links_settings` | `#aculta-social-links-settings` (`outerHTML`) | `aculta_events/tests` (padrão `EventFormDatesKernelTest`) |
| 2 | `aculta_paths/src/Form/SurfaceEditForm.php` | "Salvar" | `aculta_paths.editar` | `#aculta-paths-surface-edit`; `HX-Redirect` para `aculta_paths.form` | — |
| 3 | `aculta_events/src/Form/EventForm.php` | "Adicionar outra data" | `event_add` / `organizer_event_add` | `#event-dates-wrapper` | `aculta_events/tests/src/Kernel/EventFormDatesKernelTest.php` |
| 4 | `aculta_secrets_manager/src/Form/SecretEditForm.php` | "Salvar" | `aculta_portal.secrets_edit` | `#aculta-secret-edit`; erro devolve o popup; sucesso `HX-Redirect` para `aculta_portal.secrets_import` | `aculta_secrets_manager/tests/src/Kernel/SecretFormsHtmxKernelTest.php` |
| 5 | `aculta_secrets_manager/src/Form/SecretClearForm.php` | "Apagar valor" | `aculta_portal.secrets_clear` | `#aculta-secret-clear`; sucesso `HX-Redirect` para `aculta_portal.secrets_import` | `aculta_secrets_manager/tests/src/Kernel/SecretFormsHtmxKernelTest.php` |
| 6 | `aculta_events/src/Form/EventDateOrderForm.php` | "Salvar ordem" | `event_date_order` / `organizer_event_date_order` | `#aculta-event-date-order-form` (`outerHTML`); `#event-dates-list` em `hx-swap-oob`; `HX-Trigger: aculta:close-dialog` | `aculta_events/tests/src/Kernel/EventDateHtmxKernelTest.php` |
| 7 | `aculta_events/src/Form/EventDateCreateForm.php` | "Criar data" | `event_date_add` / `organizer_event_date_add` | `#aculta-event-date-form`; `#event-dates-list` OOB; `aculta:close-dialog` | `EventDateHtmxKernelTest.php` |
| 8 | `aculta_events/src/Form/EventDateEditForm.php` | "Salvar data" | `event_date_edit` / `organizer_event_date_edit` | `#aculta-event-date-edit-form`; `#event-dates-list` OOB; `aculta:close-dialog` | `EventDateHtmxKernelTest.php` |
| 9 | `aculta_events/src/Form/EventDateDeleteForm.php` | "Remover data" | `event_date_delete` / `organizer_event_date_delete` | `#aculta-event-date-delete-form`; `#event-dates-list` OOB; `aculta:close-dialog` | `EventDateHtmxKernelTest.php` |
| 10 | `aculta_events/src/Form/EventLocationModalForm.php` | "Buscar", "Limpar", "Selecionar", "Cadastrar novo local", "Salvar e selecionar", "Voltar para busca" | `location_modal` / `organizer_location_modal` (com `field_name`, `wrapper_id`, `is_multiple`, `target_id`) | `#aculta-location-modal-form-wrapper`; seleção/criação devolve o widget do formulário pai em `hx-swap-oob` + `aculta:close-dialog` | `aculta_events/tests/src/Kernel/EventModalHtmxKernelTest.php` |
| 11 | `aculta_events/src/Form/EventOrganizationModalForm.php` | Idem para organizações | `organization_modal` / `organizer_organization_modal` | `#aculta-organization-modal-form-wrapper`; widget do pai em OOB + `aculta:close-dialog` | `EventModalHtmxKernelTest.php` |
| 12 | `aculta_user/src/Form/UserPictureForm.php` | "Sortear novo avatar" (`#name` `sortear`) | `aculta_user.foto` | `[data-aculta-user-avatar]` (`outerHTML`); o rebuild devolve o avatar pendente | `aculta_user/tests/src/Kernel/UserPictureHtmxKernelTest.php` |
| 13 | `src/Cart/AddToCartAjax.php` | "Adicionar ao carrinho" (Commerce) | URL da página do produto (o formulário não tem `#action`) | `#portal-cart-status-*` (`innerHTML`); `#aculta-cart-offcanvas-body` OOB; `HX-Trigger: aculta:cart-updated` com contagem e rótulo | `tests/src/Kernel/Cart/AddToCartHtmxKernelTest.php` |
| 14 | `src/Hook/FormHooks.php` (`accountAjaxSubmit`) | "Salvar" dos formulários de conta no host `account` | rota atual do formulário | `#portal-account-<form_id>` (`outerHTML`); mensagens viajam dentro do wrapper | `tests/src/Kernel/Form/AccountHtmxKernelTest.php` |

Total: **14 envios convertidos** (o item 1 cobre dois botões).

Observações:

- `SurfaceEditForm` mantém o `#ajax` de `change` do seletor de modo; não é
  envio de formulário.
- Os formulários de data mantêm os submit handlers e o `#limit_validation_errors`;
  só o canal de resposta mudou. `EventDateCreateForm`/`Edit`/`Order`/`Delete`
  reutilizam `HtmxDateListResponseTrait`.
- `AddToCartAjax` deixou de usar `AjaxResponse`; `status_messages` não é
  renderizado nessa resposta, por isso a caixa de status viaja no próprio
  fragmento.
- Os botões "Cancelar"/"Fechar" dos modais deixaram de usar `#ajax`: são
  `#type => button` com `data-aculta-dialog-close`, tratado pelo JS do tema.

## Não convertidos

| Arquivo | Envio com `#ajax` | Motivo técnico |
| --- | --- | --- |
| `src/Form/RegisterPopupForm.php` | `actions.submit` | O `#ajax` só existe quando a requisição NÃO é HTMX: é o contrato da V1 (tema `aculta420`). A V2 já usa HTMX no mesmo formulário. Remover o fallback muda a regra de compatibilidade da V1 registrada no ADR-050. |
| `src/Form/LoginPopupForm.php` | `actions.submit` | Mesmo caso do cadastro. |
| `modules/aculta_gallery/src/Form/GalleryEmbedDialogForm.php` | "Criar e inserir" | O sucesso insere atributos no CKEditor 5 (`EditorDialogSave`) e fecha o diálogo do editor. A inserção no editor não é expressa por HTMX; regra 4. |
| `modules/aculta_forum/src/Form/ForumGalleryUploadDialogForm.php` | "Criar galeria e inserir" | Idem: upload + `EditorDialogSave` no CKEditor; regra 4. |
| `modules/aculta_forum/src/Form/ForumAttachmentUploadDialogForm.php` | "Enviar e inserir" | Idem: upload de anexo + `EditorDialogSave`; regra 4. |

### `#ajax` de campo ou de botão de widget (não são envios de formulário)

Continuam como estão por serem gatilhos de campo/widget, não envio:

- `src/Account/AccountAddressForm.php` — `country_code` (`change`).
- `src/Plugin/Commerce/CheckoutPane/AcultaCheckoutLogin.php` — `blur` do e-mail.
- `modules/aculta_donation/.../DonationCheckoutPane.php` — `change`.
- `modules/aculta_pagamentos_mercadopago/.../CheckoutPro.php` — `change`.
- `aculta_paths/src/Form/SurfaceEditForm.php` — seletor `modo` (`change`).
- `modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`
  — botão `#type => button` "Remover local", que troca o próprio widget.
- `modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`
  — botões de limpar/remover do widget.

## Links `use-ajax` mantidos

Continuam abrindo ou fechando o diálogo jQuery do Core (os formulários dentro
dele agora enviam por HTMX):

| Arquivo | Link |
| --- | --- |
| `aculta_paths/src/Controller/SurfaceListController.php` | Editar superfície |
| `aculta_user/src/Hook/PhotoEditorHooks.php` | Avatar / Alterar foto |
| `aculta_user/src/Social/SocialLinksCard.php` | Editar rede social |
| `aculta_secrets_manager/src/Controller/SecretsController.php` | Ações do cofre |
| `aculta_events/.../EventDateListBuilder.php`, `EventLocationModalWidget`, `EventOrganizationModalWidget` | Datas, locais e organizações |

## Validação

- `php -l` nos arquivos PHP alterados.
- `php scripts/validate-aculta420-ui-styles.php` e
  `php scripts/validate-aculta420-v2-foundations.php`.
- Testes Kernel criados (não executados nesta rodada por falta de
  `SIMPLETEST_DB`): `EventDateHtmxKernelTest`, `EventModalHtmxKernelTest`,
  `SecretFormsHtmxKernelTest`, `UserPictureHtmxKernelTest`,
  `AddToCartHtmxKernelTest`, `AccountHtmxKernelTest`.
