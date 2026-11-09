/**
 * Abas da biblioteca de componentes: marca como atual (aria-current) a aba do painel aberto,
 * com o mesmo destaque do componente de navegação de suíte.
 */
(function (Drupal, once) {
  'use strict';

  function syncTabs() {
    const hash = window.location.hash;
    document.querySelectorAll('.ui_patterns_quicklinks nav.aculta-tabs a').forEach((link) => {
      if (hash && link.getAttribute('href') === hash) {
        link.setAttribute('aria-current', 'page');
      }
      else {
        link.removeAttribute('aria-current');
      }
    });
  }

  Drupal.behaviors.acultaUiLibraryTabs = {
    attach(context) {
      once('aculta-ui-library-tabs', 'body', context).forEach(() => {
        window.addEventListener('hashchange', syncTabs);
      });
      syncTabs();
    },
  };
})(Drupal, once);
