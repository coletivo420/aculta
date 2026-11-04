# Changelog — ACULTA420 V2

## 1.2.0-dev.3 — identidade no menu da barra institucional (2026-11-04)

- O SDC `account-menu` recebe o apelido público aprovado pelo Portal e exibe-o
  somente quando tem até dez caracteres Unicode; sem apelido ou com texto mais
  longo, mantém apenas o avatar/ícone. Em telas menores que 40rem o rótulo fica
  oculto.
- O avatar e o apelido chegam por render array/valor escalar do módulo
  `aculta_user`; o tema não consulta entidades, Profile ou `user_picture`.
- O painel mantém os links do menu Drupal, o login continua usando o diálogo
  AJAX existente e o disclosure fecha por Escape ou clique fora.
- O painel de conta usa largura fluida para caber na viewport estreita.

## 1.2.0-dev.2 — navegação SDC no shell (2026-11-04)

- O bloco Drupal `system_menu_block:main` permanece na mesma posição da região
  `header`; o preprocess o envolve no render element nativo do SDC
  `navigation-bar`, preservando o render array original como slot e mantendo
  acesso, peso e cacheabilidade.
- O menu continua visível sem JavaScript; JavaScript aprimora o drawer mobile,
  com foco ao abrir, fechamento por Escape e retorno de foco. A marca permanece
  opcional no componente para evitar duplicação do Domain Header.
- Os landmarks do SDC e do menu Core têm nomes distintos (`Primary navigation`
  e `Main navigation`); links e título continuam sob o sistema de menus Drupal.
- A Institution Bar permanece no topo acima das camadas administrativas. A
  sidebar Gin começa após a altura medida da barra; a bandeja vertical Core
  começa após a barra e os controles do toolbar.
- Firefox ESR visível: baseline `33e4dddbf5`; a matriz funcional cobre dez
  purposes, 320/767/768/1440 px, Enter/Escape/foco e fallback sem JS. A matriz
  visual passou nas dez raízes de purpose em claro/escuro e 320–1440 px, sem
  overflow; zoom 200/400, foco de teclado, sem JS e movimento reduzido passaram.
  O runner injeta o CSS baseline no DOM atual, portanto não representa o
  histórico Twig anterior. O teste de camadas usa as folhas Gin/Core e fixtures
  DOM anônimas (10/10 checks em 320/390/768/1024/1280 px): a barra ocupa
  0–73 px, Gin começa em 73 px, controles Core em 73 px e bandeja Core em
  112 px. Sessão administrativa real e leitor de tela ficam DEFERRED.

## 1.2.0-dev.1 — tokens e fundações com identidade da V1 (2026-11-04)

- Consolidados tipografia Inter/Oswald, escala de espaçamento herdada, foco
  visível tokenizado, movimento reduzido e regras de reflow mobile-first.
- A biblioteca Drupal carrega as mesmas famílias tipográficas externas usadas
  pela V1; nenhum Node, Vite ou Bootstrap foi adicionado ao tema.
- O novo gate `validate-aculta420-v2-foundations.php` verifica tokens e pares
  de contraste do modo escuro. Os 33 pares de texto passam 4,5:1; os 4 pares
  de foco e 4 pares de borda passam 3:1. Piores resultados: texto muted em
  superfície elevada 6,78:1; link editorial em superfície elevada 6,07:1;
  borda em superfície elevada 3,21:1.
- Firefox ESR visível: baseline `676ff17449` comparado ao estado `1.2.0-dev.1`
  nos dez purposes documentados, nas raízes públicas (Conta redireciona à tela
  de entrada). Em 320/360/390/599/600/768/1024/1280/1440 CSS px, os modos
  claro/escuro passaram 180/180 estados sem overflow; todos responderam HTTP
  200. Tab e Shift+Tab: 10/10 focos visíveis. Zoom nativo a 200% e 400%, com
  viewport efetiva de 320 px: 10/10 em cada nível sem overflow. Sem JavaScript:
  90/90 larguras/purposes sem overflow. Movimento reduzido: 180/180
  combinações de largura/modo, transição calculada em 0,01 ms. Sessões anônimas;
  logins autenticados e leitores de tela não foram cobertos. Evidências e URLs
  ficam em `$SUITE_OUT/aculta420-v2-foundations-full/`; purposes e hosts vêm da
  matriz em `docs/architecture/multidomain.md`.
- O gate legado `scripts/validate-aculta420-foundation.php` continua exigindo a
  V1 como tema público e Bootstrap5 como base. No Runtime ativo em V2 ele falha
  com `ACULTA420 is the default public theme.`; o gate específico V2 passou.
- A declaração Drupal `^11.5 || ^12` permanece sem alegação de suporte
  validado no Core 12, conforme ADR-045.

## 1.1.0-dev.5 — menu de conta e login modal da barra (2026-11-04)

