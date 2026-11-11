# TODO — aculta420_v2

Estado: Dev · versão 1.5.0 (`aculta420_v2.info.yml`) · status: `docs/operations/status/aculta420_v2.yml`.

## Próximo passo

- **ADR-054/055 US-00–14/RN-00–05:** 22 configs usam ComponentLayout; gate 276 PASS, duas exclusões. UI Styles Block ativo em Documentos; UI Styles Page não suporta seleção por Domain ID. Cache-hit e aceite amplo pendentes.
- Composer: Skins ^1.2, Icons ^2.1. Field Group dev SHA `4403e7b0e27679f47113e96fcdc5349c95e164d1` é `BLOCKED_UPSTREAM` (segurança/Core 12). UI Styles CKEditor 5 e Entity Status ativos; preview unpublished configurado, teste upstream 1/7, saída/perfis/cache pendentes. UI Patterns CKEditor 5 preexistente sem consumidores, `LEGACY_OBSOLETE`. UI Icons Picker Sync/Runtime; submódulos divergem e aceite visual pende.
- Inventário: 437 itens (54 instâncias de bloco) e 83 Twig; 16 fallbacks mapeados. Álbuns usa row/style/display; Fórum/Eventos preservam acesso e rows; Documentos usa UI Styles Block, três resultados, foco e cinco larguras/dois modos PASS. Revista mantém adapter SDC; Runtime/browser pendentes. Demais Views/domínios, cache-hit, zoom, perfis e aceite visual pendentes.

## Marcos e pendências

- SDC/Library: 82 componentes PASS (schema 1774 checks); FST-08 tem oito SDCs. Links 1280/320 claro/escuro e A/B/A sem overflow; manifesto em `tests/browser/capturas/ui-suite-links/2026-11-10/manifest.json`.
- Pendentes: aceite visual e cache-hit; capturas P3/Revista/F2/F3/editor/Loja; sessão autenticada, perfis, teclado e zoom; 64 itens de fronteira módulo/tema. `price` aguarda contrato Loja; `card-cultural` aguarda `domain_presentation.accent`.

## Leitura e entrega

Consulte `CHANGELOG.md` e roadmap; atualize este TODO/status no mesmo commit, sem histórico.
