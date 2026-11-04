# Migração ACULTA420 para Bootstrap5

## Contrato confirmado no Runtime e divergências do checkout

O Runtime consultado em `toca` reporta `drupal/bootstrap5` **5.0.x-dev**, source
`c2292008a9a54d30f321b221e0df4a645821b6fe`, framework embarcado Bootstrap 5.3.8.
O manifesto instalado declara `bootstrap5/base`, `bootstrap5/messages`,
`bootstrap5/bootstrap5-js-latest` e `bootstrap5/global-styling`; o JS é
`bootstrap5/dist/bootstrap/5.3.8/dist/js/bootstrap.bundle.js`. `bootstrap5`
expõe regiões `header`, `nav_branding`, `nav_main`, `nav_additional`,
`breadcrumb`, `content`, `sidebar_first`, `sidebar_second` e `footer`.

Há divergência no checkout limpo de `origin/dev` (HEAD `0d5a8123e5`):
`composer.json` e `composer.lock` não declaram `drupal/bootstrap5`, enquanto o
Runtime tem o pacote. `config/sync/core.extension.yml` também não habilita o
tema no HEAD limpo. Essa migração não modifica requisito nem lock.
`composer show drupal/bootstrap5` falha neste checkout porque o pacote não está
declarado/resolvido; a confirmação `5.0.x-dev` é da instalação do Runtime.
Não houve `composer update` nem alteração de versão. A reconciliação do
manifesto/configuração com a instalação do Runtime é pendência concreta antes
do rebuild e da validação integrada.

## Matriz de migração

| Local/contrato | Antes | Consumer/owner | Mudança | Risco e validação |
| --- | --- | --- | --- | --- |
| `aculta420.info.yml` | `base theme: bootstrap` | ACULTA420 | machine name passa a `bootstrap5` | confirmar enablement/configuração e rebuild Runtime |
| `aculta420.libraries.yml` | `bootstrap/bootstrap`, `bootstrap/global-styling` | todas as páginas; gavetas | usa libraries declaradas pelo pacote instalado | conferir árvore e ausência de duplicação no HTML/rede |
| `templates/page.html.twig`, `commerce-product--produto-loja.html.twig`, `components/patterns/card/card.twig`, `js/address-layout.js` | `col-xs-*`, grid sem base móvel uniforme | shell, Commerce, SDC card, endereço Portal | `col-*`/`col-12` Bootstrap5 | validar 320/360/390/768/1280 e overflow |
| `templates/page.html.twig` | `data-toggle/target=collapse` | menu ACULTA420 | `data-bs-toggle/target` | teclado/Escape e AJAX attach |
| `components/patterns/dropdown/dropdown.twig` | `data-toggle=dropdown` | SDC dropdown | `data-bs-toggle=dropdown` | teclado, posicionamento e consumer |
| `components/patterns/product-tabs/product-tabs.twig` | anchors e `fade in` Bootstrap3 | Loja / SDC product-tabs | botões, `data-bs-*`, `show active` | teclado, foco e conteúdo de cada aba |
| `alerts-trigger`, `messenger-trigger`, `cart-offcanvas` | plugin modal jQuery Bootstrap3 | Avisos, Mensagens, Commerce | markup e atributos Bootstrap5 Offcanvas | foco, Escape, backdrop, AJAX e navegação |
| `js/navigation.js`, `js/messenger-drawers.js` | eventos/collapse jQuery Bootstrap3 | menu e gavetas | eventos DOM `show/hidden.bs.offcanvas`; `bootstrap.Collapse` | Drupal behaviors e detach/attach; no `navigation.js`, Collapse é global fornecido pelo bundle da base |
| `PortalUiHooks.php`, SDC card e CSS associados | classes Bootstrap3 `.panel*` usadas como contrato de UI | Portal: doações, LMS, checkout, Conta, Studio e editorial | classes semânticas `aculta-panel*`/`aculta-card__*` | atualizar producers/consumers/tests; screenshots de cada purpose |
| `templates/portal-cart-panel.html.twig` | `alert-dismissable`, `.close`, `data-dismiss`, `fade in` | AJAX cart panel | `show`, `.btn-close`, `data-bs-dismiss` | confirmar que Commerce não depende da remoção local do item |
| `core/jquery` em módulos ACULTA | jQuery ainda exigido por owners independentes | pagamentos Mercado Pago, Fórum/history, H5P/contrib, Drupal Core | preservado; nenhuma conclusão de remoção global | verificar libs/owners individuais |

## Classificação de JavaScript/jQuery

| Dependência | Classe | Decisão |
| --- | --- | --- |
| Bootstrap3 Collapse/Modal + eventos `*.bs.*` em `navigation.js` e `messenger-drawers.js` | exclusiva da base anterior | migrada para Bootstrap5 JS/eventos DOM |
| `core/jquery`, AJAX e Dialog | Drupal Core/Form API | permanece |
| `aculta_pagamentos_mercadopago` checkout Pro | módulo custom/SDK proprietário | permanece |
| `aculta_forum` histórico e integrações H5P | módulo custom/contrib | permanece |
| JavaScript próprio restante do tema | ACULTA420 | inventariar individualmente; sem remoção ampla nesta etapa |

## Owners e checks de interação

| Interação | Owner | Verificação necessária |
| --- | --- | --- |
| Menu / collapse | ACULTA420 + Bootstrap5 | teclado, Escape, breakpoint, Drupal AJAX |
| Dropdown | Bootstrap5 + consumer SDC | teclado e posicionamento |
| Offcanvas de carrinho | ACULTA420 + Commerce | foco, backdrop, Escape, estado vazio/cheio e AJAX |
| Offcanvas de avisos | ACULTA420 + Portal | foco, atualização AJAX e anúncio |
| Offcanvas de mensagens | ACULTA420 + `aculta_messenger` | foco, AJAX, cancelamento e dialogs Drupal aninhados |
| Tabs do produto | Bootstrap5 / Commerce | setas/teclado e estado acessível |
| Modal Drupal | Core `drupal.dialog` / jQuery UI | preservado; não deve ser substituído pelo modal Bootstrap |
| Carousel | consumer editorial local | não usa plugin Bootstrap; validar controls e reduced motion |

## Estado da validação

Fonte local do tema: worktree baseada em `origin/dev` commit
`0d5a8123e576ba1d701efabe71bf87e6d24fc8e2`. `git diff --check`, sintaxe PHP
dos arquivos alterados, parse dos manifests YAML, `node --check`, SDC schema,
browser validators, institution content, shell fixtures e Studio identity
passaram. Composer validate passou com avisos preexistentes de constraints;
`composer show drupal/bootstrap5` falhou porque o pacote não consta no lock local.
Design Foundations falha em dois checks preexistentes de blocos de tokens; o
gate de fixtures falha por consequência. PHPUnit não iniciou: a worktree não
tem Drupal Core e o bootstrap não resolve `Drupal\\Tests\\UnitTestCase`.
Stylelint/ESLint indisponíveis por dependências de configuração ausentes no
`node_modules` compartilhado. O Runtime não foi alterado nem recebeu cache
rebuild. Interações e pagesets do site, árvore de assets e validação visual
integrada permanecem pendentes; não declarar homologação visual. O
`updatedb:status` e `config:status` read-only do Runtime passaram sem
pendências/diferenças. O Foundation Runtime gate não passa porque `bootstrap5`
não está habilitado em `core.extension`; B.4 shell gate passou.
