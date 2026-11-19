/**
 * @file
 * Resolves an automatic color preference before the page is painted.
 */
(function () {
  'use strict';

  const html = document.documentElement;
  const modes = ['light', 'auto', 'dark'];
  let mediaQuery = null;
  let mediaHandler = null;

  function apply(preference) {
    const mode = modes.includes(preference) ? preference : 'light';
    html.setAttribute('data-color-preference', mode);
    html.setAttribute('data-theme', mode === 'auto'
      ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
      : mode);
    if (mediaQuery && mediaHandler) {
      mediaQuery.removeEventListener('change', mediaHandler);
      mediaQuery = null;
      mediaHandler = null;
    }
    if (mode === 'auto') {
      mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
      mediaHandler = (event) => html.setAttribute('data-theme', event.matches ? 'dark' : 'light');
      mediaQuery.addEventListener('change', mediaHandler);
    }
  }

  document.addEventListener('aculta:color-mode-preference-change', (event) => apply(event.detail?.mode));
  apply(html.dataset.colorPreference);
})();
