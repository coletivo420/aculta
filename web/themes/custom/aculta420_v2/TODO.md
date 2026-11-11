# TODO — aculta420_v2

Estado: Dev · versão 1.5.0 (`aculta420_v2.info.yml`) · status: `docs/operations/status/aculta420_v2.yml`.

## Próximo passo

- **ADR-054/055 US-00–14/RN-00–05:** 22 configs usam ComponentLayout no Sync/Runtime; 21 saídas renderizam `layout-section`, Wiki full compõe direto `wiki-entry`. Gate 276 checks PASS; duas exclusões preservadas. Cache `UNCACHEABLE`; cache-hit e aceite amplo pendentes.
- Composer: Skins ^1.2, Icons ^2.1. Field Group dev SHA `4403e7b0e27679f47113e96fcdc5349c95e164d1` é `BLOCKED_UPSTREAM` (segurança/Core 12). UI Styles CKEditor 5 e Entity Status ativos; preview unpublished configurado, teste upstream 1/7, saída/perfis/cache pendentes. UI Patterns CKEditor 5 preexistente sem consumidores, `LEGACY_OBSOLETE`. UI Icons Picker Sync/Runtime; submódulos divergem e aceite visual pende.
- Inventário: 383 itens/83 Twig; 16 fallbacks mapeados a SDCs. `aculta_albuns` usa row/style/display; `/categorias` usa `forum-category` + `views-display`, agrupamento nativo, 12 resultados e acesso anônimo; Firefox cinco larguras claras, duas escuras, foco e alvos ≥44 px PASS. Seis páginas Revista usam adapter SDC `revista-teaser` (Sync confirmado; Runtime/browser pendentes). Bloco da home oculto, sem aceite visual. Ícones, Entity Status visual, demais Views/domínios, cache-hit, zoom e perfis pendentes. Planos em `docs/architecture/`.

## Marcos e pendências

- SDC/Library: 82 componentes PASS (schema 1774 checks); FST-08 tem oito SDCs. Links 1280/320 claro/escuro e A/B/A sem overflow; manifesto em `tests/browser/capturas/ui-suite-links/2026-11-10/manifest.json`.
- Pendentes: aceite visual e cache-hit; capturas P3/Revista/F2/F3/editor/Loja; sessão autenticada, perfis, teclado e zoom; 64 itens de fronteira módulo/tema. `price` aguarda contrato Loja; `card-cultural` aguarda `domain_presentation.accent`.

## Leitura e entrega

Consulte `CHANGELOG.md` e roadmap; atualize este TODO/status no mesmo commit, sem histórico.
