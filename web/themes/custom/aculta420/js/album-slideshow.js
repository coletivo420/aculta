/**
 * @file
 * Presentation behavior for the album stage: thumbnails drive the VVJB slides.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaAlbumStage = {
    attach(context) {
      once('aculta-album-stage', '[data-aculta-album-stage]', context).forEach((stage) => {
        const carousel = stage.querySelector('vvjb-carousel');
        const thumbs = [...stage.querySelectorAll('[data-aculta-album-thumb]')];
        if (!carousel || thumbs.length === 0) {
          return;
        }

        // Thumbnails are 1-based slide numbers in the VVJB public API (goToSlide).
        thumbs.forEach((button) => {
          button.addEventListener('click', () => {
            carousel.goToSlide?.(Number(button.dataset.acultaAlbumThumb));
          });
        });

        // Mirror the current slide on its thumbnail, from the VVJB markup (no VVJB event API needed).
        const markCurrent = () => {
          const current = carousel.getCurrentSlide?.();
          thumbs.forEach((button) => {
            button.setAttribute('aria-current', Number(button.dataset.acultaAlbumThumb) === current ? 'true' : 'false');
          });
        };
        new MutationObserver(markCurrent).observe(carousel, { subtree: true, attributes: true, attributeFilter: ['class'] });
        markCurrent();

        // Mobile: the thumbnail panel is collapsed behind a toggle button.
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.setAttribute('data-aculta-album-toggle', 'true');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Mostrar ou ocultar miniaturas');
        toggle.innerHTML = '<i class="bi bi-chevron-left" aria-hidden="true"></i>';
        toggle.addEventListener('click', () => {
          const open = !stage.hasAttribute('data-aculta-album-open');
          stage.toggleAttribute('data-aculta-album-open', open);
          toggle.setAttribute('aria-expanded', String(open));
          toggle.firstElementChild.className = open ? 'bi bi-chevron-right' : 'bi bi-chevron-left';
        });
        stage.prepend(toggle);
      });
    },
  };

})(Drupal, once);
