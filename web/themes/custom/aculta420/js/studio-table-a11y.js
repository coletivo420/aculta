(function (Drupal, once) {
  Drupal.behaviors.acultaStudioTableA11y = {
    attach(context) {
      once('aculta-studio-table-a11y', '.studio__listing-wrap > .table-responsive', context).forEach((wrapper) => {
        if (!wrapper.hasAttribute('tabindex')) {
          wrapper.setAttribute('tabindex', '0');
        }
        if (!wrapper.hasAttribute('aria-label')) {
          wrapper.setAttribute('aria-label', 'Tabela rolável');
        }
      });
    },
  };
}(Drupal, once));
