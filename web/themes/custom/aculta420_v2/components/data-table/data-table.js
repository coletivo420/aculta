(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaDataTable = {
    attach(context) {
      once('acultaDataTable', '[data-aculta-data-table]', context).forEach((root) => {
        const viewport = root.querySelector('[data-aculta-data-table-viewport]');
        const table = viewport && viewport.querySelector('table');
        if (!viewport || !table) return;

        const media = window.matchMedia('(width < 40rem)');
        const sync = () => {
          const cardsOnMobile = root.dataset.mobileVariant === 'cards' && media.matches;
          const isScrollable = !cardsOnMobile && viewport.scrollWidth > viewport.clientWidth + 1;
          if (isScrollable) {
            viewport.setAttribute('role', 'region');
            viewport.setAttribute('tabindex', '0');
            viewport.setAttribute('aria-label', viewport.dataset.scrollLabel || 'Tabela rolável');
          }
          else {
            viewport.removeAttribute('role');
            viewport.removeAttribute('tabindex');
            viewport.removeAttribute('aria-label');
          }
        };

        const resizeObserver = 'ResizeObserver' in window ? new ResizeObserver(sync) : null;
        const mutationObserver = 'MutationObserver' in window ? new MutationObserver(sync) : null;
        resizeObserver?.observe(viewport);
        resizeObserver?.observe(table);
        mutationObserver?.observe(table, { attributes: true, childList: true, subtree: true, characterData: true });
        media.addEventListener('change', sync);
        window.addEventListener('resize', sync);
        root.__acultaDataTableCleanup = () => {
          resizeObserver?.disconnect();
          mutationObserver?.disconnect();
          media.removeEventListener('change', sync);
          window.removeEventListener('resize', sync);
          delete root.__acultaDataTableCleanup;
        };
        sync();
      });
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') return;
      once.remove('acultaDataTable', '[data-aculta-data-table]', context).forEach((root) => {
        root.__acultaDataTableCleanup?.();
      });
    },
  };
})(Drupal, once);
