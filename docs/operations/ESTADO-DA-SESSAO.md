# Continuação — Institution Bar, Gin e bloco Core Navigation quebrado — 2026-11-04

- **Objetivo:** corrigir a sobreposição da Institution Bar com o Gin na sessão administrativa, remover o aviso de bloco quebrado e seguir a migração visual V1→V2.
- **Causa do aviso:** `navigation.block_layout` mantinha `navigation_shortcuts` com `shortcut` desativado. O plugin legado não é descoberto e o Core renderizava `block_broken`. A configuração exportada foi corrigida e importada; Firefox autenticado não mostra mais o aviso.
- **Implementado:** barra fixa mede `#admin-toolbar`, ocupa a coluna geral depois da sidebar e atualiza durante resize/transição sem deslocar Gin. Coluna estreita empilha marca/utilitários. Margem fluida; gap dos ícones reduzido a zero; busca, carrinho, mensagens e avisos em 32 px (avatar). Painel de conta restrito à coluna geral.
- **Validação:** Firefox ESR visível, UID de teste 257, 11 larguras 320–1440, Gin aberto/fechado, claro/escuro e menu de conta aberto: 44/44 verificações de geometria PASS. SDC 25/25; Foundations 51 pares PASS; Shell contract 13 checks PASS. Config import e `drush cr` PASS; `updatedb:status` sem pendências; `config:status` limpo. Capturas: `$SUITE_OUT/aculta420-v2-admin-sidebar-20261104/accepted-final/`.
- **Limite observado:** com Gin recolhido, `scrollWidth` excede a viewport (385 px em 320; 420 px em 390). Diagnóstico aponta `.top-bar__actions` e controles do toolbar administrativo, fora da Institution Bar. A rolagem horizontal geral ficou DEFERRED para correção própria do toolbar. Zoom e teclado DEFERRED.
- **Causa e sync de configuração:** removida a entrada obsoleta `navigation_shortcuts`; import executado após snapshot externo de 66.256.506 bytes, SHA-256 `ff5eea49d393f4901f706bd57a53d8e07481edc212c097f912bc5724f95a1ea5`.
- **Git:** commit `26f0af0657` (`fix(aculta420_v2): reserva coluna do Gin e remove bloco quebrado`) publicado por fast-forward em `origin/dev`. Arquivos de CSS/JS, ícones e config do mesmo commit sincronizados no Runtime; HEAD local não foi trocado por estar 35 commits atrás e conter alterações paralelas.
- **Próximo:** continuar inventário da família Eventos conforme `web/themes/custom/aculta420_v2/docs/roadmap.md`, após concluir esta correção administrativa.

---

# Continuação — migração visual do Fórum V1 para SDC V2 — 2026-11-04

- **Objetivo:** prosseguir na migração visual completa V1→V2 por famílias de página; esta fatia cobre tópicos, categorias, Views públicas e apresentação compartilhada de discussão.
- **Feito:** SDCs `forum-topic`, `forum-category` e `discussion-comment`; adapters para node, Views e comentário; estilos de listagem, busca, estados vazios e discussão. A V2 mantém render arrays de conteúdo/autoria/contagem.
- **Arquivos na worktree de migração:** `web/themes/custom/aculta420_v2/components/{forum-topic,forum-category,discussion-comment}/`, `src/styles/forum.css`, `templates/content/node--aculta-forum-topic.html.twig`, `templates/views/views-view-{fields,unformatted}--aculta-forum-*.html.twig`, `templates/comment--aculta-discussion-post.html.twig`, `scripts/validate-aculta420-v2-sdc.php`, catálogo, roadmap e changelog.
- **Validações:** Drush descobriu e renderizou 25/25 SDCs; foundations 51 pares de contraste PASS; PHP lint e sintaxe Python PASS; Firefox ESR visível no purpose `forum`, landing/categorias/tópico real, 9 larguras, claro/escuro, teclado e zoom 200/400: 76/76 PASS. Corrigido o bloco da landing, que antes imprimia campos genéricos e não usava o SDC. Capturas em `$SUITE_OUT/aculta420-v2-forum-landing-20261104/`.
- **Runtime:** `toca`, Drupal 11.5.0-beta1, banco `aculta_runtime`; `updatedb:status` sem pendências, `config:status` limpo; `drush cr` PASS. Nenhuma alteração de config/conteúdo. O comentário `aculta_discussion_post` não apareceu nas rotas públicas verificadas; o componente passou render isolado e o estado real continua DEFERRED.
- **Git:** commit `79138060ae` (`feat(aculta420_v2): migra apresentação do Fórum para SDC`) enviado por fast-forward para `origin/dev`. Os templates de Views e CSS relevantes foram sincronizados ao checkout Runtime compartilhado e exercitados; o HEAD completo do Runtime não foi trocado para preservar alterações locais paralelas de conta, LMS e shell.
- **Próximo:** inventariar a família de Eventos. Retomar lendo `web/themes/custom/aculta420_v2/docs/roadmap.md`, `docs/architecture/ACULTA420-MOBILE-FIRST-GUARDRAILS.md`, ADR-046 seção 3 e os templates/CSS de Eventos V1.

# Continuação — Phosphor Icons, Leaflet e cache/CDN da V2 — 2026-11-04

- **Objetivo:** completar a integração Phosphor Icons (Duotone) no `aculta420_v2` e usar os SVGs do SDC nos marcadores Leaflet locais do `aculta_maps`, com compatibilidade de cache/CDN sem reintroduzir Vite, Bootstrap ou jQuery.
- **Feito:** vendorizados Phosphor Icons Core 2.1.1 (MIT), 1.512 SVGs regular e 1.512 duotone; criado SDC `icon` com `source()` inline e schema estrito. `badge`, `button`, `card-cultural`, barra superior, busca, carrinho, mensagens, avisos, navegação e status usam agora o sistema Phosphor; auditoria confirmou zero SVG de interface inline fora do SDC.
- **Maps:** hook neutro fornece template inerte de ícone; o tema escolhe Phosphor. `aculta-map.js` monta `L.divIcon` a partir do template, sem transferir SVG em `drupalSettings`; usa `core/once` e mantém o agrupador Leaflet próprio.
- **Cache/CDN:** bibliotecas Drupal versionadas/agregáveis, URLs relativas e sem hostname CDN fixado. SVG é parte do HTML cacheado; metadados de cache continuam em render arrays. TTL, surrogate headers e purge são configuração do ambiente. Vite permanece removido por decisão anterior.
- **Arquivos principais:** `web/themes/custom/aculta420_v2/assets/icons/phosphor/`, `components/icon/`, `components/{badge,button,card-cultural}/`, `templates/aculta-maps-marker-icon-template.html.twig`, `src/styles/tokens.css`; `web/modules/custom/aculta_portal/modules/aculta_maps/{src/MapRenderer.php,src/Hook/MapsThemeHooks.php,js/aculta-map.js,css/aculta-map.css,templates/}`; `scripts/validate-aculta420-v2-sdc.php`.
- **Validações:** 15/15 SDCs (schema/asset/render) PASS; MapRenderer Unit 4 testes/26 assertions PASS; PHP lint, PHPCS, Node syntax, YAML e `git diff --check` PASS. Drush render confirmou SVG inline; `drush cr` PASS; Runtime `updatedb:status` sem pendências e `config:status` limpo. HTTP home e Eventos `/mapa` retornaram 200. PHPUnit exibiu aviso de `browser_output` não gravável, mas os testes Unit passaram.
- **Runtime:** `toca`, Drupal 11.5.0-beta1, banco `aculta_runtime`, tema V2 ativo. Nenhum import de configuração, update de banco ou alteração de conteúdo. Após o push, repetir `drush cr` e os dois status read-only para confirmar HEAD.
- **Pendências:** revisão visual/assistiva e validação de headers/purge quando uma CDN/reverse proxy for habilitada.
- **Preservar:** alterações paralelas em `composer.json`, `composer.lock`, `docs/operations/status/aculta_lms.yml`, módulo `aculta_lms`, `web/themes/custom/aculta420/` e `web/themes/custom/aculta_theme/`; não incluir nesta entrega.
- **Retomar lendo:** este handoff; `web/themes/custom/aculta420_v2/assets/README.md`; `web/themes/custom/aculta420_v2/docs/roadmap.md`; `web/modules/custom/aculta_portal/modules/aculta_maps/README.md`.

## Continuação — conta fixa e popup AJAX nativo V2 — 2026-11-04

- A barra fixa volta a renderizar o menu Drupal `account` dentro de sua região
  utilitária. O render array original é movido do header preservando acesso,
  lazy builder e cacheabilidade; o menu entrega “Minha conta” e “Sair” após
  autenticação e “Entrar” para visitantes.
- “Entrar” abre `<dialog>` nativo; V2 busca o formulário do Portal com Fetch e
  envia o POST no formato `drupal_ajax`. O cliente aplica somente os comandos
  Portal/Core previstos: atualizar build ID, substituir o formulário em erro
  ou redirecionar no sucesso, validando destino same-origin. Busca também usa
  Fetch e mantém o behavior Vanilla de prévia do Portal.
- A V2 remove o carregamento global de `core/drupal.dialog.ajax` para esta
  apresentação. A home ainda carrega jQuery porque uma View VVJB existente
  anexa `core/drupal.ajax`; não é dependência dos componentes da barra e foi
  preservada para não remover AJAX da View.
- Validação: 15/15 SDCs; PHPCS e PHP lint; Node syntax; `drush cr`; HTTP 200 na
  home, popup de login e popup de busca; os dois formulários e a prévia foram
  encontrados no HTML dos endpoints. `updatedb:status` e `config:status` sem
  pendências/diferenças. Nenhuma credencial foi enviada; autenticação de ponta
  a ponta permanece para validação manual.
- **Refinamento visual V1 (dev.14):** o modal voltou ao verde exato `#6b9a2c`,
  com texto escuro (5,45:1), Google com SVG V1, sombra e realce creme em hover/
  foco. Firefox ESR visível no Runtime confirmou formulário, Google e Escape
  em claro/escuro, sem overflow nos viewports efetivos 500 e 1430 CSS px. O
  Firefox/X11 não reduziu abaixo de 500 px; testes em 320/390, zoom 200%, foco
  real do Google e outros purposes ficaram DEFERRED. Capturas e resultados em
  `$SUITE_OUT`. Gates: fundações 55 pares PASS; schemas 55/764 PASS; render SDC
  19/19 PASS; `drush cr` PASS. Pré-check `updatedb:status`/`config:status` limpo.
  Commit `e546eba028` publicado; só os arquivos do tema desta entrega foram
  sincronizados ao Runtime, sem trocar HEAD nem tocar config/banco.
- Arquivos desta entrega: `web/themes/custom/aculta420_v2/src/Hook/PagePresentationHooks.php`,
  `templates/layout/page.html.twig`, `components/multidomain-bar/`, manifesto/
  bibliotecas/changelog/roadmap do tema e `scripts/validate-aculta420-v2-sdc.php`.
  Foram preservadas alterações paralelas em Composer, LMS, `aculta420` e
  `aculta_theme`.
- **Publicação:** commit `bebdc2cde5e8d25082b17f476f5c2a9eaea82115` enviado por
  fast-forward a `origin/dev`.
- **Runtime:** `toca`, Drupal 11.5.0-beta1, banco `aculta_runtime`, tema
  `aculta420_v2`. Pós-push, `drush cr` passou, `updatedb:status` não requer
  updates e `config:status` não encontrou diferenças. Runtime é o checkout
  compartilhado e está no mesmo HEAD. Nenhum import de configuração ou update
  de banco foi executado.
- **Retomar lendo:** seção acima, `components/multidomain-bar/`,
  `PagePresentationHooks.php` e `LoginPopupForm.php`.

## Retomada — objetivo persistente LMS → pagamentos → updatedb, 2026-11-04

- **Objetivo:** concluir lacunas funcionais do LMS; somente depois trabalhar pagamentos e, por último, resolver pendências `updatedb` individualmente. Escopo da sessão é código/funcionalidade, sem revisão estética do tema.
- **LMS Resources:** `aculta_lms_resources` local está em dev.33. Além do upload PDF AJAX e dos widgets leitura/galeria/vídeo já validados, o builder de vídeo corrige a rota Core `media/{id}/edit` para linkar a URL oEmbed apenas depois de verificar Media publicada e Entity Access sob `courses`; URLs inválidas, credenciais e esquemas diferentes de HTTP(S) não são expostos. Unit + Kernel focal: 9 testes / 89 assertions PASS.
- **Smoke funcional Runtime:** conteúdo de teste descartável foi autorizado pelo responsável. Um Group temporário autenticado referenciou Wiki, galeria, vídeo e Media `remote_video` existente ID 12; os widgets carregaram e salvaram. A estrutura exibiu Wiki em `wiki420`, galeria em Revista e vídeo no destino YouTube. Antes da correção, o canonical Media `/media/12/edit` retornava 404 sem sessão editorial. Firefox headless verificou Form API, submit, foco e ausência de overflow em 320/390/600/768/1280 px; sem capturas ou critérios estéticos. Group removido e fontes editoriais preservadas. O PDF privado também passou pelo AJAX upload, persistência Group→Media→File e lista; todas as fixtures foram removidas. Formulário de PDF sem overflow em 320/390/768/1280 px.
- **Bloqueio Runtime H5P:** `aculta_lms_h5p.framework` falhou antes da criação de fixtures com “The private H5P storage directory is unavailable” (watchdog 843707). Nenhuma conta, turma, tópico ou conteúdo H5P de teste foi criado nesse smoke. Não alterei `private/` nem contornei a configuração do storage. Kernel permanece como cobertura da integração de discussão.
- **Runtime:** `toca`, Drupal 11.5.0-beta1, PHP 8.5.12, DB `aculta_runtime`, tema `aculta420_v2`. Pós-smoke: `updatedb:status` sem updates, `config:status` limpo e zero conteúdo de teste residual.
- **Git:** commits `972a1b4a33` (`corrige recursos funcionais dos materiais LMS`) e `c5e3da6f2e` (`atualiza status do LMS Resources`) enviados por fast-forward a `origin/dev`; o primeiro precisou rebasear após o remoto avançar. O Runtime usa o checkout principal ainda no HEAD `b05a3b131b`, 17 commits atrás, com alterações locais paralelas; os arquivos de Resources e status foram comparados ao publicado e coincidem, inclusive o hook novo. `updatedb:status` e `config:status` limpos; `drush cr` PASS. O HEAD local não foi trocado para preservar alterações alheias.
- **Índice geral:** `docs/operations/status/aculta_lms_resources.yml` atualizado para dev.33. `scripts/validate-project-status-index.php` continua FAIL por divergências preexistentes de nove outros componentes e README raiz desatualizado; não rodei `--write` para não editar outros módulos.
- **Próximos passos:** revisar os contratos F15/F16 ainda abertos, especialmente a indisponibilidade de storage H5P e o fechamento das integrações Forum/entregas; não avançar para pagamentos antes de resolver o que pode ser concluído no LMS. Depois pagamentos; por último `updatedb` item a item. O link direto à fonte oEmbed não altera o Content Index nem `media.settings` global.
- **Retomar lendo:** seção F15 em `docs/modules/ACULTA-LMS-ROADMAP.md`, `aculta_lms_resources/src/CourseResourceBuilder.php`, o erro H5P 843707 e a regra Runtime de `AGENTS.md`; depois revisar as integrações Forum/entregas antes de iniciar pagamentos.

# Estado da sessão

## Handoff — assets ACULTA, multidomain-bar e fundamentos V2 — 2026-11-04

- `aculta420_v2` recebe assets copiados seletivamente da V1: logos preto/branco
  horizontais e quadrados, variante horizontal 900×300 e favicon ICO. Não há na
  V1 logo separado do Coletivo 4e20, banners/texturas adequados ou manifest/touch
  icon. `site.webmanifest` foi criado e `apple-touch-icon.png` (180×180) foi
  gerado do bitmap 32×32 do favicon com fundo da superfície clara. Inventário:
  `web/themes/custom/aculta420_v2/assets/README.md`.
- `multidomain-bar` agora representa a faixa institucional V1: fixa no topo,
  logo com link ao domínio principal, somente ícones de busca/carrinho/mensagens/
  avisos e espaço reservado para não cobrir o conteúdo. Links de portais são
  separados em uma linha secundária opcional. `aculta_portal` fornece os dados
  neutros e cacheáveis; modo de cor continua no seletor explícito.
- O V2 permanece `base theme: false` e sem cadeia Bootstrap/jQuery: os ícones
  da barra são SVG inline, os painéis e confirmações usam `<dialog>` nativo, e
  nenhuma biblioteca do tema declara Bootstrap, Popper ou jQuery. O validador
  SDC reprova regressões de tema base, dependências, atributos ou classes do
  framework. Os behaviors usam Vanilla JS, `Drupal.behaviors` e `core/once`.
- A reconciliação V2 dos tokens segue a identidade da V1. A pendência do gate
  Design Foundations foi resolvida renomeando o token de tipografia Studio para
  seu namespace de fidelity, sem mudar seu valor nem sua apresentação.
- Validações atuais: SDC schemas 55/764 PASS; V2 SDC 14 componentes com
  descoberta, schema e render isolado PASS; Design Foundations 2468 PASS;
  JavaScript syntax, PHP lint, PHPCS focal e `git diff --check` PASS. Os scripts
  npm `lint:css/js` não se aplicam: o projeto e o tema V2 não têm `package.json` nem
  pipeline Vite. A validação de fronteira tema/módulo falha em seis classes
  antigas de formulários LMS, todas fora do escopo. O Runtime `toca`/
  `aculta_runtime` estava limpo antes do rebuild desta retomada.
- A captura visual não foi executada: `DISPLAY` e `SUITE_OUT` não estão
  configurados e `DISPLAY=:0` não tem servidor X disponível. A referência
  legada 390×844 permanece como artefato externo `aculta-institution-bar-scroll-baseline-581c9f571-final`;
  manter aceite visual como pendência, sem usar Firefox headless.
- O gate amplo `validate-aculta420-foundation.php` não pode validar esta sessão
  porque exige V1 como tema público padrão e Runtime está com V2. O SDC strict
  e Design Foundations foram executados separadamente e passaram. O validador
  de índice de status ainda reporta dez divergências preexistentes entre
  manifests e status de componentes fora do escopo; o README gerado foi
  mantido apenas com a linha do tema ACULTA420; linhas de outros componentes
  foram excluídas desta entrega. A validação do índice ainda reporta divergências
  preexistentes fora do tema.
- **Publicação:** commit `3a7341656da7fb248f37b1387c4a6352b5c7c7cb` enviado por
  fast-forward para `origin/dev`. O Runtime é o mesmo checkout; pós-push,
  `drush cr` passou e `updatedb:status`/`config:status` permaneceram limpos.
- **Retomar lendo:** assets README, `components/multidomain-bar/`,
  `PagePresentationHooks.php`, roadmap/changelog V2 e este handoff. Arquivos
  Composer, alterações paralelas do módulo Messenger, JS solto da V1 e o tema
  `aculta_theme` foram preservados fora desta entrega.

## Handoff — integração SDC `map-popup` / `aculta_maps` — 2026-11-04

- `aculta_maps` entrega dados de popup por render arrays neutros e hooks de tema; ACULTA420 V2 seleciona o componente SDC e o JavaScript de Leaflet e Google clona o template inerte. O fallback DOM seguro continua disponível para consumidores que não renderizam os templates.
- Requisitos de URL são filtrados server-side (caminhos relativos e HTTPS); texto segue autoescape Twig. Nenhum HTML do popup é serializado em `drupalSettings`.
- Composer: nenhuma configuração aplicada após auditoria. `drupal/geofield` já está em `composer.json` como `^10.3`; Maps usa Leaflet 1.9.4 vendorizado. O exemplo de Path exigiria migração dos fontes e traz package names/constraints incorretos para este repositório.
- Validação: SDC schema 55/764 PASS; MapRenderer Unit 4/23 PASS; PHP lint, Node syntax, `drush cr` e `git diff --check` PASS. `updatedb:status` e `config:status` limpos. Gates abrangentes de documentação de fronteira falham somente por lacunas preexistentes em módulos não relacionados.
- Runtime compartilhado: `toca`, Drupal 11.5.0-beta1, PHP 8.5.12, `aculta_runtime`, tema `aculta420_v2`. Nenhuma atualização de banco/import de config ocorreu. Smoke `/mapa` HTTP 200, sem marcadores Runtime; renderização sintética do serviço gerou template SDC. Revisão visual DEFERRED.
- Commit focal criado; push fast-forward confirmado no commit `3e8cfedfd160c3638c79f428ace832ebd3e40e5e`. Arquivos alheios não entraram no stage: `composer.json`, `composer.lock`, os arquivos do harness LMS e mudanças não rastreadas de temas.
- Próximo passo: concluir a revisão visual pré-RC dos popups Leaflet/Google.

## Handoff — instalar Gin como tema administrativo — 2026-11-03

- Instalado `drupal/gin:5.0.x-dev@dev`, lockado no commit upstream `cbb271ec8655495e61436049dd4c1c7ec780c669`; Composer também instalou `drupal/gin_toolbar` 3.0.3 como dependência.
- Gin foi habilitado e definido em `system.theme:admin`; Config Sync registra Gin em `core.extension` e inclui os nove objetos de configuração padrão exportados. `drush cr` passou, `updatedb:status` sem pendências e `config:status` sem diferenças.
- Claro não pôde ser desinstalado: `ThemeInstaller` bloqueia a remoção porque `gin.info.yml` declara `base theme: claro`. Claro permanece habilitado e necessário para a herança de Gin.
- Publicação: commit `d7d0be3f7856a022801e00ec31333a8d2644b590` enviado por fast-forward a `origin/dev`; Runtime está no mesmo checkout/HEAD.

## Handoff — chave duplicada no mapa PortalUiHooks — 2026-11-03

- Removida a segunda declaração idêntica do token `event-date-actions` em `web/themes/custom/aculta420/src/Hook/PortalUiHooks.php`; o mapeamento restante preserva a classe `aculta-event-date-actions`.
- `php -l` e `git diff --check` passaram. Reanálise `upgrade_status:analyze aculta420` não aponta a chave duplicada; ainda lista dois avisos `Ignore` de UI Patterns em `web/core/includes/bootstrap.inc`.
- O gate `validate-aculta420-foundation.php` falha na verificação existente da camada Bootstrap do tema; não relacionada à alteração. Nenhum teste foi executado.
- Publicação concluída no commit `0fcdbe6f802d8267d6247ca461035a2f45f2711d`, enviado por fast-forward a `origin/dev`; Runtime é o checkout compartilhado e está no mesmo HEAD.

## Handoff — alerta Upgrade Status em ACULTA MCP — 2026-11-03

- Corrigida a propriedade injetada `entityTypeManager` em `McpAccessSettingsForm`: `private readonly` passou a `protected readonly`, para ficar visível ao `DependencySerializationTrait` herdado de `FormBase`.
- `php -l` e `git diff --check` passaram. `upgrade_status:analyze aculta_mcp` reexecutado após `drush cr`: “No known issues found.” `updatedb:status` sem pendências e `config:status` sem diferenças.
- Publicação: commit `97524b75466ad8d0ada75315eb4be086d1c87652` enviado por fast-forward a `origin/dev`; Runtime é o checkout compartilhado, no mesmo HEAD, e já executou a reanálise.

## Handoff — Text With Summary 2.0.x-dev — 2026-11-03

- Adicionado `drupal/text_with_summary:2.0.x-dev@dev` ao Composer; lockado no commit upstream `89c4282cf25259564ce7fa41b9dd70f7fe7f496d` em `web/modules/contrib/text_with_summary`.
- Após `drush cr`, `extension.list.module` resolveu `text_with_summary` para `modules/contrib/text_with_summary`; módulo já habilitado. Nenhuma atualização de banco/configuração pendente.
- `composer validate --no-check-publish` válido, com avisos preexistentes de constraints exatas; `git diff --check` passou.
- Commit `4b176c0e72` enviado a `origin/dev`; Runtime compartilha o checkout e está no mesmo código.

## Handoff — remoção do módulo Search — 2026-11-03

- Desinstalado `search` do Runtime `aculta_runtime`; removido de `core.extension.yml` e excluídos `search.page.user_search` e `search.settings` do Config Sync. `search_api` e `search_api_db` continuam habilitados.
- `composer.json` não tinha requisito direto `drupal/search`; `composer.lock` registra o pacote como substituído por `drupal/core`. Nenhuma remoção Composer era aplicável.
- Validações: `updatedb:status` sem pendências, `config:status` sem diferenças, Search ausente da lista de módulos ativos, `composer validate --no-check-publish` válido com avisos preexistentes de constraints exatas.
- Commit `20d5a2041a175a0cdc1bf88780b8ab918b5d0fbb` enviado por fast-forward a `origin/dev`; Runtime no mesmo checkout/HEAD. Próximo passo: nenhuma ação pendente para Search.

## Handoff — LMS, pagamentos e estado do Drupal 12 — 2026-11-03

- Fluxo de revisão de respostas concluído no código: listagem paginada e
  limitada à turma/estudantes ativos, verificação de curso-turma/matrícula,
  nota validada (0–100), feedback via Comment API e arquivo privado protegido.
  A permissão `administer lms` agora tem serviço de hook registrado.
- Harnesses Kernel ajustados para os tipos Group/Comment e relação LMS atuais,
  sem carregar `lms`/`lms_classes` contrib legados.
- Validações: Unit LMS 234/828 e Kernel LMS 33/996 PASS; Unit Mercado Pago
  258/1.156 e Kernel Checkout Pro + update de cartão 4/152 PASS. PHPCS Drupal,
  PHP lint e `git diff --check` PASS. O Kernel LMS emite 62 depreciações
  upstream de Core/contrib; alerta de diretório `browser_output` não gravável.
- Não houve chamada de pagamento real nem mensagem externa. A homologação com
  comprador sandbox continua pendente do desafio de segurança do provedor.
- Runtime `aculta_runtime`: checkout compartilhado em `5d6f14ad4b`, Drupal
  11.5.0-beta1, PHP 8.5.12. Após a sincronização dos commits, `drush cr` PASS;
  `updatedb:status` sem pendências e `config:status` sem diferenças.
  A migração para D12 ainda não foi feita.
- O dry-run Composer para D12 continua bloqueado por vários contrib. A release
  Bootstrap 8.x-3.45 declara Drupal 12 no `bootstrap.info.yml`, mas o requisito
  no `composer.json` do pacote continua `drupal/core ^10.5 || ^11.2`; Commerce
  3.3.x declara apenas `^10.3 || ^11`. Não trocar a linha de Bootstrap.
- Search foi removido no commit `71a86fa9fa`; o módulo substituto
  `text_with_summary` 2.0.x-dev (`^11.5 || ^12`) foi instalado no commit
  `4b176c0e72`. `core:requirements` deixou de reportar módulos depreciados.
  Restam avisos de Claro,
  traduções disponíveis, 7 mídias duplicadas, filtros de status redundantes
  nas Views Forum/Revista/Conteúdo, cache de desenvolvimento desabilitado,
  Security Review e metadados de atualização do Core ausentes. Conteúdo e
  configuração do Runtime ficaram intactos nesta auditoria.
- Arquivos desta entrega: módulo `web/modules/custom/aculta_portal/modules/aculta_lms/`
  (controllers, formulário, acesso, membership reader, services/routing,
  testes e changelog), metadados/changelog do `aculta_portal` e este handoff.
- Próxima etapa do objetivo: resolver os bloqueios Composer upstream para
  Drupal 12 e continuar tratando os avisos de requisitos sem apagar conteúdo;
  homologação de pagamento real depende do acesso do comprador de teste.

## Handoff — espaçamento Studio e escopo de Próximos eventos, 2026-11-03

