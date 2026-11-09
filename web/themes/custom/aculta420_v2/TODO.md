# TODO — aculta420_v2

Estado: em desenvolvimento · versão 1.4.0 (`aculta420_v2.info.yml`) · status: `docs/operations/status/aculta420_v2.yml`.

## Próximo passo

- **ADR-054 P5:** fonte explícita da landing por purpose; piloto Links com composição própria, teste de cache e isolamento de domínio.
- **ADR-054 P4:** Layout Builder por display DEFERRED até staging isolado (24 compatíveis, 48 adaptador, 23 incompatíveis).
- **ADR-054 P6/P7 (feito):** prévias reais de blocos em `aculta_ui_library`; `statistics.css` e `studio.css` fora do global (`global-styling` 268 KB → 180 KB). Playwright autenticado confirmou escopo e 86 checks sem falhas.
- **ADR-054 P8/P9 (parcial):** Playwright Firefox 2/2 no shell v2; HTTP 200 nos dez purposes; rollback `classic` testado. Cutover não executado: faltam aceitação visual humana, Layout Builder por display, landings e import/export em staging. Relatório: `docs/architecture/ACULTA420-V2-P8-HOMOLOGACAO.md`.
- **P3:** shell v2 opt-in com cinco áreas; smoke HTTP com 1 H1 e 1 `main` em nove hosts; capturas Firefox e aceite visual pendentes.
- **Pendências visuais:** Revista (accent via UI Styles), F2/F3 (aceite visual), editor (suíte PASS), UI Patterns (aceite autenticado), Loja (aguarda autorização).

## Dívidas

- `price` (ADR-046): aguarda contrato de Loja.
- Accent de `card-cultural` (D1): aguarda `domain_presentation.accent`.
- Gates de fundações e classes mapeadas falham por dívidas pré-existentes (caminho de tokens e 5 classes de perfil).

## Leitura

Este arquivo, código alvo e trecho do `CHANGELOG.md`. Roadmap: `web/themes/custom/aculta420_v2/docs/roadmap.md`.

## Ao concluir uma fase

Atualize este TODO e `docs/operations/status/aculta420_v2.yml`, no mesmo commit; sem histórico.
