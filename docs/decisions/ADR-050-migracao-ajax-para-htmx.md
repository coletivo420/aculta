# ADR-050 — Migração do AJAX do ACULTA para htmx

- **Status:** aceito pelo responsável em 2026-11-05. F0 concluída no Homelab em 2026-11-05 com testes oficiais do Core e fixture de diálogo/formulário em Firefox e Chromium; detalhes e reexecução em [`docs/htmx-confirmacao.md`](../htmx-confirmacao.md). Nenhum código de produção foi migrado.
- **Data:** 2026-11-05.
- **Relaciona-se com:** ADR-044 (opção C, htmx), ADR-045 (um único módulo de AJAX; sem Core fora do escopo), ADR-046 (V2), ADR-048 (popup de foto), ADR-049 (AJAX do Studio, revisado por este ADR), `docs/portal/CROSS-DOMAIN-REQUEST-POLICY.md`, `docs/portal/PAYMENT-DOMAIN-POLICY.md`.

## 1. Contexto

- O ADR-049 escolheu Fetch próprio (mecanismo B). O responsável decidiu migrar para htmx (mecanismo C), por ser o caminho do próprio Core (11.3 em diante, `Drupal\Core\Htmx\Htmx`, library `core/drupal.htmx`).
- O escopo é **todo AJAX de código ACULTA**: tema V2 e módulos `aculta_portal`. Não entra nada que seja do Core ou de módulo contrib.
- Verificado em fontes públicas (change records do Core 11.3, documentação da classe `Htmx`): os atributos `data-hx-*`, o método `onlyMainContent()` (resposta só do conteúdo), o `FormBuilder` tratando requisições htmx como as do AJAX da API atual, e a ausência de anúncio de remoção do `#ajax`.
- Verificação local/Core 11.5 em 2026-11-05: `Htmx` não tem anotação `@internal`; `FormBuilder` mantém o token CSRF do Form API e renova `form_build_id` em POST HTMX; FunctionalJavascript, Kernel e fixture de popup/form dentro de `<dialog>` passaram, incluindo token adulterado rejeitado. Ver `docs/htmx-confirmacao.md`.

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
- Cada módulo do Portal que muda de `#ajax` para htmx muda o contrato da resposta. Isso exige entrega/commit separado com gate.
- A migração do Drupal 12 (htmx 4) pode exigir ajustes nos atributos. Por isso os atributos ficam concentrados nos templates e nos `Htmx` builders, não em JavaScript.

## 5. Fases e critério de aceite

| Fase | Entrega | Critério |
| --- | --- | --- |
| F0 | Confirmar a API `Htmx` no `core/lib` do 11.5 no Homelab; prova com um popup e um formulário; CSRF e `@internal` verificados | nota com saída de teste; ADR-050 atualizado |
| F1 | Tema V2 e login | login, gavetas e busca funcionam com e sem JavaScript; foco e `aria-live` conferidos |
| F2 | Studio, conta e popup de foto (entrega separada no `aculta_user`) | navegação, `aria-current`, popup de foto e formulários de conta; gate de foto |
| F3 | Módulos do Portal (uma entrega por módulo) | cada módulo passa no próprio gate |
| F4 | Carrinho e frete | autorização da loja |
| F5 | Pagamento e checkout | autorização explícita por tarefa |

Cada fase termina em commit atômico publicado em `dev`, com `RUNTIME STATUS: DEFERRED` enquanto não for testada no Homelab.

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

## 8. Plano detalhado de execução

Este plano é a ordem de trabalho no Homelab. Pela instrução vigente do responsável
e pelo `AGENTS.md` da raiz, cada entrega é um commit atômico diretamente em `dev`;
não criar branches nem PRs.

### 8.1 Regras de execução

