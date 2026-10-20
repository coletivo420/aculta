# Mapa módulo x tema (correspondências para agentes)

> Quem gera marcação do módulo registra aqui onde está a contraparte visual no ACULTA420.
> Regra de fronteira: `FRONTEIRA-MODULO-TEMA.md`. Gate: `php scripts/validate-module-theme-map.php`.

Este documento responde, para cada módulo e submódulo customizado, três perguntas: **o que o módulo entrega**,
**quem desenha** e **onde fica cada peça no tema**. Antes de editar CSS, template, SDC ou JavaScript de um módulo,
leia a linha dele. Não procure a contraparte por busca textual: use esta tabela.

## Procedimento de busca (obrigatório antes de alterar um módulo)

Antes de alterar template, CSS, componente, JavaScript ou token de um módulo ou contrib, o agente faz nesta ordem:

1. **Mapa.** Localiza a linha do módulo ou do contrib neste documento e lista as peças do tema que lhe correspondem.
2. **Documentação do tema.** Consulta `web/themes/custom/aculta420/docs/` (sobretudo `development.md`, `components.md`, `design-system.md` e os guias por tema, como `messenger.md`) e `docs/portal/TOKENS-E-ATRIBUTOS.md` para o contrato `data-portal-ui`.
3. **Código do tema.** Procura o nome do módulo, do hook de tema e das classes nos caminhos do tema: `templates/`, `css/`, `components/`, `js/`, `src/Hook/PortalUiHooks.php` e `aculta420.libraries.yml`.
4. **Web, se o contrato não estiver claro.** Para contrib ou para comportamento do Core que o mapa não cobre, pesquisa a documentação oficial (`api.drupal.org`, `drupal.org/docs`, a página do módulo e o changelog da versão instalada). Confere a versão instalada em `web/modules/contrib/<nome>/`. Cita as fontes na resposta. Se a pesquisa não confirmar o contrato, diz isso e pergunta.
5. **Só depois, edita.** Se a alteração criar ou remover uma peça do tema, atualiza a linha do mapa no mesmo commit.

Um registro marcado como "Verificar" ou "Sem contraparte" não autoriza suposição: trate como não documentado e siga os passos 2 a 4.

## Regras para manter o mapa

1. **Cobertura.** Todo módulo customizado (diretório com `*.info.yml` em `web/modules/custom/**`) precisa de uma linha nas
   tabelas do Portal, com o caminho completo em crase. Em `web/modules/custom/` só há `aculta_portal` no nível superior;
   os demais são submódulos e entram com o próprio caminho. Todo módulo **contrib habilitado** (em
   `config/sync/core.extension.yml`, com diretório em `web/modules/contrib/`) precisa aparecer pelo nome de máquina,
   em crase, na seção "Módulos contrib habilitados". O gate falha se algum módulo ficar de fora.
   Um módulo sem contraparte visual também precisa ser listado, com o motivo.
2. **Mesma entrega.** Quem cria ou renomeia um template, CSS, JS, SDC, token `data-portal-ui` ou tema de hook de um módulo
   atualiza a linha correspondente no mesmo commit. Caminho citado deve existir (o gate confere).
3. **Dono da peça.** Cada peça tem um dono: módulo (dados, comportamento, fallback neutro sem classe) ou tema
   (aparência, layout, classes, CSS, SDC). Não coloque CSS, classe de design ou `data-portal-ui` com nome de componente
   no módulo; use o contrato de tokens em `TOKENS-E-ATRIBUTOS.md`.
4. **Plugins.** Um plugin que gera marcação (field formatter, block, checkout pane, widget) segue a mesma regra:
   emite dados neutros e tokens; a aparência fica no tema, identificada pelo nome do template, SDC ou CSS listado
   na linha do módulo dono do plugin. Plugins citados abaixo: `AcultaPdfMediaFormatter` (`aculta_pdf_viewer`),
   `BillingAddressPane` e `AcultaShippingInformation` (Portal, checkout).
5. **Sem contraparte.** Se o módulo não tem peça visual própria, escreva "sem contraparte" e o motivo. Não deixe a
   célula vazia.
6. **Estado.** "Tema pendente" significa que a marcação existe e o tema ainda não a desenha. "Fallback" significa que o
   módulo emite marcação neutra e o site continua funcional sem o tema.

