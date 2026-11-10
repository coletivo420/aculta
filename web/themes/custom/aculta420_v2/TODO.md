# TODO — aculta420_v2

Estado: em desenvolvimento · versão 1.5.0 (`aculta420_v2.info.yml`) · status: `docs/operations/status/aculta420_v2.yml`.

## Próximo passo

- **ADR-055 / UI Suite integral (US-00 a US-14, PLANEJADO):** substituir **todo visual legado** por UI Suite, classificar `LEGACY_DEPRECATED`/`LEGACY_OBSOLETE`, migrar **motor visual de purposes para UI Skins** sem alterar roteamento/ACL e inventariar Composer/lock + Runtime/Sync. Preferir UI Patterns Field Group `2.0.x-dev@dev` após gates de segurança/Drupal 12. **Não usar UI Patterns CKEditor 5**; UI Styles CKEditor 5 permanece. Planos: `docs/architecture/ACULTA420-V2-UI-SUITE-INTEGRACAO-VISUAL-PLANO.md` e `docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md`.

- **SDCs de superfície (concluído):** `surface`, `surface-accent` e `surface-panel`, integrados a UI Styles, UI Patterns e UI Library; gate SDC PASS.
- **FST-08 (entregue):** oito SDCs de estatísticas do Fórum, schema PASS; captura visual pendente.
- **ADR-054:** P4 em 22/24 displays; P5 piloto Links publicado; P6/P7 prévias e CSS contextual; P8/P9 corte 1.5.0. Restam boundary dos módulos do Portal (64) e revisão visual humana.
- **P3:** shell v2 opt-in; smoke HTTP em nove hosts; captura Firefox pendente.
- **Pendências visuais:** Revista, F2/F3, editor, UI Patterns autenticado e Loja.

## Dívidas

- `price` (ADR-046): aguarda contrato de Loja.
- Accent de `card-cultural` (D1): aguarda `domain_presentation.accent`.
- Boundary módulo/tema com 64 itens pré-existentes; revisão visual humana pendente.

## Leitura

Este arquivo, código alvo e trecho do `CHANGELOG.md`. Roadmap: `web/themes/custom/aculta420_v2/docs/roadmap.md`.

## Ao concluir uma fase

Atualize este TODO e `docs/operations/status/aculta420_v2.yml`, no mesmo commit; sem histórico.