- **Dependências.** F0 precede todas. F1 precede F2 (ambas usam `studio-htmx.js`). F3 depende só de F0, porque cada módulo usa o `Htmx` do Core e não o JavaScript do tema. F4 e F5 dependem de autorização, não de outra fase.
- **Bloqueio.** Se uma fase falhar em gate ou verificação, registre `BLOCKED: <motivo>` no roadmap/handoff e siga apenas para fases independentes.
- **Falha de F0** interrompe a migração inteira: sem a API confirmada, nenhuma fase começa.
- **Autorização.** F4 e F5 não rodam sem pedido explícito para a tarefa. Registre `AGUARDANDO AUTORIZAÇÃO` e siga.
- **Decisões em aberto** (seção 7 do ADR): não decida. Registre e deixe de fora da fase.
- **Proibido:** jQuery, `core/drupal.ajax`, Bootstrap, Vite, Node no tema; `prefers-color-scheme`; hostnames fixos; segredo, token ou caminho de Homelab no repositório; alterar `web/themes/custom/aculta420/` (V1); alterar Core ou contrib; mesclar em `main`; force push; escrever no Runtime ou em `estados/`; versionar estado não validado.
- **Commit e push** direto para `dev`; seguir os trailers permitidos pelo `AGENTS.md` da raiz (sem coautoria).
- **Documentação no mesmo commit** (AGENTS do tema, seção 6): componente em `docs/components.md`, biblioteca e JavaScript em `docs/architecture.md`, gate em `docs/gates.md`.
- **Versão:** não marque versão. Só na entrega validada.

### 8.2 F0 — Confirmação da API no Core 11.5

**Estado atual (2026-11-05): CONCLUÍDA.** A classe, library, atributo
`onlyMainContent`, fluxo de `form_build_id` e caminho CSRF foram inspecionados.
Testes Core FunctionalJavascript (Chromium headless, 1/19) e Kernel (5/60), mais
fixture ACULTA em `<dialog>`/form (Firefox e Chromium headless, 1/7 em cada)
passaram. O token inválido é rejeitado. Saídas e depreciações estão em
`docs/htmx-confirmacao.md`. F1 pode iniciar; F2 segue após F1a e F3 segue a ordem
de módulos. Nenhum módulo de produção mudou de mecanismo.

1. Localizar no Homelab o `core/lib/Drupal/Core/Htmx/Htmx.php`, o `core/core.libraries.yml` (entrada `drupal.htmx`) e o tratamento de htmx em `core/lib/Drupal/Core/Form/FormBuilder.php`.
2. Registrar: se `Htmx` é `@internal`; como o CSRF do `form_build_id` é atualizado em requisição htmx; e se `onlyMainContent()` cria `hx-drupal-only-main-content` ou `data-hx-drupal-only-main-content` (divergência entre fontes).
3. Prova mínima, em fixture isolada de teste: uma rota de fragmento e um formulário com `Htmx::post()`, trocado dentro de um `<dialog>`. Registrar a saída do navegador (`$SUITE_OUT`).
4. Entregar `docs/htmx-confirmacao.md` e atualizar o ADR-050. A fixture e o teste ficam exclusivamente em `aculta_portal/tests/`; nenhum código de produção muda nesta fase.

**Aceite:** nota com trechos de código e referências do Core; prova executada; sem divergência sem registro.

### 8.3 F1 — Tema V2 e login (F1a tema; F1b Portal)

Entrega direta em `dev` conforme fluxo vigente.

**F1a (tema):**

