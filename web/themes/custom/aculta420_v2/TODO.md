# TODO — aculta420_v2

Estado: Dev · versão 1.5.0 (`aculta420_v2.info.yml`) · status: `docs/operations/status/aculta420_v2.yml`. ADR-054/055 seguem abertas.

## Próximo passo

- Link do aviso Mercado Pago agora valida HTTPS/host e usa `link()` do Drupal; CI pendente.
- **Superfícies 2026-11-12:** canvas branco e tom green-soft por padrão nos três SDCs; raised neutro. Rodar gates, UI Library, dez domínios, acessibilidade e aceite visual real antes de declarar homologado. Ver docs/architecture/ACULTA420-V2-HOMOLOGACAO-SUPERFICIES-2026-11-12.md.

- **US/RN:** layouts 22 configs/gate 276; Skins 300/300 em dez domínios, cinco larguras e dois modos, contraste ≥7,16:1 e sem overflow. Pendem cache, zoom 200%, teclado, perfis, tecnologia assistiva e aceite humano. UI Patterns Views parcial; Wiki 247/247 e Loja 10/10.
- Composer: Skins ^1.2 e Icons ^2.1; Field Group `BLOCKED_UPSTREAM` (Core 12). UI Styles CKEditor 5/Entity Status ativos; Patterns CKEditor 5 sem uso; Picker ativo, validação parcial.
- Inventário: atualizar gerador após cada microfase; SDC `layout-grid` inclui `max_columns`. Manter componentes descobertos por UI Patterns Library.
- CSP: zero violações públicas de `style-src`; sessão autenticada DEFERRED no Runtime.

## Marcos e pendências

- SDC/Library: catálogo admin 84 SDCs; Drawer Claro fora da viewport a 320; zoom e teclado pendentes.
- Pendentes: completar inventário das dez áreas e interfaces administrativas; P8/P9, cache isolation/hit, contraste, zoom, teclado/perfis, rollback e aceite amplo.

## Leitura e entrega

Consulte `CHANGELOG.md` e roadmap; atualize este TODO/status no mesmo commit, sem histórico.

- **Correção de cobertura da inversão (2026-11-12):** `layout-section` recebe
  `green-soft` opt-in + UI Styles + story e gates. Pendente escolher as seções
  reais apropriadas no Layout Builder e confirmar se UI Styles aplica a classe
  na instância correta. Não alterar por padrão os 22 displays.
