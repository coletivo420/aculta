(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaNavigation = {
    attach(context) {
      once('acultaNavigation', '[data-aculta-navigation]', context).forEach((root) => {
        const toggle = root.querySelector('[data-aculta-navigation-toggle]');
        const panel = root.querySelector('[data-aculta-navigation-panel]');
        const close = root.querySelector('[data-aculta-navigation-close]');
        if (!toggle || !panel || !close) return;

        root.classList.add('has-js');
        const desktop = window.matchMedia('(min-width: 48rem)');
        const setOpen = (open) => {
          toggle.setAttribute('aria-expanded', String(open));
          panel.classList.toggle('is-open', open && !desktop.matches);
        };

        toggle.addEventListener('click', () => {
          const open = toggle.getAttribute('aria-expanded') !== 'true';
          setOpen(open);
          if (open) close.focus();
        });
        close.addEventListener('click', () => {
          setOpen(false);
          toggle.focus();
        });
        root.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            toggle.focus();
          }
        });
        desktop.addEventListener('change', () => {
          panel.classList.remove('is-open');
          toggle.setAttribute('aria-expanded', 'false');
        });
        panel.classList.remove('is-open');
      });
    },
  };
})(Drupal, once);