1. Criar `web/themes/custom/aculta420_v2/js/studio-htmx.js` com `Drupal.behaviors` e `once()`. Responsabilidades, e só estas: foco após troca (`[data-aculta-focus]` ou `h1`); `document.title` a partir de `data-aculta-title` do fragmento; região `aria-live` com o texto da troca; descarte de resposta antiga por `hx-sync="this:replace"`, no lugar do `AbortController` da V1.
2. Criar a biblioteca `studio-htmx` em `aculta420_v2.libraries.yml`, dependendo de `core/drupal.htmx`. Documentar em `docs/architecture.md`, seção 5.
3. `components/multidomain-bar/multidomain-bar.js`: remover o Fetch do popup de login. O gatilho e o `<dialog>` passam a usar `data-hx-get` e `data-hx-target` (conferir a sintaxe no ADR-050 F0).
4. `js/messenger-drawers.js`: substituir o `fetch` e o `AbortController` por atributos htmx nos gatilhos e nos links da gaveta. Manter o estado de carregamento (`aria-busy`) e o erro com `role="alert"`, que a V1 tem.
5. `js/alerts-dismiss.js` e `js/counters.js`: manter. Só atualizam o contador após `htmx:afterSettle`.
6. Filtros de avisos (grupos recolhíveis por origem e leitura) conforme `studio-estrutura.md` 4.5. Estes filtros são o item que faltava na V2.
7. Ícones de 30 px nos gatilhos da barra: adicionar tamanho `bar` em `icon.component.yml` e no CSS (ADR-047 item 10). Alvo de toque de 35 px.
8. Alvo de toque e foco visível conferidos em 320, 390 e 1440 px, claro e escuro, zoom 200%.

**F1b (Portal, entrega separada no mesmo fluxo `dev`):**

1. `aculta_portal/src/Form/LoginPopupForm.php` e `RegisterPopupForm.php`: devolver fragmento quando a requisição é htmx; usar `Htmx` no formulário.
2. `aculta_portal/src/Hook/FormHooks.php`, `PortalHooks.php`, `PortalFormCallbacks.php`: remover o AJAX dos formulários migrados.
3. `aculta_portal/src/Search/SearchUtility.php` e `js/search-preview.js`: busca com `hx-get`.
4. `aculta_portal/src/EventSubscriber/DomainPurposeRequestSubscriber.php`: conferir que o redirecionamento de purpose continua funcionando com htmx (`HX-Redirect` quando necessário, nunca redirect cru para outro host).
5. Contrato de sites para a lista do menu (ADR-047 item 9): o Portal entrega `aculta_domain_sites` (nome e URL de cada purpose publicado). Contrato neutro, sem hostname fixo no tema.

**Gates F1:** `validate-aculta420-sdc-schemas.php`, `validate-aculta420-design-foundations.php`, `validate-browser-validators.php`, `validate-aculta420-foundation.php`, `validate-aculta420-shell-contract.php`, `validate-aculta420-v2-sdc.php` (Drush), `validate-aculta-portal-drupal11.php`, `validate-admin-routes-main-domain.php`.

**Aceite:** login, gavetas, busca e filtros funcionam com e sem JavaScript; foco e `aria-live` conferidos; sem jQuery nem `core/drupal.ajax`.

### 8.4 F2 — Studio, conta e popup de foto

Entrega direta em `dev` após F1a.

1. `aculta_studio/js/studio-navigation.js` e `account-navigation.js`: remover o `pushState` próprio. Os links ganham `hx-get`, `hx-target` (a área de conteúdo) e `hx-push-url`. Sem JavaScript, o `href` responde a página completa.
2. `aculta_studio/src/Controller/StudioProfileController.php`: devolver fragmento com `onlyMainContent()` quando htmx. `Cache-Control: private` e `Vary: Cookie`.
3. `aculta_user/js/account-bio-form.js` e `src/Bio/AccountBioCard.php`: `hx-post` no formulário da biografia; remover o `fetch`.
4. `aculta_user/src/Social/SocialLinksCard.php` e `aculta_portal/src/Account/AccountAddressForm.php`: fragmento. Regra de país `BR` preservada (ACCOUNT-ADDRESS).
5. **Popup de foto** (`aculta_user`, entrega separada):
   - `src/Hook/PhotoEditorHooks.php`: remover `use-ajax` e `core/drupal.dialog.ajax`; gatilho com `hx-get` para o `<dialog>`.
   - `src/Form/UserPictureForm.php`, `src/Form/AvatarCropForm.php`, `src/Controller/AvatarRerollController.php`: fragmento; recorte aplicado no mesmo `<dialog>`.
   - `src/Access/AccountModalAccess.php`: mantém as regras de acesso.
   - `js/photo-flow.js`: removido; o que restar (abrir o recorte após o envio) vai para `studio-htmx.js`.
   - Regras de estado do ADR-048 item 10: acervo sem "Remover" e sem recorte; nunca inicial.
