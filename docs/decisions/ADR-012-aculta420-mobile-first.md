# ADR-012: ACULTA420 Mobile First

**Status:** Aceita como direção de evolução; execução em fases.

**Data:** 2026-10-21.

## Contexto

ACULTA420 já usa Bootstrap 5.3.8, Twig, SDC do Drupal Core, UI Patterns,
UI Styles, tokens semânticos e CSS nativo. A responsividade existe em várias
partes do tema, mas não segue uma base uniforme: há consultas de mídia com
limiares diferentes, componentes de gestão com navegações simultâneas no
celular e assets visuais carregados pela biblioteca global. O ACULTA Studio já
tem rotas por área, seção e aba, AJAX progressivo e componentes de apresentação;
deve ser o primeiro consumidor exigente dos padrões compartilhados.

A fotografia inicial e suas evidências estão em
[`mobile-first-audit.md`](../../web/themes/custom/aculta420/docs/mobile-first-audit.md).

## Decisão

- Evoluir o ACULTA420 como o único design system visual da plataforma. Bootstrap
  continua infraestrutura; SDC, UI Patterns, UI Styles, tokens e CSS nativo
  continuam os mecanismos existentes. Não introduzir framework visual ou
  arquivo CSS genérico de responsividade.
- Projetar primeiro para a menor largura e adicionar layout simultâneo quando
  a largura e a tarefa o justificarem. Usar os breakpoints Bootstrap 5.3.8 como
  referência compartilhada; limiares locais continuam válidos quando houver
  razão documentada. Não converter `max-width` para `min-width` em lote.
- Tratar a capacidade real do container e o conteúdo como critérios de layout.
  Grid, Flexbox, propriedades lógicas, `minmax(0, 1fr)` e container queries são
  opções conforme o componente, sem exigir container query em todo lugar.
- Manter o reflow do conteúdo comum em 320 CSS px. Se um elemento precisar de
  duas dimensões (por exemplo, tabela de comparação), restringir a rolagem ao
  próprio componente. Preservar zoom e texto ampliado. WCAG 2.2 AA é a meta; 24
  × 24 CSS px é o mínimo normativo do critério 2.5.8 sujeito às suas exceções,
  enquanto 44 × 44 px é o alvo de usabilidade preferencial para controles
  móveis frequentes.
- Manter estrutura e comportamento iguais em claro e escuro. Somente valores
  visuais semânticos variam entre os modos.
- Preservar as fronteiras: Core/contrib e kernels mantêm dados, acesso e
  comportamento; ACULTA Studio agrega operações dos proprietários; ACULTA420
  apresenta contratos neutros sem mover lógica funcional para Twig/CSS/JS.
- No Studio, preservar níveis área → seção → aba, URLs reais, permissões do
  proprietário, histórico, acesso por teclado, foco e fallback sem JavaScript.
  Preferência de UX registrada pelo responsável em 2026-10-21: no celular, o
  nível área deve começar com um seletor nativo compacto. Cada opção deverá
  corresponder a uma URL existente; uma mudança de seleção sem JavaScript não
  pode ser o único meio de navegação. A solução de fallback e a interação serão
  avaliadas na MF-0.4 antes da implementação.
- Executar MF-0.1 a MF-1.0 conforme o roadmap canônico do tema. IDs `MF-*` são
  marcadores do projeto e não alteram o SemVer do ACULTA420. Uma fase altera
  somente os componentes e contratos que seu inventário justificar.

## Consequências

- O primeiro trabalho é documental: inventariar breakpoints, shell,
  componentes, carregamento de assets e Studio antes de refatorar estilos.
- O Studio será o piloto dos padrões, sem ganhar um design system separado nem
  duplicar listagens, dados ou permissões.
- Toda alteração visual precisa de evidência responsiva e regressão nos
  consumidores afetados; inspeção estática não equivale à homologação visual.
- A matriz de execução e os pontos de atenção ficam no roadmap e na auditoria;
  a versão do tema só avança quando código, configuração, componente ou gate
  mudar, conforme `docs/versioning.md`.

## Referências consultadas

- [Bootstrap 5.3 — breakpoints e abordagem mobile-first](https://getbootstrap.com/docs/5.3/layout/breakpoints/)
- [Bootstrap 5.3 — grid](https://getbootstrap.com/docs/5.3/layout/grid/)
- [Bootstrap 5.3 — offcanvas responsivo](https://getbootstrap.com/docs/5.3/components/offcanvas/)
- [Drupal — Single-Directory Components](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components)
- [Drupal — JavaScript behaviors](https://www.drupal.org/docs/drupal-apis/javascript-api/javascript-api-overview)
- [MDN — CSS container queries](https://developer.mozilla.org/en-US/docs/Web/CSS/Guides/Containment/Container_queries)
- [W3C — WCAG 2.2, Reflow (1.4.10)](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html)
- [W3C — WCAG 2.2, Target Size Minimum (2.5.8)](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum)
