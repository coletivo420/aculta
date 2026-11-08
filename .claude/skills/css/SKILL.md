---
name: css
description: Use para escrever ou revisar CSS do tema aculta420_v2 (tokens semânticos, modo claro e escuro por data-theme, contraste medido, alvo de toque, layout mobile-first). Aplica as regras do ACULTA, não uma base genérica de tokens.
---

# CSS no ACULTA (aculta420_v2)

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
3. `php scripts/validate-aculta420-palette-schemes.php`
4. Auditoria de contraste no modo escuro, sem falha nova.
5. Remoção de regra de área só depois de `grep` confirmar que nenhum markup ativo usa o seletor. Nunca remova regra global de elemento (`button`, `input[type=submit]`, `.form-submit`).
