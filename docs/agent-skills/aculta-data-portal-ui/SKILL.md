---
name: aculta-data-portal-ui
description: Migra classes de design de módulos do ACULTA para o atributo data-portal-ui e mapeia os tokens no tema V2. Use ao criar ou mover classe em src/ ou templates de aculta_portal.
---

# data-portal-ui: migrar e mapear

Regra: o módulo marca `data-portal-ui="token"`; o tema traduz o token em classes. O módulo nunca escreve `class` de apresentação.

1. Ache as classes: `rg -n "'class' => \[|'#attributes' => \['class'|addClass\(" web/modules/custom/aculta_portal`. Ignore as classes do `allowedClasses` do gate (ex.: `use-ajax`, `is-active`, `draggable`).
2. Troque por `'#attributes' => ['data-portal-ui' => 'token']`. O token usa minúsculas, hífen e `__`/`--` (BEM). Um token por função visual. Sufixo de estado que termina em `-` é montado em runtime.
3. Abra o mapa `TOKENS` em `web/themes/custom/aculta420_v2/src/Hook/PortalUiHooks.php`. O V2 é o tema ativo; o V1 é somente referência.
4. Para cada token, escolha uma das opções:
   - classe que existe no CSS do V2: `'token' => ['classe']`;
   - sem classe no V2, estilizado por seletor de atributo: `'token' => []`. Não invente classe.
5. Não remova `data-portal-ui` do DOM: o CSS do V2 seleciona por `[data-portal-ui~="token"]`.
6. Rode `php scripts/validate-module-theme-boundary.php`. Esperado: PASS. Cada FAIL aponta um token sem mapa.
7. Estilo visual: DEFERRED até a captura no Runtime (320 e 1440 px, claro). Diga isso no relatório.
8. Documente a lista de tokens em `web/themes/custom/aculta420_v2/docs/architecture.md`, seção 7, no mesmo commit.