- Dashboard Studio recebeu intervalo de 12 px e reset de margens nos títulos internos (`studio.css`); com CSS candidato em Firefox ESR, todas as verificações específicas passaram em 320/390/768/1280 px, grade 1/2 colunas e sem overflow. A suíte ampla teve 126 PASS, 2 FAIL (Montserrat candidata não carregou; verificação de moldura das abas secundárias) e 21 SKIP condicionais; os FAILs foram preservados no relatório externo `$SUITE_OUT/studio-spacing-dev36-20261103/results.json`.
- `block.block.aculta420_aculta_events_upcoming.yml` agora exige purpose `main` e rota `<front>`, mantendo a caixa somente na landing principal e removendo-a do Perfil público no mesmo domínio.
- Versão do tema `0.7.0-dev.36` publicada no commit `4bc2bb508558d0dad1d1e4c7b5f93c642906054c`. Runtime `toca` / Drupal 11.4.10 / `aculta_runtime`: preflight sem updates de banco e somente a configuração do bloco diferente; snapshot pré-import com 270.104.476 bytes, SHA-256 `f1461615bbec2363ab70ee762cac15b5d50115c2c80537fef5272c6500261a0f`; importou somente `block.block.aculta420_aculta_events_upcoming`; `drush cr` PASS, `updatedb:status` sem updates e `config:status` limpo.
- Smoke HTTP anônimo pós-import: landing principal `200`, bloco `.aculta-events-upcoming` presente; perfil público `/u/pessoa-5` `200`, bloco ausente. `extension.list.theme` após reset confirma dev.36. Firefox ESR visível com CSS real do Runtime: 106/106 PASS, 21 SKIP condicionais, 0 FAIL; capturas/JSON em `$SUITE_OUT/studio-spacing-runtime-dev36-20261103/`. A preferência clara da conta foi restaurada pelo runner.
- Captura de configuração/snapshot e teste não alteraram conteúdo do site. Snapshot e respostas HTML ficam fora do Git.

## Handoff — compactação da escala de espaçamento ACULTA420, 2026-11-03

