# Acessibilidade

Meta: WCAG 2.2 AA para o design system público.

## Contratos globais

- teclado para toda funcionalidade interativa;
- foco visível;
- ordem de foco lógica;
- headings sem saltos arbitrários;
- labels e accessible names;
- landmarks;
- `aria-current` para navegação atual;
- contraste AA;
- reduced motion;
- sem informação transmitida apenas por cor;
- estados empty/loading/error compreensíveis.

## Target size

Controles novos devem buscar pelo menos 24 x 24 CSS px ou spacing equivalente,
conforme WCAG 2.2 SC 2.5.8.

Referência:
https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum

## Motion

`prefers-reduced-motion: reduce` deve:

- remover/reduzir animação não essencial;
- impedir auto-rotation quando aplicável;
- preservar funcionalidade.

## Carousel

Carrosséis são conteúdo de alto risco de acessibilidade.

Se houver rotação automática:

- deve existir stop/start;
- foco dentro do carousel interrompe rotação;
- hover interrompe rotação;
- reduced-motion inicia pausado;
- controles são operáveis por teclado;
- mudanças não devem desorientar leitor de tela;
- foco não salta ao trocar slide.

Preferência ACULTA420: evitar autoplay por padrão em novos patterns.

Referências:
- https://www.w3.org/WAI/tutorials/carousels/
- https://www.w3.org/WAI/ARIA/apg/patterns/carousel/

## Menus/Offcanvas

Bootstrap fornece behavior; ACULTA420 deve validar:

- botão com accessible name;
- estado expandido correto;
- Escape;
- retorno de foco;
- foco dentro do painel;
- menu útil sem JavaScript quando houver fallback aplicável.

## Color modes

Light/dark/auto só podem ser liberados quando:

- tokens semânticos cobrem todas as superfícies principais;
- foco continua visível;
- contraste AA é preservado;
- assets/logos funcionam nos modos;
- escolha explícita do usuário não é sobrescrita pelo sistema.

## Links, botões e unidades (2026-10-28)

Origem: princípios do OpenCulturas (`DEVELOPMENT.md`), item I-18 do comparativo.

- **Link** leva a outra página ou a outro ponto da página. **Botão** dispara uma ação na página atual.
  Quando o visual precisar de um link com cara de botão, o elemento continua sendo `<a>`, com a classe
  visual; quando for ação, usar `<button>`. Não usar `<div>` ou `<span>` com comportamento de clique.
- Tamanhos de texto, espaçamento e áreas de toque em unidades relativas (`rem`, `em`), para respeitar a
  fonte escolhida pelo usuário. Evitar largura e altura fixas em pixels para texto.

## Component checklist

Antes de promover um SDC para stable:

- keyboard;
- focus;
- target size;
- long title;
- empty slot;
- mobile;
- zoom/reflow;
- reduced motion;
- color/contrast;
- accessible name/state quando interativo.
