# Changelog — ACULTA420 V2

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
