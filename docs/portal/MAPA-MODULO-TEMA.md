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
   tabelas correspondentes, com o caminho completo em crase. Os módulos do Portal ficam nas tabelas acima;
   módulos ACULTA fora do Portal têm tabela própria abaixo. Todo módulo **contrib habilitado** (em
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
   na linha do módulo dono do plugin. Plugins citados abaixo: `AcultaPdfMediaFormatter`, `DocxMediaFormatter`, `SpreadsheetMediaFormatter` e `AcultaDocumentMediaFormatter` (`aculta_document_viewer`),
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
| `web/modules/custom/aculta_portal/modules/aculta_user` | 0.4.0-dev.46 | Perfil público, identidade/autoria compartilhada e variantes contextualizadas; cartões de comentários; ano de conta só em perfil público no Fórum; variante editorial entrega avatar, bio e redes com opt-in; atividade privada inclui tópicos/respostas publicadas | SDC `content/author-card` via hook neutro `aculta_user_author_card`; template `comment--aculta-discussion-post.html.twig`; `public-profile.css`; SDC `patterns/social-links-page`; templates de perfil e foto | User aplica política e cache; ACULTA420 apresenta o cartão em Fórum, Revista e demais contextos preparados |
| `web/modules/custom/aculta_portal/modules/aculta_forum` | 0.1.0-dev.134 | Motor de discussão e Context API; Views públicas e administrativas, categorias, comentários, autoria via `aculta_user`, Flags, movimento individual/em lote, anexos contextuais pela política central do Portal e integração com `aculta_gallery` | ACULTA420 apresenta tópicos, comentários, autoria, categorias, estado vazio e modais de anexo/galeria via tokens `forum-attachment-dialog` e `forum-gallery-dialog` | ACULTA420 controla apresentação; Portal mantém access/cache/publicação e revalida entidades. Leitor PDF e Office continuam em fases futuras do roadmap do Fórum. |
| `web/modules/custom/aculta_portal/modules/aculta_links` | 0.4.0-dev.30 | Entidades revisionáveis de destino, micropágina e composição; dashboard com navegação por seção, filtros de publicação e editor inline; QR via serviço compartilhado `aculta_qrcode` | ACULTA420 reutiliza o SDC `social-links-page` no template público; dashboard usa apresentação administrativa nativa do Drupal sem classe de design emitida pelo módulo | Runtime de teste confirmou serviços e landing; sem micropágina publicada para download QR HTTP positivo; revisão visual autenticada pendente antes de RC |
| `web/modules/custom/aculta_portal/modules/aculta_lms_messenger` | 0.1.0-dev.8 | Adapter opcional que publica avisos LMS em Message/`aculta_messenger`, processa lembretes de prazo por cron/fila e envia e-mails conforme evento; sem storage ou painel paralelo | Apresentação compartilhada pelos componentes `content/messenger-notification`, `patterns/messenger-list` e `feedback/unread-badge`, além de `css/components/messenger-drawer.css`; sem componente visual próprio do adapter | Dev; resumo diário, prazo e fila de lembrete cobertos em Unit; persistência Kernel, Runtime/cron e revisão visual pendentes |
| `web/modules/custom/aculta_portal/modules/aculta_qrcode` | 0.4.0-dev.2 | Serviço central de validação/geração e gerenciamento de códigos QR dinâmicos; a geração estática SVG é consumida pelo `aculta_links` | SDCs `content/qr-preview` e `content/qr-download`; formulários administrativos continuam com apresentação Core/ACULTA420 | Dev; módulo incluído em `core.extension` para suportar Links; Runtime e conexão visual dos SDCs pendentes |
| `web/modules/custom/aculta_portal/modules/aculta_statistics` | 0.9.0-dev.2 | Catálogo de métricas, gravação única, retenção por métrica (cron e painel), auditoria de prazo, painel central e por área (período 7/14/30, comparação, CSV) e comportamento do banner de consentimento (biblioteca `aculta_statistics/consent`) | `components/feedback/consent-banner` (SDC, oculto até a decisão, marcador `data-portal-ui="consent-banner"`) e `css/components/consent-banner.css`; painel com tokens `data-portal-ui="statistics-*"` (panel, areas, controls, form, summary, comparison, chart, table, note) mapeados em `PortalUiHooks` e estilizados por `css/components/statistics.css` | Dev |
| `web/modules/custom/aculta_portal/modules/aculta_mail_confirmer` | 0.1.0-dev.2 | Confirmação de endereço de e-mail (entidade `aculta_mail_confirmer`, resposta e reenvio por link de 24 h), incorporada do contrib `email_confirmer`. Sem contraparte visual própria: a página de resposta usa o tema pelo Core/Portal | Sem contraparte visual | Dev: validar no navegador |
| `web/modules/custom/aculta_portal/modules/aculta_mail_confirmer/modules/aculta_mail_confirmer_user` | 0.1.0-dev.3 | Confirmação de troca de e-mail e sincronização de estado no login; não bloqueia acesso global de conta ativa não confirmada | Sem contraparte visual | Dev: validar confirmação/troca no navegador e capacidades restritas |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms` | 0.1.0-dev.28 | Adapter LMS com painel e filas por curso/turma, discussões gerais e vínculo direto Forum↔H5P com access revalidado | ACULTA420 mapeia os tokens `lms-activity-discussions`, `lms-h5p-activity` e `lms-h5p-discussions` para primitives Bootstrap | Kernel valida acesso contextual, isolamento e montagem do player; revisão visual do player com tópico depende de conteúdo H5P de teste no Runtime. |
| `web/modules/custom/aculta_portal/modules/aculta_wiki` | 0.1.0-dev.77 | Wiki420, WE SATIVA, verbetes versionados, propostas, workflow Core, busca e Content Index; domínio e purpose continuam no Portal | SDCs `wiki-entry`, `context-header`, `content-section`, `content-grid`, `card`, `empty-state`; `wiki.css`; tokens `we-sativa-*` em `PortalUiHooks` no ACULTA420 | Dev; Kernels Wiki/Studio aprovados; visual Firefox/X11, mobile/Olivero e homologação Runtime pendentes |
| `web/modules/custom/aculta_portal/modules/aculta_forum_wiki` | 0.1.0-dev.1 | Integra o motor de discussões do `aculta_forum` aos verbetes da Wiki | Sem contraparte própria: reutiliza a apresentação de comentários do Fórum e o SDC `aculta420:wiki-entry` | Dev; depende da habilitação autorizada da Wiki |
| `web/modules/custom/aculta_portal/modules/aculta_messenger` | 0.1.1-dev.149 | Mensagens privadas, barra do Messenger, avisos, alertas e provider tagged do resumo diário; filtros por origem/leitura na central e gaveta; idioma único `pt_BR`; notificações de conteúdo não criam conversas | SDC: `patterns/messenger-list`, `patterns/messenger-trigger`, `patterns/messenger-read-status`, `content/messenger-compose`, `content/messenger-item`, `content/messenger-notification`, `feedback/messenger-state`, `feedback/unread-badge`. Templates: `private-message-thread`, `site-alert`, marcação da barra em `page`. CSS: `messenger-drawer.css` e `alerts-trigger.css`. JS: `messenger-drawers.js`; loading/error específicos da gaveta e vazio da central acessíveis. Guia: `web/themes/custom/aculta420/docs/messenger.md` | Ativo |
| `web/modules/custom/aculta_portal/modules/aculta_magazine` | 0.4.0-dev.1 | Bundle único `revista`; workflow editorial, comentários contextuais e incorporação de galerias compartilhadas; `article`/`blog` aposentados | `node--revista--full.html.twig` usa o SDC `patterns/revista-publication`; `comment--aculta-discussion-post.html.twig`; `discussion.css`; SDC `content/author-card` via `aculta_user`; overrides dos bundles aposentados removidos em 0.6.1-dev.4 | O tema apresenta conteúdo, comentários e autoria; access e identidade pertencem aos módulos proprietários |
| `web/modules/custom/aculta_portal/modules/aculta_gallery` | 0.1.0-dev.14 | Galerias transversais `media_gallery`, plugin CKEditor e renderização de imagens anexadas pelo Fórum | `album-slideshow.css`, `album-slideshow.js`. Detalhes em `aculta_gallery/docs/TEMA-E-HOOKS.md` | Ativo; validação pré-RC pendente |
| `web/modules/custom/aculta_portal/modules/aculta_editor` | 0.1.0-dev.5 | Formatos e editor CKEditor 5 do Portal (Plugin Pack). Submódulo opcional: desabilitado, o Portal não depende dele | Nenhuma: sem template, CSS nem JS próprios; o editor é configuração do Core | Dev |
| `web/modules/custom/aculta_portal/modules/aculta_lms_resources` | 0.1.0-dev.2 | Vincula publicações, verbetes Wiki e galerias existentes aos cursos do Drupal LMS | Nenhuma: sem template, CSS nem JS próprios; os recursos aparecem pelas listas e cartões dos donos | Dev; configuração e smoke autenticado pendentes |
| `web/modules/custom/aculta_portal/modules/aculta_studio` | 0.1.0-dev.1 | Página própria `/studio` (domínio conta) em três níveis: áreas no topo, submenu à esquerda com as seções e abas de cada seção; cada área expõe ações do dono, sem conteúdo próprio | Tokens `studio-*` (raiz, áreas, layout, submenu, abas, painel, título, ações, vazio) traduzidos em `PortalUiHooks::TOKENS`; CSS `css/components/studio.css`; JavaScript próprio do módulo (navegação AJAX dos três níveis) | Dev; Runtime de teste com as áreas Revista e Cursos; revisão visual no Firefox/X11 13/13 |
| `web/modules/custom/aculta_portal/modules/aculta_document_viewer` | 0.1.0-rc.1 | Leitores de PDF (`aculta_document_viewer_pdf`), DOCX (`aculta_document_viewer_doc`, docx-preview em iframe isolado) e planilhas XLSX, ODS, XLS e CSV (`aculta_document_viewer_sheet`, SheetJS + Tabulator), formatter composto `aculta_document_viewer_media`, miniatura PDF (`aculta_image.pdf_thumbnail`), cabeçalho e barra compartilhados (`DocumentViewerChrome`) e painel administrativo em 4 subabas (`Geral`, `PDF`, `Documentos`, `Planilhas`) | Marcação neutra `data-aculta-pdf-viewer`, `data-aculta-document-part` e `data-aculta-document-viewer`; CSS `css/components/pdf-viewer.css` e `css/components/document-viewer.css` (tokens semânticos) | RC (`0.1.0-rc.1`) |
| `web/modules/custom/aculta_portal/modules/aculta_donation` | 0.1.0-dev.3 | Campanhas de doação, fluxo de pedido e checkout (port do commerce_donation_flow), blocos, configuração do fluxo | Tokens `data-portal-ui` `donation-*` (traduzidos em `PortalUiHooks`); SDC `donation-quick`, `donation-pane`, `donation-dedication`, `donation-notification`, `donation-summary-container`; templates `aculta-donation-*` e `commerce-checkout-pane--commerce-*` | Runtime aplicado (corte de 2026-10-17); conferência visual pendente |
| `web/modules/custom/aculta_portal/modules/aculta_image` | 0.1.0-dev.23 | Ingestão, política de upload, catálogo de finalidades (`ImageCatalog`), editor de imagem (`aculta-image-model.js`) | `revista-post.css` (largura das imagens do texto); a escolha de tamanho vem do módulo, a apresentação do tema | Ativo |
| `web/modules/custom/aculta_portal/modules/aculta_mailer` | 0.1.0-dev.1 | Plataforma de e-mail transacional em F0/F1: contratos `MailMessage`, `MailStatus`, `MessageIdFactory`, `RetrySchedule` e interface `AcultaMailerInterface`. Opt-in, não habilitado, sem envio ativo | Nenhuma nesta fase: não há templates, SDC nem CSS. Templates de layout (F3/F4) exigirão contrato de tema antes de implementação | Sem efeito em runtime |
| `web/modules/custom/aculta_portal/modules/aculta_portal_cep` | 0.1.0-dev.1 | Consulta de CEP (`/aculta_portal_cep/{cep}`, ViaCEP com fallback BrasilAPI, cache, limite por IP) e autocompletar do endereço (`cep-address.js`, biblioteca `aculta_portal_cep/cep-address`), portados do `cep_autocomplete` 1.0.0 | Nenhum (o campo é do formulário do Portal; sem CSS próprio) | Ativo |
| `web/modules/custom/aculta_portal/modules/aculta_deployer` | 0.1.25 | Relatórios de ambiente e status (`DeployerStatusController`) | `portal-requirements-report.css` | Ativo |
| `web/modules/custom/aculta_portal/modules/aculta_portal_sitemap` | 0.1.1 | Sitemap | Sem contraparte visual própria | Sem contraparte |
| `web/modules/custom/aculta_portal/modules/aculta_game` | 0.1.0-dev.12 | Conquistas e missões; aprovação tardia no Fórum só é elegível na primeira publicação; legados e outros contextos ficam excluídos | Sem contraparte identificada no tema | Sem contraparte visual própria |
| `web/modules/custom/aculta_portal/modules/aculta_lms` | 0.1.0-dev.18 | Integração com o LMS (cursos, aulas, matrícula, certificados, painéis, prazos por turma e eventos educacionais); permissões Group contextualizadas | Sem contraparte própria; o cartão de curso é do contrib `lms` (ver abaixo) | Unit, Kernel de instalação e Kernel Group dos prazos PASS; Kernel de aviso Message, visual e Runtime DEFERRED |
| `web/modules/custom/aculta_portal/modules/aculta_patcher` | 0.1.0-dev.1 | Registro administrativo de diffs Composer e exportação validada para build | Aviso do formulário com `notice-warning` (tema); demais formulários e tabela administrativa nativos do Drupal | Sem contraparte de tema |
| `web/modules/custom/aculta_portal/modules/aculta_activity` | 0.1.0-dev.8 | Bundle `activity`, validação condicional e provider de metadados do Content Index | Sem contraparte visual: o módulo não gera marcação de apresentação | Infraestrutura de conteúdo; apresentação pertence aos consumidores |
| `web/modules/custom/aculta_portal/modules/aculta_commerce` | 0.1.0-dev.4 | Integração de entidades Commerce da Loja, checkout configurado e publishers de eventos de pedidos | Sem contraparte visual própria do módulo; a experiência Green Stash é composta pelo Studio | Dev; testes de contrato Commerce e homologação de checkout pendentes |
| `web/modules/custom/aculta_portal/modules/aculta_commerce/modules/aculta_commerce_melhorenvio` | 0.1.0-dev.1 | Adaptadores de endereço/CEP e integração logística com Melhor Envio | Sem formulário nem marcação própria; consome os dados de endereço do Portal | Fundação; cotação, etiquetas e integração Commerce Shipping pendentes |
| `web/modules/custom/aculta_portal/modules/aculta_commerce/modules/aculta_commerce_messenger` | 0.1.0-dev.1 | Identidade hidden de compatibilidade; publishers/consumers executáveis foram migrados | Sem contraparte visual; não habilitar em novas instalações | Aposentado; desinstalação controlada requer janela autorizada |
| `web/modules/custom/aculta_portal/modules/aculta_index` | 0.1.0-dev.175 | Catálogo derivado e APIs de consulta/rebuild para providers dos módulos proprietários | Sem contraparte visual: consumidores de conteúdo compõem seus próprios resultados | Dev; Runtime e módulos owners ativos fora do Config Sync permanecem por reconciliar |
| `web/modules/custom/aculta_portal/modules/aculta_wiki/modules/aculta_wiki_messenger` | 0.1.0-dev.1 | Identidade hidden de compatibilidade; publisher permanece em `aculta_wiki` e consumidor em `aculta_messenger` | Sem contraparte visual; não habilitar em novas instalações | Aposentado; desinstalação controlada requer janela autorizada |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p` | sem versão no info.yml | Conteúdo interativo H5P em atividades e cursos: editor, renderização por curso e biblioteca administrativa | Sem template próprio no tema. Tokens `h5p-admin-header`, `h5p-dashboard-course`, `h5p-content-unavailable` e `lms-h5p-course-documents` (`PortalUiHooks`). Classes `h5p-content`, `h5p-editor`, `h5p-iframe-wrapper` e `h5p-iframe` são contratos do H5P Core, não classes de design (exceções do gate de fronteira) | Ativo |
| `web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago` | 0.1.0-dev.63 | Gateway Checkout Pro, retorno, webhooks assinados, recorrência e conciliação do Mercado Pago | Aviso `portal-payment-notice` (Portal) e token `checkout-help`; configuração nativa permanece no Claro, sem UI de credenciais: o ambiente fornece as chaves | Dev; homologação sandbox pendente |
| `web/modules/custom/aculta_portal/modules/aculta_secrets_manager` | 0.1.0-dev.5 | Gestão de segredos e requisitos operacionais do Portal | Sem contraparte visual; interface é administrativa/nativa | Dev |
| `web/modules/custom/aculta_portal/modules/aculta_index/modules/aculta_index_file` | 0.1.0-dev.1 | Integração de arquivos no índice de conteúdo | Sem contraparte visual própria; resultado usa a apresentação do índice | Dev |
| `web/modules/custom/aculta_portal/modules/aculta_id_cpf` | 0.3.0-dev.1 | Identidade fiscal oficial (CPF/CNPJ) | Sem contraparte própria; usa formulários do tema (`forms.css`) | Sem contraparte |
| `web/modules/custom/aculta_portal/modules/aculta_calendar` | 0.1.0-dev.3 | Motor de recorrência, calendário interativo, embed e exportação a partir dos provedores de itens | Tokens `calendar-interactive-*` e `calendar-embed-hosts` mapeados em `PortalUiHooks`; a biblioteca DHTMLX permanece responsabilidade do módulo | Dev; gate módulo–tema e testes passam; revisão visual ADM8 e do calendário pendente |
| `web/modules/custom/aculta_portal/modules/aculta_content_admin` | 0.1.0-dev.2 | Painel geral de coleções cujo usuário já pode moderar | Sem contraparte visual específica; tabela administrativa nativa do Drupal | Dev |
| `web/modules/custom/aculta_portal/modules/aculta_events` | 0.1.0-dev.2 | Eventos, catálogo, datas e locais do purpose eventos | SDC `content/event_card`; tokens de mapa/aviso em `PortalUiHooks`; apresentação pública do evento usa as peças registradas para o catálogo | Dev; catálogo e gate módulo–tema cobertos por Kernel |
| `web/modules/custom/aculta_portal/modules/aculta_ugc` | 0.1.0-dev.1 | Infraestrutura transversal de moderação, denúncias e conteúdo gerado por usuários; decisões e filas são consumidas pelos módulos proprietários | Sem apresentação própria; Fórum e Studio usam os componentes visuais dos respectivos owners | Dev; segurança, integração e homologação seguem ARQ-06 |
| `web/modules/custom/aculta_portal/modules/aculta_events_openstreetmap` | 0.1.0-dev.1 | OAuth e integração de edição OSM para locais | Sem contraparte específica; telas de configuração administrativa usam o tema de administração Drupal | Dev; validação OAuth/escrita OSM segue gates do módulo |
| `web/modules/custom/aculta_portal/modules/aculta_faq` | 0.1.0-dev.2 | Perguntas frequentes e blocos editáveis com texto sanitizado | Conteúdo segue renderização genérica de Node/Block do Drupal; sem componente visual próprio identificado | Dev |
| `web/modules/custom/aculta_portal/modules/aculta_lms_agenda` | 0.1.0-dev.1 | Provedor de prazos e eventos de turma, restrito às matrículas | Sem contraparte própria; agenda apresentada pelo `aculta_calendar` | Dev; acesso de matrícula coberto no roadmap LMS Agenda |
| `web/modules/custom/aculta_portal/modules/aculta_maps` | 0.1.0-dev.5 | API compartilhada de mapas e geocodificação | Tokens `map-*` mapeados em `PortalUiHooks`; motor Leaflet e atribuição alimentam a marcação neutra | Dev; fronteira módulo–tema e Kernel passam; revisão editorial/visual pendente |

### Módulos ACULTA fora do Aculta Portal

| Caminho | Entrega (módulo) | Contraparte no tema | Estado |
| --- | --- | --- | --- |
| `web/modules/custom/aculta_mcp_client` | Cliente de documentação MCP com ferramentas remotas allowlistadas para Mercado Pago e Melhor Envio | Sem contraparte visual; a configuração dos serviços usa a interface administrativa do Drupal | Ativo |

## Módulos contrib habilitados

Escopo: módulos com diretório em `web/modules/custom/**` ficam nas seções acima; módulos com diretório em
`web/modules/contrib/` e habilitados em `config/sync/core.extension.yml` ficam aqui. Módulos do Core
(`web/core/modules/`) não entram; as peças de tema que sobrescrevem templates do Core estão em "Conteúdo sem módulo
customizado próprio". A regra de cobertura do gate vale para cada nome de máquina listado abaixo.

### Com contraparte no tema

| Módulo (nome de máquina) | Papel no site | Contraparte no tema | Estado |
| --- | --- | --- | --- |
| `ckeditor5_plugin_pack` | Plugins adicionais do editor CKEditor 5 | Sem contraparte própria do tema; controles integram a interface do editor | Ativo |
| `google_tag` | Integração de tags de medição | Sem contraparte visual própria do tema | Ativo |
| `geofield` | Campo geográfico usado pelos dados de localização | Sem apresentação própria; os widgets e formatters seguem a infraestrutura de Field API/Core | Ativo |
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
| `views_bulk_operations` | Seleção de linhas, configuração, confirmação e Batch API para ações Views | Campos de formulário/admin da View, sem peça visual customizada; o tema admin apresenta os controles | Usado pelo `aculta_forum` para seleção em lote; a Action valida cada Node e não substitui Entity Access |
| `webform`, `webform_ui` | Formulários de envio (suporte, contato) | `css/components/forms.css` (`.webform-submission-form`); `css/components/institutional.css` | Ativo |
| `turnstile`, `captcha` | Anti-bot dos formulários | Sem aparência própria no tema; o widget é do contrib e a política é do Portal (`TURNSTILE.md`) | Sem contraparte visual |
| `pdfpreview` | Miniatura de PDF (habilitado) | Sem contraparte: o viewer do tema é do `aculta_document_viewer` (`css/components/pdf-viewer.css`). O `aculta_document_viewer` usa `aculta_image.pdf_thumbnail` sem exigir `pdfpreview` como dependência obrigatória | Verificar se pode ser desabilitado |
| `photoswipe`, `photoswipe_dynamic_caption` | Visualizador de imagens | Sem template ou CSS próprio identificado no tema | Verificar |

### Sem contraparte visual própria

Módulos de API, infraestrutura, segurança, dados ou editor. Não têm marcação que o tema precisa desenhar.

- **Entidades e dados:** `entity`, `entity_embed`, `entity_reference_revisions`, `entity_usage`, `entity_print` (exportação de entidade sem componente visual próprio do ACULTA), `inline_entity_form`, `file_mdm`, `physical`, `token`, `key`, `embed`, `freelinking`, `node_co_authors`, `diff`, `content_lock`, `group`, `history`.
- **Mídia e imagem:** `media_duplicates`, `media_gallery`, `image_widget_crop`, `imageapi_optimize`, `imageapi_optimize_binaries`, `imagemagick` (a política de imagem é do `aculta_image`).
- **Conta, acesso e segurança:** `email_confirmer`, `flexible_permissions`, `flood_control`, `login_emailusername`, `role_hierarchy`, `role_inheritance`, `username_enumeration_prevention`, `security_review` (Termos e consentimentos ficam no `aculta_user`; busca de CEP é do Portal).
- **Busca, SEO e rotas:** `metatag`, `schema_metatag`, `pathauto`, `redirect`, `rename_admin_paths`, `simple_sitemap`, `search_api`.
- **Correio e fila:** `symfony_mailer`, `sophron`, `state_machine`.
- **Segurança do navegador e relatórios:** `csp` (política Report-Only para o leitor PDF; sem marcação própria) e `reporting` (endpoint local de relatórios, gravado no DBLog). Sem contraparte visual.
- **Desenvolvimento:** `sdc_devel` (apoio ao desenvolvimento de componentes SDC; não aparece no site público).
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
