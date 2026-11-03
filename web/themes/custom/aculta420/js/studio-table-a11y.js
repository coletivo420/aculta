(function (Drupal, once) {
  const selector = '.table-responsive, .studio__listing-wrap, [data-aculta-studio="listing-wrap"]';

  const markScrollable = (wrapper) => {
    if (wrapper.scrollWidth <= wrapper.clientWidth + 1) {
      return;
    }
    if (!wrapper.hasAttribute('tabindex')) {
      wrapper.setAttribute('tabindex', '0');
    }
    if (!wrapper.hasAttribute('role')) {
      wrapper.setAttribute('role', 'region');
    }
    if (!wrapper.hasAttribute('aria-label')) {
      wrapper.setAttribute('aria-label', 'Tabela rolável');
    }
  };

  Drupal.behaviors.acultaStudioTableA11y = {
    attach(context) {
      const wrappers = once('aculta-table-a11y', selector, context);
      if (!wrappers.length) {
        return;
      }
      window.requestAnimationFrame(() => wrappers.forEach(markScrollable));
      if (!window.acultaTableA11yResize) {
        window.acultaTableA11yResize = true;
        window.addEventListener('resize', () => {
          document.querySelectorAll(selector).forEach(markScrollable);
        }, { passive: true });
      }
    },
  };
}(Drupal, once));
