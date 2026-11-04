# ACULTA420 V2 roadmap

## Fase 1 — Scaffold, tokens e shell

**Status: concluída em `1ea7970ace` e ativada no Runtime em `65ce844bce`.**

- Drupal 11.5/12 compatibility metadata with Drupal 12 API focus.
- CSS-only Drupal libraries; Vite and Bootstrap are not dependencies.
- Asset paths are Drupal-relative; Phosphor SVGs are inlined by the icon SDC
  (no cross-origin sprite). Cache tags bubble through Twig render arrays; CDN
  TTL and purge headers are configured at the environment/reverse-proxy layer.
- No Bootstrap base theme, classes, data attributes, JavaScript or jQuery UI
  runtime is loaded by V2; interactions use native browser APIs and Drupal
  behaviors.
- Mobile-first shell, base styles, semantic tokens, and `card-cultural` SDC.

## Fase 2 — Catálogo SDC e integração Twig

**Status: integrada em `1.1.0-dev.5`; schemas e renderização SDC PASS; login modal e revisão visual em validação.**

- Fourteen components with strict schemas, local assets, responsive CSS, and
  progressive enhancement for mobile navigation.
- Core template adapters for pager, status messages, and node teaser.
- ACULTA Portal light/dark preference is applied through `data-theme`; no
  system color-scheme detection is enabled.
- Drush gate validates component discovery, schemas, assets, and isolated
  rendering.
- ACULTA Maps supplies neutral popup render arrays; the active theme chooses
  the component and Leaflet/Google reuse cloned, inert rendered templates.
- Leaflet marker icons use theme-rendered Phosphor SVG in inert templates and
  `L.divIcon`; map JS uses `core/once` and keeps markup outside `drupalSettings`.
- Phosphor Icons Core 2.1.1 (MIT) is vendored locally; all 1,512 regular and
  1,512 duotone SVGs are available to inline SDC rendering.
- The account menu render array is moved from Drupal's header region into the
  fixed bar; login and search use native dialogs and Fetch, with no Core jQuery
  dialog library in the V2 shell. Portal's search preview behavior is retained.
- The institutional bar is fixed at the V1 top position and contains the ACULTA
  logo and icon-only search, cart, messages, and alerts. Optional network links
  sit in a separate row; URLs/counts come from Portal data.

### Pendências antes de promover a RC

- Revisão visual Firefox em larguras móveis e amplas, nos modos claro e escuro,
  com zoom e teclado.
- Revisão assistiva das páginas reais que usam pager, mensagens e teasers.
- Não trocar tiles OSM por CartoDB nem introduzir endpoint de pontos sem decisão
  e revisão dos contratos de privacidade, acesso, cache e purpose.

## Compatibilidade e limites

O tema é desenvolvido no Drupal 11.5 instalado e declara `^11.5 || ^12`. A
execução no Drupal 12 ainda requer validação em Core 12. O Runtime de
desenvolvimento não equivale a validação Alpha ou produção.