- **Objetivo:** pesquisar padrões compactos e reduzir/padronizar espaçamentos globais e nas telas Studio sem alterar paleta/cabeçalho.
- **Decisão:** escala semântica de 4/8 px em tokens; seções agora 24–40 px; conteúdo principal usa 16 px na base e 16–24 px em desktop. Studio usa gutters/row gaps menores em todos os propósitos que compartilham o token; Conta reduz os intervalos da navegação e mantém links com 44 px. Perfil e Conta Portal foram alinhados aos tokens. O conteúdo continua progressivo por viewport.
- **Pesquisa:** Carbon, GOV.UK Design System e USWDS; links e resumo estão em `web/themes/custom/aculta420/CHANGELOG.md` e em [Spacing](https://www.carbondesignsystem.com/building-blocks/foundations/spacing/overview), [GOV.UK](https://design-system.service.gov.uk/styles/spacing/) e [USWDS](https://designsystem.digital.gov/design-tokens/spacing-units/).
- **Arquivos do ajuste:** `tokens.css`, `layout.css`, `studio.css`, `portal-account.css`, `components/patterns/profile-editor/profile-editor.css`, `content.css`, `docs/design-system.md`, `CHANGELOG.md`, `docs/roadmap.md`, `aculta420.info.yml` (dev.35), e `tests/browser/aculta-studio/visual.mjs` (dev.34, contrato visual responsivo).
- **Validações:** Stylelint PASS; Foundation 354 PASS; Design Foundations 2462 PASS; token-only claro/escuro PASS; SDC schema 764 PASS; `git diff --check` PASS. Firefox ESR visível após dev.35: Marley/Dashboard/Perfil 62/62 checks aplicáveis PASS, 21 SKIP; Conta 102/102 aplicáveis PASS, 21 SKIP e zero falhas, com colunas 2/1 responsivas, AJAX/histórico, retry, timeout, Form API e sessão expirada. Capturas/JSON de todas as telas e matriz Conta: `$SUITE_OUT/studio-spacing-dev35-20261103/`. Medição de caixa anterior: `$SUITE_OUT/studio-spacing-account-dev34-20261103/`. Preferência clara original restaurada.
- **Pesquisa visual e limite:** capturas reais pós-mudança em 1440/320px conferidas; não foi feita uma nova comparação pareada com a prancha nesta rodada. Os passos pendentes de senha e persistência do avatar permanecem conforme o handoff Studio logo abaixo.
- **Git/Runtime:** espaçamento dev.32 enviado em `70c677550fdba05255da9db3d73f78d756508a9e`; correção de harness e retestes em `4c139219d29caaeb594b675097e9ed0ecc4be1d0`; ajuste do título global dev.35 em `0ed970d168688661523d16d304f9264f7ea75dce`; documentação desses resultados em `f6f95a81eda0d99c305d5875fadfe0d61ad9aea4`. `origin/dev` avançou paralelamente até `0965aa5123733b13aaab1c3e72b6ec605f6f9844` (LMS), sem conflito. Runtime é o mesmo checkout (`toca`, Drupal 11.4.10, `aculta_runtime`), `updatedb:status` sem updates, `config:status` sem diferenças, `drush cr` PASS; após `ExtensionList::reset()` confirmou ACULTA420 dev.35. Sem snapshot/update/import.
- **Retomar lendo:** tokens/layout/componentes acima, `web/themes/custom/aculta420/docs/versioning.md`, e os handoffs Studio nesta página.

## Handoff — estabilidade de UID na exportação ICS, 2026-11-03

- **Objetivo:** manter o UID ICS estável quando atributos editáveis de uma entidade de calendário mudam, sem alterar o Runtime nem publicar.
- **Implementação local:** `IcsEventBuilder` agora deriva o UID somente da chave estável e de `$uidDomain`; início, título, local, recorrência e reagendamento não participam do hash. O adaptador LMS deixou de usar título/início: prazos usam o ID da relação e eventos usam o ID de `aculta_event_date`, preservando o UID da série.
- **Arquivos da entrega:** `web/modules/custom/aculta_portal/modules/aculta_calendar/src/Ics/IcsEventBuilder.php`; seu teste Unit; `web/modules/custom/aculta_portal/modules/aculta_lms_agenda/src/AgendaEntriesProvider.php`; `Calendar/LmsCalendarItemProvider.php`; `Calendar/PersonalCalendarSeries.php`; teste Unit de `PersonalCalendarSeries`; este handoff.
- **Validações:** PHP lint dos cinco arquivos PASS; PHPCS focal PASS; `git diff --check` PASS; Calendar + LMS Agenda Unit: 106 testes/317 asserções PASS; foco ICS/LMS: 14 testes/41 asserções PASS. PHPUnit exibiu apenas o aviso preexistente de diretório de saída de browser não gravável.
- **Git:** branch `dev`, HEAD inicial `cf286b7258ad2fa5c846eceb2631902004f812ab`; arquivo não relacionado `tests/browser/aculta-studio/avatar-write.mjs` permaneceu untracked e fora do staging. Commit desta entrega ainda pendente; não houve push, merge, PR ou alteração do Runtime.
- **Retomar lendo:** `IcsEventBuilder.php`, `IcsEventBuilderTest.php`, `AgendaEntriesProvider.php`, `PersonalCalendarSeries.php`, `git status --short --branch`.

## Handoff — complementos de validação Studio, 2026-11-03

- **Base:** `dev`, HEAD inicial `f5ae2bf16d7b1f6da7a7a656e2f4e2f929b2a602`, igual a `origin/dev`; trabalho LMS paralelo `ad4b42e01e` já estava publicado e foi preservado.
- **Ajuste local:** harness `tests/browser/aculta-studio/orca-speech.mjs` passou a usar `BASE`, `drush uli --no-browser`, validar labels/headings realmente presentes, exigir artefatos externos via `$SUITE_OUT`, proteger diretório/arquivos e evitar interpolação insegura do caminho em Python. `node --check` passou; Orca + Firefox ESR passou 5/5 em Marley, com três abas disponíveis, heading `Meu trabalho` e áudio não silencioso. Relatórios/capturas permanecem fora do repositório.
- **Validações adicionais:** `teste.comum` atualmente tem papel `content_editor`, com permissão real de moderação; uma conta sintética sem esse grant não recebe a aba. Um PNG sintético chegou ao formulário de corte de avatar; não foi aplicado/salvo e o estado de avatar permaneceu idêntico. Nenhuma troca de senha ocorreu: a conta descartável exige aceite individual dos Termos, não realizado; a fixture comum proíbe troca sem decisão. Credencial sintética permanece em arquivo externo modo 0600.
- **Referências:** cotejo real feito com `docs/portal/studio-visual/referencias/image(20261103-123227).png` e capturas Firefox existentes para Dashboard, Conta, Perfil e Marley. Cinco PNGs passaram `file` e `identify`; ZIP ausente, então `unzip -t` não executado. Não se declara igualdade pixel a pixel.
- **Documentos atualizados:** auditoria AJAX, plano visual geral, P1 pessoal/editorial, roadmap Studio, README do harness e `docs/operations/TESTE-COMUM.md` receberam estado/limites atuais. Senha e persistência final de avatar continuam pendentes; teste Orca cobre Marley somente.
- **Publicação/Runtime:** commit `b7d0207336633f68532d565ab49a30e68d5a3585` enviado por fast-forward a `origin/dev`; `git ls-remote` confirmou o mesmo hash. O checkout servido está nesse HEAD, Drupal 11.4.10/banco `aculta_runtime`; `updatedb:status` sem updates e `config:status` sem diferenças. Não houve alteração de grants, dados das contas existentes, Termos ou configuração; a conta sintética e o upload temporário registrados acima permanecem no Runtime.
- **Retomar lendo:** os cinco documentos acima; `tests/browser/aculta-studio/orca-speech.mjs`; auditoria de Conta Studio.

## Handoff — revisão visual Studio, 2026-11-03

### Reteste da navegação em caixas da Conta — commit `963ab7c6b10a375819e9bb0651bbb1a8e43540da`

- Cotejo da captura real apontou que `studio-account-contextual-nav` ainda herdava `nav-tabs`/`studio__subtabs` do Marley, mantendo itens em linha e sem caixas separadas. ACULTA420 passou a usar lista vertical própria e painéis de navegação/conteúdo com tokens de superfície, borda, raio e sombra; `<details>`, Form API e owners foram preservados. Cabeçalho global e suas cores não mudaram.
- Push fast-forward confirmado em `origin/dev`; Runtime `toca`/`aculta_runtime` sincronizado no mesmo HEAD. `drush cr` PASS; tema `0.7.0-dev.31`; `updatedb:status` sem updates; `config:status` limpo.
- Firefox ESR visível: 97/97 PASS aplicáveis, 22 SKIP; menu vertical/ativo e caixas computadas verificados, nove rotas Conta HTTP 200, navegação AJAX, histórico, retry, timeout, Form API inválido/válido, teclado, claro/escuro e 320–1440px. No-JS 14/14 PASS. Capturas: `$SUITE_OUT/studio-dashboard-final-20261103/account-panels-dev31-final/` (Dashboard, Conta 1280/320, Perfil, Marley); no-JS: `$SUITE_OUT/studio-dashboard-final-20261103/final-nojs-retry/`.
- Tema Unit 50/358, Design Foundations 2441, SDC schema 764, Portal Drupal 11+ 1202 e status index 53 PASS; Stylelint, PHP lint e `node --check` PASS. `validate-portal-ui-docs.php` mantém 14 falhas preexistentes em tokens/atributos LMS/Eventos/uploader; fronteira mantém 3 classes preexistentes de `aculta_paths`.
- Dados e Endereço foram submetidos validamente via Firefox uma vez cada com os valores já exibidos na conta sintética; mensagem de sucesso, zero erros e valores iguais antes/depois. Hash dos quatro campos de perfil comparado a snapshot externo 0600 e inalterado. Upload/avatar e senha não foram submetidos. Módulos opcionais, conteúdo editorial da conta e leitor de tela seguem DEFERRED. Cinco PNGs atuais foram reconhecidos por `file`/`identify`, incluindo a prancha 1536×1024 inspecionada; ZIP original ausente, então `unzip -t` não foi executado.
- Retomar lendo: `docs/portal/STUDIO-CONTA-AJAX-AUDITORIA.md`, `docs/portal/ACULTA-STUDIO-VISUAL-PLANNING.md`, `docs/portal/STUDIO-VISUAL-P1-PESSOAL-EDITORIAL.md`, `web/modules/custom/aculta_portal/modules/aculta_studio/docs/ROADMAP.md`.

- Branch de trabalho `dev`. Commits de código desta continuação: `1fb7231447` corrige ações do Perfil comprimidas; `39c6f4035c` alinha “Criar” ao título “Meu trabalho”. O Runtime de aplicação está no código do segundo commit. O commit paralelo LMS `ad4b42e01e` já era ancestral de `origin/dev` e estava publicado; não foi republicado nem reescrito.
- Cinco referências PNG válidas permanecem em `docs/portal/studio-visual/referencias/`; `image(20261103-123227).png` (1536×1024) foi inspecionada. O ZIP não estava disponível. A imagem desktop/mobile não está no remoto atual e não foi restaurada.
- Composição aplicada: Dashboard com identidade, perfil público condicionado ao owner, uma entrada de Conta e experiências reais; Conta em Grid com AJAX próprio; Perfil separado em duas áreas e ações legíveis; Marley com abas owner, botão Criar junto ao título e listagem em scroll interno. Não houve mudança de cores do cabeçalho nem novo SDC; `aculta420:card` (`studio_fidelity`) e `profile-editor`/`profile-identity` foram reutilizados.
- Firefox ESR visível no Runtime: `post-push` 94/94 verificações aplicáveis PASS (21 SKIP, 0 falhas); após Marley dev.44/tema dev.30, `marley-header-dev30-retest` 97/97 PASS (21 SKIP, 0 falhas). Nove rotas Conta HTTP 200; no-JS 4/4; navegação AJAX/histórico, falhas 403/404/retry, timeout/resposta tardia, sessão expirada, Form API inválido/válido, Dashboard, Perfil, Marley, 320–1440px e zoom 200/400% verificados. Capturas externas em `$SUITE_OUT/studio-dashboard-final-20261103/post-push/` e `$SUITE_OUT/studio-dashboard-final-20261103/marley-header-dev30-retest/`.
- PHPUnit Studio + tema: 114 testes/989 assertions PASS, incluindo 65/634 do Studio e 49/355 do tema. PHP lint/PHPCS focais, Design Foundations 2441, SDC schemas 764, SDC asset audit 0 findings, Portal Drupal 11+ 1202 e status index 53 passaram. `validate-module-theme-boundary.php` ainda acusa apenas três classes preexistentes em `aculta_paths` (`button`, `button--small`, `superficies-dns`).
- Runtime `toca`, Drupal 11.4.10, banco `aculta_runtime`: atualizado por fast-forward ao hash acima; `drush cr` PASS, sem DB updates e sem config drift; `aculta_studio=0.1.0-dev.44`, `aculta420=0.7.0-dev.30`. A lista de extensões precisou de `reset()` para descartar metadado de tema antigo; nova execução Drush informa `.30`.
- DEFERRED: envios válidos de dados pessoais/endereço, upload/avatar, alteração de senha, ausência de módulos opcionais, conteúdo editorial privado aguardando moderação e leitor de tela. Preferência sintética foi submetida via Form API AJAX e restaurada pelo harness. A conta comum não tinha conteúdo próprio, então as ações de edição foram SKIP; não houve escrita pessoal/endereço.
- O seletor do harness do cabeçalho usa `section-action`, conforme o contrato real. O ajuste e a atualização documental desta rodada são uma entrega de teste/documentação sem alteração funcional adicional.

## Handoff — implementação Conta Studio e Dashboard, 2026-11-03

### Reteste adicional no-JS — 2026-11-03

- Runtime confirmado em `toca`, `aculta_runtime`, tema ACULTA420; `updatedb:status` sem pendências e `config:status` limpo. Nenhuma alteração de conteúdo foi feita.
- Firefox ESR visível com a conta sintética `teste.comum`: `tests/browser/aculta-studio/nojs.mjs` passou 14/14. Cobriu autenticação temporária, shell/Form API em Dados, link HTML nativo para Endereço, voltar e as nove rotas diretas com região de conteúdo e item ativo. Relatório em `$SUITE_OUT/studio-audit-20261103/nojs-aculta-final2/nojs-results.json`.
- Seis PNGs do commit `d732e95c` em `references/studio-visual/` estão truncados/corrompidos (IDAT incompleto/CRC inválido); comparação pareada continua pendente de reenvio íntegro. Sem cópia de conteúdo fictício.
- O harness no-JS agora recebe o redirect `/conta/dados` no ULI, confirma a sessão antes das rotas privadas e não depende de classes de layout do tema. Atualizados auditoria Conta AJAX, plano visual, plano P1 e roadmap.
- `config:status` anteriormente mostrou Olivero durante validação paralela; ao retomar, Runtime já estava restaurado em ACULTA420 e configuração limpa. O estado paralelo LMS foi preservado; esta entrega será publicada separadamente sobre o HEAD atual de `origin/dev`.

- **Objetivo:** concluir Dashboard, shell AJAX de Conta, preservar Perfil público independente e ajustar a hierarquia editorial Marley, conforme planos e auditoria.
- **Base/entrega Studio:** trabalho iniciado em `50050dc4dbf07a46dadb861fba45cce32ec194b4`; `8ccbcb0618` e o reparo autowired `bf731e8789` publicados em `origin/dev`. O pedido de reset foi cumprido preservando o patch fora do repositório.
- **Implementação local:** biblioteca Conta própria com `Drupal.Ajax`, histórico, metadados de título/breadcrumb, foco, estado e falha/retry; detalhes nativos para menu móvel; wrappers Grid próprios. Reteste corrigiu a construção `Drupal.Ajax(null, null, ...)` para não associar listener de clique Core duplicado ao handler delegado. Dashboard consulta providers e acesso de destino em `DomainPurposeManager`; identidade usa resolver público e falha fechada. Culturas registrado pelo owner Eventos. Marley remove destino Studio quebrado de “Criar” e aponta para a rota owner; moderação permanece gated.
- **Verificações:** números da validação atual estão no item de retomada abaixo (Unit Studio 72/659 e Firefox comum 92/92 aplicáveis, 22 SKIP). Gates Portal Drupal 11+ 1202, slugs 410, admin routes 172 e status index 53 passaram; `node --check`, PHP lint e PHPCS focal passaram. Os números anteriores deste handoff são históricos.
- **Runtime:** hostname `toca`, Drupal 11.4.10, PHP 8.4.27, banco `aculta_runtime`. Pós-push: HEAD local e `origin/dev` em `27e8d0626353bdedfff2d962ac1b27b43b76af6a`; mesmo checkout serve o Runtime. `updatedb:status` sem updates, `config:status` limpo, módulo em `0.1.0-dev.41`; `drush cr` PASS após ajuste do JS. Sem import nem escrita de dados nesta retomada.
- **Entrega desta retomada:** corrigido o listener perdido após `replaceWith` movendo a delegação para `document`, única e guardada pelo behavior; `Drupal.Ajax` não recebe alvo/evento Core e não acumula handlers. Ampliado `tests/browser/aculta-studio/visual.mjs` e adicionado `nojs.mjs`. No Firefox ESR visível, Conta fechou 92/92 verificações aplicáveis PASS, 22 SKIP; rotas Conta 9/9 HTTP 200, AJAX/histórico/mobile, 403/404+retry, timeout/resposta tardia, sessão expirada, Form API inválido/válido, Dashboard/Perfil, Marley, 320–1440px, zoom 200/400%; no-JS 4/4. Unit Studio 72/659 PASS; Gates Drupal 11+ 1202 PASS, slugs 410 PASS, admin routes 41 arquivos/170 rotas PASS e índice 53 PASS. Atualizados auditoria, planos, roadmap, CHANGELOG, versão `0.1.0-dev.41`, status e README. A prancha original não estava disponível localmente para cotejo; uploads/senha/escrita pessoal, opcionais ausentes, conteúdo pendente de moderação e screen reader seguem DEFERRED. O trabalho LMS paralelo foi publicado em `8fccd52da2`; não há alterações LMS pendentes no status atual.
- **Git:** commit de código, harness e planos `27e8d0626353bdedfff2d962ac1b27b43b76af6a` enviado por fast-forward a `origin/dev`. O commit metadata `a29db64627` e o registro LMS `139aea1f12` ocorreram em paralelo antes dele; nenhum arquivo LMS entrou no commit Studio. Worktree limpo após a publicação.
- **Pendências de entrega:** nenhuma. Permanecem somente os itens DEFERRED funcionais/visuais enumerados abaixo e na auditoria.
- **Pendências:** módulos opcionais ausentes no Runtime, upload/avatar/senha/anexos, escritas pessoais/endereço, conteúdo aguardando moderação, leitor de tela e comparação visual integral com a prancha; não marcar homologação integral.
- **Leitura para retomar:** `docs/portal/STUDIO-CONTA-AJAX-AUDITORIA.md`; `docs/portal/ACULTA-STUDIO-VISUAL-PLANNING.md`; `docs/portal/STUDIO-VISUAL-P1-PESSOAL-EDITORIAL.md`; `web/modules/custom/aculta_portal/modules/aculta_studio/docs/ROADMAP.md`; `git status --short --branch`.

## Handoff — medição de carga MariaDB para xAPI, 2026-11-03

- **Objetivo:** medir carga sintética de escrita para `aculta_lms_xapi_statement` em banco separado no Homelab, sem escrita em `aculta_runtime`.
- **Entrega:** `docs/operations/CARGA-MARIADB-XAPI.md`; commit `8efb3af9b600a25217c5cf5c3aa45a06eae8ce18` publicado em `origin/dev`.
- **Medição:** 5.000 linhas em 50 lotes, 6.389 linhas/s, p95 SQL individual 0,118 ms, p95 transação de lote 14,843 ms; tablespace 81.920 → 22.024.192 bytes. Limites de 5.000 linhas, 64 MiB e 30 s; encerrou pelo limite de linhas após 0,783 s.
- **Descarte e Runtime:** `aculta_carga` removido, verificação de zero schema/tabelas. Contagens xAPI, progresso, aula, atividade e resposta no `aculta_runtime` permaneceram em zero. A soma `information_schema` variou +16.384 bytes durante o intervalo; causa não atribuída. Nenhum comando de escrita foi direcionado ao Runtime.
- **Verificação:** carga feita por conexão local socket Unix, só dados sintéticos; sem usuário de teste, senha ou segredo criado. Sem importação de configuração. `git diff --check` passou; nenhum teste de aplicação foi executado.
- **Pendências:** medição de persistência, não de validação, HTTP ou capacidade production-like. Para fechar ADR-041, repetir em ambiente production-like representativo antes de ativar conteúdo xAPI em produção.
- **Retomar lendo:** `docs/operations/CARGA-MARIADB-XAPI.md`; `docs/decisions/ADR-041-xapi-2-lrs-proprio.md`; este handoff.

## Handoff anterior — Conta Studio AJAX, 2026-11-03

- Objetivo: aprofundar comparação Conta/legado e preparar execução da correção.
- Entrega: docs/portal/STUDIO-CONTA-AJAX-AUDITORIA.md, integração nos planos ACULTA-STUDIO-VISUAL-PLANNING.md e STUDIO-VISUAL-P1-PESSOAL-EDITORIAL.md, e no roadmap do aculta_studio.
- Evidências: rotas distintas; AccountShellBuilder anexa AJAX legado; StudioAccountSectionController emite links comuns; JS Studio editorial limitado por seletores e histórico Marley; PortalUiHooks mapeia conta para layout flex; riscos de histórico mobile/timeout legados documentados.
- Verificação: leitura de fontes e revisão documental; nenhum teste de aplicação executado. Código funcional não alterado. Publicação via API GitHub sobre dev em commit único; sem checkout local.
- Runtime: sem acesso nesta sessão; sincronização e homologação DEFERRED. Próximo agente deve cumprir preflight operacional antes de sincronizar.
- Retomar: auditoria C0-C5 e P1.2; registrar DOM/rede/estilos computados antes de corrigir. Handoffs de outras frentes abaixo preservados.


## Handoff mais recente — ADR-042 P5 concluído, 2026-11-03

- **Objetivo:** corte P5 do LMS contrib concluído. P6 e reescrita de histórico não executados.
- **Commits publicados em `origin/dev`:** `baeb4339a` prepara configuração e campo do Fórum; `28f8bf887` torna seguro o update quando falta tabela de revisões; `d328c658b` remove `drupal/lms` do Composer/lock e conclui P5. Versões: `aculta_lms` 0.1.0-dev.172; `aculta_forum_lms` 0.1.0-dev.33.
- **Runtime:** checkout `dev` em `origin/dev`, Drupal 11.4.10, PHP 8.4.27, banco `aculta_runtime`. Snapshot anterior ao P5: SHA-256 `37947c05fe3d523fbce0ff42c78f61f5d90f56a2a566de7c06f1869774bcd595`. Updates 10012 (LMS) e 10001 (Fórum) aplicados; cache rebuild e Composer install passaram; `updatedb:status` sem pendências. Módulos contrib desinstalados na ordem `lms_answer_comments`, `lms_answer_plugins`, `lms_classes`, `lms`; `lms_file_upload` não era pacote Composer e suas cópias manuais saíram dos caminhos de descoberta.
- **Descarte explicitamente autorizado em P5:** 8 aulas, 17 atividades, 3 respostas, 3 status de aula, 2 status de curso e 2 relacionamentos de turma `[TESTE P1]`; nenhum conteúdo migrado. Os cinco Groups permaneceram. A relação de turma ACULTA continua configurada.
- **Verificações:** LMS Unit 218/758 PASS; Fórum Unit 33/329 PASS; `AcultaLmsInstallTest` 6/172 PASS (35 depreciações); Kernel focal Fórum 1/25 PASS (38 depreciações); Portal 1.172 checks PASS; public-slugs 410 rotas PASS; composer validate PASS com avisos preexistentes de constraints fixas; busca literal por imports `Drupal\\lms` em `web/modules/custom` retorna zero. `composer show drupal/lms` confirma pacote ausente.
- **Configuração Runtime:** `config:status` foi lido antes/depois e não houve importação. Permanecem diferenças de `aculta_portal.superficies`, config de Groups e tópicos, `core.extension`, papel `lms_teacher` e Views de cursos. Detalhes e causa da tentativa inicial falha/retry do update 10001 estão no CHANGELOG do Fórum.
- **Retomar lendo:** `docs/decisions/ADR-042-remocao-dependencias-lms-contrib.md`; `docs/modules/P2-ENTIDADES-PROPRIAS-LMS.md`; `web/modules/custom/aculta_portal/modules/aculta_lms/CHANGELOG.md`; `docs/operations/status/aculta_lms.yml`; `git status --short --branch`.

## Handoff anterior — continuação ADR-042 P5, 2026-11-03

- **Tarefa:** continuar a troca de consumidores LMS para entidades próprias e concluir P4/P5 quando não houver perda funcional sem contrato aprovado. Usuário autorizou commit/push e intervenção em outros módulos/tema. P6 e reescrita de histórico continuam proibidos.
- **Commits publicados:** `.159` `9c998f1ec` adiciona payload JSON imutável por tentativa em `aculta_lms_answer`; `.158` `1b0333f89` migra agenda; `ecd5daf3f`, `afc1689d8`, `381eff801`, `9d6bf2fa3` e `bc99862a9` registram gate, Runtime, handoff, desenho P2 e índice. `.155` `20d56c7f3` (ciclo de certificados usa progresso ACULTA), `.156` `4f7ea82bc` (relação Group de turmas própria), `31915dbe9` (README de status), `.157` `536994ba1` (calculador e configuração ACULTA de permissões) e `fa9a5cf5a` (registro Runtime). `.158` foi publicado em `1b0333f89`; o ajuste do baseline do gate Portal em `ecd5daf3f`. Após novo avanço remoto, ambos foram rebaseados com segurança sobre `e9935d948` e enviados por fast-forward. A agenda usa entidades P2, corrige a negação de acesso entre turmas e corrige a sintaxe inválida herdada em `EventClassAccess.php`.
- **Arquivos `.155`/`.156`:** `tests/src/Kernel/CertificateLifecycleTest.php`; `src/Plugin/Group/Relation/CourseClassRelation.php`; `src/Hook/CourseClassRelationHooks.php`; `tests/src/Kernel/ClassRosterVisibilityTest.php`.
- **Arquivos `.157` preparados:** `src/Access/ClassPermissionCalculator.php`; `aculta_lms.services.yml`, `.info.yml`, `.install`; `config/install/aculta_lms.settings.yml`, `config/schema/aculta_lms.settings.schema.yml`; `config/sync/aculta_lms.settings.yml`; Kernels de instalação/permissões; CHANGELOG, status e README.
- **Arquivos `.158` publicados:** `aculta_lms_agenda/src/AgendaEntriesProvider.php`, `EventClassAccess.php`, `Hook/EventAccessHooks.php`, services/module, teste Kernel e docs; `config/sync/group.relationship_type.lms_class-lms_class_activity_due.yml` remove dependência do módulo `lms`; versões `aculta_lms` .158 e agenda .2, status e changelogs.
- **Verificações `.160`–`.164` e purpose da rota:** commits `.160` `8905e21bc`, `.161` `e62791b04`, `.162` `42c9684af`, `.163` `637e0ed1f`; a rota ACULTA `/resposta/{answer}/arquivo` recebeu purpose `courses` em `b0bd84dc9`, enviado por fast-forward. O harness Kernel de `AttemptArchiveLifecycleTest` foi corrigido para instalar `comment_body` antes de `aculta_lms`; Kernel conjunto `AcultaLmsInstallTest` + `AttemptArchiveLifecycleTest` 16/603 PASS (57 depreciações), Unit LMS 236/829 PASS (1 depreciação). Comentário Core ACULTA fechado por padrão, AnswerPayload valida referência managed file, e a rota própria exige owner matriculado ou grade students, tentativa enviada/avaliada e arquivo permanente `private://`. Unit focal de autorização 3/30 PASS; Portal `DomainRouteSubscriberLocalizedPathsTest` 1/30 PASS e gate 1.172 checks PASS. Runtime `10009` após snapshot SHA-256 `d44704f1…84bcfe9a4`; `10010` após `a382cb02…8400b0bf`; `updatedb:status` sem updates, `drush cr` PASS e rota confirmou `/resposta/{answer}/arquivo | courses`. Config Sync mantém somente diferenças anteriores em `aculta_portal.superficies` e `core.extension`, sem import global.
- **Verificações após rebase:** Unit LMS 228 testes/786 assertions PASS (1 depreciação PHPUnit); Kernel ClassRoster 3/66 PASS (41 depreciações Core/contrib); Kernel AcultaLmsInstall 6/143 PASS (28 depreciações); PHPCS focal PASS; gate Portal 1.172 checks PASS; índice 49 componentes PASS; `git diff --check` PASS.
- **Verificações `.158`:** Agenda Kernel 4/168 PASS (94 depreciações Core/contrib); Unit agenda 8/18 PASS; PHPCS dos arquivos alterados PASS; gate Portal 1.172 PASS após atualizar baseline para o subscriber registrado de superfícies (interruptor desligado); `validate-public-slugs.php` 423 rotas/0 violações PASS. Inventário atual: 14 arquivos PHP ainda importam `Drupal\lms`, nenhum no submódulo agenda; Unit LMS reexecutada 228/786 PASS (1 depreciação PHPUnit). O teste Kernel segue com LMS habilitado por dependência no manifesto de `aculta_lms`; a fixture já não importa entidades contrib nem instala seus schemas.
- **Runtime:** hostname `toca`, Drupal 11.4.10, DB `aculta_runtime`. A entrega .157 já foi atualizada no Runtime: não há updates pendentes e o cache/config estão limpos. Após publicar .158, preflight mostrou `updatedb:status` sem updates e duas diferenças de Config Sync: `aculta_portal.superficies` adiciona `leitura_ativa: false`, e o relacionamento de prazo remove a dependência `lms`. Snapshot anterior ao import: SHA-256 `d1bd180e6883f5efccd12ad9a65a094cb7f22dc681f6b09595e3c0c06ec72848`. Import das duas configs e `drush cr` concluídos; status posterior sem updates e sem diferenças. Snapshot anterior ao update 10007: 79.373.653 bytes, SHA-256 `624bb5573a378f556600fabdc4ab2d679f8730990973acf661ecdfacc6c6e424`. Update 10007 concluiu; cache rebuild PASS; pós-verificação sem updates e sem diferenças de config. Para `.159`, snapshot anterior ao update 10008 SHA-256 `8feef18cc9fd459b6cb6c92a47d1b6db67d4c1b5440323edd386b888ee10ca3e`; update e cache rebuild PASS, status final limpo.
- **Nota operacional:** `config:import --diff` mostrou a diferença e também executou a importação. Criou `aculta_lms.settings` com `view group_node:aculta_lms_topic entity` → `grade students` e `aculta_portal.superficies` com dez superfícies ativas em modo subdomínio, trazida pelo upstream `aculta_superficies`. Nenhum config existente foi alterado ou removido; estados finais estão limpos. A segunda criação não foi antecipadamente reportada e fica registrada como desvio operacional.
- **Inventário que bloqueia P4/P5 sem mudança de contrato:** `AttemptArchiveManager` e hooks arquivam texto/comentários/anexos e encadeiam estados de tentativa ausentes em `CourseAnswer`; upload/download e autorização (`Plugin/ActivityAnswer/FileUpload.php`, `AnswerFileDownloadController`, `UploadedAnswerAccessCheck`) dependem do payload e uso de arquivo contrib; revisão dissertativa (`FreeTextReviewFeedback`) está fora do escopo de P3; `CourseLinkHooks` depende do hook, rota/componente de início e configurações de revisita/avaliação do LMS, sem rota ACULTA equivalente. O Kernel da agenda agora cria LessonEntity/ActivityEntity e não importa Drupal\lms. O inventário global tem 14 arquivos PHP com imports restantes. A relação `lms_classes` já tem plugin ACULTA, mas o P4 sem o contrib ainda não foi verificado.
- **Limite:** responsável aprovou em 2026-11-03 payload JSON, tentativas imutáveis, comentários Core vinculados e referências a arquivos seguros, mantendo `CourseAnswer` como projeção de estado/nota. `.163` adiciona rota própria de download, mantendo a rota contrib enquanto os plugins forem portados. Ainda faltam revisão, UI/rota de início, inventário de 14 imports PHP, revisão de 71 YAML de Config Sync e dependência `lms:lms` no manifesto `aculta_lms_h5p`. `ActivityEntity` não armazena enunciado/gabarito; aguarda decisão sobre `activity_data` JSON para portar os plugins objetivos. P4/P5 incompletos; P6 e reescrita de histórico não executados.
- **Runtime desta continuação:** hostname `toca`, banco `aculta_runtime`, Drush 13.8.0. Após `10010`, `updatedb:status` sem pendências; `config:status` mostra somente `aculta_portal.superficies` (`leitura_ativa` true ativo/false sync) e `core.extension` (`aculta_superficies` ativo/ausente sync). A importação global permanece fora desta entrega porque desativaria superfícies. Snapshots: antes de `10009`, SHA-256 `d44704f1c612c95b2854bdfb5401196d9293a896dd1dfde33d49c5684bcfe9a4`; antes de `10010`, SHA-256 `a382cb025e0593ecaee741e1f285fb509995fb2b467990defa240fa68400b0bf`.
- **Próximos passos:** portar os consumidores restantes; após decisão sobre `activity_data`, ligar os plugins objetivos e remover módulos contrib na ordem P5. Responsável autorizou desinstalação no Runtime durante P5 mesmo com descarte de dados de teste; não executar P6 nem remover dados fora dessa ordem.
- **Retomar lendo:** ADR-042; `docs/modules/P2-ENTIDADES-PROPRIAS-LMS.md`; `src/Plugin/Group/Relation/CourseClassRelation.php`; `src/Entity/AnswerEntity.php`; `src/Plugin/ActivityAnswer/`; `src/AttemptArchiveManager.php`; `src/Hook/CourseLinkHooks.php`; `aculta_lms_agenda/tests/src/Kernel/AgendaEntriesProviderKernelTest.php`; `rg -l 'Drupal\\lms' web/modules/custom --glob '*.php'`; `git status --short --branch`.

## Handoff anterior — superfícies de domínio (planejamento), 2026-11-02

- **Tarefa:** planejar o controle de superfícies do aculta_portal (modo subdomínio ou diretório, slug e ativar/desativar), mantendo o módulo Domain para hosts e ambientes. Autorizado commit e push em `dev`.
- **Commits:** `c5333a1` (planejamento do submódulo e ADR, enviado a `origin/dev`, fast-forward a partir de `93b638a`); commit seguinte desta entrega (ADR-043 movido para `docs/decisions/`, roteiro 3.0, status do submódulo, handoff).
- **Arquivos principais:** `web/modules/custom/aculta_portal/modules/aculta_superficies/` (info.yml, README, CHANGELOG, `docs/PLANO-FINAL.md`); `docs/decisions/ADR-043-superficies-dominio.md` e anexo; `docs/architecture/TESTES-3.0-ROTAS-SUPERFICIES.md`; `docs/architecture/rotas-por-purpose-dev-93b638a.csv`; `docs/operations/status/aculta_superficies.yml`.
- **Verificações:** `validate-environment-addresses.php` FAIL preexistente em `aculta_lms/tests/src/Unit/XapiBatchIngestorTest.php:134` (fora desta entrega, não corrigido). `validate-public-slugs.php` e `validate-project-status-index.php` não executados: exigem `vendor/`, ausente nesta sessão.
- **Pendências:**
  1. ~~Corrigir `CANONICAL_STUDIO_PATHS`~~: não é conflito. A retificação do responsável (2026-10-30, `web/modules/custom/aculta_portal/AGENTS.md`) aprova `/studio/we-sativa` e `/studio/green-stash`, e o código está conforme. Pendente: o corpo do ADR-038 ainda cita `/wesativa` e `/greenstash`; corrigir é decisão do responsável.
  2. Regenerar a linha do `aculta_superficies` na tabela de status do README raiz com `php scripts/validate-project-status-index.php --write`, em ambiente com `vendor/`.
  3. ADR-043 aceito pelo responsável em 2026-11-02 como direção de modelo; implementação na Fase 3.
  4. Fase 3.0: testes de rota no Runtime (DEFERRED).
- **Limites e riscos registrados:**
  - O commit `c5333a1` tem trailer `Co-Authored-By`, proibido pelo `AGENTS.md`. Não foi reescrito por causa da regra de não reescrever histórico compartilhado. Decisão do responsável.
  - O remoto `origin` respondeu que o repositório foi movido para `coletivo420/aculta`. Atualizar a URL do remoto depende de autorização explícita.
  - A permissão de escrita da sessão foi marcada como recusada pelo classificador em uma troca de remoto, mas o push para `dev` foi aceito.
- **Runtime:** sem acesso nesta sessão. Sincronização pós-push (D-70) não executada; registrar como DEFERRED.
- **Retomar lendo:** `docs/decisions/ADR-043-superficies-dominio.md`; `web/modules/custom/aculta_portal/modules/aculta_superficies/docs/PLANO-FINAL.md`; `docs/decisions/ADR-038-aculta-studio-suites-por-dominio.md`; `git status --short --branch`.

## Handoff anterior — contexto do fórum sobre entidades LMS próprias, 2026-11-02

- **Tarefa:** continuar leitores/consumidores em P5 da ADR-042. Checkout `dev` iniciou em `1034dbb0c9679b786b5448e618a8ab94ae689edb`; `origin/dev` estava alinhada. Usuário autorizou commit/push e intervenção em outros módulos/tema. Sem P4, P5 de remoção de pacotes ou P6 executados ainda.
- **Trabalho desta etapa:** Forum LMS passou a usar `GroupInterface` e entidades P2 ACULTA para resolver discussões por atividade; seleção H5P e acesso de membros usam permissões Group. Configuração de instalação do fórum referencia `aculta_lms_activity`. Removido o hook antigo que alterava formulário de resposta contrib inexistente na UI ACULTA. `config/sync` permaneceu intacta e conserva `lms_activity`, portanto a diferença e a estratégia de atualização dessa configuração ainda precisam de revisão antes de P5.
- **Arquivos principais:** `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Context/`; `src/Plugin/EntityReferenceSelection/Courseaculta_H5PContentSelection.php`; `config/install/field.*.field_lms_activity.yml`; testes Unit e Kernel sob `tests/src/`; manifesto/CHANGELOG e `docs/operations/status/aculta_forum_lms.yml`. Versão preparada `0.1.0-dev.31`.
- **Verificações:** Unit Forum LMS 33 testes / 323 asserções PASS. Kernel focal 1/27 asserções PASS. Kernel completo 27 testes / 1.348 asserções PASS, 58 depreciações Core/contrib. Gate Portal PASS (1.159 checks); índice regenerado oficialmente e validado PASS (48 componentes). `git diff --check` PASS.
- **Runtime:** `toca`, Drupal 11.4.10, PHP 8.4.27, DB `aculta_runtime`; antes da sincronização `updatedb:status` sem updates e `config:status` sem diferenças. Nenhuma atualização ou import de config executada. A sincronização de código/cache após o push ainda precisa ser registrada.
- **Pendências P5:** migrar consumidores restantes em `aculta_lms`, agenda, Messenger e demais módulos; avaliar relacionamentos `lms_classes` e a configuração `field_lms_activity` sincronizada, sem apagar/migrar dados sem estratégia aprovada; só então remover imports/dependências conforme ADR. Ainda não verificado: P4 sem contrib e grep global zero. P6 e reescrita de histórico não executados.
- **Retomar lendo:** ADR-042; `docs/modules/P2-ENTIDADES-PROPRIAS-LMS.md`; `docs/operations/status/aculta_forum_lms.yml`; `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Kernel/LmsForumDependencyHarnessTrait.php`; `git status --short --branch`; inventário via `rg -n 'Drupal\\\\lms' web/modules/custom`.

## Handoff mais recente — P2 e plugins objetivos P3 do LMS, 2026-11-02

- **Tarefa:** implementar as quatro entidades próprias do `aculta_lms` segundo ADR-042 e `docs/modules/P2-ENTIDADES-PROPRIAS-LMS.md`; P5/P6 e remoção de dados não executados.
- **Referência:** branch `dev`, HEAD de referência `cd5e4c818b9443bbc088c4fbf7e0669ffa4dadbe`; `composer install` concluído. Sem commit/push conforme instrução explícita.
- **P2:** `LessonEntity`, `ActivityEntity`, `AnswerEntity`, `ProgressEntity`; handler único interno de acesso negado; `LearningEntityStorageSchema` define índices compostos, inclusive chave única `course_id + uid`; updates idempotentes `aculta_lms_update_10005` e `10006` (colunas requeridas e tamanhos exatos). Campos/validações seguem o desenho; nenhuma rota/formulário.
- **P3:** plugins ACULTA `TrueFalseAnswerPlugin`, `SelectAnswerPlugin` e `FillBlanksAnswerPlugin`, resolvidos pelo tipo estável de `CourseActivity` e usando `ObjectiveAnswerScorer` injetado. Os plugins de LMS contrib continuam ativos; não há formulário, persistência, dissertativa, upload ou troca de leitor nesta fase.
- **Arquivos:** `web/modules/custom/aculta_portal/modules/aculta_lms/src/{Access,Entity}/`; `aculta_lms.install`; `tests/src/Unit/LearningEntitiesTest.php`; teste Kernel em `tests/src/Kernel/AcultaLmsInstallTest.php`; versão/CHANGELOG, `docs/operations/status/aculta_lms.yml` e README gerado.
- **Verificações P2:** Unit 211/673 PASS; Kernel focal `AcultaLmsInstallTest` 5/126 PASS, 27 depreciações Core/contrib. O Kernel chama os dois updates duas vezes e verifica os comprimentos SQL e índice único. Gate Portal Drupal 11+ PASS, 1.159 checks; public-slugs PASS (423 rotas, 0 violações); status index PASS, 48 componentes.
- **Verificações P3:** Unit focal `ObjectiveAnswerPluginsTest` 5/13 PASS; Unit completa 216/686 PASS, 1 depreciação PHPUnit; Kernel focal `AcultaLmsInstallTest` 5/128 PASS, 27 depreciações, incluindo DI e resolução por tipo; PHP lint e `git diff --check` PASS. Gate Portal e validadores de tema não aplicáveis a P3.
- **Git:** P2 publicado em `2e27589c6896c84a42be7927c861f01aa8bd7def`; o patch produzido a partir de `cd5e4c8` bate integralmente com esse diff e passou verificação de aplicação e reversão em clone limpo. P3 publicado em `535cd54fdd4552aeb69705836d052f35f2b1696d`, com trailer Claude-Session solicitado. O commit P2 preexistente não foi reescrito para alterar sua mensagem/trailer.
- **Runtime:** hostname `toca`, Drush confirmou Drupal 11.4.10 e banco `aculta_runtime`. Snapshot P2 SHA-256 `d8be1298ddbc961a1397ffc22db74a3aa044d74edc2d1caeaafffcef2de0526e`; aplicados somente updates P2 `10005` e `10006`. Após P2 e após push P3: `updatedb:status` sem pendências e `config:status` sem diferenças. `drush cr` após o push P3 PASS; nenhum import de configuração. Tabelas P2 criadas vazias, sem migração de legado.
- **Pendências e limites:** instalação limpa total da closure Portal não exercitada; PHP 8.5 não verificado (Runtime/CLI usam 8.4.27). Não executar P4, P5 ou P6 nesta missão.
- **Retomar lendo:** `docs/modules/P2-ENTIDADES-PROPRIAS-LMS.md`; `src/Assessment/ObjectiveAnswerScorer.php`; `src/Plugin/ActivityAnswer/`; serviço `aculta_lms.objective_answer_plugins`; testes `ObjectiveAnswerPluginsTest` e `AcultaLmsInstallTest`; changelog e status.


## Handoff mais recente — adaptadores ACULTA do LMS contrib, 2026-11-02

- **Tarefa:** concluir os adaptadores P1 de aula, atividade e resposta do `aculta_lms`, seguindo o ADR-042; P5/P6 não executados.
- **Referência e publicação:** iniciou no commit informado `ea7a74788d3442333ce448b90ffd16d5c9f599d0`; remoto avançou para `892d1a71a` durante a tarefa. Entrega `c0a0ca40f` foi rebaseada sem conflitos e enviada por fast-forward a `origin/dev`. `composer install` concluído.
- **Implementação:** adicionados `ContribCourseLessonAdapter`, `ContribCourseActivityAdapter` e `ContribCourseAnswerAdapter`, seus aliases por interface e cobertura Unit. Atualizados manifest, CHANGELOG, status do componente e README gerado.
- **API contrib verificada:** `LessonInterface.php` expõe métodos adicionais de randomização/navegação/handler; ID, label e campos vêm das interfaces Drupal base. Aula/posição vêm de `CourseInterface::LESSONS`; publicação é o campo `status`. `ActivityInterface.php` não acrescenta métodos; tipo vem do bundle, aula e pontuação máxima vêm das referências em `LessonInterface::ACTIVITIES`. `AnswerInterface.php` oferece `getActivity`, `getLessonStatus`, `isEvaluated`, `getScore`, `getData` e `getUserId`; `TrainingManager.php` oferece `getActivityMaxScore` e operações de tentativa, sem estado textual. O adaptador normaliza pontos para percentual usando o máximo da atividade e recusa resposta avaliada sem nota ou máximo.
- **Arquivos alterados:** `README.md`; `docs/operations/status/aculta_lms.yml`; `web/modules/custom/aculta_portal/modules/aculta_lms/CHANGELOG.md`; manifesto e serviços do módulo; três adaptadores em `src/Learning/`; teste Unit dos adaptadores e asserções dos aliases em `tests/src/Kernel/AcultaLmsInstallTest.php`.
- **Verificações:** Unit completa PASS, 204 testes/666 assertions, 1 deprecação PHPUnit; conversão `4/5 = 80%`, placeholder zero como NULL e recusa sem máximo cobertos. Kernel `AcultaLmsInstallTest` PASS, 4 testes/95 assertions e 27 depreciações upstream; confirma os três aliases. `LearningDashboardKernelTest` PASS, 1/41, 43 depreciações upstream no banco `aculta_simpletest`. Gate Portal PASS (1.159 verificações), índice PASS (48 componentes), `git diff --check` PASS. `AccountCoursesReaderTest` continua UnitTestCase; não foi convertido para Kernel.
- **Runtime:** Drush confirmou o Runtime de desenvolvimento no checkout servido conforme `docs/architecture/environments.md`. `drush cr` PASS e os três aliases resolveram para seus adaptadores. `updatedb:status`: sem updates; `config:status`: sem diferenças. `aculta_lms_update_10004` não está pendente; nenhum update/import foi aplicado e não foi necessário snapshot.
- **Pendências:** estado textual desconhecido não é representável na API contrib, que expõe booleano; o adaptador mapeia os dois valores válidos e recusa valor inválido. Nenhuma alteração de P5/P6.
- **Leitura para retomar:** `docs/decisions/ADR-042-remocao-dependencias-lms-contrib.md`; `vendor/drupal/lms/src/Entity/{LessonInterface,ActivityInterface,AnswerInterface}.php`; `vendor/drupal/lms/src/TrainingManager.php`; adaptadores em `web/modules/custom/aculta_portal/modules/aculta_lms/src/Learning/`.

## Handoff mais recente — xAPI F3/Homelab e sincronização, 2026-11-02

- **Tarefa:** concluir a implementação F3 do LRS xAPI, sincronizar com `origin/dev`, validar no checkout Homelab e publicar. O usuário autorizou push e o teste real ZIP/H5P na sessão anterior.
- **Git:** remoto avançou para `1be85100e`; os commits locais foram reaplicados por rebase sem conflitos. Push fast-forward confirmado em `origin/dev` até `fe2e9c6f4`; F3 está em `5d2c53b04` (`feat(aculta_lms): conclui ingestão e retenção xAPI`).
- **Entrega:** `aculta_lms` 0.1.0-dev.124; endpoint para emissão de token da tentativa e `POST /xapi/statements`, validação/idempotência/limite Flood por token, retenção mensal em lotes e cobertura Unit/Kernel. ADR-041, plano xAPI, CHANGELOG, status e README foram atualizados.
- **Runtime:** Drush confirmou `toca`, Drupal 11.4.10, PHP 8.4.27 e banco `aculta_runtime`. `updatedb:status`: sem updates pendentes; `config:status`: sem diferenças. Snapshot criado anteriormente para a validação de config/Olivero: `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-xapi-olivero-20261102/runtime.sql`, SHA-256 `385f539d6a8f82e12dfe2c8e293bab691f3242e00b5545b33913453ff2868153`.
- **Validações executadas nesta retomada:** PHPUnit oficial Unit de XAPI, AttemptRule, Mailer e H5P: 97 testes/190 asserções PASS; Kernel xAPI retention, Mailer e H5P installer no `aculta_simpletest`: 22 testes/365 asserções PASS, 27 depreciações de Core/contrib; gate Portal Drupal 11+: 1.159 checks PASS; gate MAIN: 39 arquivos/163 rotas PASS; status index: 48 componentes PASS; PHP lint e `git diff --check` PASS.
- **Validações Homelab executadas anteriormente nesta tarefa:** Firefox ESR visível com ACULTA420 e Olivero aceitou e persistiu statement em ambos; formulário de configuração somente no MAIN e 404 em `cursos`; Flood aceitou as primeiras 60 submissões inválidas e respondeu 429 à 61ª. Artefatos fora do repositório: `/home/piradopirata/aculta-suite-out/xapi-browser-20261102/`.
- **ZIP/GZIP da tarefa predecessora:** resultados reais e achados foram registrados sem reescrever os achados em `docs/operations/SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md`; inclui execução autorizada de ZIPs de bomba pelo instalador H5P. Portal/Revista funcional por tema ficaram DEFERRED pelas dependências de instalação/configuração descritas naquele documento; Fórum passou em ACULTA420 e Olivero.
- **Próximos passos:** nenhum bloqueio de implementação. Uso em produção depende de medir a carga de escrita MariaDB e provisionar o segredo HMAC pelo deploy, conforme ADR-041.
- **Leitura para retomar:** commit `5d2c53b04`; `docs/modules/PLANO-XAPI-2-SUBSTITUI-SCORM.md`; `docs/decisions/ADR-041-xapi-2-lrs-proprio.md`; `docs/operations/SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md`.

Este é o ponto único de handoff para as próximas sessões e agentes. Atualize este documento antes de trocar de sessão, encerrar uma fase ou transferir o trabalho para outro agente/ferramenta, conforme `AGENTS.md`.

## Handoff mais recente — validação Homelab concluída parcialmente, 2026-11-02

- **Tarefa e contexto:** validar no Homelab as entregas `6f978b5`, `42ed25f`, `7fe8bae`, `7adb5fc`, `313c227` e `fac3d58`; usuário autorizou depois a bomba ZIP real e pediu o relatório completo.
- **Checkout/publicação:** `dev`, limpo e sincronizado com `origin/dev` no momento do handoff. Commits desta validação enviados: `3a1a8569e` (fixture H5P), `9dddc1fff` (fixtures Mailer) e `5e73f2279` (bombas ZIP reais). Commit documental da consolidação final ainda precisa ser criado/enviado.
- **Runtime:** `toca`, Drupal 11.4.10, PHP 8.4.27, Drush 13.8.0, banco `aculta_runtime`. Snapshot pré-import: `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-homelab-validation-20261102/runtime.sql`, SHA-256 `f49e3b6a72f9cbec3d34cec1edb51fcdf464d2c25bc8695a06f1f9f1c413f68`. Atualização sem pending updates; import somente de `config/sync`, que removeu a configuração DB-only `aculta_file_uploader.settings`; verificações finais de status limpas.
- **Resultados executados:** Mailer Unit 15/40 PASS; LMS `AttemptRuleTest` 11/11 PASS; H5P Unit 6/40 PASS; Mailer Kernel 7/33 PASS; H5P instalador Kernel 13/310 PASS; casos reais de bomba ZIP 2/78 PASS, rejeitando entrada de 33 MiB e entrada de 2 MiB com razão >100:1 antes da extração. Gates Portal Drupal 11+ 1.159 checks PASS; índice de status 48 componentes PASS. Fórum funcional: ACULTA420 6/33 PASS e Olivero 6/33 PASS.
- **Limite:** não há testes `BrowserTestBase`/`FunctionalJavascriptTestBase` para Portal e Revista. Smoke temporária da Revista falhou antes da execução por dependências de Views (`node.type.revista`, `field.field.node.revista.field_aculta_discussion`) ausentes em instalação limpa sem `config/sync`; logo Portal/Revista não foram validados em nenhum dos dois temas. O achado e motivo estão no relatório abaixo.
- **Documentação a finalizar:** atualizar e publicar `docs/operations/SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md` com os resultados e este handoff. Não alterar o texto dos achados da revisão.
- **Referências:** `docs/operations/SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md`; `docs/operations/BANCO-DE-TESTE.md`; `docs/architecture/environments.md`; `docs/operations/AGENT-OPERATIONS.md`; `git status --short --branch`.

## Handoff mais recente

- **Data/hora:** 2026-11-02, America/Sao_Paulo.
- **Tarefa e contexto:** alinhar o checkout Homelab a `dev`, validar as entregas `6f978b5`, `42ed25f`, `7fe8bae`, `7adb5fc`, `313c227` e `fac3d58`, corrigir falhas das suítes e publicar fases concluídas em `origin/dev`.
- **Checkout e publicação:** branch `dev` no checkout servido, sincronizada com `origin/dev`; HEAD verificado `904bdcbc5` após a publicação de duas fases. Commits da tarefa enviados: `3a1a8569e` (`test(aculta_lms_h5p): corrige fixture do instalador`) e `9dddc1fff` (`test(aculta_mailer): registra uploader nos Kernels`).
- **Runtime:** hostname `toca`, Drupal 11.4.10, PHP CLI 8.4.27, Drush 13.8.0, banco `aculta_runtime`. Snapshot pré-import em `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-homelab-validation-20261102/runtime.sql`, 102.039.549 bytes, SHA-256 `f49e3b6a72f9cbec3d34cec1edb51fcdf464d2c25bc8695a06f1f9f1c413f68`. `updatedb`: sem updates; `config:import` apenas de `config/sync` removeu `aculta_file_uploader.settings` DB-only. Verificação final após novas mudanças remotas: `updatedb:status` sem updates e `config:status` sem diferenças.
- **Verificações finais:** gates Portal Drupal 11+ PASS (1.159 checks) e índice PASS (48 componentes). PHPUnit oficial: Mailer Unit 15/40 PASS; LMS `AttemptRuleTest` 11/11 PASS; H5P Unit 6/40 PASS; H5P instalador Kernel 11/232 PASS; Mailer Kernels 7/33 PASS, estes quatro Kernels/Units no banco correto conforme aplicável (`aculta_simpletest` para Kernel). H5P foi corrigido para habilitar `aculta_file_uploader` e instalar seu schema; Mailer para habilitar `file` e `aculta_file_uploader`.
- **Documentação:** `SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md` recebeu resultados executados e versões atualizadas, mantendo pendentes o teste real de bomba (aguarda autorização explícita) e os testes Portal/Revista/Fórum com Olivero. `QrCodeBatchController` foi verificado como exportação ZIP de saída; fluxo PPT Opigno permanece fora do escopo conforme plano.
- **Arquivos alterados nesta fase:** commits acima cobrem H5P e Mailer. Permanecem alterações documentais locais neste handoff e no relatório de segurança, ainda não commitadas. Preservada fora do escopo a edição de `web/modules/custom/aculta_portal/modules/aculta_studio/src/Form/StudioUploadEmbedForm.php`.
- **Próximos passos:** confirmar autorização antes da bomba real; decidir/rodar os testes de Portal, Revista e Fórum com Olivero; então finalizar e publicar a fase documental em `dev`, sem incluir a edição alheia de Studio.
- **Leitura para retomar:** `docs/operations/SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md`; `docs/operations/BANCO-DE-TESTE.md`; `docs/architecture/environments.md`; `docs/operations/AGENT-OPERATIONS.md`; `git status --short --branch`.

- **Data/hora:** 2026-11-02, America/Sao_Paulo.
- **Tarefa e contexto:** resolver todas as pendências de `php scripts/validate-project-status-index.php` em `dev`, por pedido explícito do responsável.
- **Concluído nesta sessão/fase:** alinhadas as versões dos 12 status files aos manifestos, criado o status e README ausentes do `aculta_file_uploader`, e README raiz regenerado pelo script oficial. Commit `4ac8f1d0a` foi enviado e confirmado em `origin/dev`. Validador final PASS para 48 componentes.
- **Arquivos alterados nesta tarefa:** `README.md` (índice gerado); `docs/operations/status/aculta_{document_viewer,file_uploader,forum,gallery,image,lms,lms_h5p,magazine,portal,qrcode,studio,user,wiki}.yml`; `web/modules/custom/aculta_portal/modules/aculta_file_uploader/README.md`; este handoff.
- **Verificações executadas e resultados:** `php scripts/validate-project-status-index.php --write` regenerou o índice; `php scripts/validate-project-status-index.php` PASS (48 componentes, incluindo todos os status e links `next_phase`); parse YAML de todos os status e do manifesto do Uploader PASS; `git diff --check` PASS.
- **Pendências e bloqueios:** nenhum bloqueio no índice. O checkout primário `/home/piradopirata/projetos/aculta` está separado da branch `dev` e pode conter estado de outras frentes; não foi alterado. O trabalho ocorre no checkout existente da branch `dev`.
- **Próximos passos:** nenhum para o índice global; `4ac8f1d0a` está publicado em `origin/dev`, branch limpa, validador PASS. Gates autenticados/externos da tarefa Contributions anterior continuam descritos no handoff anterior e no plano correspondente.
- **Leitura para retomar:** `scripts/validate-project-status-index.php`; `docs/operations/status/`; `web/modules/custom/aculta_portal/modules/aculta_file_uploader/README.md`; este arquivo.

### Handoff anterior — execução do plano Contributions, 2026-11-02

Commits publicados em `origin/dev`: `6a0f0d072`, `7495f7a3d` e `2445c89b2`. A tarefa do plano, testes e gates operacionais pendentes estão registrados nas seções acima e em `docs/architecture/PLANO-ACULTA-CONTRIBUTIONS.md`.

Não inclua credenciais, tokens ou outros dados sensíveis. Atualize este handoff ao transferir para um novo ponto de retomada, mantendo referências anteriores apenas se ainda forem necessárias para entender o estado atual.

## Handoff — superfícies e caminhos públicos, 2026-11-03

- **Entrega:** validação do Runtime para as superfícies concluída no commit `8263f1776`, enviado a `origin/dev`; `git ls-remote origin dev` confirmou `8263f17767fbe3910ac335bbcc381b375771bcc4`.
- **Arquivos:** teste Kernel de superfícies, changelog/README/manifesto/status do `aculta_superficies`, matriz HTTP, revisão de fases e registro de dívidas.
- **Runtime:** hostname `toca`, PHP 8.4.27, Drupal 11.4.10, banco `aculta_runtime`; `composer install`, `updatedb:status`, `config:status`, `config:import --diff` e `cr` passaram. `leitura_ativa` está TRUE somente no banco do Runtime; `config/sync/aculta_portal.superficies.yml` continua FALSE.
- **Testes:** Unit focal 38/38 e 311 asserções; Kernel `SurfaceRoutesKernelTest` 2/2 e 22 asserções, com depreciações de Core/contrib. Matriz HTTP antes/depois registrada em `docs/architecture/TESTES-3.0-ROTAS-SUPERFICIES.md`.
- **Gates:** admin routes, environment addresses, browser validators, public slugs, tema ACULTA420, boundaries e suíte do deployer PASS. Índice/Portal Drupal 11 falham por alteração simultânea do LMS: manifest `.160`, status `.159` e README fora deste commit.
- **DEFERRED:** revisão autenticada da interface `/painel-administrativo/aculta/caminhos` e das Suites; resolução inbound de `/slug` no MAIN (3.5); exportação de `leitura_ativa: true` para `config/sync` somente no RC.
- **Estado do checkout:** alterações não staged do `aculta_lms` pertencem ao trabalho paralelo e foram preservadas; não incluir no próximo commit de superfícies.

- **Continuação:** expectativas antigas de `/wesativa` e `/greenstash` foram corrigidas para `/we-sativa` e `/green-stash` nos testes de Studio e Commerce. A suíte combinada passou 257 testes/1.642 asserções; commit `52c52301b` está publicado em `origin/dev`.
- **Interface administrativa:** rota, permissão restrita, parent de menu ACULTA e purpose MAIN conferidos nos YAML e no teste estático. A requisição anônima retorna 404; validação autenticada segue DEFERRED por falta de sessão de teste.
- **Interface administrativa no Runtime:** `aculta_superficies` foi habilitado para a validação. `/painel-administrativo/aculta/caminhos` retorna 403 no MAIN para anônimo e 404 em subdomínio; a rota expõe path, purpose `main`, permissão `administrar superficies` e `_admin_route: true`. `config:status` mostra `core.extension` e `aculta_portal.superficies` como diferenças esperadas.

## Handoff — ADR-042 P5, histórico de tentativas ACULTA, 2026-11-03

- **Objetivo ativo:** concluir P4/P5 da ADR-042. P6 e reescrita de histórico permanecem fora.
- **Publicados:** dev.165 `af2c0352f` migra histórico para AnswerEntity; dev.166 `ad1135bce` cobre idempotência do update 10011; dev.167 `534ef1b3b` remove feedback legado; dev.168 `acdc05d0e` remove FileUpload contrib, access/controller/rota antigos e respectivos testes/configs.
- **Runtime/update 10011:** snapshot SHA-256 `a344c5509ee02a565b4bd72790da51dd541d86298f75ba42eb7ba5f91c1fa076`. Tentativa de alterar `score` inteiro falhou sem alteração; update ajustado instalou `score_precise` ao lado do inteiro e passou. `drush cr` passou; sem updates pendentes. `config:status` mantém apenas `aculta_portal.superficies` e `core.extension` diferentes; sem import.
- **Local dev.169:** removidos `ActivityDeadlineFormHooks` e `CourseLinkHooks`, presos ao formulário/rota do contrib. Unit 218/758 PASS; `AcultaLmsInstallTest` 6/196 PASS (44 depreciações); `AttemptArchiveLifecycleTest` 5/201 PASS (50 depreciações); gate Portal 1.172 checks PASS. `rg -F 'Drupal\lms' web/modules/custom -g '*.php'` retorna zero.
- **Pendências:** dev.169 ainda local. Dependências `lms*` seguem no manifesto; `composer.json`/lock, config sync e módulos Runtime não foram cortados. O formulário ACULTA de submissão de arquivo segue pendente. Configuração `activity_data` para os plugins objetivos aguarda definição; não inventar formato de enunciado/gabarito.
- **Próximos passos:** publicar dev.169; preparar P4 removendo deps do manifesto e retirando lms do harness; executar testes limpos; então P5 Composer/config/Runtime com snapshot e ordem autorizada. P6 não executar.
## Renomeação LinkWeed — 2026-11-03

- Objetivo: renomear `aculta_links` para `aculta_linkweed`, mantendo purpose `links` e Domain `links_aculta_org`.
- Feito: código, namespaces, entidades, tabelas, config, rotas administrativas e referências customizadas renomeados; título da superfície virou LinkWeed; Runtime atualizado e cache reconstruído.
- Entidades/tabelas: `aculta_link` → `aculta_linkweed`, `aculta_link_revision` → `aculta_linkweed_revision`, `aculta_link_item` → `aculta_linkweed_item`, `aculta_link_item_revision` → `aculta_linkweed_item_revision`, `aculta_link_page` → `aculta_linkweed_page`, `aculta_link_page_revision` → `aculta_linkweed_page_revision`.
- Contagens Runtime antes/depois: 1/1, 1/1, 0/0, 0/0, 0/0, 0/0, na ordem acima.
- Verificações: `updb`, `cr`, `pm:list`, config `aculta_linkweed.social_links`; gates administrativos, endereços, browser validators, slugs, status index e Drupal 11 passaram. Unit do módulo: 8 testes/65 assertions.
- Entrega: commit `f07f922b9c44d77a5ad93b82189b717cc17eb1bf` enviado a `origin/dev`; `ls-remote` confirmou o mesmo hash. Smoke HTTP no MAIN simulado: `/painel-administrativo/linkweed` respondeu 403 anônimo (rota presente e protegida) e `/painel-administrativo/links` respondeu 404. `config:status` ainda mostra divergências LMS preexistentes e `core.extension`; não foram exportadas por segurança de escopo.
- DEFERRED: resultado final do Kernel focal não foi capturado pelo runner longo; não houve falha explícita registrada. Navegador visual não foi necessário após smoke HTTP e gates.

## Handoff — remover imports LMS reintroduzidos pelo PR #301 — 2026-11-03

- **Objetivo:** substituir os usos de `Drupal\\lms` em sete arquivos de código e quatro testes, conforme ADR-042, com escopo ampliado autorizado para fechar as dependências arquiteturais identificadas.
- **Estado Git:** worktree isolado em `bf731e8789795f987a9bbc7246b3e7a17a31a933`; durante a tarefa, `origin/dev` avançou a `9c2e64018b8603ba8d3b6e178e5b1728211243c4` (três commits à frente). Alterações ainda locais, sem commit/push; rebase pendente.
- **Implementação:** formulário ACULTA próprio de submissão (`AnswerSubmissionForm`) com acesso por matrícula, armazenamento de tentativas via payload ACULTA, resposta livre com feedback formativo, tipos objetivos do P3 e upload privado; configuração `answer_config` em `ActivityEntity` com update idempotente 10013. Hooks de prazo, imagem/upload e discussão agora operam no formulário ACULTA. O portal deixou de manter slugs duplicados; ficou a implementação em `aculta_lms`, conforme escolha do responsável. Atualizado teste de notice stale answer e fixture antiga do dashboard.
- **Arquivos/versões:** alterações em `aculta_lms` (0.1.0-dev.175), `aculta_forum_lms` (0.1.0-dev.35), `aculta_portal` (0.1.0-dev.68), status yml, CHANGELOGs, README gerado e este handoff. Removidos os quatro arquivos de implementação de slug duplicados do portal e seu teste; equivalentes e teste permanecem em `aculta_lms`.
- **Testes:** `aculta_lms` Unit 224 testes/773 assertions PASS; `aculta_forum_lms` Unit 33/331 PASS; `LearningNoticeStaleAnswerTest` 1/8 PASS. `AcultaLmsInstallTest` Kernel 6/178 PASS (35 depreciações upstream). `LmsActivityDiscussionResolverKernelTest` Kernel 1/25 PASS (38 depreciações upstream). Aviso de diretório browser_output não gravável nas execuções Unit.
- **Gates:** `validate-aculta-portal-drupal11.php` PASS (1202 checks); `validate-public-slugs.php` PASS (410 routes, 0 violações); índice de status PASS (53 componentes); `git diff --check` PASS; busca de `Drupal\\lms` em `web/modules/custom` sem resultados. Gates auxiliares: `validate-module-theme-map.php` ainda falha em cinco módulos sem linha no mapa (`aculta_links`, `aculta_lms_messenger`, `aculta_paths`, `aculta_commerce_messenger`, `aculta_wiki_messenger`); `validate-portal-ui-docs.php` ainda reporta 13 tokens/atributos existentes de Studio, Events e File Uploader sem documentação. O novo código não adiciona tokens ao tema.
- **Runtime:** sem alterações. Não houve atualização/import/caches. Sincronização de código no Runtime fica DEFERRED para respeitar a instrução específica de não alterar dados/runtime nesta tarefa.
- **Próximos passos:** rebasear sobre `origin/dev`, rerodar Unit/gates, revisar diff staged, commitar em português com trailer Claude-Session e sem coautoria; push fast-forward em `origin/dev`; depois avançar `origin/main` somente se continuar ancestral e conforme ordem explícita. Confirmar hashes remotos. Não executar P4/P5 adicionais, P6, nem operações de dados.
- **Leitura para retomar:** `web/modules/custom/aculta_portal/modules/aculta_lms/src/Form/AnswerSubmissionForm.php`; `src/Plugin/ActivityAnswer/FreeTextAnswerPluginBase.php`; `src/Access/AnswerSubmissionAccessCheck.php`; `src/Hook/ActivityDeadlineFormHooks.php`; `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Hook/LmsActivityDiscussionHooks.php`; este handoff.

## Handoff — validação Runtime LMS interrompida — 2026-11-03

- **Ordem e referência:** validar `aculta_lms` 0.1.0-dev.175 no Homelab, sem produção. Checkout em `dev`, sincronizado a `775295ac3d1c8c21524a9b15696515b70182b1b2`; branch remota `main` estava no mesmo commit.
- **Snapshot:** íntegro via `drush sql:dump`, 54.968.655 bytes, modo 0600, SHA-256 `374e09c05e47de60cc42a01d14c0e0d4186c1fa222a1c787acce00f1e92dd5a9`. Caminho lógico: `$SUITE_OUT/lms-runtime-validation-20261103/aculta_runtime-before-775295ac3.sql`.
- **Runtime:** `composer install` sem mudanças de pacotes; lockfile sem `drupal/lms*`. `drush cr` PASS. Antes do update: somente `aculta_lms_update_10013` pendente, configuração sem diferenças. Update 10013 aplicado. Depois: sem updates, sem diferenças de configuração; nenhum import de config/sync.
- **Dados sintéticos criados:** Group curso ID 88, Group turma auxiliar ID 89, duas aulas ACULTA publicadas e cinco atividades (true_false, select, fill_blanks, free_text, file_upload), além de uma conta aluna e uma educadora sintéticas. Nenhuma resposta ou arquivo foi submetido. Dados ficam para revisão; não remover sem ordem própria.
- **Parada funcional:** primeira requisição anônima à rota de resposta retornou HTTP 500, com corpo `O site encontrou um erro inesperado. Tente novamente mais tarde.` O smoke runner não interrompeu o loop após o primeiro 500: consultou também as outras quatro rotas (todas 500) e `/user/login` em Conta (404), sem autenticar nem escrever. Depois, uma única requisição GET anônima repetiu a primeira rota para capturar o corpo exato. Encerrei o fluxo sem POST/upload; Watchdog não registrou mensagem correspondente.
- **Gates estáticos:** Portal 1202 checks, slugs públicos 410 rotas, rotas administrativas MAIN 41 arquivos/170 rotas e índice 53 componentes PASS. Firefox ESR 153.5.1 e geckodriver 0.37.1 estão disponíveis; `DISPLAY=:0` responde, mas o navegador não foi iniciado devido à parada.
- **Entrega documental:** versão `aculta_lms` 0.1.0-dev.176 registra update e falha; este relatório e o gate estático compõem a entrega versionada.
- **DEFERRED:** respostas e upload autenticados, acesso do educador a respostas/avaliação discursiva, isolamento entre estudantes, screenshots 320/1440 em ACULTA420 e Olivero. O código atual não registra rota/tela de revisão de respostas; o painel de turma expõe métricas e prazos, sem payloads de resposta. Retomar após ordem para tratar o HTTP 500; qualquer revisão docente exige decisão/implementação separada. Não apagar dados sintéticos sem ordem.
- **Leitura para retomar:** `docs/operations/AGENT-OPERATIONS.md`; `docs/architecture/environments.md`; `web/modules/custom/aculta_portal/modules/aculta_lms/aculta_lms.routing.yml`; `AnswerSubmissionForm.php`; snapshot externo sob `$SUITE_OUT`.

### Continuação — correção do acesso e retomada dos testes, 2026-11-03

- **Correções locais:** `AnswerSubmissionAccessCheck` aceita slugs antes da conversão dos parâmetros; `LearningEligibility` usa os relacionamentos `lms_classes` do curso. Também foram corrigidos os serviços do formulário perdidos na serialização do Form API, o encadeamento `setPermanent()->save()` (o método retorna `void`) e a árvore de valores das lacunas. Causas 500 no watchdog: `Typed property ... $currentRoute must not be accessed before initialization` e `Call to a member function save() on null`.
- **Testes:** LMS Unit 226/788 PASS (4 depreciações PHPUnit); `AcultaLmsInstallTest` 7/193 PASS (35 depreciações upstream). `AnswerSubmissionAccessCheckTest` cobre slugs crus sem TypeError; `LearningEligibilityTest` cobre matrícula em turma relacionada/outsider; Kernel verifica serialização dos serviços. `ClassActivityDeadlineTest` não executa porque o harness ainda declara o módulo removido `lms` (`Unavailable module: 'lms'`); não foi reintroduzido.
- **Runtime:** snapshot pré-alterações em `$SUITE_OUT/lms-runtime-validation-20261103/aculta_runtime-before-lms-500-fix-9512e69.sql`, SHA-256 `ec8259784afe036efba985dcab557694530cb106dfa20fadbd31b7d9ff71bc88`. `updatedb:status` limpo e `config:status` sem diferenças; `drush cr` passou. Rotas anônimas: cinco HTTP 403. Conta sintética autenticada abriu e enviou os cinco tipos sem 500. Contagens: verdadeiro/falso 1, seleção 1, lacunas 2 (score 0 inicial e 100 após correção), resposta livre 1 `submitted` sem nota, upload 2 `submitted`. Upload tem dois arquivos referenciados: um permanente com uso registrado e um temporário da submissão que falhou após salvar a tentativa. Dados permanecem sem descarte.
- **Visual/limites:** Firefox ESR visível, ACULTA420, upload 10/10 em 1440/320; capturas em `$SUITE_OUT/lms-runtime-validation-20261103/firefox-upload/` e `$SUITE_OUT/lms-runtime-validation-20261103/firefox-answer-types/`. Painel docente no domínio MAIN responde HTTP 200 e mostra a turma, mas não existe revisão/avaliação de resposta. Isolamento entre duas estudantes não executado (uma estudante sintética autorizada); Olivero DEFERRED.
- **Versão/gates:** `aculta_lms` 0.1.0-dev.177. Portal 1202 checks, slugs 410 rotas e admin MAIN 41 arquivos/170 rotas PASS.
- **Retomar lendo:** `web/modules/custom/aculta_portal/modules/aculta_lms/src/Access/AnswerSubmissionAccessCheck.php`; `src/Notice/LearningEligibility.php`; `src/Form/AnswerSubmissionForm.php`; `tests/src/Unit/AnswerSubmissionAccessCheckTest.php`; `tests/src/Unit/LearningEligibilityTest.php`; este handoff.

### Continuação — isolamento entre estudantes e prazo sem contrib, 2026-11-03

- **Snapshot:** `$SUITE_OUT/lms-runtime-validation-20261103/final/aculta_runtime-before-isolation-review.sql`, SHA-256 `cd8ac930dcb95354074846929273253327045fd907f1cec0b1c53f04101c2616`.
- **Isolamento:** segundo aluno sintético autenticado abriu somente o próprio formulário (HTTP 200), recebeu HTTP 403 ao baixar a resposta de outro aluno e a rota de lista de respostas inexistente respondeu HTTP 404. Não houve vazamento de conteúdo nem 500.
- **Revisão docente:** DEFERRED. O painel docente existente mostra turma, métricas e prazos, mas o módulo não possui rota/formulário para listar, avaliar ou publicar feedback de respostas.
- **Prazo:** `ClassActivityDeadlineTest` foi ajustado para não habilitar `lms` ou `lms_classes` e usar as entidades ACULTA. Execução: 1 teste, 15 assertions, erro antes da conclusão: `The "entity:group_relationship:lms_course-group_membership" plugin does not exist`.
- **Gates:** Portal, slugs públicos e rotas administrativas passaram; a busca de `Drupal\\lms` permanece sem ocorrências. O índice global não passou porque já existe divergência não relacionada do `aculta_studio` entre status e manifesto.
- **Decisão:** Olivero dispensado pelo responsável em 2026-11-03, motivo: validação dispensada pelo responsável.

### Continuação — independência LMS fechada, 2026-11-03

- **Frente 1:** `ClassActivityDeadlineTest` usa somente os plugins de relação ACULTA e passa 1/29, com 39 depreciações upstream. Nenhum módulo `lms*` é habilitado no harness; as referências históricas de configuração contrib permanecem apenas em Config Sync/histórico e não são recriadas.
- **Frente 2:** criadas as rotas de lista e revisão docente. Educador teve HTTP 200 na lista e no detalhe e gravou nota 77, feedback e `state=graded`; aluno B teve HTTP 403 na lista e no detalhe. A resposta própria do aluno exibe a avaliação. Unit LMS 226/788 PASS e Kernel `AcultaLmsInstallTest` 7/193 PASS.
- **Frente 3:** manifesto, status e changelog do Studio alinhados em `0.1.0-dev.41`, versão evidenciada pelo manifesto/status e pela entrada de changelog existente. Nenhum código Studio foi incluído.
- **Runtime:** snapshot anterior ao update 10014 em `$SUITE_OUT/lms-runtime-validation-20261103/final/aculta_runtime-before-isolation-review.sql`, SHA-256 `cd8ac930dcb95354074846929273253327045fd907f1cec0b1c53f04101c2616`. Update `aculta_lms_update_10014` aplicado; depois não há updates nem diferenças de configuração; cache rebuild passou.
- **Gates:** Portal 1202 checks PASS, slugs 410 PASS, rotas administrativas 172 PASS e grep `Drupal\\lms` zero. Studio Unit 72/661 PASS. Olivero dispensado pelo responsável.

### Continuação — validação visual Olivero, 2026-11-03

- **Snapshot:** `$SUITE_OUT/lms-runtime-validation-20261103/olivero/aculta_runtime-before-olivero.sql`, SHA-256 `a1ddcb237d01e62c2eebbd531844df75ac3e573d92510fc8018e8c04b411c7f4`.
- **Firefox ESR visível:** Olivero passou 10/10 verificações do formulário de upload em 1440 px e 320 px, incluindo recusa `.exe`, aceite PNG, envio e ausência de erros JavaScript. Capturas e relatório em `$SUITE_OUT/lms-runtime-validation-20261103/olivero-final4/`.
- **Correção:** o harness passou a desconsiderar o menu móvel oculto do Olivero no cálculo de overflow; o formulário recebeu proteção de viewport para a navegação colapsada. O tema foi restaurado para `aculta420` e Olivero desinstalado.
- **Runtime final:** `drush cr` passou, sem updates pendentes e sem diferenças de configuração.

## Handoff — Bootstrap 8.x-3.45 — 2026-11-03

- Atualizado `drupal/bootstrap` para 8.x-3.45 (Composer 3.45.0); `ACULTA420` e Bootstrap confirmados ativos pelo Drush. O asset local `twbs/bootstrap` 5.3.8 foi mantido para as fixtures e verificações já existentes.
- Ajustados README, sincronizador de assets do Composer e gate Foundation. `composer validate`, `php -l` nos scripts alterados, gate Foundation (355 checks) e `git diff --check` passaram.
- Commit `fee0f65c11` enviado para `origin/dev`.
- Snapshot pré-update do `aculta_runtime`: `$SUITE_OUT/runtime-snapshots/aculta_runtime-before-bootstrap-8x-20261103.sql.gz.gz`; SHA-256 `2468c0ac57ea21092a753cc6289b73df623ebe38e62d1467dce0ffa11b27a853`.
- **Bloqueio Runtime:** `updatedb` falhou em `system_update_11501`, pois o update procura o tipo de entidade `aculta_link`, ausente no Runtime. Nenhum `config:import` foi executado. Diagnosticar/alinha o estado do entity type antes de retomar `updatedb`.

## Handoff — preparação Drupal 12 e ImageMagick 5 — 2026-11-03

- Upgrade Status 5.0.0-alpha3 instalado/ativado e Rector 1.1.3 configurado para os módulos Portal/MCP e tema ACULTA420. 83 manifests customizados agora declaram `core_version_requirement: ^11 || ^12`.
- Rector aplicou os ajustes de APIs removidas/depreciadas no código customizado; a segunda execução em dry-run não encontrou transformações restantes. Depreciações PHP 8.5 de cURL, GD e Reflection foram tratadas; o shim público legado `UploadedAnswerAccessCheck` foi preservado por compatibilidade.
- ImageMagick atualizado para 5.0.2; PDFPreview está no branch `1.x-dev` (`6bdd374e`) porque a release estável 1.2.0 não aceita ImageMagick 5. Composer também remove Storybook. Drupal Core continua 11.5.0-beta1; isso é preparação de compatibilidade, não upgrade do Core.
- Verificações: Composer validate, gate Portal (1202 checks), gate ACULTA420 Foundation (355 checks), índice do README (53 componentes), PHP lint e Rector dry-run PASS. PHPUnit não executado.
- Upgrade Status registrou avisos manuais não impeditivos, incluindo o shim legado de compatibilidade e depreciações removidas só no Drupal 13.
- Snapshot pré-update do Runtime e bloqueio em `system_update_11501` estão descritos acima. `config:import` ficou pendente após a falha de `updatedb`.

## Handoff — compatibilidade ACULTA420 com Bootstrap 8.x — 2026-11-03

- ACULTA420 carrega agora `bootstrap5-framework` a partir de `/libraries/bootstrap`, depois das bibliotecas do Drupal Bootstrap 8.x-3.45 e antes dos CSS/JS do tema. Isso mantém as classes e APIs `data-bs-*` já usadas pelo tema.
- Versão do tema marcada `0.7.0-dev.38`; CHANGELOG, roadmap, gate Foundation, `AGENTS.md` do tema e índice gerado atualizados.
- Verificações estáticas: PHP lint, parsing de YAML, status index (53 componentes) e `git diff --check` PASS. Testes e revisão visual não executados.
- Cache do Runtime não reconstruído. A sincronização permanece bloqueada pelo `system_update_11501` que procura `aculta_link`; registro do snapshot e hash no handoff Bootstrap acima.

## Handoff — ACULTA420 com Bootstrap 8.x nativo — 2026-11-03

- **Objetivo:** usar somente Bootstrap 3.4.10 entregue pelo Drupal Bootstrap 8.x-3.45, sem carregar camada Bootstrap 5 no ACULTA420.
- **Implementação:** removida a biblioteca `bootstrap5-framework` do `aculta420/global`; gavetas de carrinho, avisos e mensagens usam o plugin modal Bootstrap 3 com apresentação lateral CSS própria. Dropdown, abas, menu colapsável, alertas e eventos AJAX usam atributos/classes/eventos nativos do Bootstrap 3/jQuery. Ajustada a grade do endereço, cartões de conteúdo e utilitários que `PortalUiHooks` entrega para layouts ACULTA com classes `aculta-u-*`.
- **Versão:** tema `0.7.0-dev.39`; atualizado CHANGELOG, roadmap, gate Foundation, instruções locais, status do componente e linha gerada do README.
- **Validações:** status index regenerado (53 componentes); `git diff --check`, PHP lint, `node --check` e parsing de 3 arquivos YAML PASS. Testes e inspeção visual não executados.
- **Runtime:** sincronização DEFERRED. `updatedb` permanece bloqueado por `system_update_11501` que procura o tipo de entidade `aculta_link`, ausente no Runtime; snapshot/hash constam no handoff Bootstrap 8.x acima. Não houve operação de escrita.
- **Retomar lendo:** `web/themes/custom/aculta420/aculta420.libraries.yml`, `components/patterns/{cart-offcanvas,alerts-trigger,messenger-trigger,product-tabs}`, `js/{navigation,messenger-drawers}.js`, `css/components/{portal-cart,utilities}.css`, `scripts/validate-aculta420-foundation.php` e esta seção. A validação visual e de comportamento no tema base ainda é necessária antes de declarar RC.

## Handoff — política de contrib até ACULTA 1.0 — 2026-11-03

- **Decisão D-72:** até a versão oficial 1.0, manter Drupal contrib em atualização ativa, aceitar alpha/beta para compatibilidade D12, adaptar módulos ACULTA e evitar constraints exatas no `composer.json` sem razão técnica. `composer.lock` permanece versionado como resolução reproduzível e deve avançar com cada atualização.
- **Documentação alterada:** decisão D-72 em `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md`, política em `docs/operations/AGENT-DEPENDENCIAS.md` e matriz em `docs/portal/MIGRACAO-DRUPAL-12-MATRIZ-CONTRIB.md`.
- **Inventário Composer (lock atual):** upgrades diretos identificados: `geofield` 1.67.0 → 10.3.5 (estável, D12; major e constraint atual `^1` bloqueia); `commerce_cart` e `commerce_checkout` 3.3.10 → 3.3.14 (estável, mas ainda sem D12); CKEditor 5 Plugin Pack 1.5.6 → 2.0.0-beta1 e Premium Features 1.8.7 → 2.0.0-beta1 (D12, coordenar com Drupal 12; não compatíveis com D11). Bootstrap omitido conforme orientação do responsável.
- **Acompanhar upstream:** Simple OAuth 6.1.x-dev (patch D12 em revisão), Pathauto 8.x-1.x-dev (compatibilidade D12 fechada no issue; ainda sem release alpha/beta confirmado), Simple Sitemap 5.x-dev (issue D12 fechada) e Webform 6.x-dev (issue D12 precisa revisão). Não tratados como releases prontas.
- **Verificações:** Composer `outdated --all --locked 'drupal/*'`, `composer show --all` para Group/Premium Features e consultas das releases/issues oficiais Drupal.org; `git diff --check` PASS. Nenhuma dependência atualizada neste trabalho.
- **Estado do Runtime:** não sincronizado; pendências de `updatedb` e `config:status` já constam nos handoffs anteriores. Não executar Config Import.
- **Retomar lendo:** esta decisão, `docs/operations/AGENT-DEPENDENCIAS.md`, `docs/portal/MIGRACAO-DRUPAL-12-MATRIZ-CONTRIB.md` e os manifests `composer.json`/`composer.lock`.

### Continuação — atualização contrib e branches solicitados — 2026-11-03

- **Composer:** atualizados `simple_oauth` para `6.1.x-dev`, `pathauto` para o branch Composer `1.x-dev` (branch upstream `8.x-1.x-dev`), `simple_sitemap` para `5.x-dev` e `webform` para `6.x-dev`. Também liberadas as constraints exatas antigas: Commerce Cart/Checkout `^3.3` (lock 3.3.14) e Geofield `^10.3` (lock 10.3.5).
- **Lock:** resolução regenerada; Composer instalou os quatro branches e as três versões estáveis acima. Update amplo `composer update 'drupal/*' -W` não encontrou outros upgrades Drupal permitidos pelas constraints atuais; atualizou PHPStan 2.3.5 e phpstan-phpunit 2.1.1.
- **Bootstrap/Core:** Bootstrap mantido conforme instrução do responsável. Drupal Core continua em 11.5.0-beta1; os branches escolhidos foram resolvidos no Core 11, mas isso não comprova compatibilidade efetiva desses módulos com Drupal 12.
- **Verificações:** `composer validate --no-check-publish` válido com avisos de constraints exatas preexistentes e dos branches `-dev`; `git diff --check` passou. Sem testes funcionais.
- **Runtime:** não sincronizado. Estado anterior registra pendências de updates e diferenças de configuração após falha em `text_post_update`; consultar `updatedb:status`/`config:status` antes da próxima troca de head. Nenhum import ou update de banco executado nesta tarefa.
- **Retomar lendo:** `composer.json`, `composer.lock`, a decisão D-72 e este handoff.

### Continuação — revisão de compatibilidade ACULTA — 2026-11-03

- **Regra de agentes:** `docs/operations/AGENT-DEPENDENCIAS.md` agora exige inspeção dos contratos e módulos ACULTA dependentes sempre que Core ou contrib forem atualizados, além de registrar evidências e bloqueios.
- **Achado Simple Sitemap 5:** removida a opção `xsl` obsoleta da configuração exportada. O plugin ACULTA continua descoberto pelo manager 5.x (atributos e anotações); hooks de links e variantes usam APIs mantidas. README e changelog do módulo e tabela da raiz alinhados às versões do lock.
- **LMS:** referências a módulos contrib indisponíveis `lms`/`lms_classes` removidas das fixtures Kernel de Agenda; `aculta_lms` permanece como módulo da suíte. A referência `lms` sem uso na fixture do Sitemap também foi removida. Outros identificadores `lms` no código são purpose/contrato de produto ou fixtures explícitas de migração, não dependências Drupal ativas.
- **Compatibilidade restante:** Pathauto 8.x-1.x-dev corrige APIs para Core 12; OAuth 6.1.x-dev não afeta a configuração MCP atual (usa scopes, não cria consumers); Webform 6.x-dev mantém as APIs chamadas pelo ACULTA, mas ainda emite depreciações upstream para Drupal 12. Core 12 não foi ativado.
- **Falhas de container corrigidas:** DomainNegotiator sem `setActiveDomain()` foi substituído pelo contexto público; fixture Sitemap inclui `aculta_file_uploader`, `crop` e `image_widget_crop` e injeta `surface_config_reader`; fixture Agenda remove módulos `lms`/`lms_classes` não instalados e prepara entidades Group a partir de `config/sync`. Definições antigas de relacionamento tinham `use_creation_wizard`, fora do schema Group instalado, também removido.
- **Validação:** DomainPurposeManager Kernel 8/47, Sitemap Kernel 4/15, Agenda Kernel 4/160 e `WebformScoreOwnershipTest` 1/11 PASS em PHP 8.5.12. Depreciações Core/contrib: 60, 36 e 42, respectivamente. PHPUnit avisou que o diretório padrão BrowserTest não é gravável, sem impedir os Kernels. `git diff --check`, quatro PHP lint e runner estático do Sitemap PASS.
- **Versão:** Portal avançado para 0.4.1-dev.70; Core 12 não foi ativado. Nenhuma sincronização/import no Runtime.
- **Runtime:** sem sincronização/import. Drush confirmou `aculta_runtime`, mas `updatedb:status` tem 5 pendências (Simple Sitemap 8409 e post-updates de Text/User/Views), e `config:status` mostra drift amplo. Resolver o procedimento D-70 de snapshot/update/import antes da próxima troca de head. Core continua 11.5.0-beta1.

## Handoff — remoção de módulos de IA e definições de entidade/campo — 2026-11-03

- **IA:** nenhum módulo de IA estava habilitado ou instalado. Removidos os pacotes Composer Drupal AI e clientes DeepSeek/OpenAI já ausentes do lock atual; quatro submódulos opcionais que vêm dentro de pacotes contrib (CKEditor AI, CKEditor AI Assistant, Tool AI Connector e VVJ Core AI) estavam desabilitados e foram removidos do checkout. `scripts/remove-optional-ai-modules.php` torna essa remoção persistente após `composer install`/`composer update`.
- **Definições:** os updates customizados de calendário, doação, LinkWeed, mailer, mensageria e UGC já constam aplicados no Runtime. Criados updates para os campos Card do Commerce PaymentMethod e para migrar autores de revisão do Group preservando os valores antes de remover `revision_user`.
- **Status read-only após as correções existentes:** restam apenas as mudanças de PaymentMethod (4 campos) e Group (`revision_user` a desinstalar). `updatedb:status` lista os dois novos updates e quatro post-updates upstream de Text/User/Views. `config:status` mostra 86 diferenças.
- **Snapshot anterior às alterações de banco:** `$SUITE_OUT/runtime-snapshots/aculta_runtime-before-entity-status-fix-20261103.sql`, SHA-256 `5dfe8df21e1246166948636fd5a3d0a738641aad606203fe83e474252faa6074`.
- **Bloqueio conhecido:** um post-update de Text falhou anteriormente por configuração de Views que referencia o plugin ausente `course_card_row`; por isso não executar import de configuração até resolver esse estado e confirmar os updates pendentes. Ainda não foi executado `updatedb` desta entrega.

## Handoff — campos de cartão do Commerce no Runtime — 2026-11-03

- Commit `879c544f27` e o update `aculta_pagamentos_mercadopago_update_10002` instalaram os campos `card_type`, `card_number`, `card_exp_month` e `card_exp_year` como campos do bundle de pagamento Mercado Pago.
- `PaymentCardFieldsUpdateKernelTest`: 1 teste / 17 assertions PASS em `aculta_simpletest`, com 13 depreciações Core/contrib.
- Verificação final no Runtime `toca`/`aculta_runtime`: resumo de mudanças de entidade/campo vazio, `updatedb:status` sem pendências e `config:status` sem diferenças. Nenhum `config:import` foi necessário.
- O preflight do Drush ainda emite avisos upstream de versão do Simple OAuth e post-updates antigos do Group; eles não impediram este update. Não reexecutar updates relacionados sem revisar esses avisos.

## Continuação — corrigir a sala de aprendizagem ACULTA — 2026-11-03

- **Achado LMS:** `CourseJourneyPresentationHooks::preprocessLearningRoom()` ainda verificava a rota removida `lms.group.answer_form`; respostas agora passam por `aculta_lms.answer_submission`, então a sala Ganjalab não envolvia o formulário atual.
- **Correção:** hook ajustado para a rota ACULTA; fixtures do composer agora usam os route IDs atuais. Novo `CourseJourneyPresentationHooksTest` prova que a rota ACULTA recebe o shell e preserva o formulário.
- **Validações:** Studio Unit completo 66/639 PASS; PHPCS Drupal/DrupalPractice nos quatro arquivos alterados PASS; gate Portal Drupal 11+ 1202 checks PASS; índice 53 componentes PASS; `git diff --check` PASS. PHPUnit avisou que `sites/simpletest/browser_output` não é gravável.
- **Runtime:** antes da publicação, `updatedb:status` sem atualizações e `config:status` sem diferenças; nenhuma escrita de banco/configuração necessária. Próximo passo após push: confirmar Runtime, então continuar a auditoria LMS; pagamentos e demais requisitos Drupal ficam para as etapas seguintes do objetivo.
- Snapshot pré-update protegido: `$SUITE_OUT/runtime-snapshots/aculta_runtime-before-mp-card-fields-20261103.sql`, SHA-256 `81715e89580503147ba03ea4f5fdc9454518fd7d2a2efa243ea10e83da1271ce`.
## Continuação — páginas individuais de aula LMS — 2026-11-03

- A jornada do estudante agora fornece link por aula publicada e a rota
  `/curso/{curso}/{aula}` renderiza atividades ordenadas com acesso às
  submissões ACULTA. A autorização exige usuário autenticado, curso e aula
  publicados e matrícula vigente; posições de URL preservam aulas não
  publicadas na ordenação original.
- Kernel confirma URL e renderização de aula sem atividades, incluindo aula
  não publicada antes da visível: 1 teste/35 assertions PASS. Unit LMS 230/805
  PASS; PHPCS e PHP lint focais PASS. Há seis depreciações upstream no Kernel e
  quatro depreciações PHPUnit na suíte Unit.
- Arquivos da entrega: `web/modules/custom/aculta_portal/modules/aculta_lms/`
  (rota, serviços, acesso, controller, jornada, testes, versão e changelog),
  `docs/operations/status/aculta_lms.yml`, linha gerada do `README.md` e este
  handoff.
- Ainda não sincronizado no Runtime. Antes do push, conferir `updatedb:status`
  e `config:status`; em seguida publicar commit isolado e validar o Runtime.
- Próxima etapa do objetivo: concluir auditoria LMS, depois pagamentos e então
  resolver item a item os problemas reportados por `updatedb`.
## Continuação — regressão Kernel de pagamentos — 2026-11-03

- Atualizada a suíte `CheckoutProTest` para habilitar `text_with_summary`,
  necessário ao schema de `commerce_product.default.body` no Drupal atual.
  Antes, os três cenários falhavam durante a instalação do fixture, antes de
  executar código do gateway.
- Validação pós-correção: Unit do Mercado Pago 258/1.156 PASS; Kernels
  `CheckoutProTest` + `PaymentCardFieldsUpdateKernelTest` 4/152 PASS, com 72
  depreciações upstream. PHPCS e PHP lint PASS.
- Runtime: campos de cartão instalados, `updatedb:status` sem pendências e
  `config:status` sem diferenças; nenhuma chamada externa ou cobrança criada.
- Entrega em preparação: `aculta_pagamentos_mercadopago` 0.1.0-dev.85, teste,
  changelog, status e índice do README. Próxima etapa: inventariar os itens
  reais do `updatedb:status`/requirements e corrigi-los um a um.
## Continuação — erro de dependência Simple OAuth no relatório — 2026-11-03

- `core:requirements` indicava erro de versão para `simple_oauth_21` porque o
  checkout Git Composer de `drupal/simple_oauth` não inclui `version` no
  `.info.yml`. `ComposerVersionMetadataHooks` no owner `aculta_mcp` injeta o
  branch `6.1.x-dev` consultando `Composer\InstalledVersions`, sem ampliar a
  faixa de Core permitida pelo upstream.
- **Validações:** Unit novo 3/6 PASS; PHPCS Drupal/DrupalPractice e PHP lint
  focais PASS. Após `drush cr`, o `.info` descoberto mostra `6.1.x-dev`,
  `core:requirements` não tem mais erros e `updatedb:status`/`config:status`
  seguem limpos. `MIGRACAO-DRUPAL-12-MATRIZ-CONTRIB.md` registra que isso não
  equivale a compatibilidade Drupal 12; os módulos OAuth upstream ainda
  declaram suporte até Drupal 11.
- Restam avisos de requisitos independentes (módulos/temas depreciados,
  tradução, mídia duplicada, filtros redundantes, cache/render debug, Search,
  Security Review e estado de atualização do Core); devem ser avaliados em
  escopo próprio, preservando dados e sem desinstalação automática.
- Arquivos: `web/modules/custom/aculta_mcp/src/Hook/` e `tests/src/Unit/Hook/`,
  README do módulo, matriz Drupal 12 e este handoff.
## Correção posterior — registro da sincronização LMS

- O texto “Runtime ainda não sincronizado” na entrada inicial do LMS e “entrega
  em preparação” na entrada de pagamentos foram redigidos antes dos pushes.
  Ambos foram publicados e sincronizados: LMS `808879f6e2`, pagamentos
  `a799f461c8`; `drush cr`, `updatedb:status` e `config:status` passaram após
  cada push.
## Handoff — remoção autorizada do tema Stable 9 — 2026-11-03

- Removido `stable9` de `config/sync/core.extension.yml`, commit `1f7ac3348c` (`config: remove Stable 9 do Config Sync`), enviado a `origin/dev`.
- Runtime `aculta_runtime` (Drupal 11.5.0-beta1): snapshot antes da importação em `$SUITE_OUT/runtime-snapshots/aculta_runtime-before-stable9-uninstall-20261103.sql`, 64,433,105 bytes, modo 0600, SHA-256 `3dd1d7f02621e813011b578b4104de32ad1a44535bb5615ccce0e149036f24e2`.
- Preview de `config:import --diff` continha somente a remoção de `stable9`; importação concluiu. `drush cr` PASS. `updatedb:status`: sem atualizações; `config:status`: sem diferenças; temas ativos restantes: Claro, Bootstrap 8.x-3.45 e ACULTA420.
- `core:requirements` deixou de reportar Stable 9; permanece o aviso de Claro depreciado. Search e Text With Summary Field também seguem como módulos depreciados instalados; não foram desinstalados nesta entrega.
- Próximo: prosseguir no trabalho aberto do ACULTA420 com arquivos locais já modificados, revisar diffs por escopo e validar antes de qualquer commit. Preservar as alterações alheias existentes.
## Handoff — inventário do próximo bloqueio Drupal 12 — 2026-11-03

- Estado do Runtime após Stable 9: `updatedb:status` sem atualizações; `config:status` sem diferenças. Nada foi escrito no Runtime nesta fase.
- O Core permanece em `11.5.0-beta1`, fixado junto com `drupal/core`, `core-recommended`, scaffold, project-message, recipe-unpack e `core-dev` no `composer.json`. PHP é 8.5.12; Composer local é 2.8.8.
- A consulta Composer para `drupal/core 12.0.0-beta2` identificou bloqueios em dependências contrib ainda limitadas a `^11`, entre elas Commerce/Address, Bootstrap, CKEditor5 Plugin Pack, Tool/MCP, Webform, Simple OAuth, Search/Media e várias outras. O Core 12 também requer Symfony 8.1, Guzzle 8 e outras versões transitivas que precisam ser resolvidas em conjunto. Não houve mudança no `composer.json` nem no `composer.lock`.
- O tema Bootstrap instalado é `drupal/bootstrap` 3.45.0 (`8.x-3.45` no Drupal); os metadados Composer dessa release não aceitam Drupal 12. A release 5.0.2 também ainda declara apenas Drupal 10/11; a branch 5.0.x-dev declara `>11.2`, mas representa outra linha de framework e não foi instalada, para preservar a decisão explícita do responsável sobre a base Bootstrap 8.x. Necessário validar compatibilidade real e adaptar conforme essa decisão antes de resolver Core.
- A documentação Drupal consultada lista `12.0.0-beta2` e recomenda PHP 8.5+ e Composer 2.9.3. Como o alvo ainda é beta, atualizar primeiro o conjunto Composer em ambiente isolado/dry-run e validar os módulos; sincronizar Core no Runtime somente após solver limpo, verificações e snapshot.
- Próximo passo: levantar versões `*-dev`/alpha/beta com metadados Drupal 12 para cada bloqueio direto, atualizar em lotes pequenos e executar `composer update --dry-run` até resolver o conjunto. Não desinstalar módulos ativos só para satisfazer o solver; avaliar substituição compatível e preservar funcionalidade.

## Correção do aviso de filtros de status em Views — 2026-11-03

- Removido o filtro redundante `status_extra` (`node_status`) das Views Conteúdo,
  Fórum — tópicos públicos e Revista — conteúdo editorial. O Core 11 reportava
  que os grants do `aculta_forum` já controlam acesso e tornam esses filtros
  inefetivos.
- Validação YAML focal das três Views passou; a importação de Config Sync
  atualizou somente essas três Views. `updatedb:status` e `config:status` ficaram
  limpos, e `core:requirements` deixou de listar `node_status_filter`.
- Snapshot de recuperação pós-import: `$SUITE_OUT/runtime-snapshots/aculta_runtime-after-node-status-filter-fix-20261103.sql`,
  54.082.293 bytes, SHA-256 `246cebc28f16da9496f951120047eb9cc40c67b7b377f7784c90ffbb77bdb8f5`,
  modo 0600. O snapshot foi criado após a importação, pois `config:import --diff`
  efetua a importação neste Drush.

## Continuação — Views administrativas fornecidas pelo ACULTA LMS — 2026-11-03

- Incluídas `courses_admin` e `classes_admin` em `aculta_lms/config/install`, mantendo-as em paridade com Config Sync e preservando as permissões de criação. A navegação continua curso-primeiro: a lista global de turmas não foi adicionada ao menu.
- Adicionada a classe CSS vazia padrão nas tabelas das duas Views para evitar a atualização/depreciação de configuração removida no Drupal 12.
- **Validações:** Unit `CourseViewsConfigTest` 1/24 PASS; Kernel `AcultaLmsInstallTest` 7/302 PASS em `aculta_simpletest` (17 depreciações upstream); `git diff --check` PASS. PHPCS nos dois arquivos de teste encontrou 1 erro preexistente de `unserialize()` na linha 168 de `AcultaLmsInstallTest`; a checagem não foi limpa.
- **Runtime:** snapshot anterior à importação parcial em `$SUITE_OUT/runtime-snapshots/aculta_runtime-before-lms-views-config-20261103.sql`, 65.270.522 bytes, modo 0600, SHA-256 `982c9691ef79f10459bc9fc3d246ebd10872e30a70788b45c8999891ffa2615a`. Importadas somente as duas Views; `drush cr` passou, `updatedb:status` sem atualizações e `config:status` sem diferenças. `core:requirements` não indicou falha relacionada a estas Views.
- **Arquivos:** configurações Sync e `config/install` de cursos/turmas, `aculta_lms.info.yml`, CHANGELOG, testes Unit/Kernel e este handoff. Publicação e sincronização de código ainda pendentes.

## Continuação — regressão de upload privado de resposta LMS — 2026-11-03

- O código já conectava `managed_file` ao payload ACULTA; README continha texto obsoleto afirmando que o fluxo estava pendente. Atualizei a documentação e acrescentei cobertura real de Kernel para o formulário: arquivo privado do próprio aluno gera resposta com referência no payload, torna-se permanente e registra uso; PNG com conteúdo falsificado é recusado sem resposta.
- **Validações:** `AnswerReviewWorkflowKernelTest` 3 testes/83 assertions PASS em `aculta_simpletest`, 14 depreciações upstream; PHPCS Drupal/DrupalPractice, PHP lint do teste e `git diff --check` PASS.
- **Runtime:** sem alteração de configuração ou dados; conferir `updatedb:status`/`config:status` antes do push, rebuild após publicação. Runtime não recebeu fixture nem arquivo.
- **Arquivos:** `AnswerReviewWorkflowKernelTest.php`, `aculta_lms/README.md`, `CHANGELOG.md`, `aculta_lms.info.yml`, status YAML do LMS, linha correspondente do README raiz e este handoff.

## Continuação — atualização UI Patterns para Drupal 12 — 2026-11-03

- Atualizado `drupal/ui_patterns` para `2.0.x-dev` (commit upstream `a7e9c28c522a5b0c8b71c731cab5482d60164e1e`) e salvo em `patches/aculta_patcher/ui_patterns_drupal_static_deprecation.patch` um patch reprodutível que troca quatro chamadas `drupal_static()` pelo cache de memória do Drupal.
- Manifesto e `patches.lock.json` atualizados; `composer patches-repatch` removeu/reinstalou os pacotes e reaplicou o patch de `drupal/ui_patterns` com sucesso.
- **Validações:** lint PHP dos três arquivos alterados PASS; `rg drupal_static` nos arquivos de UI Patterns sem ocorrências; Composer validate válido com avisos de constraints exatas existentes/dev; cache rebuild PASS; Upgrade Status do `aculta420` sem problemas conhecidos. Não foram executados testes automatizados.
- **Runtime/publicação:** `updatedb:status` sem pendências; `config:status` aponta cinco Views de Commerce diferentes, alterações preexistentes fora desta entrega. Não foram importadas. O patch não altera schema nem configuração; após o push, sincronizar somente o código no Runtime.

## Continuação — remove atualização automática de classes de tabela em Views Commerce — 2026-11-03

- A suíte Kernel de pagamentos revelou a depreciação do Core para `commerce_order_item_table`, `commerce_order_item_table_admin`, `commerce_orders`, `commerce_user_orders` e `commerce_order_payments`: o updater do Views adicionava `class: ''`, transformação removida no Drupal 12. Declarei o valor diretamente nas cinco Views do Config Sync.
- **Validações:** cinco YAMLs validados; import parcial confirmou somente essas cinco Views. `drush cr` executado; `updatedb:status` sem updates; `config:status` sem diferenças; `drush config:get` confirmou classe vazia nas cinco definições ativas.
- **Runtime:** snapshot anterior à importação `$SUITE_OUT/runtime-snapshots/aculta_runtime-before-commerce-view-table-classes-20261103.sql`, 69.200.703 bytes, modo 0600, SHA-256 `c20cb2eb73d22f485b3c5da3d6f501ef7eff436dcef790079516faf60c9f2acf`. Nenhum pedido, pagamento ou conteúdo alterado.
- A suíte Mercado Pago foi revalidada: Unit 258/1.156 PASS; Kernel 4/152 PASS em `aculta_simpletest`, com 72 depreciações do Core/contrib; nenhuma chamada externa.
- **Arquivos:** cinco Views Commerce em `config/sync` e este handoff. Commit/push desta correção ainda pendentes.

## Continuação — descoberta automática de patches no ACULTA Patcher — 2026-11-03

- **Causa:** `patches-aculta.json` e `composer.json` continham patches Composer, mas a tela administrativa lista entidades `aculta_patch`. A atualização manual do manifesto de UI Patterns não criou seu registro, por isso não aparecia na coleção.
- **Implementação:** `PatchManifestImporter` descobre entradas locais de `patches-aculta.json` ao carregar a coleção, importa registros ausentes de forma idempotente e preserva diffs já editados na UI usando hash de origem. `composer.json` deixou de manter uma lista paralela de patches; os cinco patches atuais foram centralizados no manifesto administrado pelo ACULTA. Schema/config exportam `source_url` e `source_hash`.
- **Runtime:** snapshot anterior à criação/atualização de entidades em `$SUITE_OUT/runtime-snapshots/aculta_runtime-before-patcher-autodiscovery-20261103.sql`, 73.666.006 bytes, modo 0600, SHA-256 `7124a3a18ffeca146a93db66d12a3a9ee38f76ab85fab2bdfe0e3273b9ca88ab`. Importados quatro registros e associados metadados ao registro H5P; exportados seletivamente os cinco itens `aculta_patcher.patch.*` para Config Sync. A coleção renderizada por Drush mostrou 5 linhas; repetição importou 0 duplicatas. `aculta:patcher-export --check` validou 5 patches; `updatedb:status` sem pendências e `config:status` sem diferenças.
- **Validações:** Composer repatch aplicou os cinco patches; Composer validate válido com avisos de constraints exatas; PHP lint e PHPCS Drupal/DrupalPractice do Patcher PASS; `git diff --check` PASS; cache rebuild PASS. Testes automatizados e navegador não foram executados.
- **Bloqueio não relacionado corrigido:** o cache rebuild encontrou conflito de propriedade herdada `$routeMatch` em dois formulários LMS que ainda estavam não rastreados; renomeei apenas a propriedade local para `$currentRouteMatch` em `LessonAuthoringForm.php` e `ActivityAuthoringForm.php`. Esses arquivos continuam fora do commit do Patcher.
- **Arquivos da entrega:** módulo `aculta_patcher` (importer, entidade, list builder, schema, serviços, README, CHANGELOG e versão); `composer.json`/lock, `patches-aculta.json`, `patches.lock.json`, quatro patches; cinco itens de Config Sync; status do componente, índice README e este handoff. Commit `708a9bf1ba` publicado em `origin/dev`; Runtime atualizado no mesmo checkout.

## Continuação — operações de ativação no ACULTA Patcher — 2026-11-03

- O dropdown mantém `Editar` como ação principal e apresenta `Desabilitar` nos registros ativos ou `Habilitar` nos inativos. Cada ação abre formulário ConfirmForm POST, exige `administer aculta patches` e redireciona à coleção; o estado vale para o próximo build após exportação.
- Rotas validadas no Runtime: `/painel-administrativo/configuracoes/desenvolvimento/patches-aculta/{aculta_patch}/habilitar` e `/desabilitar`. A renderização com contexto administrativo mostrou `Editar, Desabilitar` e, em estado inativo simulado sem salvar, `Editar, Habilitar`.
- **Validações:** cache rebuild, PHP lint, PHPCS Drupal/DrupalPractice, `updatedb:status` sem pendências, `config:status` sem diferenças. Não houve escrita de configuração/dados; os testes e o navegador não foram executados.
- **Arquivos:** list builder, entidade (templates de links), `AcultaPatchStatusForm`, rotas, versão/CHANGELOG/README do módulo, status e índice de componentes, e este handoff. Commit `3f04891c21` publicado em `origin/dev`; Runtime permanece no mesmo HEAD.

## Continuação — correção do HTTP 500 ao desabilitar patch — 2026-11-03

- **Causa confirmada no watchdog:** `AcultaPatchStatusForm::getQuestion()` chamava `getParameter()` em `routeMatch` nulo durante a renderização do `ConfirmFormBase`.
- **Correção:** `buildForm()` agora prepara rótulo e ação com os argumentos de rota já recebidos; a pergunta e o botão de confirmação usam esse estado escalar. Não houve alteração no patch ativo durante a reprodução.
- **Validações:** formulários de desabilitar e habilitar renderizados por Form API com pergunta/botão corretos; patch H5P continuou ativo no banco; PHP lint e PHPCS Drupal/DrupalPractice PASS; `drush cr` PASS; `updatedb:status` sem updates; `config:status` sem diferenças. Nenhum teste automatizado foi executado.
- **Arquivos:** `AcultaPatchStatusForm.php`, versão/CHANGELOG/status do `aculta_patcher`, índice README e este handoff. Commit `d0afac02f4` enviado a `origin/dev`; Runtime no mesmo HEAD.
## Continuação — estrutura de cursos no Ganjalab Educador — 2026-11-03

- **Implementado:** o Studio apresenta a ação “Editar estrutura” nos cursos geridos e o `aculta_lms` entrega a página de aulas/atividades, formulários Form API para criar/editar aulas e atividades e serialização validada de `answer_config` compatível com a submissão do estudante. A permissão `grade students` é contextual ao curso; curso→aula→atividade é revalidado em acesso e submit. Atividade com resposta existente fica bloqueada para edição, protegendo o histórico de avaliação.
- **Arquivos principais:** `web/modules/custom/aculta_portal/modules/aculta_lms/src/{Access/CourseAuthoringAccessCheck.php,Controller/CourseStructureController.php,Form/ActivityAuthoringForm.php,Form/LessonAuthoringForm.php,Learning/ActivityConfiguration.php}`, serviços e testes Unit do LMS; rotas, controller e testes Unit do `aculta_studio`; README/CHANGELOG/manifests/status LMS, Studio e Portal; `docs/portal/STUDIO-LMS-COURSE-BUILDER-VISUAL.md`.
- **Validações:** Unit LMS 247/880 PASS; Unit Studio 67/679 PASS; PHPCS Drupal/DrupalPractice focal, PHP lint e `git diff --check` PASS. `validate-project-status-index.php` PASS (53 componentes); gate Drupal 11+ PASS (1.202 verificações). Gate módulo/tema acusa classes preexistentes em `aculta_lms/AnswerReviewForm.php` e `aculta_paths/SurfaceListController.php`, fora do escopo. Suíte visual Studio não executada: ambiente sem `DISPLAY` e `SUITE_OUT`.
- **Runtime:** snapshot anterior à importação em `$SUITE_OUT/runtime-snapshots/aculta_runtime-before-ganjalab-course-structure-20261103.sql.gz.gz`, 7.702.164 bytes, modo 0600, SHA-256 `12b6e59abeca1de1badca28372b2253b06be04a8b7b4f14aca140002888cfb30`. `updatedb` concluiu sem updates; `config:import` sincronizou o status do patch Social API. `drush cr` PASS; rotas `aculta_lms.course_structure`, `lesson_add` e `activity_add` confirmadas; `updatedb:status` sem updates e `config:status` limpo. O Drush emitia aviso para cinco post-updates do Group removidos na linha 2.0. Após leitura do estado, dependências computadas e armazenadas coincidem em 2 GroupTypes/7 RelationshipTypes, nenhum papel com `administer group` carece de `access group overview`, as duas exibições da View `group_members` têm `route.group` e `user.group_permissions`, e `groups_revision`/`groups_field_revision`/`revision_uid` existem; `aculta_portal` está em schema 11010 e a coluna legada `revision_user` não existe. Com esses efeitos confirmados, registrei os cinco nomes via `UpdateRegistry::registerInvokedUpdates()` sem executar código legado contra Group 4. Snapshot anterior à reconciliação: `$SUITE_OUT/runtime-snapshots/aculta_runtime-before-group-post-update-registry-reconcile-20261103.sql.gz`, 6.117.396 bytes, modo 0600, SHA-256 `286702b850cf14188a8cb756a1cd285023718bd135f41a716f438e73ddbaa855`. `updatedb:status` e `config:status` seguem limpos; o aviso de Group não apareceu mais em `core:requirements`. Visual do Educador pendente.
- **Próximos passos:** commit `49cc9757a4` enviado em fast-forward para `origin/dev` e runtime sincronizado. Continuar pela auditoria dos post-updates Group/Drupal 12 e pelas pendências de pagamentos; não declarar a frente geral concluída.

## Continuação — reconcilia documentação do Ganjalab Educador — 2026-11-03

- **Escopo verificado:** as Views `courses`, `courses_catalog`, `courses_admin` e `classes_admin` já pertencem ao `aculta_lms`: há quatro YAMLs em `config/install`, cópias equivalentes no Config Sync, commit próprio das Views públicas e commit próprio das administrativas. `CourseViewsConfigTest` compara os quatro arquivos; o Kernel de instalação verifica o carregamento. Não restam outras Views de cursos/turmas no Config Sync com essas tabelas-base.
- **Ajuste realizado:** `web/modules/custom/aculta_portal/modules/aculta_lms/docs/ROADMAP.md` deixou de afirmar que o Course Builder ainda não foi implementado. Registra a autoria inicial de aulas/atividades publicada em `0.1.0-dev.183`, separando limites pendentes (ordenação, exclusão e integração visual completa).
- **Verificação:** `git diff --check` no arquivo alterado passou. Sem alteração de código, configuração Drupal, dados ou Runtime nesta passagem.
- **Auditoria Drupal 12:** Upgrade Status já tinha um processo concorrente analisando `aculta_portal`; para evitar análise duplicada e disputa de CPU, a execução global iniciada nesta passagem foi interrompida após começar pelos módulos MCP. Resultado global não obtido. Composer informa que `drupal/entity` e `drupal/entity_reference_revisions` têm branches `1.x-dev` com `^12`, mas `drupal/address` (2.0.4 e branch 2.1.x-dev disponíveis) ainda exige Core `^9.5 || ^10 || ^11`. A simulação de atualização também foi bloqueada pela árvore Composer em edição: `composer.json` declara PHPUnit/PHPStan que divergem do lock e o Core beta travado não satisfaz `minimum-stability: stable`. Não alterei Composer/lock nesta etapa.
- **Ambiente confirmado:** host `toca`, checkout `dev`, Runtime `aculta_runtime`, Drupal 11.5.0-beta1 e PHP 8.5.12. Nenhum `updatedb`, import de configuração ou alteração persistente foi executado nesta passagem.
- **Próximos passos:** aguardar a análise de `aculta_portal` em andamento; completar Upgrade Status e Rector sem escrever sobre arquivos modificados por terceiros; retomar a resolução das dependências Composer depois de reconciliar o manifesto e o lock, priorizando branches Drupal 12 compatíveis e registrando bloqueios upstream.

## Continuação — resultados Upgrade Status/Rector — 2026-11-04

- **Upgrade Status:** a análise Checkstyle de `aculta_portal` concluiu em processo já ativo. Foram 51 diagnósticos em 28 arquivos: 36 recomendações de assertions PHPUnit, seis tentativas de mockar `DomainNegotiatorInterface::setActiveDomain()`, uma tentativa de mockar `AvisoDigestProviderInterface::discardDigestItems()`, dois avisos de propriedades privadas com `DependencySerializationTrait`, quatro referências no teste ao wrapper `UploadedAnswerAccessCheck` explicitamente mantido como compatibilidade legada, e dois avisos por extensões/bibliotecas opcionais não instaladas. Esses resultados não são 51 deprecações Drupal 12. O artefato Checkstyle permanece fora do repositório.
- **Rector:** `vendor/bin/rector process --dry-run --output-format=console` percorreu 216 arquivos dos caminhos configurados, sem propor alterações customizadas. O CLI emitiu uma depreciação PHP 8.5 no contrib `webform_score` (`$items` implicitamente nullable), não em código ACULTA.
- **Limite da varredura:** a análise global iniciada nesta sessão foi encerrada para evitar duplicar o processo de `aculta_portal`; `aculta_mcp` e `aculta_mcp_client` chegaram a ser processados, mas não há relatório global completo. Não apliquei correções automáticas por serem sugestões de estilo/testes ou contratos falsos em mocks, não correções de API Drupal comprovadas.
- **Bloqueio Composer:** o manifesto de trabalho exige Core `^11.5`, enquanto o lock mantém `11.5.0-beta1`; além disso, as constraints de PHPStan/PHPUnit no manifesto não coincidem com as versões do lock. A simulação parcial de atualização falha por essa divergência e `minimum-stability: stable`. A metadata consultada confirma `drupal/entity` e `drupal/entity_reference_revisions` em `1.x-dev` com `^12`; `drupal/address` 2.0.4 e 2.1.x-dev seguem sem `^12`. Não tentei atualizar pacotes nesta passagem.
- **Próximos passos:** reconciliar composer.json/lock sem descartar as alterações presentes; mapear versões/branches D12 dos pacotes restantes; fechar correções de APIs removidas com testes focais; repetir Upgrade Status completo e validar no Core 12 quando houver pacote executável e closure de dependências compatível.

## Continuação — cobertura completa dos projetos custom e bloqueios Core 12 — 2026-11-04

- **Upgrade Status final por projeto:** executei `aculta_mcp`, `aculta_mcp_client`, `aculta_portal` e `aculta420` com `--skip-existing`; Checkstyle reportou 42 diagnósticos em 21 arquivos. São 36 recomendações de estilo PHPUnit, quatro usos no teste que verifica o wrapper ACULTA deliberadamente deprecated e dois avisos de bibliotecas pertencentes a extensões opcionais não instaladas. Não houve achado de API removida do Drupal em código custom de produção nesta rodada. O exit code 3 corresponde aos diagnósticos; não é indicação de falha de execução do Drupal.
- **Rector:** permanece sem alterações propostas em 216 arquivos; a única depreciação emitida foi de `webform_score` contrib em PHP 8.5.
- **Core 12:** Composer tem `drupal/core` 12.0.0-beta2 disponível. Dry-run de resolução em manifesto temporário, sem tocar arquivos do repositório, parou em dois bloqueios: o único `mcp_server_oauth` disponível requer `drupal/core ^11`; Commerce 3.x-dev também requer somente `^10.3 || ^11` (não há branch 4.x nessa metadata). O Core 12 beta2 requer Symfony 8.1, Guzzle 8, PSR-7 3 e PHPUnit 12.5, enquanto o lock atual contém Symfony 7.4, Guzzle 7, PSR-7 2 e PHPUnit 11. Nenhum pacote ou dado foi atualizado.
- **Runtime:** `updatedb:status` sem updates e `config:status` sem diferenças depois das análises; o Runtime continua em Drupal 11.5.0-beta1/PHP 8.5.12. Não sincronizar Core 12 até a closure resolver e os testes passarem.
- **Próximos passos:** decidir substituição/remoção de contrib sem branch D12 após inventário de uso e contratos; acompanhar versão Drupal 12 de Commerce/MCP OAuth; então atualizar o conjunto Core/Symfony/dependências e corrigir eventuais incompatibilidades funcionais.
## Handoff — Course Builder e materiais LMS — 2026-11-04

- **Objetivo:** continuar a implementação do Course Builder no Ganjalab Educador; o responsável pediu para criar as lacunas sem interromper o trabalho. As Views públicas/administrativas já pertencem ao `aculta_lms` em `config/install` e têm cópias correspondentes em `config/sync`.
- **Feito nesta etapa:** prévia privada confirmada no Firefox; materiais no Course Builder incluem leitura, galeria, vídeo e documentos Group por Entity Access nos owners. Editor contextual reutiliza os widgets existentes e revalida `CourseAuthoringAccessCheck` no GET/POST. Views públicas e administrativas continuam sob `aculta_lms`; nenhum storage paralelo. Implementada reordenação por arraste para aulas e atividades, com fallback pelos controles de teclado/sem JavaScript e validação de permissão, ancestralidade, permutação completa, lock e transação no serviço. Dev.26 implementa criar/editar PDF privado no contexto do curso usando os formulários Media/Group existentes, Entity Access e `update own` (sem `update any`); testes Unit e de ownership das rotas passam.
- **Arquivos:** `web/modules/custom/aculta_portal/modules/aculta_lms_resources/` (builder, form contextual, hooks, update 8002 e edição de documentos); `web/modules/custom/aculta_portal/modules/aculta_studio/aculta_studio.routing.yml`; `web/modules/custom/aculta_portal/modules/aculta_lms/src/Controller/CourseStructureController.php`, `src/Form/CourseStructureOrderForm.php`, `src/Order/CourseStructureOrderManager.php`, `js/course-structure-sort.js`, biblioteca/serviços/CSS, README, CHANGELOG e info; configuração de field Group sob `config/sync/`.
- **Validação desta entrega:** Unit `aculta_lms_resources` 20/20 (132 assertions); `StudioIdentityRoutingTest` 4/4 (270 assertions); PHPCS Drupal/DrupalPractice e PHP lint focais PASS. Runtime preflight: `updatedb:status` sem updates; `config:status` mostra somente `group.role.lms_course-teacher` diferente. Import, smoke autenticado e homologação visual ainda pendentes.
- **Validação:** Unit Resources 14/14 (67 assertions); Kernel CourseResourceBuilder 4/4 (67 assertions); essas execuções são das entregas anteriores. Nesta etapa, PHP lint, Node `--check`, parse Symfony YAML e `git diff --check` passaram; não executei testes Drupal. O navegador não foi aberto porque a sessão atual não tem `DISPLAY`.
- **Commits enviados:** `92ab862659` lista materiais; `1bb2b21dd8` cria editor contextual; `ca1d145b0d` resolve colisão da propriedade `FormBase`; `4eadf5e8ff` corrige import do `EntityFormDisplay`; `7b8352e703` adiciona update reparador; `662bfd821d` sincroniza field/widget; `bd0eeead67bd819501a6f9321167c36095fdb31e` implementa reordenação acessível. Todos enviados a `origin/dev` por fast-forward.
- **Runtime:** `toca`, Drupal 11.5.0-beta1/PHP 8.5.12, banco `aculta_runtime`, HEAD `bd0eeead67bd819501a6f9321167c36095fdb31e`. Cache rebuild passou; `updatedb:status` informa nenhuma atualização pendente e `config:status` nenhuma diferença. Nenhuma alteração de banco, configuração ou conteúdo ocorreu nesta etapa. Snapshots históricos das atualizações de materiais: `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-lms-resources-8002-20261104.sql.gz.gz`, SHA-256 `711a0afe4d336a312f1b830e7b935fb66d0c2f9c8ee277e62bd07f7a5fee3f95`; `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-lms-videos-sync-20261104.sql.gz.gz`, SHA-256 `a06031b969baf85fa2291517f33d32e464e27e2b5d580de64f0dd3f9ec60b333`.
- **Pendências ativas:** validar reordenação no navegador autenticado; investigar `ckeditor5_plugin_pack/drupal_powered_by` quando a árvore Core/Plugin Pack Drupal 12 estiver ativa (1.5.6 usa DLL e declara Drupal 10/11; 2.0.0-beta1 é Drupal 12); revisão visual de PDF/H5P pelos owners. Depois de fechar LMS, continuar pagamentos e revisar `updatedb` um item de cada vez.
- **Retomar lendo:** este handoff, `docs/portal/STUDIO-LMS-COURSE-BUILDER-VISUAL.md`, `web/modules/custom/aculta_portal/modules/aculta_lms_resources/README.md` e `docs/modules/ACULTA-LMS-ROADMAP.md`.

## Handoff — migração ACULTA420 para Bootstrap5 — 2026-11-04

- **Objetivo:** migrar o tema ACULTA420 para o tema base `bootstrap5` do pacote mantido em `5.0.x-dev`, sem alterar Composer/lock nem atualizar Drupal.
- **Commits publicados:** `141456311d` migra o tema; `5128c30975` habilita `bootstrap5` em `config/sync/core.extension.yml`. HEAD remota confirmada: `5128c3097544164122f54de598dd8ddb98adbb65`.
- **Feito:** confirmou-se no Runtime o pacote instalado `drupal/bootstrap5 5.0.x-dev` (source `c2292008a9a54d30f321b221e0df4a645821b6fe`), framework 5.3.8 e libraries `bootstrap5/base`, `bootstrap5/messages`, `bootstrap5/bootstrap5-js-latest`, `bootstrap5/global-styling`. Atualizados base theme/libraries, markup de grid, menu, dropdown, tabs, Offcanvas, alertas, cartão SDC, tokens de UI Portal e eventos JavaScript. Adicionada matriz `web/themes/custom/aculta420/docs/bootstrap5-migration-matrix.md`; inventário TSV de libraries atualizado. jQuery de Core, pagamentos e Fórum/H5P permanece.
- **Divergências:** `origin/dev` não declara `drupal/bootstrap5` em `composer.json` nem `composer.lock`; o checkout compartilhado contém alterações Composer não commitadas que adicionam o pacote em `5.0.x-dev`, junto de outras alterações extensas. Preservá-las; não copiar nem alterar manifests/lock nesta migração. Runtime tem o tema instalado em `5.0.x-dev` (Composer confirmou source `c2292008a9a54d30f321b221e0df4a645821b6fe`), mas `core.extension` ativo mantém `theme.bootstrap5: null`; o sync publicado agora adiciona `bootstrap5: 0`. `updatedb:status` e `config:status` read-only passaram antes de sincronizar o código. Nenhum arquivo/dado/config Runtime foi alterado; sincronização foi adiada porque o checkout compartilhado está sujo em muitos arquivos, inclusive no tema e Composer, e uma sobreposição poderia perder trabalho paralelo.
- **Validações:** SDC schema PASS (55 componentes/764 checks); browser validators PASS (21); institution content PASS (37); B.4 shell fixtures PASS (13); Studio identity PASS (33); PHP lint focais, `node --check`, parse YAML focais, `composer validate` (warnings de constraints exatas existentes) e `git diff --check` PASS. `composer show drupal/bootstrap5` falha no worktree limpo baseado no remoto porque o pacote não consta do lock; no Runtime passou e confirmou `5.0.x-dev`. Design Foundations e fixtures falham em duas regras pré-existentes do contrato de `tokens.css` relativas a blocos/escopos de fidelidade Studio; LMS skin falha porque os arquivos `web/modules/contrib/lms` não existem na worktree e suas classes/versão esperadas estão desatualizadas. Foundation Runtime gate bloqueado porque a configuração ativa ainda não habilita bootstrap5; Unit/Kernel, PHPCS, lint CSS/JS, browser Runtime e visual ainda pendentes.
- **Próximos passos:** resolver no fluxo responsável a declaração ausente de `drupal/bootstrap5` no branch sem alterar constraint/versão; combinar isolamento para sincronizar Runtime sem sobrescrever as alterações locais sujas; fazer snapshot se `config:status` apontar pendência após aplicar código/config; executar import/rebuild conforme regra Runtime e então Foundation, Unit/Kernel, PHPCS, lint CSS/JS e browser por purpose. A matriz visual e consumers reais seguem pendentes.
- **Retomar lendo:** esta seção; `web/themes/custom/aculta420/docs/bootstrap5-migration-matrix.md`; `web/themes/custom/aculta420/docs/development.md`; `web/themes/custom/aculta420/docs/versioning.md`; arquivos instalados `bootstrap5.info.yml` e `bootstrap5.libraries.yml` no checkout Runtime.

## Handoff — Course Builder e materiais LMS — 2026-11-04

- **Objetivo:** continuar a implementação do Course Builder no Ganjalab Educador; o responsável pediu para criar as lacunas sem interromper o trabalho. As Views públicas/administrativas já pertencem ao `aculta_lms` em `config/install` e têm cópias correspondentes em `config/sync`.
- **Feito nesta etapa:** prévia privada confirmada no Firefox; materiais no Course Builder incluem leitura, galeria, vídeo e documentos Group por Entity Access nos owners. Editor contextual reutiliza os widgets existentes e revalida `CourseAuthoringAccessCheck` no GET/POST. Views públicas e administrativas continuam sob `aculta_lms`; nenhum storage paralelo. Implementada reordenação por arraste para aulas e atividades, com fallback pelos controles de teclado/sem JavaScript e validação de permissão, ancestralidade, permutação completa, lock e transação no serviço. Dev.26 implementa criar/editar PDF privado no contexto do curso usando os formulários Media/Group existentes, Entity Access e `update own` (sem `update any`); testes Unit e de ownership das rotas passam.
- **Arquivos:** `web/modules/custom/aculta_portal/modules/aculta_lms_resources/` (builder, form contextual, hooks, update 8002 e edição de documentos); `web/modules/custom/aculta_portal/modules/aculta_studio/aculta_studio.routing.yml`; `web/modules/custom/aculta_portal/modules/aculta_lms/src/Controller/CourseStructureController.php`, `src/Form/CourseStructureOrderForm.php`, `src/Order/CourseStructureOrderManager.php`, `js/course-structure-sort.js`, biblioteca/serviços/CSS, README, CHANGELOG e info; configuração de field Group sob `config/sync/`.
- **Validação desta entrega:** Unit `aculta_lms_resources` 20/20 (132 assertions); `StudioIdentityRoutingTest` 4/4 (270 assertions); PHPCS Drupal/DrupalPractice, PHP lint focal e `git diff --check` PASS. `drush cr` PASS; as rotas novas de documento resolvem; `updatedb:status` sem updates e `config:status` sem diferenças. Smoke autenticado e homologação visual permanecem pendentes.
- **Validação:** Unit Resources 14/14 (67 assertions); Kernel CourseResourceBuilder 4/4 (67 assertions); essas execuções são das entregas anteriores. Nesta etapa, PHP lint, Node `--check`, parse Symfony YAML e `git diff --check` passaram; não executei testes Drupal. O navegador não foi aberto porque a sessão atual não tem `DISPLAY`.
- **Commits enviados:** `92ab862659` lista materiais; `1bb2b21dd8` cria editor contextual; `ca1d145b0d` resolve colisão da propriedade `FormBase`; `4eadf5e8ff` corrige import do `EntityFormDisplay`; `7b8352e703` adiciona update reparador; `662bfd821d` sincroniza field/widget; `bd0eeead67bd819501a6f9321167c36095fdb31e` implementa reordenação acessível; `57eb65d4cf` adiciona testes; `531cd670aa` adiciona edição contextual de PDFs. Todos enviados a `origin/dev` por fast-forward.
- **Runtime:** `toca`, Drupal 11.5.0-beta1/PHP 8.5.12, banco `aculta_runtime`, HEAD `531cd670aa`. Snapshot pré-import em `$HOME/aculta-suite-out/runtime-snapshots/aculta_runtime-before-lms-documents-20261104/runtime.sql`, 95.674.052 bytes, modo 0600, SHA-256 `d69f56a66ea60841f255295e47c039e1f6d86ba6f756f2eeac2d638780c2a098`. Importada somente `group.role.lms_course-teacher`; `drush cr` PASS, `updatedb:status` sem pendências e `config:status` sem diferenças. Nenhum conteúdo foi alterado. Snapshots históricos: `aculta_runtime-before-lms-resources-8002-20261104.sql.gz.gz` (SHA-256 `711a0afe4d336a312f1b830e7b935fb66d0c2f9c8ee277e62bd07f7a5fee3f95`) e `aculta_runtime-before-lms-videos-sync-20261104.sql.gz.gz` (SHA-256 `a06031b969baf85fa2291517f33d32e464e27e2b5d580de64f0dd3f9ec60b333`).
- **Pendências ativas:** validar reordenação no navegador autenticado; investigar `ckeditor5_plugin_pack/drupal_powered_by` quando a árvore Core/Plugin Pack Drupal 12 estiver ativa (1.5.6 usa DLL e declara Drupal 10/11; 2.0.0-beta1 é Drupal 12); revisão visual de PDF/H5P pelos owners. Depois de fechar LMS, continuar pagamentos e revisar `updatedb` um item de cada vez.
- **Retomar lendo:** este handoff, `docs/portal/STUDIO-LMS-COURSE-BUILDER-VISUAL.md`, `web/modules/custom/aculta_portal/modules/aculta_lms_resources/README.md` e `docs/modules/ACULTA-LMS-ROADMAP.md`.


## Handoff — revisão da fundação do tema aculta420_v2 — 2026-11-04

- **Objetivo:** revisar e fortalecer a Fase 1 de `web/themes/custom/aculta420_v2` para a arquitetura SDC do Drupal instalado, sem habilitar nem sincronizar o tema no Runtime.
- **Implementado:** removida a configuração e dependências do Vite e seus artefatos aninhados; CSS único em `src/styles/` com bibliotecas Drupal estáticas. O tema agora exige schemas SDC (`enforce_prop_schemas: true`), e `card-cultural.component.yml` usa JSON Schema nativo com props requeridas/opcionais e slots. Metadados declaram `core_version_requirement: ^11.5 || ^12`, conforme orientação do responsável: desenvolvimento no Core 11.5 com foco de compatibilidade no Drupal 12. Shell Twig foi alinhado aos placeholders e classes do Core; page layout mantém navegação fora do `<main>` e regiões help/breadcrumb/sidebar/footer. Adicionados tokens `surface` e texto de contraste.
- **Validação:** Symfony YAML dos metadados/bibliotecas/schema PASS; validator `Drupal\\Core\\Theme\\Component\\ComponentValidator` PASS com schema obrigatório; descoberta isolada do Core encontrou `aculta420_v2:card-cultural`; bibliotecas Drupal resolvem e seus arquivos CSS existem; `validate-aculta420-shell-contract.php` PASS (13 checks); `updatedb:status` sem atualizações; `config:status` sem diferenças; `git diff --check` PASS. `validate-aculta420-foundation.php` não conclui porque `web/themes/contrib/bootstrap5/bootstrap5.info.yml` está ausente neste checkout. O tema não foi habilitado e o Runtime não foi modificado.
- **Limitações/pendências:** não foi validada renderização HTTP nem descoberta com tema habilitado; não declarar fundação visual/runtime concluída. O diretório `node_modules/` existente foi apenas ignorado por `.gitignore`, preservando seus arquivos locais. Alterações Composer e `web/themes/custom/aculta420/`, `web/themes/custom/aculta_theme/` são de outra frente e foram preservadas.
- **Arquivos desta frente:** `web/themes/custom/aculta420_v2/.gitignore`, `aculta420_v2.info.yml`, `aculta420_v2.libraries.yml`, remoção de scaffold Vite/aninhado, `src/styles/tokens.css`, `templates/layout/html.html.twig`, `templates/layout/page.html.twig`, `components/card-cultural/card-cultural.component.yml`; este handoff.
- **Git/Runtime:** nenhum commit/push nesta revisão; nenhum cache rebuild, enable, updatedb ou config import foi executado.

### Complemento da revisão aculta420_v2 — layout e APIs Drupal — 2026-11-04

- A revisão adicional removeu `aculta420_v2.theme`, que continha apenas hooks preprocess vazios e dependia de APIs legadas de preprocess removidas no Drupal 12. `html.html.twig` passou a preservar `body_classes` do preprocess do Core em vez de reconstruí-las, mantendo classes adicionadas por integrações.
- A página preserva regiões `help` e `breadcrumb`; o grid tem área própria para essas regiões, e `.page__main` contém conteúdo e sidebar. Sidebar aparece como coluna somente a partir de 992 px e apenas quando presente, por classe condicional Twig sem dependência de `:has()`.
- Contraste computado dos pares usados: primary claro/branco 11.57:1; primary dark/escuro 5.24:1; texto escuro/amarelo warning 7.06:1; primary escuro/amarelo 7.10:1. Não houve alteração de tokens nesta rodada.
- Validações repetidas: YAML PASS; validator SDC estrito do Core PASS; shell contract 13 checks PASS; `git diff --check` PASS. Sem ativação, cache rebuild, render HTTP ou alteração de Runtime.

### Complemento da revisão aculta420_v2 — regiões e assets — 2026-11-04

- `.info.yml` agora declara as regiões `help` e `breadcrumb` que já eram renderizadas pelo `page.html.twig`; uma checagem contra a lista ativa do Core confirma que as seis regiões declaradas aparecem no template.
- Verificação read-only pelo `library.discovery`: todas as bibliotecas do tema foram resolvidas e todos os arquivos CSS referenciados existem. A discovery isolada do componente e o validator SDC estrito continuam PASS; o contrato shell continua PASS (13 checks); `git diff --check` PASS.
- Nenhuma ativação do tema, rebuild de cache, render HTTP ou alteração persistente do Runtime.

### Compatibilidade declarada aculta420_v2 — 2026-11-04

- A pedido do responsável, `web/themes/custom/aculta420_v2/aculta420_v2.info.yml` declara `core_version_requirement: ^11.5 || ^12`: o ambiente de desenvolvimento segue em Drupal 11.5.0-beta1, enquanto a implementação mira APIs compatíveis com Drupal 12. Parse YAML, busca por preprocesses legados/service locator/jQuery/Vite e shell contract (13 checks) passaram. Isto confirma o manifesto e a auditoria estática local; ainda não comprova execução do tema em Core 12.

### Complemento da revisão aculta420_v2 — compilação Twig — 2026-11-04

- Sidebar do shell agora entra em coluna a partir de 960 CSS px (largura mínima total necessária para conteúdo + 14rem de sidebar), com uma única regra progressiva e classe condicional do Twig; removida a repetição do grid em 1200px. Base continua uma coluna e sem overflow horizontal conhecido por configuração de colunas rígidas.
- Drupal Twig Environment compilou `html.html.twig`, `page.html.twig` e `card-cultural.twig` sem erro. Core SDC metadata validator PASS; shell contract 13 checks PASS; `git diff --check` PASS. Sem render HTTP/visual, ativação ou escrita Runtime.

### Consolidação mobile-first da fundação V2 — 2026-11-04

- **Mudanças:** `base.css` agora parte de padding fluido constante (sem media query max-width), usa propriedades lógicas, respeita preferência de movimento removendo smooth scroll global, limita imagens/vídeos/iframes e permite quebra de código longo; body usa `--color-surface`, foco usa `--color-primary`. Removidos tokens breakpoint que não podiam ser interpolados em media queries. O layout V2 mantém uma coluna como base e só cria sidebar em viewport amplo (960px), com min-width zero e classe condicional; não esconde conteúdo/operação.
- **Escopo visual escolhido:** fundação V2 e SDC card-cultural; nenhum consumer real ou theme ativo na worktree, portanto não foi declarada cobertura de browser/viewport nem aceite visual.
- **Validação:** Stylelint direto nos 4 CSS V2 PASS; gate SDC global PASS (55 componentes, 27 includes, 764 checks); Twig compile/strict SDC previamente PASS; shell contract PASS (13 checks); YAML e `git diff --check` PASS. `validate-aculta420-design-foundations.php` FAIL em 2 regras preexistentes de fidelidade Studio; `validate-module-theme-boundary.php` FAIL (6 classes LMS em módulos, fora do V2). O `npm run lint:css` do tema principal não cobre `src/styles/` do V2, portanto não foi usado como prova do V2.
- **Pendente:** captura Firefox ESR visível nas larguras da matriz e render real de consumer após instalação/ativação em ambiente autorizado; Drupal 12 não está instalado neste checkout. Nenhuma alteração Runtime.

## Encerramento da Fase 1 — fundação aculta420_v2 — 2026-11-04

- **Escopo concluído:** a fundação do tema novo `aculta420_v2` está publicada no commit `1ea7970ace` (mobile-first, tokens CSS, bibliotecas Drupal sem Vite, shell e SDC `card-cultural`). O tema declara `^11.5 || ^12`, sendo desenvolvido no Drupal 11.5 com foco de compatibilidade no Drupal 12. A Fase 2 não foi iniciada.
- **Runtime:** por solicitação do responsável, o tema novo é o default ativo no Runtime `aculta_runtime`; Bootstrap não foi instalado e não é dependência da V2. Snapshot anterior à ativação: `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-aculta420-v2-activation-20261104/runtime.sql`, 110.506.169 bytes, SHA-256 `1415da7dd954d69fad6854026d7c0ad4c44929cc718de091b739d47f256800d2`. `updatedb:status` não requer updates; `config:status` limpo após exportação; cache rebuild concluído.
- **Configuração versionada nesta entrega:** `system.theme` define `aculta420_v2`; `core.extension` registra a V2 habilitada; 36 configurações de posicionamento de blocos geradas pelo hook padrão do Core foram exportadas para o tema novo.
- **Smoke HTTP após ativação:** MAIN, Apoio, Revista, Wiki, Loja, Cursos, Fórum e Culturas retornaram 200; Conta retornou 302. LINKS segue 500 por template não encontrado `aculta-linkweed-landing.html.twig`, conforme watchdog 835633–835665; isso é um defeito isolado no módulo `aculta_linkweed`, fora do escopo da Fase 1. Os erros anteriores de templates Bootstrap5 são históricos; não houve instalação do pacote.
- **Validações da fundação:** schema SDC estrito e compilação Twig passaram; contrato shell (13 checks), gate SDC (55 componentes/764 checks), Stylelint nos CSS V2 e matriz visual mobile-first (320–1440 px, sem overflow no fixture) passaram. Os limites do fixture e os gates globais com falhas pré-existentes estão detalhados nas seções acima.
- **Estado Git preservado:** alterações Composer, `web/themes/custom/aculta420/`, e `web/themes/custom/aculta_theme/` não pertencem a esta entrega e não foram incluídas.
- **Próxima etapa:** iniciar Fase 2 somente em tarefa posterior, após o aceite da Fase 1; nenhum componente adicional foi iniciado neste encerramento.

## Implementação do catálogo SDC aculta420_v2 — 2026-11-04

- **Escopo:** implementação do catálogo solicitado da Fase 2 no tema novo, preservando a Fase 1. O Runtime continua usando `aculta420_v2`; nenhuma dependência Bootstrap/jQuery foi adicionada. A Fase 2 não está declarada como RC.
- **Componentes:** `button`, `badge`, `card-cultural`, `audio-player`, `hero-banner`, `navigation-bar`, `pagination`, `filter-chips`, `status-messages` e `map-popup`, cada qual com `.component.yml`, `.twig` e `.css`; navegação também tem JS Vanilla com `Drupal.behaviors.acultaNavigation` e `core/once`.
- **Integrações:** pager, status messages e node teaser agora adaptam templates Drupal para SDC. `card-cultural` usa `badge`; hero usa `button`. Menu mobile é drawer progressivo, Escape fecha e restaura foco, e o conteúdo segue visível sem JS.
- **Cores:** o modo automático por `prefers-color-scheme` não está habilitado. O hook `ColorModeHooks` lê a preferência já entregue pelo `aculta_portal` e coloca `data-theme="light|dark"`; o valor legado `auto` cai em claro. O seletor existente do Portal não foi removido nem redesenhado.
- **Documentação/versionamento:** criada `docs/architecture/ARQUITETURA-ACULTA420-V2.md`, `web/themes/custom/aculta420_v2/CHANGELOG.md` e `web/themes/custom/aculta420_v2/docs/roadmap.md`; versão marcada `1.1.0-dev.1`. O roadmap deixa revisões visuais/assistivas e integração efetiva do popup ao JS de `aculta_maps` como gates pendentes. Tiles CartoDB e endpoint de pontos seguem sem alteração por conflito com ADR-030/contratos de domínio.
- **Validações:** gate `drush scr scripts/validate-aculta420-v2-sdc.php` PASS (10 SDCs, schema obrigatório e render isolado); 15 Twig parse PASS; node teaser real de nó publicado PASS; YAML 12 arquivos PASS; PHP lint e PHPCS Drupal/DrupalPractice PASS; JS `node --check` PASS; CSS parse/mobile-first (13 folhas, nenhuma media query `max-width`) PASS; contraste dos pares de texto avaliados em claro/escuro ≥ 5.08:1; modos light/dark/auto fallback testados no hook; HTTP MAIN/Eventos/Revista 200 e MAIN emite `data-theme="light"`; `drush cr` PASS, `updatedb:status` sem updates e `config:status` limpo.
- **Limites:** teste visual mobile/dark com Firefox e revisão assistiva completa não foram executados; contraste calculado não substitui auditoria WCAG da página real. LINKS permanece com o erro independente já registrado no handoff de encerramento da Fase 1.
- **Arquivos:** `web/themes/custom/aculta420_v2/` (catálogo, tokens, hook, templates, versão/changelog/roadmap); `scripts/validate-aculta420-v2-sdc.php`; `docs/architecture/ARQUITETURA-ACULTA420-V2.md`; este handoff.
- **Entrega:** commit `ba2d92eabb` publicado por fast-forward em `origin/dev`; Runtime está no mesmo checkout/HEAD. Após o push, `drush cr` passou, `updatedb:status` não exige updates, `config:status` está limpo e MAIN/Eventos/Revista retornaram HTTP 200.
- **Pendências:** visual Firefox com tamanhos móveis/amplos nos dois modos de cor, revisão assistiva das páginas consumidoras e integração do SDC `map-popup` no JS Leaflet do owner `aculta_maps`. Não promover RC antes desses gates.

## Continuação — avaliação funcional de responsividade LMS, 2026-11-04

- **Escopo confirmado:** avaliar formulários e outros fluxos quanto à operação em telas estreitas, sem revisão estética.
- **Inspeção:** Form API do LMS (resposta, upload, autoria de aula/atividade, prazo e certificado) usa controles Drupal nativos; `course-structure.css` limita largura, permite shrink e mantém comandos de teclado. `answer-form.css` continha apenas seletores `#header-nav`/`.primary-nav__menu` específicos de Olivero, ausentes nos temas ACULTA V1/V2; removidos CSS, biblioteca e anexo pelo formulário. Kernel `AnswerReviewWorkflowKernelTest` cobre que resposta com arquivo não anexa essa folha.
- **Navegador somente leitura:** página inicial, jornada, aula e atividade passaram em 500 e 1.280 px (24/24); controles de resposta passaram no teclado (9/9), sem selecionar/enviar resposta. Upload foi utilizável de 320 a 1.280 px, mas em 320 px o Turnstile ultrapassou o viewport em 10 px; a partir de 390 px não houve overflow. Nenhuma análise de aparência, zoom ou leitor de tela foi feita.
- **Course Builder:** a rota autenticada `/ganjalab/educador/cursos/88/estrutura` inicialmente falhava ao renderizar por incompatibilidade de assinatura em `CourseStructureOrderForm::buildForm()`. O método agora segue `FormInterface` e lê os argumentos adicionais em `FormState::getBuildInfo()`. Unit focal 2/35 e suíte Unit LMS 270/1.024 PASS. Firefox autenticado confirmou controles/listas nas larguras de 320, 360, 390, 500, 768 e 1.280 px (31/31 baseline), sem overflow. Simulação DOM-only de arrasto reordenou aulas e atividades e habilitou os formulários de salvar sem submeter; o ajuste `min-inline-size: 0` nos itens evitou que a lista estoure horizontalmente.
- **Limite responsivo restante:** em 320 px, o fieldset do Turnstile continua causando overflow de 8–10 px em formulário de upload e após arrasto; 360 px ou mais não apresentam overflow após o ajuste. O widget é configurado globalmente no Runtime, fora do Config Sync; tamanho `compact` ou `flexible` mudaria todos os formulários protegidos, então não foi alterado nesta correção local. Não houve revisão de aparência.
- **Runtime:** confirmado em `toca`, Drupal 11.5.0-beta1, PHP 8.5.12, DB `aculta_runtime`; antes e depois da entrega, `updatedb:status` sem pendências e `config:status` limpo. Sem import, atualização de banco ou mudança de conteúdo.
- **Validações de código:** PHPUnit Unit LMS 270/1.024 PASS; Kernel `AnswerReviewWorkflowKernelTest` 3/84 PASS incluindo upload de resposta sem biblioteca Olivero (12 depreciações Core/contrib); CourseStructureOrderForm focal 2/35 PASS; PHPCS dos arquivos alterados na dev.205, Node 3/3, Node syntax, PHP lint, YAML via Symfony e `git diff --check` PASS. `AnswerSubmissionForm.php` mantém alertas PHPCS preexistentes em outras linhas. O comportamento sem JavaScript da ordenação segue disponível; as simulações de arrasto não persistiram alterações.
- **Dev.204/Runtime:** removida `answer-form.css` porque só alterava seletores Olivero ausentes nos temas ACULTA; formulário de resposta não anexa a biblioteca. Kernel de upload passou 3/84, incluindo a asserção da biblioteca ausente. Commit `96e66bcfdefaa6e7bbd54ebfbf6935edafb8b99b`; Runtime sincronizado e cache reconstruído, sem updates/config drift.
- **Dev.205/Runtime:** alerta nativo antes de sair com alterações em formulários de aula, atividade e ordenação; submissão limpa o estado. Commit `d91887ca23a86546a5194c5b116cbc6b8ca9e84e` enviado a `origin/dev`, Runtime sincronizado, cache reconstruído, `updatedb:status` sem pendências e `config:status` limpo. O teste Node cobre o comportamento do listener, sem navegação browser dedicada nesta rodada.
- **Pendências funcionais:** Turnstile global causa overflow de cerca de 8–10 px a 320 px e sua configuração afeta todos os formulários; não foi alterada. Integração visual AJAX/edição inline e homologações end-to-end permanecem pendentes conforme os gates.

## Continuação LMS — limpeza segura de documentos de curso, 2026-11-04

- **Feito:** teste Kernel Group→Media→File real mostrou que `FileReferenceInspector` não contava `group_relationship_target`, cujo destino varia por bundle. Corrigido o inspector do `aculta_file_uploader` para consultar definições Group por bundle; agora uma mídia/arquivo compartilhado sobrevive ao primeiro detach e é removido após a última relação.
- **Arquivos:** `web/modules/custom/aculta_portal/modules/aculta_file_uploader/` (inspector, Unit, versão dev.34 e changelog); `web/modules/custom/aculta_portal/modules/aculta_lms_resources/` (Kernel, README, versão dev.31 e changelog).
- **Validação:** suites selecionadas Unit/Kernel: 91 testes / 403 assertions PASS (10 depreciações PHPUnit); PHPCS focal, PHP lint e `git diff --check` PASS. `validate-aculta-portal-drupal11.php` permanece FAIL porque o índice/status compartilhado tem divergências de versões em componentes fora desta entrega; alterações não foram sobrescritas.
- **Commit/Runtime:** `b9ed4d3451` enviado a `origin/dev`; no `toca`, `drush cr` PASS, `updatedb:status` sem atualizações e `config:status` limpo. HEAD Runtime/origin `b9ed4d3451f6625996b3b5cb38e8bd06329ac55f`; nenhuma configuração ou conteúdo do site foi alterado.
- **Correção Messenger/LMS:** consultas paginadas ordenavam Message por `id`, mas o campo de entidade é `mid`, gerando `QueryException` no inbox. Corrigidos os adaptadores de Amizades, Loja, Doações, Eventos, Game, LMS, Revista e Wiki. Dispatcher real executado para a conta de teste sem registrar nova falha de adapter.
- **Validação e entrega:** Kernel LMS 5/58 PASS; integração Messenger 130/1.652 PASS; Unit Messenger 402/4.374 PASS; PHPCS focal e `git diff --check` PASS. Commit `7945c9eef8` enviado a `origin/dev`; `drush cr` PASS, `updatedb:status` sem atualizações, `config:status` limpo. Sem alterações a dados/configuração.
- **Próximos passos:** validar a rota do Course Builder no navegador autenticado e concluir a avaliação funcional LMS; depois avançar para pagamentos e resolver os itens `updatedb` um por vez, conforme objetivo vigente.

- **Dev.204:** removida `answer-form.css` porque só alterava seletores Olivero ausentes nos temas ACULTA; formulário de resposta não anexa a biblioteca. Kernel de upload de resposta `AnswerReviewWorkflowKernelTest` passou 3/84, incluindo a asserção da biblioteca ausente (12 depreciações upstream). Unit LMS 268/1.014 PASS; código publicado em `96e66bcfdefaa6e7bbd54ebfbf6935edafb8b99b` e Runtime sincronizado, cache reconstruído, sem updates/config drift.
- **Dev.205:** alerta nativo de alterações não salvas em formulários de autoria e reordenação do Course Builder. Teste Node 3/3; Unit LMS 270/1.024; publicado em `d91887ca23a86546a5194c5b116cbc6b8ca9e84e`, Runtime sincronizado. Turnstile a 320 px, integração inline/AJAX e homologações continuam pendentes.
- **Dev.206 publicado:** análise estática do Upgrade Status/PHPStan percorreu 225 arquivos; 19 alertas sobre visibilidade/`@covers` foram corrigidos. PHPStan 0 erros; Unit LMS 270/1.024; Kernel `AcultaLmsInstallTest` 9/409; commit `a6f175f2af` está em `origin/dev` e no Runtime. `drush cr` passou; `updatedb:status` e `config:status` estão limpos.
- **Avaliação funcional responsiva no Runtime:** Firefox autenticado, somente GET, sem submissões, em `/ganjalab/educador/cursos/88/estrutura`: 320, 360, 390, 500, 768 e 1.280 px carregaram sem overflow; 25/25 controles receberam foco e nove links de mover aula/atividade estavam disponíveis. Formulários de nova aula e nova atividade carregaram com controles focáveis e botão habilitado em 320, 390, 768 e 1.280 px; não foram enviados. A criação de aula tem overflow horizontal em 320/390 px causado por `#edit-title` com largura de 635 px; a atividade também em 320/390 px por `#edit-question` com 635 px. Não alterei tema/CSS por estar fora do escopo visual; o overflow funcional segue pendente de solução compatível com a refatoração do tema. A leitura da página de atividade foi feita pelo link real do curso (`aula/1`); uma tentativa anterior com ID inexistente foi descartada. Nenhum conteúdo do Runtime foi alterado.
- **Próximos passos:** continuar fechando lacunas funcionais e gates restantes do LMS. Depois avançar para pagamentos e, em seguida, resolver os itens de `updatedb` um a um, criando e testando o código ou páginas ausentes. O overflow de autoria móvel permanece encaminhado ao tema em refatoração; esta frente LMS não altera CSS.
- **F7 — cobertura Kernel adicional:** `CourseEnrollmentPurchaseKernelTest` agora cobre transição real de pedido gratuito pelo hook Commerce: rascunho sem entitlement, pedido concluído com membership ligada por `field_course_order`, e seleção forjada de turma fechada/estrangeira negada sem carrinho. MariaDB `aculta_simpletest`, 4/173 PASS; 67 depreciações upstream. Nenhum gateway chamado e nenhum dado do Runtime alterado.
- **Bloqueio externo Drupal 12:** Entity Print instalado é 2.19.0; `3.0.x-dev` listado pelo projeto ainda declara `drupal/core ^9.4 || ^10.0 || ^11`, assim como 2.x-dev. Kernel registra que `EntityPrintPluginManager` ainda não suporta descoberta por atributo, removida no Drupal 12. Não troquei versão Composer sem um branch que declare essa compatibilidade; isso bloqueia o fechamento do gate de certificado PDF para Drupal 12.
- **Content Index do LMS Resources:** removida dos testes a dependência obsoleta em `lms`/`lms_classes` contrib; fixtures criam Group types e memberships ACULTA. Corrigido o contexto Domain do fixture para não chamar `\Drupal::service()` antes do boot do Kernel. `LmsCourseContentIndexAccessKernelTest` 5/239 PASS (14 depreciações upstream); `ContentIndexOwnerIntegrationKernelTest` 2/65 PASS (213 depreciações upstream, incluindo uma de `webform_score`). PHPCS, PHP lint e `git diff --check` PASS. Runtime preflight mostra zero updates e zero config drift; nenhum estado foi alterado.
- **Responsividade Runtime:** matriz autenticada GET em 11 larguras (320–1440 px): estrutura do curso sem overflow; formulários de autoria mantêm botão ativo, mas `#edit-title` e `#edit-question` medem 635 px e causam overflow de 320 a 600 px. Não foi feito trabalho visual/CSS nesta frente. O harness Ganjalab genérico falhou por pressupor markup diferente do atual; foi descartado e os endpoints reais foram medidos diretamente por WebDriver BiDi.
- **Microfase access/cache da agenda:** `ClassActivityDeadlineAccessCheck::courseAccessForClass()` agora adiciona o curso pai como dependência quando está despublicado, invalidando a negativa quando o curso é publicado. Kernel prova cache tags Group/relacionamento e acesso do estudante matriculado após a publicação. Agenda Kernel 5/195 e Unit 8/18; LMS Unit 270/1.024 e Kernel 41/1.330 passaram. PHPCS, PHP lint e `git diff --check` passaram. Gate `validate-aculta-portal-drupal11.php` continua falhando pelas divergências baseline de nove componentes e índice README desatualizado; não regenerado para evitar alterações alheias. Runtime preflight: `updatedb:status` sem pendências e `config:status` limpo. Runtime read-only confirma provider Content Index ativo, curso 88 e turma 89 publicados, mas nenhum evento ligado a turma; sem criar conteúdo, smoke de evento permanece pendente. Sem import, update de banco ou alteração de conteúdo.
- **Arquivos da microfase:** access checker e Kernel da Agenda LMS; changelogs/roadmap do LMS e da Agenda; manifest e status do `aculta_lms`; roadmap central LMS; este handoff. Composer e temas paralelos preservados fora do stage.
- **Microfase de cache do currículo (dev.208):** resoluções por delta/slugs agora são invalidadas pelas listas Core `aculta_lms_lesson_list` e `aculta_lms_activity_list` em páginas, access checks permitidos/negados e URLs de saída. Kernel `LearningDashboardKernelTest` confirmou tags e invalidação real de uma entrada cacheada após salvar atividade; página refeita contém a atividade. LMS Unit 270/1.027 PASS; Kernel focal 1/45 PASS; PHPCS focais PASS. Sem alteração visual ou escrita Runtime. Próximo: concluir hardening/gates funcionais LMS e revisão funcional responsiva sem mexer no tema; então avançar a pagamentos e, depois, processar pendências `updatedb` individualmente.
- **Dev.210 — invalidação de matrícula:** cache tags do curso e das memberships das turmas vinculadas foram centralizadas em `LearningEligibilityInterface`. Acesso a aula, submissão e download de anexos propagam as tags em resultados permitidos/negados. LMS Unit 272/1.039 PASS; `LearningDashboardKernelTest` 1/47 PASS; PHPCS, PHP lint e `git diff --check` PASS. O Kernel apresentou quatro depreciações de Entity Print/Core na inicialização, sem falhas. Commit `efdcc5f949af10a3f3462589c4a3adf4e761e5f7` enviado a `origin/dev`; Runtime no mesmo HEAD, `drush cr` PASS, sem updates ou config drift.
- **Dev.209 — cobertura de URLs:** `LmsFriendlyRouteProcessorTest` (1 teste/7 assertions) comprova tags de lista de aulas e atividades nos links de resposta; Unit LMS 271/1.034, PHPCS, PHP lint e diff check PASS. Nenhuma escrita Runtime.
- **Auditoria funcional LMS — 2026-11-04:** suíte Kernel completa de `aculta_lms` passou: 41 testes / 1.342 assertions; 115 depreciações de Core/contrib, sem falhas. Runtime `cursos` (read-only) confirmou `aculta_lms_resources` habilitado, `field.storage.group.field_lms_{readings,galleries,videos}` e as três instâncias do bundle `lms_course`; `updatedb:status` sem updates e `config:status` sem diferenças. Corrigidos os registros obsoletos de instalação pendente no roadmap e README de recursos. Não houve update, import, conteúdo ou login autenticado. Próximo gate funcional F15: smoke autenticado dos recursos efetivamente vinculados a um curso autorizado; fixtures reais apropriadas ainda não estão disponíveis. F11 também tem bloqueador Drupal 12 em Entity Print; F9 tem Webform/Webform Score sem compatibilidade Drupal 12 declarada pelas versões/branches hoje usadas. Manter pagamentos depois das lacunas funcionais do LMS.
## Handoff — tokens/fundações V2 e correção da raiz Links — 2026-11-04

- **Estado:** tokens/fundações (`1.2.0-dev.1`) publicados; Fase 1 do ADR-046
  permanece aberta para shell, chave de purpose e UI Styles. Sem fase completa
  anunciada antes de cobrir os gates restantes.
- **Fix imediato:** `aculta_linkweed` registrava templates Twig com nomes que
  não correspondiam aos arquivos existentes. Commit publicado `b726811a24`
  (`corrige templates Twig do purpose links`), versão `0.4.0-dev.36`. Root
  Links HTTP 200 e alias interno HTTP 404 após `drush cr`; Unit focal 1/4,
  PHPCS e PHP lint PASS. Runtime `updatedb:status` sem atualizações e
  `config:status` sem diferenças.
- **Fundação visual:** commit publicado `05b23f88b3`
  (`feat(aculta420_v2): consolida fundações mobile-first`). Inclui tokens,
  tipografia, foco, reduced-motion, dimensionamento responsivo de controles,
  gate de contraste e runner Firefox ESR.
- **Evidência:** baseline visual/código `676ff17449`; dez purposes anônimos,
  URLs e capturas em `$SUITE_OUT/aculta420-v2-foundations-full/` (fora do Git).
  180/180 estados de viewport/modo sem overflow; 10/10 Tab e 10/10 Shift+Tab;
  zoom real 200% e 400% em 320 CSS px: 10/10 por nível; sem JS 90/90;
  reduced motion 180/180, transição 0,01 ms. Domínios/URLs vêm de
  `docs/architecture/multidomain.md`. Estados autenticados e leitor de tela
  permanecem DEFERRED.
- **Contraste escuro:** 33 pares de texto passam 4,5:1 (mínimos: muted/raised
  6,78:1; link editorial/raised 6,07:1). Foco: mínimo 7,16:1; bordas: mínimo
  3,21:1.
- **Gates:** V2 foundations PASS; SDC schema 55 componentes/764 checks PASS;
  Design Foundations 2468 PASS; browser validators 21 PASS; shell contract 13
  PASS; SDC discovery 15/15 PASS. `validate-aculta420-foundation.php` falha
  com `ACULTA420 is the default public theme.` porque valida a V1/Bootstrap5;
  o tema ativo no Runtime é `aculta420_v2`. Não alterado o gate legado.
- **Runtime:** `toca`, Drupal 11.5.0-beta1, banco `aculta_runtime`, V2 ativa.
  Após cada push: cache rebuild PASS, nenhuma atualização de banco e nenhuma
  divergência de config. Runtime no commit `05b23f88b3`.
- **Preservado fora dos commits:** alterações paralelas em `composer.json`,
  `composer.lock`, `web/themes/custom/aculta420/js/utility-drawers.js` e
  `web/themes/custom/aculta_theme/`.
- **Próximo:** revisar e comparar Institution Bar, Domain Header, navegação,
  rodapé e regiões Twig contra a V1 somente leitura; não redesenhar os contratos
  do shell. Releia guardrails mobile-first e ADR-046 §3 antes de CSS/SDC/Twig.
  Depois, avaliar a chave neutra do Portal, compatibilidade do UI Styles e
  auditoria dos componentes conforme ADR-046 e gates por purpose.


## Continuação — navegação do shell e sobreposição administrativa V2 — 2026-11-04

- **Estado:** correção visual confirmada no Runtime; a Institution Bar não é
  coberta pelo menu administrativo. A Fase 2 do ADR-046 continua em andamento;
  Domain Header, rodapé e demais regiões ainda precisam de revisão contra a V1.
- **Comportamento:** CSS da V2 desloca Gin para baixo pela altura medida da
  Institution Bar. No toolbar Core, desloca primeiro a faixa de controles e
  depois a bandeja vertical, que começa após ambas. A barra institucional tem
  prioridade de camada e segue interativa.
- **Evidência:** Firefox ESR visível, fixture anônima carregando as folhas de
  estilo locais Gin/Core, 10/10 checks em 320/390/768/1024/1280 px. Geometria
  medida: Institution Bar 0–73 px; Gin inicia em 73 px; controles Core em 73 px;
  bandeja Core em 112 px. Sessão autenticada de toolbar permanece DEFERRED.
  Artefatos fora do repositório em
  `$SUITE_OUT/aculta420-v2-admin-toolbar-layer-accept/`.
- **Navegação SDC:** o menu principal é envolvido pelo SDC `navigation-bar` na
  posição e peso originais do bloco Drupal em `page.header`, preservando o
  render array, acesso e cacheabilidade. Firefox ESR passou 70/70 verificações
  funcionais nos dez purposes. A matriz visual atual passou 670 registros: dez
  purposes, claro/escuro, larguras 320–1440, zoom 200/400, foco, sem JS e
  movimento reduzido, sem respostas HTTP diferentes de 200 ou overflow.
  Observação: o runner injeta CSS baseline no DOM atual e não representa Twig
  histórico.
- **Gates:** SDC schema 55 componentes/764 checks PASS; Design Foundations 2468
  PASS (contraste escuro mínimo dos pares reportados 7,59:1); browser validators
  21 PASS; PHP lint, PHPCS focal, Python compile e `git diff --check` PASS;
  shell contract 13 PASS; runtime SDC 15/15 PASS. `updatedb:status` sem updates,
  `config:status` limpo e `drush cr` PASS. A matriz visual cobre usuários
  anônimos; leitor de tela e toolbar autenticado continuam DEFERRED.
- **Próximo:** verificar os arquivos e templates de identidade do Domain Header
  contra a referência V1 em modo somente leitura, corrigindo o logo ausente da
  V2 sem alterar o contrato Institution Bar/Domain Header. Antes de qualquer
  edição visual, reler `docs/architecture/ACULTA420-MOBILE-FIRST-GUARDRAILS.md`
  e ADR-046 §3.
- **Preservar fora da entrega:** `composer.json`, `composer.lock`,
  `web/themes/custom/aculta420/js/utility-drawers.js` e
  `web/themes/custom/aculta_theme/`.

## Continuação — composição V1 do shell em SDC — 2026-11-04

- **Objetivo:** retomar a Fase 2 do shell ACULTA420 V2 e corrigir diferenças
  comprovadas nas capturas V1/V2. Fase não concluída.
- **Baseline:** commit `18a113f4e8`; V1 consultada somente para leitura. Capturas
  anteriores mostram o Domain Header com logo horizontal e navegação juntos,
  a vitrine de portais depois do header e o bloco de eventos na coluna lateral.
  A V2 tinha marca ausente no Domain Header, links de portais acoplados ao SDC
  da Institution Bar e três blocos laterais posicionados no header.
- **Alterações locais ainda não commitadas:** `PagePresentationHooks.php` agora
  recolhe título, breadcrumb e help do header; agrupa marca e menu principal no
  SDC de navegação; e mapeia a marca neutra `wiki420` para assets da V2. O tema
  declara logo horizontal ACULTA como asset padrão. Criado o SDC
  `purpose-showcase` com story e consumer no page layout; links foram retirados
  de `multidomain-bar`. A navegação usa o breakpoint histórico de 1100 px. O
  layout aplica containers/tokens semânticos e mantém a sidebar em Grid. Assets
  Wiki420 horizontais, empilhados e globe foram copiados da V1. Três arquivos
  `block.block.aculta420_v2_*` tiveram `region` alterado para `sidebar_first`.
  Teste browser de navegação ampliado com logo carregado e fronteira 1100 px.
- **Validação de código:** PHP lint e PHPCS PASS; schema estático SDC 55
  componentes/764 checks PASS; Design Foundations 2468 checks PASS (menor
  contraste reportado no modo escuro 7,59:1); browser validators 21 PASS;
  descoberta/render SDC passou 16/16. `drush cr` PASS.
- **Runtime:** snapshot integral de `aculta_runtime` feito fora do repositório
  antes da importação (SHA-256
  `a11dd840651452b1fd5e530282f84907797df9fa58d35e10d713ae1e10834163`). A
  primeira importação encontrou uma entrada obsoleta `bootstrap5: 0` em
  `core.extension`; ela foi removida do sync após confirmar que o código do
  tema não existe nem está habilitado. A importação completa foi então aplicada
  com sucesso: os três blocos foram movidos para `sidebar_first`, nenhum tema
  Bootstrap foi instalado. Confirmação final: `updatedb:status` sem updates,
  `config:status` sem diferenças e `drush cr` PASS.
- **Correções visuais posteriores:** Gin Core Navigation fixa a sidebar com
  `top: 0` usando seletor de especificidade ID; o CSS desloca sidebar e limita
  sua altura abaixo da Institution Bar. A Institution Bar recebeu camada 2000
  para permanecer sobre trilhos e bandejas do Gin/Core. Firefox ESR visível
  passou 15/15 checks com Gin, Gin Core Navigation e Toolbar Core em
  320/390/768/1024/1280 px, verificando geometria, prioridade de camada e
  acesso por hit-testing. Evidência atual em
  `$SUITE_OUT/admin-sidebar-overlap-final-20261104/`; commit enviado
  `aac5fa2418` (`corrige sobreposicao da barra institucional pelo menu`).
  A única falha da navegação em 320 px era o Turnstile (300 px) excedendo seu
  wrapper do formulário; o wrapper agora mantém essa rolagem local. Firefox
  visível passou 18/18 estados de Conta em claro/escuro, mais teclado,
  Shift+Tab, zoom 200/400, sem JavaScript, movimento reduzido e HTTP 200.
- **Matriz após correções:** navegação 100/100 checks nos dez purposes em
  320/767/768/1024/1099/1100/1440 px, incluindo Enter/Escape, foco e fallback
  sem JavaScript. Resultado anterior de 99/100 foi substituído pela execução
  posterior 100/100. Capturas e métricas estão fora do repositório em
  `$SUITE_OUT/aculta420-v2-shell-dev3-navigation-post-turnstile/` e
  `$SUITE_OUT/aculta420-v2-account-turnstile-visual/`.
- **Preservar:** modificações paralelas em `composer.json`, `composer.lock`,
  `web/themes/custom/aculta420/js/utility-drawers.js` e
  `web/themes/custom/aculta_theme/` não pertencem a esta tarefa.
- **Próximo passo:** comparar as capturas atuais do shell com as referências V1
  já salvas e revisar rodapé/regions restantes. O header agora traz marca e
  menu juntos; a vitrine está abaixo dele; eventos, fórum e agenda estão na
  sidebar. Fase 2 ainda não está concluída. Revisar roadmap/CHANGELOG e executar
  os gates finais antes do commit do shell.

## Continuação LMS — desbloqueio Twig do formulário de materiais — 2026-11-04

- **Correção funcional:** `account-menu.twig` agora usa `avatar` e
  `menu_items` como slots SDC em vez de blocos Twig declarados dentro do
  componente. Isso elimina o conflito ao renderizar o componente dentro do
  `embed` da barra institucional. Marcada como `aculta420_v2` 1.2.0-dev.9;
  nenhum CSS ou ajuste visual foi feito.
- **Runtime:** em `toca`, Drush confirmou Drupal 11.5.0-beta1 e banco
  `aculta_runtime`; preflight `updatedb:status` sem updates e `config:status`
  limpo. `drush cr` e gate `scripts/validate-aculta420-v2-sdc.php` passaram
  (16 componentes).
- **Smoke de recursos:** Firefox headless autenticado exibiu e salvou leitura
  Wiki, galeria e vídeo existentes em Group temporário; os três IDs persistiram.
  A fixture tinha associação criadora válida e foi removida. Fontes editoriais
  preexistentes foram preservadas.
- **PDF privado:** corrigido `CourseDocumentController` (Resources dev.32). O
  `form_alter` restrito à rota registra callback estático antes do cache; o
  objeto Group não fica no FormState cacheado durante o upload. Após salvar, o
  callback revalida criação e associa a Media por Group. Smoke autenticado
  passou no AJAX `managed_file`, salvamento, relação Group→Media→File e lista de
  materiais. Group, Media, File e PDF local foram removidos.
- **Responsividade funcional do upload:** no formulário de PDF, 320/390/768/
  1280 CSS px passaram sem overflow horizontal; o input file permaneceu
  habilitado e recebeu foco por script. Sem capturas ou avaliação estética.
- **Validação:** Unit controller 4 testes / 36 assertions PASS; PHPCS PASS;
  gate SDC V2 cobre 17 componentes; `updatedb:status` sem updates e
  `config:status` limpo. Tentativas com fixture sem associação de educador
  falharam como esperado e foram limpas; uma Media órfã de uma tentativa foi
  removida por ID/nome confirmado.
- **Pendências:** continuar as integrações LMS ainda abertas, depois pagamentos
  e updates pendentes. Esta correção não conclui a fase nem altera o tema
  visualmente.

## Entrega — menu de conta e avatar na Institution Bar — 2026-11-04

- **Objetivo concluído em código:** SDC `account-menu` mostra somente avatar/ícone; “Minha conta” é texto visualmente oculto no `<summary>` para nome acessível. Painel continua usando links da Menu API (Minha conta/Sair). Visitante recebe “Entrar” via slot render array e o JS existente abre o diálogo. O tema não recebe apelido nem lê User/Profile/`user_picture`.
- **Portal:** `aculta_user` fornece `aculta_account_avatar` via `AvatarResolver` e `PublicProfilePolicy`, com contexto `user` e tags cacheáveis da conta/perfil/arquivo. Sem avatar disponível, o tema usa ícone neutro; sem iniciais.
- **Commits enviados a `origin/dev`:** `61fee4f75c` (tema, primeira implementação); `cb7cc9cf5a` (somente avatar); `5bb3a94491` (Portal); `e043035681`, `a42607570f`, `95a4077897`, `e09a808c97`, `dc51b63bbd` (correções encontradas por schema/renderização/runtime). Sem trailers de coautoria.
- **Gates:** SDC estático 55 componentes/764 checks PASS; Design Foundations 2468 PASS; browser validators 21 PASS; foto do usuário INV-FOTO-1..11 PASS; rotas administrativas 42 arquivos/174 rotas PASS; PHP lint/PHPCS/`git diff --check` PASS. Drush SDC 17/17 e shell contract 13/13 PASS após `drush cr`. `validate-aculta420-foundation.php` falha porque exige ACULTA420 V1 como tema público; é incompatibilidade do gate legado com V2, não corrigida aqui.
- **Runtime:** preflight `updatedb:status` sem updates e `config:status` sem diferenças; `drush cr` PASS. A correção de menu visitor foi integrada ao checkout Runtime compartilhado, que permanece no HEAD local antigo e contém alterações paralelas; preservadas sem reset/rebase. HTTP/Firefox ESR visível no host de desenvolvimento: visitante, 320/390/1440 CSS px, claro/escuro, link Entrar presente e sem overflow (6 capturas em `$SUITE_OUT/account-menu-20261104/`). O runner antigo de avatar falhou por rota `/membros` sem cartões no Runtime (0 resultados); não é PASS desta entrega.
- **DEFERRED:** não foi possível validar autenticado com foto, avatar do acervo e sem foto, zoom nativo 200%, teclado/árvore de acessibilidade do Firefox ou Olivero. A tentativa autenticada não alcançou uma página ACULTA verificável. Não usar as capturas autenticadas em branco como evidência. Repetir com contas de teste provisionadas e sessão autenticada válida; conferir anúncio acessível “Minha conta”.
- **Próximo passo:** retornar ao objetivo pausado da revisão ADR-046 (Shell/fundações). Antes de integrar o branch local, reavaliar `git status` e fazer rebase cuidadoso: o checkout principal segue em `b05a3b131b`, atrás de `origin/dev`, com arquivos locais de shell, LMS, Composer e outros; não sobrescrever nem stagear trabalho alheio.
- **Retomar lendo:** este handoff, `docs/decisions/ADR-046-desenho-tema-v2-shell-e-fundacoes.md`, `docs/architecture/ACULTA420-MOBILE-FIRST-GUARDRAILS.md`, `web/themes/custom/aculta420_v2/docs/roadmap.md`, `PagePresentationHooks.php`, SDCs `account-menu`/`multidomain-bar`, e `aculta_user` `AccountMenuPresentationBuilder.php`/`AccountMenuPresentationHooks.php`.

### Complemento — modal de login no padrão visual V1 — 2026-11-04

- O modal de entrada da V1 foi portado para a V2 mantendo o `<dialog>` nativo,
  o fluxo Fetch já existente e o botão Google quando o Portal o disponibiliza.
  A apresentação usa os tokens semânticos do tema; não reintroduz Bootstrap,
  jQuery nem dependência de runtime externa.
- Corrigida a busca dos templates do diálogo: eles ficam fora da Institution Bar
  no DOM. Também foi ajustado o seletor do botão Drupal de submit e o foco
  visível dos controles. Escape fecha o diálogo e restaura foco ao link Entrar.
- Commits enviados a `origin/dev`: `68e3df14ea` (porte visual/modal) e
  `003716ba5a` (submit e foco). `drush cr`, SDC do tema (17 componentes),
  shell contract (13 checks), browser validators (21), Design Foundations
  (2468), SDC schemas (55 componentes/764 checks) e `git diff --check`
  passaram conforme registros da sessão.
- Correção seguinte: painel verde usa o token semântico da paleta da V1 e texto
  escuro por contraste AA: 5.08:1 claro, 8.80:1 escuro. Commit
  `3f9f0470a9` enviado a `origin/dev`.
- Firefox ESR visível confirmou o link Google, carregamento do formulário e
  layout em 320px claro, 390px escuro e 1440px nos dois modos; larguras do
  diálogo de 288/358/440px e sem overflow. Zoom real de 200% com viewport CSS
  de 320px manteve documento em 320px. Tab avançou de usuário para senha; Escape
  fechou o diálogo e restaurou foco ao link. Capturas em
  `$SUITE_OUT/login-modal-v2-20261104/`.
- Gates: fundações V2 47 pares, incluindo login, Google, controles e ações nos
  dois modos; schemas SDC 55 componentes/764 checks, PHP lint e `git diff
  --check` passaram. O gate SDC no worktree isolado não rodou por ausência de
  `vendor`; foi executado no checkout do Runtime e passou.
- Runtime em `toca`/`aculta_runtime`: `updatedb:status` sem pendências,
  `config:status` limpo e `drush cr` concluído. O smoke visual usou esse checkout
  compartilhado, que permanece sujo e 20 commits atrás de `origin/dev`; por isso
  a igualdade integral do Runtime com o HEAD publicado segue DEFERRED. O código
  do modal foi testado no Runtime. Autenticação ponta a ponta e outros purposes
  também permanecem DEFERRED.
# Retomada — migração visual da V1 para V2 (Wiki420) — 2026-11-04

- **Objetivo ativo:** migrar a apresentação de todas as páginas V1 para SDC/tokens V2, preservando estrutura mobile-first. Esta é uma fatia parcial (Wiki420); Magazine continua parcialmente em validação e as demais páginas ainda precisam de inventário.
- **Feito nesta fatia:** em `web/themes/custom/aculta420_v2`, criado SDC `wiki-entry` com variantes article/teaser, adapters Drupal para node full/teaser, CSS global para listagens e CSS do componente com tokens. Adaptação do estilo de grade após identificar o wrapper real `.aculta-wiki-list` e seu cabeçalho de contagem.
- **Evidência:** `drush cr` PASS; o gate `validate-aculta420-v2-sdc.php` rodando do worktree detectou e renderizou 22/22 SDCs. Firefox ESR visível no Runtime (toca, Drupal 11.5.0-beta1, banco aculta_runtime), anônimo, home/listagem/artigo, claro/escuro aplicado pela sessão do browser, larguras 320/390/767/768/1440 CSS px: 189/189; sem overflow, H1 duplicado ou ordem invertida; Tab e Shift+Tab PASS. Capturas em `$SUITE_OUT/aculta420-v2-wiki-20261104/`.
- **DEFERRED:** zoom físico 200%, autenticação, leitor de tela e compatibilidade Drupal 12. `/wiki/busca` respondeu 404 e a home não tinha form de busca no Runtime; não se criou rota nem alterou o owner `aculta_wiki`. Busca/categorias da V1 permanecem sem um consumer real validado.
- **Arquivos da fatia:** `web/themes/custom/aculta420_v2/components/wiki-entry/`, `src/styles/wiki.css`, `aculta420_v2.libraries.yml`, `templates/content/node--wiki-entry--{full,teaser}.html.twig`, `docs/{README.md,architecture.md,components.md,roadmap.md}`, `CHANGELOG.md`, `scripts/validate-aculta420-v2-sdc.php`, `tests/browser/aculta420-v2-wiki/`.
- **Runtime:** antes do sync, `updatedb:status` informou que não há updates; `config:status` não encontrou diferenças. Nenhuma importação, update de banco, alteração de conteúdo ou configuração foi executada.
- **Pendências globais:** concluir evidência de zoom/auth para Wiki; fechar Magazine; inventariar e portar eventos, Fórum, conta/perfil, cursos/LMS, FAQ, avisos, loja e demais páginas conforme os templates/CSS da V1.
- **Retomar lendo:** `web/themes/custom/aculta420_v2/docs/roadmap.md`, `docs/architecture/ACULTA420-MOBILE-FIRST-GUARDRAILS.md`, ADR-046, `web/themes/custom/aculta420/css/components/wiki.css`, V1 `components/patterns/wiki-entry/`, e este handoff.

# Continuação — remoção do gate de Olivero e menu V2 — 2026-11-05

- **C4 publicado:** commit `2be0a07b47` em `origin/dev`, revisão estrutural do
  shell, SDC da micropágina Links e rodapé social. `updatedb:status` sem
  alterações de banco; `config:status` sem diferenças. Runtime não foi escrito.
- **C5 bloqueado para aceite:** menu e avatar já estão implementados; a
  confirmação visual autenticada não é possível nesta sessão, pois `DISPLAY` e
  `SUITE_OUT` não estão definidos. Prosseguir em itens independentes e manter
  captura/autenticação/teclado como pendência explícita.
- **E1/P1 em andamento:** SDC `layout-page` com variantes `stack`, `aside` e
  `full`, três stories; refatoração do `<main>` de `page.html.twig` sem alterar
  regiões; documentação de componente, layout, roadmap, changelog e gate.
  Schemas 55/764, design foundations 2.468, foundations V2 55 pares, shell
  contract 13, render SDC Drupal 25, PHPCS/PHP lint e `git diff --check` PASS.
  Visual, landmarks, zoom 200/400 e teclado DEFERRED por falta de `DISPLAY`.
- **Arquivos E1:** `scripts/validate-aculta420-v2-sdc.php`;
  `web/themes/custom/aculta420_v2/components/layout-page/`;
  `web/themes/custom/aculta420_v2/{src/styles/layout.css,templates/layout/page.html.twig,docs/{components.md,layout-pagina.md,roadmap.md,gates.md},CHANGELOG.md}`.
- **P1 publicado:** commit `e2e7fedfdc` em `origin/dev`; pós-push
  `updatedb:status` sem updates e `config:status` limpo. Runtime não foi escrito.
- **P2 implementado parcialmente após E1:** preprocess escolhe `aside` para
  `wiki_entry`/`aculta_forum_topic`; `full` para `revista`, rota inicial,
  catálogo e mapa públicos de eventos; `stack` para os demais. A loja foi
  omitida porque o plano exige autorização explícita por tarefa. `CurrentRouteMatch`
  é usado sem expor entidade ao SDC nem consultar domínio/purpose. Render SDC
  25/25 PASS; PHP lint, PHPCS Drupal/DrupalPractice, shell 13/13, schema
  55/764, design foundations 2.468 e `git diff --check` PASS. Revisão visual
  ainda pendente. Próximo: commit/push P2 em `dev`, depois iniciar P3; P5 /
  Layout Builder não executar sem novo pedido.
- **Compatibilidade runtime após P2:** requisição anônima a `/conta` inicialmente
  retornou 500. Watchdog identificou cache persistente: invocador antigo passa
  três argumentos ao hook e descoberta SDC desconhece layout-page. Corrigidos
  fallback de construtor (`stack`) e fallback `<main>` para template SDC ausente;
  CSS legado fica só no caminho transitório. Nova requisição respondeu 403
  (acesso anônimo negado) com `X-Robots-Tag: noindex, nofollow, noarchive`.
  Não houve rebuild/clear de cache nem alteração de banco/configuração.
- **P3 parcial:** `PagePresentationHooks` agora mapeia rotas existentes de conta,
  WE Sativa, Ganjalab e Culturas para aside. Confirmado por leitura do
  `StudioAccountSectionController` que a navegação da conta já marca o item
  atual com `aria-current="page"`. Criado hook Portal para `noindex` em todas
  as rotas privadas `aculta_studio.*` e teste Unit correspondente. A rota
  própria do dashboard do Studio na raiz do purpose account não existe no
  módulo; P3 fica bloqueado nesse ponto até entrega do owner. Firefox autenticado
  continua DEFERRED por falta de `DISPLAY`/credenciais de sessão.

- **C4 — revisão estrutural do shell:** o adapter `aculta-links-page.html.twig`
  apresenta a micropágina do módulo Links por SDC dedicado, sem `<main>` interno;
  `page.html.twig` conserva o `social_links_page_footer` via variação social do
  `site-footer`. Stories e `docs/components.md` foram atualizados. O SDC novo
  não exigiu limpeza do cache Drupal: o gate Drush agora faz descoberta
  transitória com backend nulo, coberta por `docs/gates.md`.
- **Gates C4:** schemas 55 componentes/764 checks, design foundations 2.468,
  V2 foundations 55 pares, browser validators 21, shell contract 13, render SDC
  Drupal 24 componentes PASS; `php -l` e `git diff --check` PASS. Capturas em
  claro/escuro, 320/390/768/959/960/1440, zoom 200% e teclado DEFERRED porque
  `DISPLAY` está indisponível. O adapter de conteúdo público Links fica para G3.
- **Runtime:** nenhum cache, configuração ou banco do Runtime foi alterado.
  Antes de sincronizar qualquer head, executar `updatedb:status` e
  `config:status` como verificações somente leitura.
- **Arquivos C4:** `scripts/validate-aculta420-v2-sdc.php`;
  `web/themes/custom/aculta420_v2/{CHANGELOG.md,docs/{architecture.md,components.md,gates.md,layout-pagina.md,roadmap.md},components/site-footer/,components/social-links-page/,templates/aculta-links-page.html.twig,templates/layout/page.html.twig}`.
- **Próximo passo:** validar PHP/style e diff, commit e push direto para `dev`;
  depois prosseguir para o próximo item independente do roadmap.

- **Instrução vigente:** o responsável dispensou validação Olivero em 2026-11-03. Atualizados `AGENTS.md`, `CLAUDE.md`, roteiros de browser afetados e índice do projeto para que suítes não o exijam, não alterem `system.theme` para essa verificação e não o listem como bloqueio. Registros históricos permanecem identificados como histórico.
- **Scripts:** avatar e fórum deixaram de documentar/selecionar Olivero; suites de documento/planilha e PDF passam somente no harness ACULTA420; acessibilidade do Fórum não troca tema. Compilação Python e `git diff --check` PASS; a suíte visual não foi executada.
- **Limpeza publicada:** commit `b9ac306e28` em `origin/dev`. Inclui instruções de agentes, suites/configuração de browser e status index; não contém o trabalho C2. `py_compile` dos seis scripts afetados, `git diff --check`, `updatedb:status` e `config:status` PASS. Runtime permaneceu sem escrita, no commit publicado.
- **Trabalho C2 preservado no worktree:** menu `account-menu` consome `aculta_domain_sites` e `aculta_account_name`, exibe sites, sessão, avatar e marca purpose atual com `aria-current="page"`. O contrato Portal D1/D2 já está publicado em commits anteriores. Alterações do tema C2 continuam não commitadas, separadas da limpeza.
- **C2 validado:** schemas (55 componentes/764 checks), design foundations (2.468), render SDC Drupal (23 componentes), shell (13 checks), browser validators (21 checks), PHP lint e `git diff --check` PASS. Render apontou rótulo acessível vazio; fallback foi adicionado e o render passou.
- **C2 visual DEFERRED:** `DISPLAY` está indefinido nesta sessão; não foi possível abrir Firefox visível, capturar estados visitante/autenticado ou conferir teclado/zoom. Não afirmar aceite visual.
- **C3:** revisão de código confirmou que gavetas, filtros Portal, contadores, erro textual e proteção contra resposta antiga já estavam implementados; corrigida a tabela de paridade que ainda listava esses itens como ausentes. Gates schema 55/764, design 2.468, render SDC 23, shell 13, browser validators 21 e `git diff --check` passaram. Captura visual/auth permanece DEFERRED por falta de `DISPLAY`.
- **Próximos passos:** revisar documentação, publicar C2 em commit próprio e retomar demais fases V2 conforme roadmap. Antes de qualquer troca de HEAD no Runtime, executar `updatedb:status` e `config:status`; não fazer import nem alteração de dados.
- **Leituras para retomar:** `AGENTS.md`, `CLAUDE.md`, `tests/browser/aculta-forum/README.md`, `web/themes/custom/aculta420_v2/docs/roadmap.md`, `web/themes/custom/aculta420_v2/docs/gates.md`.

