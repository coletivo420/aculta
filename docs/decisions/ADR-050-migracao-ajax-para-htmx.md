# ADR-050 — Migração do AJAX do ACULTA para htmx

- **Status:** aceito pelo responsável em 2026-11-05. Execução **DEFERRED** no Homelab; nenhum código desta decisão foi testado nesta sessão.
- **Data:** 2026-11-05.
- **Relaciona-se com:** ADR-044 (opção C, htmx), ADR-045 (um único módulo de AJAX; sem Core fora do escopo), ADR-046 (V2), ADR-048 (popup de foto), ADR-049 (AJAX do Studio, revisado por este ADR), `docs/portal/CROSS-DOMAIN-REQUEST-POLICY.md`, `docs/portal/PAYMENT-DOMAIN-POLICY.md`.

## 1. Contexto

- O ADR-049 escolheu Fetch próprio (mecanismo B). O responsável decidiu migrar para htmx (mecanismo C), por ser o caminho do próprio Core (11.3 em diante, `Drupal\Core\Htmx\Htmx`, library `core/drupal.htmx`).
- O escopo é **todo AJAX de código ACULTA**: tema V2 e módulos `aculta_portal`. Não entra nada que seja do Core ou de módulo contrib.
- Verificado em fontes públicas (change records do Core 11.3, documentação da classe `Htmx`): os atributos `data-hx-*`, o método `onlyMainContent()` (resposta só do conteúdo), o `FormBuilder` tratando requisições htmx como as do AJAX da API atual, e a ausência de anúncio de remoção do `#ajax`.
- Não verificado (depende do Homelab): se a classe `Htmx` é `@internal`, como o CSRF é tratado em formulário e o comportamento completo de `FormBuilder` com htmx.

## 2. Decisões

1. **Mecanismo: htmx (C).** Revisa o ADR-049, itens 1, 2, 4, 6 e 8, e a seção 8 que o registrava como alternativa. O item 3 (escopo) e o item 5 (navegação entre purposes por link comum) continuam valendo.
2. **Escopo:** o AJAX de código ACULTA. Fora do escopo, sem mudança: Core, módulos contrib (incluindo o `#ajax` do Commerce, do Views e dos modais contrib), `vendor/`, e código de servidor sem interface (ICS, IMAP, deployer, H5P).
3. **Pagamento e checkout (fase F5):** só com autorização explícita por tarefa, pela `PAYMENT-DOMAIN-POLICY.md` e pela regra de não mexer em pagamento sem pedido.
4. **Módulo JavaScript:** o ADR-045 pede um único módulo. Com htmx, o único arquivo próprio é `studio-htmx.js` (nome a confirmar), que trata somente o que o htmx não faz: foco após troca, `document.title`, `aria-live` e o descarte de resposta antiga (equivalente ao `AbortController` da V1).
5. **Popup de foto e demais popups:** `<dialog>` nativo, com conteúdo trazido por `hx-get` e envio por `hx-post`. Sem `core/drupal.dialog.ajax` e sem jQuery.
6. **Resposta de fragmento:** rotas de UI da ACULTA devolvem fragmento com `onlyMainContent()` quando a requisição é htmx. Sem JavaScript, a mesma rota responde a página completa (progressão).
7. **Cabeçalho privado:** respostas de tela de conta e de Studio são privadas, com `Cache-Control: private` e `Vary: Cookie`.
8. **Enquanto a F0 não estiver validada, nenhum módulo muda** de mecanismo.

## 3. Inventário e fase de cada item

Itens de código ACULTA que usam AJAX, `fetch` de UI ou `#ajax`/`use-ajax`, em 2026-11-05.

### F1 — Tema V2 e login

| Arquivo | Papel | Ação |
| --- | --- | --- |
| `web/themes/custom/aculta420_v2/components/multidomain-bar/multidomain-bar.js` | Popup de login (Fetch) | `hx-get` e `hx-post` no `<dialog>` |
| `web/themes/custom/aculta420_v2/js/messenger-drawers.js` | Gavetas de mensagens e avisos (479 linhas) | `hx-get` por gaveta; JS fica com foco e `aria-live` |
| `web/themes/custom/aculta420_v2/js/utility-dialogs.js` | Diálogos utilitários | conferir e migrar |
| `aculta_portal/src/Form/LoginPopupForm.php` | Formulário do popup de login | fragmento para htmx |
| `aculta_portal/src/Form/RegisterPopupForm.php` | Formulário do popup de cadastro | fragmento para htmx |
| `aculta_portal/src/Form/PortalFormCallbacks.php` | Callbacks de `#ajax` | migrar para `Htmx` |
| `aculta_portal/src/Hook/FormHooks.php`, `Hook/PortalHooks.php` | Anexam AJAX a formulários | ajustar atributos |
| `aculta_portal/src/EventSubscriber/DomainPurposeRequestSubscriber.php` | Tratamento de requisição por purpose | conferir que htmx não quebra o redirecionamento |
| `aculta_portal/src/Search/SearchUtility.php`, `themes/.../js/search-preview.js` | Busca com prévia | migrar para `hx-get` |

### F2 — Studio e conta

