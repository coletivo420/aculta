---
name: css
description: Use para escrever ou revisar CSS do tema aculta420_v2 (tokens semânticos, modo claro e escuro por data-theme, contraste medido, alvo de toque, layout mobile-first). Aplica as regras do ACULTA, não uma base genérica de tokens.
---

# CSS no ACULTA (aculta420_v2)

Use para escrever ou revisar CSS do tema V2. Para definir escolhas editoriais de classe reutilizável, consulte também `ui-styles`; para mudar componentes, consulte `sdc`.

Tokens em `web/themes/custom/aculta420_v2/src/styles/tokens.css`. Bloco claro em `:root, [data-theme="light"]`; bloco escuro em `:root[data-theme="dark"], [data-theme="dark"]`. Não crie um terceiro sistema de cor.

## Regras
- Nenhum hexadecimal, `rgb()` ou nome de cor fixo em componente ou folha de área. Use token semântico: `--color-text-primary`, `--color-surface-raised`, `--color-border`, `--button-*`, `--color-focus-ring`.
- Modo claro e escuro saem dos tokens, pelo `data-theme` do contêiner. Não crie bloco `[data-theme="dark"]` por componente.
- Token derivado por `var()` precisa ser repetido no bloco escuro, senão o contêiner herda o valor claro já calculado. Ex.: `--color-focus-ring: var(--color-brand-primary)` exige a mesma linha no bloco escuro.
- Texto sobre fundo de destaque (`--color-brand-highlight`) usa `--color-on-highlight`, nunca o verde de marca.
- Seletor global como `.page a` pode vencer a regra do componente. Dê à regra do componente mais especificidade, em vez de mexer no global.
- Não use `filter: brightness()` para simular modo.
- Mobile-first: base em 320 CSS px, sem rolagem horizontal da página.

## Contraste (mínimos medidos)
- Texto: 4,5:1.
- Borda, anel de foco e ícone de controle: 3:1.
- Medição: `scripts/validate-aculta420-v2-foundations.php` (pares de texto e de faixa) e a auditoria de contraste da UI Library (`tests/browser/transversal/aculta420-v2-component-library/library-mode.py`, campo `auditoria_escuro`).

## Alvo de toque
- 44 px em controle interativo (`--tap-target-min`). Exceção só com registro (ex.: barra institucional, ADR-047).

## Ordem de verificação
1. `php scripts/validate-aculta420-ui-styles.php`
2. `php scripts/validate-aculta420-v2-foundations.php`
3. `php scripts/validate-aculta420-ui-skins.php`
4. Auditoria de contraste no modo escuro, sem falha nova.
5. Remoção de regra de área só depois de `grep` confirmar que nenhum markup ativo usa o seletor. Nunca remova regra global de elemento (`button`, `input[type=submit]`, `.form-submit`).

## Classes de design vindas de módulos (data-portal-ui)
Herdado da skill `aculta-data-portal-ui`. Regra: o módulo marca `data-portal-ui="token"`; o tema traduz o token em classes.
1. O módulo não escreve `class` de apresentação. Troque `class` por `'#attributes' => ['data-portal-ui' => 'token']`. Token em minúsculas com hífen e BEM.
2. Abra o mapa `TOKENS` em `web/themes/custom/aculta420_v2/src/Hook/PortalUiHooks.php`. Para cada token: classe que existe no CSS do V2, ou `[]` (estilizado por seletor de atributo). Não invente classe.
3. O CSS seleciona por atributo: `[data-portal-ui~="token"]`. Não remova o atributo do DOM.
4. Gate de fronteira: `php scripts/validate-module-theme-boundary.php` (esperado PASS; cada FAIL aponta token sem mapa).

