# Changelog — ACULTA420 V2

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
