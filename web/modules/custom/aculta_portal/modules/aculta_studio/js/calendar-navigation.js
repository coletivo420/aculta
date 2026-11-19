/**
 * Navegação do card de calendário do Studio via HTMX.
 *
 * Troca apenas o card #aculta-studio-calendar-card usando HTMX sem recarregar a página.
 */
(function (Drupal, htmx, once) {
  'use strict';

  const CARD_SELECTOR = '#aculta-studio-calendar-card';

  Drupal.behaviors.acultaStudioCalendar = {
    attach(context) {
      once('aculta-studio-calendar-init', CARD_SELECTOR, context).forEach((card) => {
        // Encontra links de navegação de mês, filtros e dias
        const links = card.querySelectorAll('a[data-nav], .aculta-studio-calendar__filter-chip, .aculta-studio-calendar__day-link');

        links.forEach((link) => {
          if (link.hasAttribute('data-hx-get')) {
            return;
          }
          link.setAttribute('data-hx-get', link.href);
          link.setAttribute('data-hx-target', CARD_SELECTOR);
          link.setAttribute('data-hx-select', CARD_SELECTOR);
          link.setAttribute('data-hx-swap', 'outerHTML');
          link.setAttribute('data-hx-push-url', 'false');
          link.setAttribute('data-hx-sync', 'this:replace');
          htmx.process(link);
        });
      });
    },
  };
})(Drupal, htmx, once);
