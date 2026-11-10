# TODO — aculta420_v2

Estado: Dev · versão 1.5.0 (`aculta420_v2.info.yml`) · status em `docs/operations/status/aculta420_v2.yml`.

## Próximo passo

- **ADR-054/055 US-00–14/RN-00–05 em andamento:** UI Skins corta paletas purpose. Sync Views de categorias usa SDC `forum-category`; Runtime aguarda import autorizado. Skins Disabled; UI Styles Page/regiões e Links aguardam homologação.
- Composer: Skins ^1.2, Icons ^2.1; Field Group dev SHA `4403e7b0e27679f47113e96fcdc5349c95e164d1`, **BLOCKED_UPSTREAM** (Core 12/advisories). Patterns CKEditor proibido; Styles CKEditor autorizado.
- Gates estáticos/fundações PASS; navegador, responsividade, inventário e migração visual pendentes. Entity Status desativado até revisão dos rascunhos.
- Planos: `docs/architecture/ACULTA420-V2-UI-SUITE-INTEGRACAO-VISUAL-PLANO.md`, `docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md` e `docs/architecture/ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md`.

## Marcos e pendências

- Superfícies SDC (`surface`, `surface-accent`, `surface-panel`) integradas a UI Styles, UI Patterns e UI Library; gate SDC PASS.
- FST-08: oito SDCs de estatísticas; schema PASS, captura visual pendente.
- ADR-054: P4 em 22/24 displays; piloto Links publicado; P6/P7 entregues; P8/P9 cortados em 1.5.0. Homologação integral, gates ADR e revisão visual humana pendentes.
- Faltam capturas Firefox/P3, Revista, F2/F3, editor, UI Patterns autenticado e Loja; boundary módulo/tema com 64 itens.
- Dívidas: `price` aguarda contrato Loja; `card-cultural` aguarda `domain_presentation.accent`.

## Leitura e entrega

Leia este TODO, código alvo e trecho do `CHANGELOG.md`. Roadmap: `web/themes/custom/aculta420_v2/docs/roadmap.md`. Ao concluir fase, atualize este TODO e `docs/operations/status/aculta420_v2.yml` no mesmo commit, sem histórico.
