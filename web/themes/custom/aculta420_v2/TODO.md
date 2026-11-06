# TODO — aculta420_v2

Estado: em desenvolvimento · versão 1.3.0 (`aculta420_v2.info.yml`) · status YAML ausente.

## Próximo passo

- **Revista:** accent global via UI Styles, precedência no wrapper/SDC. Cinco nós sem accent próprio; node 602 vazio. Campo legado preservado, fora do fluxo.
- **PHPUnit:** 12 testes/56 assertions PASS; bootstrap cria saída relativa. Foundations, UI Styles, SDC schema/render PASS.
- **F3:** concluída; Firefox ESR palette/botões 360/360, 10 purposes, claro/escuro, 320/1440, sem overflow. `tests/browser/capturas/purpose-buttons/2026-11-06/`.
- **F2:** shell intacto; ESR 120/120, 10 purposes, claro/escuro, 320/1440, sem overflow e contraste AA. Aceite visual pendente. `tests/browser/capturas/shell/2026-11-06/`.
- **Editor:** anônimo/negações `content_editor` PASS. Após ULI, `/node/add/revista` retorna 404 vazio; suíte para antes do CKEditor. Host/fluxo pendente; sem mudar config. `tests/browser/capturas/aculta-editor/2026-11-06/`.
- **Config:** sem diff seguro de tema/módulo. Config local `default_admin`, field legado e chaves sensíveis Turnstile preservados; sem `drush cex`. Ver handoff.
- **Outros:** Bootstrap contrib fica ativo na validação V1; `/conta` validado; Loja aguarda autorização.

## Dívidas

- `price` (ADR-046): aguarda contrato/autorização de Loja.
- Accent de `card-cultural` (D1): aguarda `domain_presentation.accent` do Portal.

## Leitura

Leia este arquivo, código alvo e trecho necessário do `CHANGELOG.md`. Roadmap: `web/themes/custom/aculta420_v2/docs/roadmap.md`.

## Ao concluir uma fase

Atualize este TODO e `docs/operations/status/aculta420_v2.yml` se existir, no mesmo commit. Remova concluídos; sem histórico.
