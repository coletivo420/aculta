# TODO — aculta420_v2

Estado: em desenvolvimento · versão 1.3.0 (`aculta420_v2.info.yml`) · status YAML ausente.

## Próximo passo

- **Revista:** accent global via UI Styles, precedência no wrapper/SDC. Cinco nós sem accent próprio; node 602 vazio. Campo legado preservado, fora do fluxo.
- **PHPUnit:** 12 testes/56 assertions PASS; bootstrap cria saída relativa. Foundations, UI Styles, SDC schema/render PASS.
- **F3:** concluída; Firefox ESR palette/botões 360/360, 10 purposes, claro/escuro, 320/1440, sem overflow. `tests/browser/capturas/purpose-buttons/2026-11-06/`.
- **F2:** shell intacto; ESR 120/120, 10 purposes, claro/escuro, 320/1440, sem overflow e contraste AA. Aceite visual pendente. `tests/browser/capturas/shell/2026-11-06/`.
- **Editor:** suíte completa PASS. ULI cria cookie compartilhado; o formulário é administrativo no host principal, não no host Revista. CKEditor editorial e os casos administrativos passaram em 1280/390/320 sem overflow. `tests/browser/capturas/aculta-editor/2026-11-06/`.
- **Config:** sem diff seguro de tema/módulo. Config local `default_admin`, field legado e chaves sensíveis Turnstile preservados; sem `drush cex`. Ver handoff.
- **UI Patterns:** stories PHP alimentam props/slots atuais; slots HTML usam render arrays Drupal. Renderer Drupal confirmou Breadcrumb, avatar, links sociais e busca Wiki420, além dos componentes previamente corrigidos. Aceite visual autenticado no Runtime pendente. Loja aguarda autorização.

## Dívidas

- `price` (ADR-046): aguarda contrato/autorização de Loja.
- Accent de `card-cultural` (D1): aguarda `domain_presentation.accent` do Portal.

## Leitura

Leia este arquivo, código alvo e trecho necessário do `CHANGELOG.md`. Roadmap: `web/themes/custom/aculta420_v2/docs/roadmap.md`.

## Ao concluir uma fase

Atualize este TODO e `docs/operations/status/aculta420_v2.yml` se existir, no mesmo commit. Remova concluídos; sem histórico.


## Fórum Growroom420 — status

- Entrega 1: lista, página de tópico e cabeçalho de categoria aplicados. Captura visual DEFERRED.
- Entrega 2: painel lateral da lista e diretrizes do `/criar-topico` aplicados. Painéis de destaque e relacionados aguardam o contrato com o Portal.
- Pendências: decisão sobre o vocabulário de tokens do validador de fundamentos; falhas de fronteira em módulos do Portal.