## Gates por escopo (convenção do projeto)
- Liste o diff e rode só os gates do escopo (`docs/operations/GATES.md`, só a tabela de escopo).
- Gate fora do escopo: N/A com motivo. Gate que não roda no ambiente: DEFERRED com motivo.
- Nunca declare PASS sem execução.
- Saída verbosa de comando: `python3 scripts/agent-output.py --lines 60 -- COMANDO`.
- Estilo visual só se confirma na captura (320 e 1440 px, claro e escuro). Sem captura, registre DEFERRED.

## Tokens em três camadas (boa prática de design tokens, adaptada ao ACULTA)
Fonte: skill `design-tokens` (laurigates/claude-plugins), revisada e adaptada. Não adotamos as sugestões de ferramentas (npx, stylelint) dela.
1. **Primitivos**: valores brutos com nome de categoria e escala (cor da marca, escala de espaço). No ACULTA ficam na paleta de `tokens.css`.
2. **Semânticos**: papel na interface (`--color-text-primary`, `--color-surface-raised`, `--color-on-highlight`). É a camada que o componente consome. Apontam para os primitivos.
3. **De componente**: declarados no próprio componente (`--button-*`), com sobrescrita por variante (ex.: `--button-danger-*`). O componente não usa primitivo direto.
- Regra: componente consome semântico ou de componente. Nunca primitivo, nem hexadecimal.
- Tema: valores claros em `:root, [data-theme="light"]`; escuros em `:root[data-theme="dark"], [data-theme="dark"]`. Não use `prefers-color-scheme` sem o `:root:not([data-theme="light"])`, para não contrariar o modo escolhido.
- Camada e cascata: o escopo do modo é o contêiner, não o `body`. Se o `body` define cor com token claro, os filhos herdam o valor já calculado (ver causa resolvida na UI Library).

## Modo escuro e acessibilidade (complemento, fontes da web revisadas)
- `color-scheme`: declare `color-scheme: light` no contêiner claro e `color-scheme: dark` no escuro. Assim scrollbar e controles nativos acompanham o modo escolhido, mesmo com o sistema no outro modo. Declare no contêiner de modo, não só no `:root`.
- `light-dark()`: útil para troca de cor pura (CSS moderno). Não substitui `data-theme` para o ACULTA: o modo vem da escolha do usuário, não só do sistema. Use `light-dark()` só se o modo escuro for o do contêiner.
- Não relaxe contraste no modo escuro. Meça cada par de texto e de fundo do modo escuro, não presuma que o claro vale.
- Modo de contraste forçado (`forced-colors`): não remova o foco nem a borda de controle. Teste também esse caso.
- Critérios de referência (WCAG 2.2): texto normal 4,5:1 (SC 1.4.3); texto grande 3:1; componente de interface e gráfico 3:1 (SC 1.4.11). O critério de aparência de foco (2.4.13) tem nível que deve ser conferido no texto oficial do W3C antes de citar: não declarar nível sem conferir.
- Fontes para conferir antes de citar: https://www.w3.org/TR/WCAG22/ e https://developer.mozilla.org (páginas de `color-scheme` e `light-dark()`).

## UI Styles e assets

- Os estilos configuráveis vivem em `web/themes/custom/aculta420_v2/aculta420_v2.ui_styles.yml`; CSS e tokens continuam pertencendo ao tema. A skill `ui-styles` define quando declarar escolha editorial nesse arquivo.
- CSS que só serve um SDC fica no diretório do SDC. Estilo de componente ou página não deve duplicar opção já mapeada em UI Styles nem carregar bibliotecas globais.
- Antes de anexar ou ampliar biblioteca, confira `aculta420_v2.libraries.yml`, o carregamento no hook/template e os gates do tema. Prefira assets condicionais do componente.
- O V2 não usa Bootstrap como framework visual. Não copie classes ou CSS da base ACULTA420 V1. Reuse `data-portal-ui` somente conforme `aculta-data-portal-ui`.
- Detalhes de escolha entre componente, variante, UI Style e utilitário: `../ui-styles/references/decisao-de-abstracao.md`.