## Convenções de CSS (contraparte no tema)

Estas regras valem para qualquer contraparte listada aqui. A fonte normativa é `web/themes/custom/aculta420/docs/development.md`.

- **Arquivo por responsabilidade.** Regra nova entra no arquivo da responsabilidade que a possui, em
  `css/components/<nome>.css` ou em `css/{tokens,base,layout}.css`. Não criar `style.css`, `responsive.css` ou arquivo
  genérico.
- **Registro obrigatório.** Todo CSS novo entra em `aculta420.libraries.yml` e aparece na linha do módulo dono.
- **Tokens, não valores.** Cores, espaçamentos e durações vêm de tokens semânticos. Palette primitives ficam em
  `tokens.css`. Hex, `rgb()`, `hsl()` e funções modernas de cor são proibidos fora de `tokens.css` (o gate da fundação do
  tema confere).
- **Seletor por classe ou atributo.** Quando o elemento pode vir do token ou da classe, use
  `:is(.classe, [data-portal-ui="token"])`. Não crie seletor de modo escuro fora de `tokens.css`.
- **Modos de cor são tokens.** Claro e escuro compartilham DOM e layout; só os tokens semânticos mudam.
- **Sem JavaScript de design.** O JS do tema trata apenas apresentação (layout, estado visual). Comportamento de
  negócio fica no módulo.
- **Sem CSS no módulo.** O módulo não tem `css/` nem anexa biblioteca de estilo; o CSS vem sempre do tema.

## Aculta Portal (`web/modules/custom/aculta_portal`, 0.4.0-dev.61)

| Caminho | Entrega (módulo) | Contraparte no tema | Estado |
| --- | --- | --- | --- |
| `web/modules/custom/aculta_portal` | Shell da Minha conta, navegação, busca, carrinho, checkout, Wiki (`WikiController`), loja (Commerce), dados de tema via `domain_presentation`; `DomainPurposeRequestSubscriber` restaura os marcadores da raiz antes do Redirect quando o cache de rotas omite atributos do path processor | Templates: `aculta-portal-shell`, `aculta-section`, `aculta-portal-photo-editor`, `aculta-portal-account-terms-modal`, `portal-cart-status`, `portal-payment-notice`, `portal-search-trigger`, `page`. CSS: `portal-account.css`, `portal-account-terms-modal.css`, `sales-panel.css` (painéis de vendas e doações, tokens `sales-*`), `portal-address-number.css`, `portal-cart.css`, `portal-payment-notice.css`, `portal-requirements-report.css`, `portal-support.css`, `search.css`, `wiki.css`, `product.css`. JS do tema: `address-layout.js`. Tokens em `PortalUiHooks::TOKENS`. `portal-element` não tem template no tema: o elemento recebe classes pelo preprocess. | Ativo |
| `web/modules/custom/aculta_portal/templates` | Fallback neutro dos templates do módulo (sem classe nem CSS): `aculta-portal-shell`, `aculta-section`, `portal-element`, `portal-cart-status`, `portal-payment-notice`, `portal-search-trigger`, `aculta-portal-photo-editor`, `aculta-portal-account-terms-modal` | Mesmos nomes no tema (ver linha acima) | Fallback |
| `web/modules/custom/aculta_portal/js` | Comportamento: `account-navigation.js`, `account-terms-modal.js`, `cart-status.js`, `cep-address.js`, `search-preview.js` | `address-layout.js` (layout do CEP, no tema) | Ativo |
| `web/modules/custom/aculta_portal/src/Plugin/Commerce/CheckoutPane` | Panes de endereço e envio (`BillingAddressPane`, `AcultaShippingInformation`), sem formulário visual próprio | `portal-address-number.css` (Número e S/N) | Ativo |

### Submódulos do Aculta Portal

