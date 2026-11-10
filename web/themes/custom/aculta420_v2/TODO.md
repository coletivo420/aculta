# TODO — aculta420_v2

Estado: Dev · versão 1.5.0 (`aculta420_v2.info.yml`) · status em `docs/operations/status/aculta420_v2.yml`.

## Próximo passo

- **ADR-054/055 US-00–14/RN-00–05:** UI Skins 1.2.1 e CSS variables ativos; Links resolve Ruderalis e MAIN Skunk. `node.page.default/full` usam `layout-section` via UI Patterns Layouts, importados no Runtime sem alterações de conteúdo. Próximo: migrar os demais displays Layout Builder compatíveis; os dois casos excluídos permanecem intactos. Cache de página reporta `UNCACHEABLE`, então cache-hit ainda não foi homologado.
- Composer: Skins ^1.2, Icons ^2.1; Field Group dev SHA `4403e7b0e27679f47113e96fcdc5349c95e164d1`, **BLOCKED_UPSTREAM** (sem cobertura de segurança e Core 12). UI Styles CKEditor 5 ativo no Runtime; Patterns CKEditor 5 está ativo preexistente e `LEGACY_OBSOLETE`, sem uso no código.
- Inventário: 381 itens/83 Twig; 16 fallbacks mapeados a adapters SDC. `aculta_section` usa `layout-section`; pagamento usa `surface`; botão usa `button`; status do carrinho usa `status-messages`. UI Icons estão no Sync, validação Runtime pendente. Cinco overrides de categorias retirados do código; View categorias usa `forum-category`. Entity Status e demais domínios pendentes.
- Planos e critérios: plano UI Suite, migração Skins e contrato SiteKey em `docs/architecture/`.

## Marcos e pendências

- Superfícies SDC integradas a UI Styles/Patterns/Library; FST-08 tem oito SDCs. Gate SDC passou com 41 componentes. Links foi capturado em 1280/320 e claro/escuro; Firefox anônimo confirmou skins/tokens A/B/A sem overflow. O aceite visual amplo e cache-hit permanecem pendentes; manifesto em `tests/browser/capturas/ui-suite-links/2026-11-10/manifest.json`.
- ADR-054: P4 em 22/24 displays; piloto Links publicado; P6/P7 entregues; P8/P9 cortados em 1.5.0. Homologação e revisão visual pendentes.
- Faltam capturas Firefox/P3, Revista, F2/F3, editor, UI Patterns autenticado, perfis/teclado/zoom e Loja; fronteira módulo/tema ainda tem 64 itens.
- Dívidas: `price` aguarda contrato Loja; `card-cultural` aguarda `domain_presentation.accent`.

## Leitura e entrega

Consulte `CHANGELOG.md` e roadmap; ao concluir fase, atualize este TODO e o status YAML no mesmo commit, sem histórico.
