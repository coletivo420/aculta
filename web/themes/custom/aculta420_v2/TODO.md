# TODO — aculta420_v2

Estado: Dev · versão 1.5.0 (`aculta420_v2.info.yml`) · status: `docs/operations/status/aculta420_v2.yml`.

## Próximo passo

- **ADR-054/055 US-00–14/RN-00–05:** 22 configs usam ComponentLayout no Sync/Runtime; 21 saídas renderizam `layout-section`, Wiki full compõe direto `wiki-entry`. Gate 276 checks PASS; duas exclusões preservadas. Cache `UNCACHEABLE`; cache-hit e aceite amplo pendentes.
- Composer: Skins ^1.2, Icons ^2.1. Field Group dev SHA `4403e7b0e27679f47113e96fcdc5349c95e164d1` é `BLOCKED_UPSTREAM` (segurança/Core 12). UI Styles CKEditor 5 ativo; UI Patterns CKEditor 5 preexistente, sem consumidores e `LEGACY_OBSOLETE`.
- Inventário: 383 itens/83 Twig; 16 fallbacks mapeados a SDCs. `aculta_albuns` usa UI Patterns row/style/display e SDCs `revista-album-card`, `layout-grid`, `views-display`; 3 resultados, acesso anônimo e browser 1280/320 claro/escuro verificados. UI Icons Runtime, View categorias, Entity Status e outras Views/domínios pendentes. Ver planos UI Suite, Skins e SiteKey em `docs/architecture/`.

## Marcos e pendências

- SDC/Library: 41 componentes PASS; FST-08 tem oito SDCs. Links 1280/320 claro/escuro e A/B/A sem overflow; manifesto em `tests/browser/capturas/ui-suite-links/2026-11-10/manifest.json`.
- Pendentes: aceite visual e cache-hit; capturas P3/Revista/F2/F3/editor/Loja; sessão autenticada, perfis, teclado e zoom; 64 itens de fronteira módulo/tema. `price` aguarda contrato Loja; `card-cultural` aguarda `domain_presentation.accent`.

## Leitura e entrega

Consulte `CHANGELOG.md` e roadmap; atualize este TODO/status no mesmo commit, sem histórico.