- O menu Drupal de conta voltou à barra institucional fixa. Os links nativos
  “Minha conta” e “Sair” permanecem sob controle de acesso do sistema de menu;
  o Portal fornece a URL de logout com token de sessão.
- Para visitantes, “Entrar” continua apontando ao popup do Portal. V2 carrega
  o formulário e envia o login por Fetch ao endpoint `drupal_ajax`, aplicando
  respostas Drupal de substituição/redirect dentro de `<dialog>` nativo. A
  busca usa modal nativo e preserva a prévia do Portal. Os dois gatilhos da
  barra não dependem de `core/drupal.dialog.ajax` nem de jQuery. Uma View
  existente ainda anexa `core/drupal.ajax` (e sua dependência jQuery) à home;
  essa biblioteca não é introduzida pela barra.
- A barra lê o layout de purpose do Portal, realça a conta no purpose account
  e respeita a supressão da faixa utilitária no purpose links.
- Validação SDC 15/15, PHP lint/PHPCS, sintaxe JavaScript e `drush cr` passaram.
  Smoke HTTP confirmou home, endpoint de login e endpoint de busca em 200.
  O envio de credenciais não foi testado. `updatedb:status` e `config:status`
  estão limpos.

## 1.1.0-dev.4 — ícones vetoriais e integração CDN/cache (2026-11-04)

- Adicionada a biblioteca Phosphor Icons Core 2.1.1 (regular e duotone, MIT)
  e o SDC `icon`, com nomes validados e SVG inline via Twig `source()` para
  herdar `currentColor` sem dependência de CORS ou sprite externo.
- Badge, button e card-cultural aceitam ícones SDC; os metadados de data e
  local usam ícones Phosphor.
- Busca, carrinho, mensagens, avisos, fechar diálogos, navegação e mensagens de
  status também usam o SDC `icon`; removidos os SVGs de interface desenhados
  diretamente nos templates.
- Documentada a estratégia de assets Drupal/CDN: URLs relativas ao Drupal,
  bibliotecas agregáveis e cache-tag invalidation pelo ambiente. Vite continua
  fora da fundação conforme a decisão anterior de usar Drupal libraries.
- Leaflet próprio recebe ícones inline renderizados pelo tema em templates
  inertes e os monta em `L.divIcon`; IDs e classes de categoria são validados.
- Validação: 15/15 SDCs discover, validate and render; icon output includes
  inline SVG, and a template audit found no inline interface SVGs outside the
  icon SDC. Runtime cache rebuild and Eventos `/mapa` HTTP smoke succeeded.
  Unit Maps: 4 tests / 26 assertions PASS. Visual/assistive review and
  environment CDN deployment remain follow-up.

## 1.1.0-dev.3 — 2026-11-04

- Added the global institutional bar SDC fixed at the legacy top position: the
  ACULTA logo links to the main domain, followed by icon-only search, cart,
  messages, and alerts. Optional portal links render in a separate secondary
  row; the four utilities have no visible text labels.
  Domain resolution, utility URLs/counts, and cache dependencies remain owned
  by ACULTA Portal.
- Migrated the ACULTA420 logo variants and favicon from V1. Added an
  `apple-touch-icon.png` derived from the V1 favicon and a web app manifest
  using square brand icons. Color mode remains controlled by the existing
  explicit selector.
- Foundation schema: 55 SDCs / 764 checks PASS; Design Foundations: 2468
  checks PASS; V2 SDC discovery/schema/render 14/14 PASS. Runtime cache rebuild
  and HTTP 200 confirmed. Visual review remains pending.

## 1.1.0-dev.2 — 2026-11-04

- Integrated the `map-popup` SDC with ACULTA Maps theme hooks. The map module
  supplies escaped, provider-neutral render data and the theme selects the SDC.
- Leaflet and Google InfoWindows clone the same inert server-rendered template;
  popup HTML is not transferred through `drupalSettings`.
- Added the optional `route_url` popup prop. Validation and visual review remain
  pending.

## 1.1.0-dev.1 — 2026-11-04

- Added ten schema-enforced Single Directory Components for buttons, badges,
  cultural cards, audio, hero banners, navigation, pagination, filter chips,
  status messages, and map popups.
- Added Drupal template adapters for pager, status messages, and node teasers.
- Added explicit light/dark token sets controlled by the existing ACULTA Portal
  appearance preference. System color-scheme detection is disabled; legacy
  `auto` resolves to light in V2.
- Added a progressive mobile navigation drawer using Vanilla JavaScript,
  `Drupal.behaviors`, and `core/once`.
- Added a Drush SDC validation gate and architecture documentation.
- Validation: Core strict SDC schemas and isolated renders, all Twig parses,
  node teaser render, YAML, JavaScript syntax, PHP lint/PHPCS, CSS parse and
  mobile-first media audit, explicit mode mapping, and development Runtime
  smoke. Full consumer visual and assistive technology review remains pending.

State: Dev. No RC, production deployment, or release tag is declared.
