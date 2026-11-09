/**
 * Abas da biblioteca de componentes: marca como atual (aria-current) a aba do painel aberto,
 * com o mesmo destaque do componente de navegação de suíte.
 */
(function (Drupal, once) {
  'use strict';

  function syncTabs() {
    const links = document.querySelectorAll('.ui_patterns_quicklinks nav.aculta-tabs a');
    // Sem endereço de painel, a primeira aba fica selecionada.
    const target = window.location.hash || (links[0] ? links[0].getAttribute('href') : '');
    links.forEach((link) => {
      if (target && link.getAttribute('href') === target) {
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
