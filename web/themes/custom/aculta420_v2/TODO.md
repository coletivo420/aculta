# TODO — aculta420_v2

Estado: em desenvolvimento · versão 1.4.0 (`aculta420_v2.info.yml`) · status: `docs/operations/status/aculta420_v2.yml`.

## Próximo passo

- **ADR-054 P4 (parcial):** Layout Builder em 22 dos 24 displays compatíveis (`allow_custom` só em `page.full`, para landing; gate 170 checks). Excluídos `document.full` e `basic.default` por perda de conteúdo reproduzível. Blocos de cabeçalho restaurados do Sync.
- **ADR-054 P5 (piloto entregue):** nó 635 publicado no host Links com alias `/redes-sociais` e duas seções LB (apresentação+links, apoio+institucional); 35 checks Playwright em cinco larguras e dois shells. Expansão aos demais purposes depende de conteúdo editorial.
- **ADR-054 P6/P7 (feito):** prévias reais de blocos em `aculta_ui_library`; oito folhas de aplicação contextuais por rota (`global-styling` 268 → 107 KB, −60%). Playwright 86/86.
- **ADR-054 P8/P9 (parcial):** gates próprios PASS (fundações, classes, Drupal 12, tokens); 86 checks Playwright. Cutover DEFERRED com runbook em `docs/architecture/ACULTA420-V2-P9-CUTOVER.md`; `shell-regions --strict` bloqueia enquanto houver 5 blocos em `highlighted`.
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