| Arquivo | Papel | Ação |
| --- | --- | --- |
| `aculta_studio/js/studio-navigation.js` | Troca de tela do Studio | links com `hx-get` e `hx-push-url`; remover o JS próprio de `pushState` |
| `aculta_studio/js/account-navigation.js` | Sub-navegação da conta | idem |
| `aculta_studio/src/Controller/StudioProfileController.php` | Perfil no Studio | fragmento |
| `aculta_user/js/photo-flow.js`, `src/Form/UserPictureForm.php`, `src/Controller/AvatarRerollController.php`, `src/Hook/PhotoEditorHooks.php`, `src/Access/AccountModalAccess.php` | Popup de foto (sorteio, envio, recorte) | `<dialog>` com htmx; remove `use-ajax` |
| `aculta_user/js/account-bio-form.js`, `src/Bio/AccountBioCard.php` | Biografia da conta (Fetch) | `hx-post` |
| `aculta_user/src/Social/SocialLinksCard.php` | Redes sociais da conta | fragmento |
| `aculta_portal/src/Account/AccountAddressForm.php` | Endereço (ACCOUNT-ADDRESS) | fragmento; regra de país `BR` preservada |

### F3 — Módulos do Portal

| Módulo | Arquivos | Ação |
| --- | --- | --- |
| `aculta_events` | 9 (formulários de data, local e organização; `EventLocationWidgetController`; `EventDateListBuilder`) | fragmento por formulário |
| `aculta_forum` | `ForumAttachmentUploadDialogForm`, `ForumGalleryUploadDialogForm` | `<dialog>` com htmx |
| `aculta_gallery` | `GalleryEmbedDialogForm` | `<dialog>` com htmx |
| `aculta_paths` (administração, MAIN) | `SurfaceEditController`, `SurfaceListController`, `SurfaceEditForm` | fragmento; só no MAIN |
| `aculta_secrets_manager` (administração, MAIN) | `SecretsController`, `SecretEditForm`, `SecretClearForm` | fragmento; só no MAIN; sem segredo na resposta |
| `aculta_links` e `aculta_linkweed` | `SocialLinksSettingsForm` (nos dois) | **conferir duplicidade**: dois módulos com o mesmo formulário |

### F4 — Carrinho e frete (loja)

| Arquivo | Ação |
| --- | --- |
| `aculta_portal/src/Cart/AddToCartAjax.php` | migrar para `hx-post` no botão e fragmento de status (`portal-cart-status`) |
| `aculta_commerce/modules/aculta_commerce_melhorenvio/assets/shipping-calculator.js` | `hx-get` no campo de CEP; exige autorização (loja) |

### F5 — Pagamento e checkout (autorização explícita por tarefa)

| Arquivo | Ação |
| --- | --- |
| `aculta_donation/src/Plugin/Commerce/CheckoutPane/DonationCheckoutPane.php`, `DonationItemPaneBase.php` | só com autorização |
| `aculta_pagamentos_mercadopago/src/Plugin/Commerce/PaymentGateway/CheckoutPro.php` | só com autorização |
| `aculta_portal/src/Plugin/Commerce/CheckoutPane/AcultaCheckoutLogin.php` | só com autorização |

### Não migrar (fora do escopo, ou não é AJAX de interface)

- `aculta_portal_cep/js/cep-address.js`: consulta de CEP que preenche campos. Não é troca de tela; avaliar na F2, com resposta de JSON ou `hx-get` com trocas fora do fluxo (`hx-swap-oob`).
- `aculta_statistics/js/wiki-read.js`: envio de leitura por `fetch` com `keepalive`. É telemetria: não muda sem revisão de consentimento.
- `aculta_calendar/js/dhtmlx/dhtmlxscheduler.js`: biblioteca de terceiros vendorizada. Decidir separadamente.
- `aculta_calendar` (ICS), `aculta_mailer` (IMAP), `aculta_deployer` (verificação), `aculta_lms_h5p`: não são interface; `fetch` de servidor.

## 4. Consequências

- Dois mecanismos vão conviver durante a migração: htmx e `#ajax` do Core/contrib (Commerce, Views, modais contrib). O ADR-045 deixa de valer como "um único mecanismo". Vale: **um único módulo próprio de comportamento**.
- Cada módulo do Portal que muda de `#ajax` para htmx muda o contrato da resposta. Isso exige PR própria com gate.
- A migração do Drupal 12 (htmx 4) pode exigir ajustes nos atributos. Por isso os atributos ficam concentrados nos templates e nos `Htmx` builders, não em JavaScript.

## 5. Fases e critério de aceite

| Fase | Entrega | Critério |
| --- | --- | --- |
| F0 | Confirmar a API `Htmx` no `core/lib` do 11.5 no Homelab; prova com um popup e um formulário; CSRF e `@internal` verificados | nota com saída de teste; ADR-050 atualizado |
| F1 | Tema V2 e login | login, gavetas e busca funcionam com e sem JavaScript; foco e `aria-live` conferidos |
| F2 | Studio, conta e popup de foto (PR própria no `aculta_user`) | navegação, `aria-current`, popup de foto e formulários de conta; gate de foto |
| F3 | Módulos do Portal (uma PR por módulo) | cada módulo passa no próprio gate |
| F4 | Carrinho e frete | autorização da loja |
| F5 | Pagamento e checkout | autorização explícita por tarefa |

Cada fase termina em PR própria, com `RUNTIME STATUS: DEFERRED` enquanto não for testada no Homelab.

## 6. Validação

- Gates do tema e do Portal, no Homelab (`docs/gates.md`).
- Capturas em 320, 390, 768, 959, 960 e 1440 px, claro e escuro, zoom de 200%, com e sem JavaScript.
- Teclado e leitor de tela: foco após sucesso e erro, `aria-live` e `role="alert"`.
- Voltar e avançar do navegador, e restauração de posição.
- Confirmar que nenhuma resposta privada entra em cache público.

## 7. Não-decisões e pendências

- O nome final do módulo JavaScript (`studio-htmx.js`).
- Se `aculta_links` e `aculta_linkweed` devem ficar em um só módulo.
- Se `dhtmlxscheduler.js` é migrado ou substituído.
- Se `cep-address.js` usa htmx ou continua com JSON.
- O prazo de F5, que depende de autorização.