6. Gates: `validate-aculta-user-photo.php` (obrigatório, o gate de foto é o da mudança), `validate-aculta-portal-drupal11.php`.

**Aceite:** navegação do Studio com `aria-current`, foco e título; popup de foto nos três cenários de avatar (foto, acervo, sem foto), em 320, 390 e 1440 px, claro e escuro, com e sem JavaScript.

### 8.5 F3 — Módulos do Portal

Depende só de F0. Uma entrega/commit por módulo, nesta ordem:

1. `aculta_forum`: `ForumAttachmentUploadDialogForm.php`, `ForumGalleryUploadDialogForm.php`. `<dialog>` com htmx.
2. `aculta_gallery`: `GalleryEmbedDialogForm.php`.
3. `aculta_events`: 9 arquivos (formulários de data, local e organização; `EventLocationWidgetController.php`; `EventDateListBuilder.php`; widgets de campo `EventLocationModalWidget.php` e `EventOrganizationModalWidget.php`). Uma entrega atômica para o módulo inteiro.
4. `aculta_paths` (administração, só MAIN): `SurfaceEditController.php`, `SurfaceListController.php`, `SurfaceEditForm.php`. Gate `validate-admin-routes-main-domain.php`.
5. `aculta_secrets_manager` (administração, só MAIN): `SecretsController.php`, `SecretEditForm.php`, `SecretClearForm.php`. A resposta **nunca** contém o valor do segredo. Gate `validate-admin-routes-main-domain.php`.

**Parado, não seguir sem decisão:** `aculta_links` e `aculta_linkweed` (`SocialLinksSettingsForm`, nos dois). Registrar `AGUARDANDO DECISÃO` e não tocar.

**Gates por módulo:** `validate-aculta-portal-drupal11.php`, mais os da tabela acima quando aplicável.

**Aceite:** cada módulo passa no gate, e o formulário funciona com e sem JavaScript.

### 8.6 F4 — Carrinho e frete (loja)

`AGUARDANDO AUTORIZAÇÃO` para cada tarefa. Quando autorizada, entrega em `dev`:

1. `aculta_portal/src/Cart/AddToCartAjax.php`: `hx-post` no botão e fragmento de status (`portal-cart-status`).
2. `aculta_commerce_melhorenvio/assets/shipping-calculator.js`: `hx-get` no campo de CEP. Mantém a regra de CEP do projeto.

### 8.7 F5 — Pagamento e checkout

`AGUARDANDO AUTORIZAÇÃO` para cada tarefa, pela política de pagamento. Nunca roda sem pedido explícito:

1. `aculta_donation`: `DonationCheckoutPane.php`, `DonationItemPaneBase.php`.
2. `aculta_pagamentos_mercadopago`: `CheckoutPro.php`.
3. `aculta_portal/src/Plugin/Commerce/CheckoutPane/AcultaCheckoutLogin.php`.

### 8.8 Relatório final do agente

Ao terminar (ou ao parar), o agente entrega:

- Uma tabela por fase: `CONCLUÍDA`, `DRAFT (BLOCKED: motivo)`, `AGUARDANDO AUTORIZAÇÃO` ou `AGUARDANDO DECISÃO`.
- Para cada entrega: commit, arquivos alterados e saída dos gates (ou `DEFERRED` com o motivo).
- Lista de itens de documentação conforme AGENTS do tema, seção 6, com o checklist respondido.
- Riscos encontrados, sem decidir por conta própria.