| Caminho | Versão | Entrega (módulo) | Contraparte no tema | Estado |
| --- | --- | --- | --- | --- |
| `web/modules/custom/aculta_portal/modules/aculta_user` | 0.4.0-dev.40 | Perfil público, identidade/autoria compartilhada e variantes contextualizadas; cartões de comentários compartilhados; ano da conta somente para perfil público em cartões do Fórum; atividade privada inclui tópicos/respostas publicadas; avatar via `aculta_image`, links sociais, foto e biografia | SDC `content/author-card` via hook neutro `aculta_user_author_card`; template `comment--aculta-discussion-post.html.twig`; `public-profile.css`; SDC `patterns/social-links-page`; templates de perfil e foto | Identidade e access preparados no Portal; ano da conta depende de perfil público e é apresentado pelo tema nas variantes do Fórum |
| `web/modules/custom/aculta_portal/modules/aculta_forum` | 0.1.0-dev.86 | Motor de discussão e Context API; Views públicas e administrativas, categorias, comentários, autoria via `aculta_user`, Flags, ferramentas de mover tópico/comentário, progresso History e busca Search API isolada por tópico/Domain | ACULTA420 apresenta tópicos, comentários e autoria pelos templates/SDCs existentes; a busca reutiliza o teaser público sem nova regra visual. `DiscussionProgressHooks` anexa biblioteca funcional do Portal | ACULTA420 controla o design; Portal mantém índice, filtros de contexto, acesso e cache. Nenhuma consulta/decisão de acesso no tema. |
| `web/modules/custom/aculta_portal/modules/aculta_messenger` | 0.1.1-dev.44 | Mensagens privadas, barra do Messenger, avisos e alertas. Não tem `hook_theme`: usa `aculta_portal_element` e `item_list`; o gatilho da barra é o hook de tema `aculta_messenger_trigger`, preparado por `ThemeHooks` | SDC: `patterns/messenger-list`, `patterns/messenger-trigger`, `patterns/messenger-read-status`, `content/messenger-compose`, `content/messenger-item`, `content/messenger-notification`, `feedback/messenger-state`, `feedback/unread-badge`. Templates: `private-message-thread`, `site-alert`, marcação da barra em `page`. CSS: `messenger-drawer.css`. JS: `messenger-drawers.js`. Guia: `web/themes/custom/aculta420/docs/messenger.md` | Ativo |
| `web/modules/custom/aculta_portal/modules/aculta_magazine` | 0.1.0-dev.14 | Revista: Comment direto via `field_aculta_discussion` em `article`/`blog`; status default legado em memória. Consumidores de `field_discussao` removidos após limpeza | `comment--aculta-discussion-post.html.twig`; `discussion.css`; templates de publicação passam o campo Core ao slot `discussion`; SDC `content/author-card` via `aculta_user`; SDC `patterns/revista-publication` | O tema apresenta o Comment da publicação no contexto editorial |
| `web/modules/custom/aculta_portal/modules/aculta_gallery` | 0.1.0-rc.1 | Álbuns e galerias, plugin do CKEditor (`acultagallery-create.js`) | `album-slideshow.css`, `album-slideshow.js`. Detalhes em `aculta_gallery/docs/TEMA-E-HOOKS.md` | Ativo |
| `web/modules/custom/aculta_portal/modules/aculta_pdf_viewer` | 0.1.0-dev.13 | Viewer PDF (`AcultaPdfMediaFormatter`, `PdfViewerBuilder`), miniatura, JS do viewer | CSS `pdf-viewer.css` (contrato neutro do módulo); documentado em `web/themes/custom/aculta420/docs/design-system.md` | Ativo |
| `web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/modules/aculta_pdf_viewer_group` | 0.1.0-dev.4 | Documentos privados de turma (Group) | Sem contraparte própria; usa a marcação do `aculta_pdf_viewer` | Ativo; Runtime em homologação |
| `web/modules/custom/aculta_portal/modules/aculta_donation` | 0.1.0-dev.3 | Campanhas de doação, fluxo de pedido e checkout (port do commerce_donation_flow), blocos, configuração do fluxo | Tokens `data-portal-ui` `donation-*` (traduzidos em `PortalUiHooks`); SDC `donation-quick`, `donation-pane`, `donation-dedication`, `donation-notification`, `donation-summary-container`; templates `aculta-donation-*` e `commerce-checkout-pane--commerce-*` | Runtime aplicado (corte de 2026-10-17); conferência visual pendente |
| `web/modules/custom/aculta_portal/modules/aculta_image` | 0.1.0-dev.23 | Ingestão, política de upload, catálogo de finalidades (`ImageCatalog`), editor de imagem (`aculta-image-model.js`) | `revista-post.css` (largura das imagens do texto); a escolha de tamanho vem do módulo, a apresentação do tema | Ativo |
| `web/modules/custom/aculta_portal/modules/aculta_mailer` | 0.1.0-dev.1 | Plataforma de e-mail transacional em F0/F1: contratos `MailMessage`, `MailStatus`, `MessageIdFactory`, `RetrySchedule` e interface `AcultaMailerInterface`. Opt-in, não habilitado, sem envio ativo | Nenhuma nesta fase: não há templates, SDC nem CSS. Templates de layout (F3/F4) exigirão contrato de tema antes de implementação | Sem efeito em runtime |
| `web/modules/custom/aculta_portal/modules/aculta_portal_cep` | 0.1.0-dev.1 | Consulta de CEP (`/aculta_portal_cep/{cep}`, ViaCEP com fallback BrasilAPI, cache, limite por IP) e autocompletar do endereço (`cep-address.js`, biblioteca `aculta_portal_cep/cep-address`), portados do `cep_autocomplete` 1.0.0 | Nenhum (o campo é do formulário do Portal; sem CSS próprio) | Ativo |
| `web/modules/custom/aculta_portal/modules/aculta_deployer` | 0.1.25 | Relatórios de ambiente e status (`DeployerStatusController`) | `portal-requirements-report.css` | Ativo |
| `web/modules/custom/aculta_portal/modules/aculta_portal_sitemap` | 0.1.1 | Sitemap | Sem contraparte visual própria | Sem contraparte |
| `web/modules/custom/aculta_portal/modules/aculta_game` | 0.1.0-dev.12 | Conquistas e missões; aprovação tardia no Fórum só é elegível na primeira publicação; legados e outros contextos ficam excluídos | Sem contraparte identificada no tema | Sem contraparte visual própria |
| `web/modules/custom/aculta_portal/modules/aculta_lms` | 0.1.0-dev.1 | Integração com o LMS (cursos, lições, matrícula) | Sem contraparte própria; o cartão de curso é do contrib `lms` (ver abaixo) | Sem contraparte própria |
| `web/modules/custom/aculta_portal/modules/aculta_patcher` | 0.1.0-dev.1 | Registro administrativo de diffs Composer e exportação validada para build | Aviso do formulário com `notice-warning` (tema); demais formulários e tabela administrativa nativos do Drupal | Sem contraparte de tema |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p` | sem versão no info.yml | Conteúdo interativo H5P em atividades e cursos: editor, renderização por curso e biblioteca administrativa | Sem template próprio no tema. Tokens `h5p-admin-header`, `h5p-dashboard-course` e `h5p-content-unavailable` (`PortalUiHooks`). Classes `h5p-content` e `h5p-editor` são contrato do H5P core (exceção do gate de fronteira) | Ativo |
| `web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago` | 0.1.0-dev.5 | Gateway Checkout Pro, retorno do popup e webhooks do Mercado Pago | Aviso `portal-payment-notice` (Portal); tokens `checkout-help` e `mercado-pago-credentials-*`. Pendência: o estilo das credenciais está em `web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/css/gateway-admin.css` e deveria estar no tema | Ativo |
| `web/modules/custom/aculta_portal/modules/aculta_id_cpf` | 0.2.0-dev.2 | Validação e campos de CPF | Sem contraparte própria; usa formulários do tema (`forms.css`) | Sem contraparte |
| `web/modules/custom/aculta_portal/modules/aculta_associado_id` | 0.3.0-dev.1 | Identificador de associado | Sem contraparte própria; usa formulários do tema | Sem contraparte |

## Módulos contrib habilitados

Escopo: módulos com diretório em `web/modules/custom/**` ficam nas seções acima; módulos com diretório em
`web/modules/contrib/` e habilitados em `config/sync/core.extension.yml` ficam aqui. Módulos do Core
(`web/core/modules/`) não entram; as peças de tema que sobrescrevem templates do Core estão em "Conteúdo sem módulo
customizado próprio". A regra de cobertura do gate vale para cada nome de máquina listado abaixo.

### Com contraparte no tema

| Módulo (nome de máquina) | Papel no site | Contraparte no tema | Estado |
| --- | --- | --- | --- |
| `address` | Campo de endereço (perfil do cliente, checkout) | `css/components/portal-address-number.css`; `js/address-layout.js`; entrada em `aculta420.libraries.yml` | Ativo |
| `commerce`, `commerce_cart`, `commerce_checkout`, `commerce_order`, `commerce_payment`, `commerce_price`, `commerce_store`, `commerce_number_pattern` | Loja, carrinho, checkout e pedidos | Template `commerce-product--produto-loja`; SDC `content/product-buy`, `content/product-details`, `patterns/product-tabs`; `css/components/product.css`; `css/components/portal-cart.css` e `portal-account.css` (tabela de pedidos); cartão `portal-cart-status` (Portal) | Ativo |
| `commerce_shipping` | Envio (pane "Envio" do checkout) | Mesma apresentação do checkout (`portal-address-number.css`); sem template próprio | Ativo |
| `aculta_pagamentos_mercadopago` | Gateway de pagamento (porte próprio) | Aviso `portal-payment-notice` (Portal, link para a conta do Mercado Pago); sem template do gateway no tema | Ativo |
| `crop` | Recorte de avatar e de foto | `css/components/dialog.css` (regra `:has()` para o popup de foto e recorte) | Ativo |
| `domain`, `domain_alias`, `domain_config`, `domain_source` | Domínios, propósitos e apresentação por domínio | Cabeçalho por propósito `css/components/header.css`; barra institucional `institution-bar.css`; navegação `navigation.css`. Contrato: `DOMAIN-PRESENTATION-CONTRACT.md` | Ativo |
| `flag` | Ações de tópico (fórum) | `css/components/forum-index.css` (`.flag` nas ações do tópico); `js/alerts-dismiss.js`; `templates/views/views-view-table.html.twig` | Ativo |
| `lms` | Cursos, cartão de curso e lições | SDC `content/course_card` substitui `lms:course_card`; View `courses_catalog` usa rail nativo | Ativo |
| `message`, `private_message`, `message_notify`, `message_subscribe` | Mensagens privadas, notificações e assinaturas | SDC `content/messenger-item`, `content/messenger-compose`, `content/messenger-notification`, `patterns/messenger-list`, `feedback/messenger-state`; template `private-message-thread.html.twig`; `css/components/messenger-drawer.css`. Guia: `web/themes/custom/aculta420/docs/messenger.md` | Ativo |
| `profile` | Perfil do usuário (entidade) | `css/components/public-profile.css`; SDC `patterns/social-links-page` | Ativo |
| `site_alert` | Avisos do site | `templates/site-alert.html.twig` | Ativo |
| `social_auth`, `social_auth_google`, `social_api` | Login com provedores | `templates/login-with.html.twig` (botão "Continuar com ..."); o protocolo é do contrib | Ativo |
| `vvjb` | Exibição de views (carrossel e lista) | `templates/views-view-vvjb.html.twig`; SDC `patterns/carousel` | Ativo |
| `webform`, `webform_ui` | Formulários de envio (suporte, contato) | `css/components/forms.css` (`.webform-submission-form`); `css/components/institutional.css` | Ativo |
| `turnstile`, `captcha` | Anti-bot dos formulários | Sem aparência própria no tema; o widget é do contrib e a política é do Portal (`TURNSTILE.md`) | Sem contraparte visual |
| `pdfpreview` | Miniatura de PDF (habilitado) | Sem contraparte: o viewer do tema é do `aculta_pdf_viewer` (`css/components/pdf-viewer.css`). O `aculta_pdf_viewer` 0.1.0-dev.8 removeu o PDFPreview da lista de dependências | Verificar se pode ser desabilitado |
| `photoswipe`, `photoswipe_dynamic_caption` | Visualizador de imagens | Sem template ou CSS próprio identificado no tema | Verificar |

### Sem contraparte visual própria

Módulos de API, infraestrutura, segurança, dados ou editor. Não têm marcação que o tema precisa desenhar.

- **Entidades e dados:** `entity`, `entity_embed`, `entity_reference_revisions`, `entity_usage`, `inline_entity_form`, `file_mdm`, `physical`, `token`, `key`, `embed`, `freelinking`, `node_co_authors`, `diff`, `content_lock`, `group`, `history`.
- **Mídia e imagem:** `media_duplicates`, `media_gallery`, `image_widget_crop`, `imageapi_optimize`, `imageapi_optimize_binaries`, `imagemagick` (a política de imagem é do `aculta_image`).
- **Conta, acesso e segurança:** `agreement`, `change_mail_page`, `email_confirmer`, `flexible_permissions`, `flood_control`, `login_emailusername`, `role_hierarchy`, `role_inheritance`, `username_enumeration_prevention`, `security_review` (busca de CEP é do Portal).
- **Busca, SEO e rotas:** `metatag`, `schema_metatag`, `pathauto`, `redirect`, `rename_admin_paths`, `simple_sitemap`, `search_api`.
- **Correio e fila:** `symfony_mailer`, `sophron`, `state_machine`.
- **Blocos e componentes:** `ui_patterns`, `vvj_core` (base do `vvjb`). Os componentes do tema são SDC do Core.
- **Ferramentas de configuração e campos:** `ctools` (blocos de Views configuráveis pelo Portal, `ctools_views`), `ui_styles` (estilos definidos por módulos e temas), `fraction` (campo numérico), `lms_file_upload` (resposta com envio de arquivo do LMS) e `webform_score` (pontuação de elementos do webform). Sem marcação própria no tema.
- **Formatos de Views do pacote `vvj`:** `vvja`, `vvjf`, `vvjh`, `vvjl`, `vvjr`, `vvjs` e `vvjt` não têm template no tema; só `vvjb` tem (`views-view-vvjb.html.twig`). Estado: verificar se algum deles é usado no site antes de desenhá-lo no tema.

## Conteúdo sem módulo customizado próprio

Estas peças são de Core, contrib ou configuração exportada. O tema as desenha, mas nenhum módulo customizado as possui.

| Peça do tema | Origem | Observação |
| --- | --- | --- |
| `comment--comment-forum`, `forum-list`, `node--forum--full` | Fórum Drupal contrib legado | Templates removidos em 0.6.0-dev.105 depois da limpeza dos dados no Runtime de teste |
| `comment--aculta-discussion-post`, `css/components/discussion.css` | Comment compartilhado do `aculta_forum` | Identidade preparada pelo `aculta_user`; o tema controla apresentação |
| `node--editorial-highlight`, `editorial-card`, `editorial-carousel.css`, `editorial-carousel.js` | Tipos de conteúdo de notícias (configuração) | Sem módulo customizado próprio |
| `node--project--teaser`, `project-card`, `views-view-unformatted--aculta-projects` | Tipo de conteúdo Projeto e view | Configuração exportada |
| `views-view-unformatted--courses-catalog`, `views-view-vvjb`, `views/views-view-table` | Views | Configuração exportada |
| `commerce-product--produto-loja`, `product-buy`, `product-details`, `product-tabs`, `product.css` | Commerce (produto) | Loja em `aculta_portal` |
| `block--block-content--type--aculta-institution`, `institutional.css`, `institution-bar.css` | Bloco institucional (configuração) | Sem módulo customizado próprio |
| `block--system-branding-block`, `header.css`, `footer.css`, `navigation.css`, `breadcrumb.css`, `forms.css`, `buttons.css`, `auth.css`, `login-with.html.twig` | Shell e formulários do tema | Não pertencem a um módulo |
| `wiki.css`, `node--wiki-entry--full`, `node--wiki-entry--teaser`, SDC `patterns/wiki-entry` | Wiki (`WikiController` do Portal) | Ligado à linha do `aculta_portal` |

## Como registrar uma correspondência nova

1. Adicione ou atualize a linha do módulo nas tabelas acima, com o caminho completo entre crases.
2. Confirme que cada caminho citado existe (`php scripts/validate-module-theme-map.php`).
3. Se o CSS for novo, registre-o em `aculta420.libraries.yml` e na linha do módulo dono.
4. Atualize `TOKENS-E-ATRIBUTOS.md` quando o módulo criar um token `data-portal-ui`.
5. Rode também `php scripts/validate-module-theme-boundary.php` e `php scripts/validate-portal-ui-docs.php`.
