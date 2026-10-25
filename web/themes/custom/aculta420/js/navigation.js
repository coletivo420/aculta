/**
 * @file
 * Progressive enhancement of the main navigation, including Drupal AJAX.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaNavigation = {
    attach(context) {
      if (typeof bootstrap === 'undefined') {
        return;
      }
      // Bootstrap owns opening, focus trapping and Escape. Reflect that state
      // on the corresponding utility icon without another overlay manager.
      once('aculta-utility-drawer-state', '[data-aculta-utility-drawer]', context).forEach((drawer) => {
        const trigger = document.querySelector('[aria-controls="' + drawer.id + '"]');
        if (!trigger) return;
        drawer.addEventListener('show.bs.offcanvas', () => trigger.setAttribute('aria-expanded', 'true'));
        drawer.addEventListener('hidden.bs.offcanvas', () => trigger.setAttribute('aria-expanded', 'false'));
      });
      once('aculta-navigation', '.aculta-navbar', context).forEach((navbar) => {
        const toggle = navbar.querySelector('.aculta-menu-toggle');
        const menu = navbar.querySelector('#aculta-primary-menu');
        if (!toggle || !menu) {
          return;
        }
        navbar.classList.add('aculta-navigation-ready');
        menu.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && !window.matchMedia('(min-width: 1100px)').matches) {
            bootstrap.Collapse.getOrCreateInstance(menu, { toggle: false }).hide();
            toggle.focus();
          }
        });
      });
    },
  };
})(Drupal, once);
