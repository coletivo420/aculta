/**
 * @file
 * Progressive enhancement of the main navigation, including Drupal AJAX.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaNavigation = {
    attach(context) {
      if (typeof jQuery === 'undefined' || !jQuery.fn.modal || !jQuery.fn.collapse) {
        return;
      }
      // Bootstrap owns opening, focus trapping and Escape. Reflect that state
      // on the corresponding utility icon without another overlay manager.
      once('aculta-utility-drawer-state', '[data-aculta-utility-drawer]', context).forEach((drawer) => {
        const trigger = document.querySelector('[aria-controls="' + drawer.id + '"]');
        if (!trigger) return;
        jQuery(drawer).on('show.bs.modal', () => trigger.setAttribute('aria-expanded', 'true'));
        jQuery(drawer).on('hidden.bs.modal', () => trigger.setAttribute('aria-expanded', 'false'));
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
            jQuery(menu).collapse('hide');
            toggle.focus();
          }
        });
      });
    },
  };
})(Drupal, once);
