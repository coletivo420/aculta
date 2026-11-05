/**
 * @file
 * Interatividade para a visualização da Paleta ACULTA Skunk Family nas configurações do tema.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaPalettePreview = {
    attach: function (context, settings) {
      const selects = once('aculta-palette-select', '.aculta-purpose-card select', context);

      selects.forEach(function (select) {
        select.addEventListener('change', function () {
          const selectedScheme = select.value;
          const card = select.closest('.aculta-purpose-card');
          if (!card) {
            return;
          }

          const previews = card.querySelectorAll('.aculta-palette-preview');
          previews.forEach(function (preview) {
            if (preview.getAttribute('data-scheme') === selectedScheme) {
              preview.style.display = 'flex';
            } else {
              preview.style.display = 'none';
            }
          });
        });
      });
    }
  };
})(Drupal, once);
