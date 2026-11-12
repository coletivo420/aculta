/**
 * Safelist do PurgeCSS para o tema aculta420_v2.
 *
 * Regra do projeto: NÃO existe padrão geral `^aculta-`. Toda classe aculta
 * preservada por padrão precisa de justificativa e de um extrator que a
 * produza. As exceções abaixo são apenas de classes geradas em tempo de
 * execução (Drupal, UI Suite, Leaflet, HTMX) ou de nomes sem consumidor
 * textual possível.
 */

/** Classes estáticas sem consumidor textual (geradas por código de terceiros). */
export const STATIC_SAFELIST = [
  // Drupal: mensagens, estados e utilidades do Core.
  'messages',
  'messages--status',
  'messages--error',
  'messages--warning',
  'messages__wrapper',
  'visually-hidden',
  'hidden',
  'is-disabled',
  'is-active',
  'is-open',
  'is-collapsed',
  'is-expanded',
  'is-selected',
  'is-empty',
  'is-hidden',
  'is-loading',
  'is-invalid',
  'is-valid',
  'is-dragging',
  'is-required',
  'is-error',
  'is-success',
  'placeholder',
  'js-form-item',
  'js-form-type-textfield',
  'js-form-type-textarea',
  'js-form-type-select',
  'js-form-type-checkbox',
  'js-form-type-radio',
  'form-item',
  'form-actions',
  'form-submit',
  'form-text',
  'form-textarea',
  'form-select',
  'form-radios',
  'form-checkboxes',
  'form-managed-file',
  'form-wrapper',
  'form-required',
  'progress',
  'progress__track',
  'progress__bar',
  'ajax-progress',
  'ajax-progress-throbber',
  'ajax-progress-fullscreen',
  'tabledrag-handle',
  'tabledrag-toggle-weight',
  'tabledrag-changed',
  'tabledrag-indent',
  'tabledrag-hide',
  'tabledrag-parent',
  'tabledrag-root',
  'tabledrag-leaf',
  'tabledrag-message',
  'tabledrag-changed-warning',
  'fieldset',
  'fieldset-wrapper',
  'details',
  'details-wrapper',
  'details__summary',
  'js-form-wrapper',
  'js-form-type-managed-file',
  // Drupal: mensageiro e diálogos do Core/UI.
  'ui-dialog',
  'ui-dialog-titlebar',
  'ui-dialog-title',
  'ui-dialog-content',
  'ui-dialog-buttonpane',
  'ui-dialog-buttonset',
  'ui-dialog-resizing',
  'ui-dialog-buttons',
  'ui-front',
  'ui-widget',
  'ui-widget-content',
  'ui-widget-header',
  'ui-state-default',
  'ui-state-hover',
  'ui-state-focus',
  'ui-state-active',
  'ui-state-disabled',
  'ui-helper-hidden',
  'ui-helper-hidden-accessible',
  'ui-helper-clearfix',
  'ui-helper-reset',
  'ui-corner-all',
  'ui-corner-top',
  'ui-corner-bottom',
  'ui-icon',
  'ui-icon-closethick',
  'ui-icon-circle-close',
  // HTMX: classes aplicadas pelo runtime durante a requisição.
  'htmx-request',
  'htmx-settling',
  'htmx-swapping',
  'htmx-adding',
  'htmx-indicator',
  // UI Suite: classes dos plugins e da biblioteca.
  'ui-patterns-library',
  'ui-patterns-preview',
  'ui-styles-preview',
  'ui-icons-preview',
  'ui-skins-preview',
  // Navegador / ambiente.
  'no-js',
  'js',
  'leaflet-container',
  'leaflet-pane',
  'leaflet-tile',
  'leaflet-tile-pane',
  'leaflet-overlay-pane',
  'leaflet-shadow-pane',
  'leaflet-marker-pane',
  'leaflet-tooltip-pane',
  'leaflet-popup-pane',
  'leaflet-map-pane',
  'leaflet-control',
  'leaflet-control-container',
  'leaflet-control-zoom',
  'leaflet-control-zoom-in',
  'leaflet-control-zoom-out',
  'leaflet-control-attribution',
  'leaflet-control-layers',
  'leaflet-bar',
  'leaflet-popup',
  'leaflet-popup-content-wrapper',
  'leaflet-popup-content',
  'leaflet-popup-tip',
  'leaflet-popup-close-button',
  'leaflet-marker-icon',
  'leaflet-marker-shadow',
  'leaflet-interactive',
  'leaflet-zoom-animated',
  'leaflet-zoom-anim',
  'leaflet-fade-anim',
  'leaflet-touch',
  'leaflet-retina',
  'leaflet-grab',
  'leaflet-dragging',
];

/**
 * Padrões justificados. Cada padrão é restrito a uma família gerada em tempo
 * de execução. Não inclua `^aculta-` nem `^button$` genérico.
 */
export const SAFELIST_PATTERNS = [
  // Rotas e entidades: body classes do Drupal (ex.: path-entrar, node--type-page).
  // Os padrões são aplicados ao texto do seletor, por isso incluem o ponto.
  { pattern: /\.path-/, justification: 'Body class de rota gerada pelo Drupal.' },
  { pattern: /\.node-/, justification: 'Classes de nó e view mode geradas pelo Drupal.' },
  { pattern: /\.page-/, justification: 'Body class de página gerada pelo Drupal.' },
  { pattern: /\.region-/, justification: 'Região gerada pelo sistema de blocos.' },
  { pattern: /\.block-/, justification: 'Classes de bloco geradas pelo Drupal/Views.' },
  { pattern: /\.view-/, justification: 'Classes de View geradas pelo Views.' },
  { pattern: /\.views-/, justification: 'Classes de render do Views.' },
  { pattern: /\.field--/, justification: 'Classes de campo geradas pela Field API.' },
  { pattern: /\.toolbar-/, justification: 'Classes da barra administrativa do Core.' },
  { pattern: /\.js-/, justification: 'Classes de comportamento JavaScript do Core.' },
  // Ícones: packs UI Icons expõem classes/nomes de ícone.
  { pattern: /\.icon-/, justification: 'Classes de ícone geradas pelo UI Icons/Icon API.' },
  // Variantes cujo valor vem de dados renderizados (não de `enum` SDC).
  { pattern: /\.forum-topic-(card|row)__badge--/, justification: 'Tom do badge vem do dado do tópico (highlight, neutral, official, success).' },
  { pattern: /\.layout-grid--min-[\d.]+/, justification: 'Largura mínima do item é numérica e vem da prop `min_item_width`.' },
  { pattern: /\.aculta-forum-topic--(full|teaser)/, justification: 'Sufixo é o view mode do nó de tópico renderizado pelo Drupal.' },
];

/** Safelist efetiva no formato aceito pelo PurgeCSS 9. */
export function buildSafelist(dynamicClasses = []) {
  return {
    standard: [...new Set([...STATIC_SAFELIST, ...dynamicClasses])],
    deep: [],
    greedy: [],
    patterns: SAFELIST_PATTERNS.map((entry) => entry.pattern),
  };
}

/** Safelist declarativa para relatório (classe/padrão + justificativa). */
export function describeSafelist(dynamicClasses = []) {
  return {
    standard: [...new Set(dynamicClasses)].sort(),
    patterns: SAFELIST_PATTERNS.map((entry) => ({
      pattern: entry.pattern.source,
      justification: entry.justification,
    })),
    static: [...STATIC_SAFELIST].sort(),
  };
}
