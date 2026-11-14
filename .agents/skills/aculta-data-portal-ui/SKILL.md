---
name: aculta-data-portal-ui
description: Migra classes de design de módulos do ACULTA para o atributo data-portal-ui e mapeia os tokens no tema V2. Use ao criar ou mover classe em src/ ou templates de aculta_portal.
---

# data-portal-ui: contrato entre módulo e tema

Use em módulo customizado do Portal quando a tarefa mexer em marcação, atributo de apresentação, classes ou tokens consumidos pelo tema V2. Em componente SDC, use `sdc`; em CSS e tokens, use `css`; para styles selecionáveis pelo editor, use `ui-styles`.

Regra: o módulo marca `data-portal-ui="token"`; o tema traduz o token em classes. O módulo nunca escreve `class` de apresentação.

1. Ache as classes: `rg -n "'class' => \[|'#attributes' => \['class'|addClass\(" web/modules/custom/aculta_portal`. Ignore as classes do `allowedClasses` do gate (ex.: `use-ajax`, `is-active`, `draggable`).
2. Troque por `'#attributes' => ['data-portal-ui' => 'token']`. O token usa minúsculas, hífen e `__`/`--` (BEM). Um token por função visual. Sufixo de estado que termina em `-` é montado em runtime.
3. Identifique o purpose e o caminho de renderização do elemento. O token descreve apresentação, não autorização ou regra de negócio. O tema V2 aplica tokens semânticos por `data-theme` e `data-purpose`; não codifique variante de cor diretamente no módulo.
4. Abra o mapa `TOKENS` em `web/themes/custom/aculta420_v2/src/Hook/PortalUiHooks.php`. O V2 é o tema ativo; o V1 é somente referência.
5. Para cada token, escolha uma das opções:
   - classe que existe no CSS do V2: `'token' => ['classe']`;
   - sem classe no V2, estilizado por seletor de atributo: `'token' => []`. Não invente classe.
6. Não remova `data-portal-ui` do DOM: o CSS do V2 seleciona por `[data-portal-ui~="token"]`. Classes resultantes são do tema, não do módulo.
7. SDC, UI Styles e tokens têm papéis distintos. Use o SDC para markup e contrato de props/slots, UI Styles para opções configuráveis reutilizáveis, e `data-portal-ui` para a ponte de apresentação de markup de módulo. Não converta regra de purpose em classe visual.
8. Rode `php scripts/validate-module-theme-boundary.php`. Cada falha aponta token sem mapa. Rode os gates do tema conforme o diff.
9. Estilo visual: DEFERRED até a captura no Runtime (320 e 1440 px, claro e escuro quando aplicável). Diga isso no relatório.
10. Documente a lista de tokens em `web/themes/custom/aculta420_v2/docs/architecture.md`, seção 7, no mesmo commit.
