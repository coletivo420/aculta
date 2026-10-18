/**
 * @file
 * Presentation behavior for the album stage: thumbnails drive the VVJS slides.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaAlbumStage = {
    attach(context) {
      once('aculta-album-stage', '[data-aculta-album-stage]', context).forEach((stage) => {
        const slideshow = stage.querySelector('vvjs-slideshow');
        const thumbs = [...stage.querySelectorAll('[data-aculta-album-thumb]')];
        if (!slideshow || thumbs.length === 0) {
          return;
        }

        // Thumbnails use the 1-based slide index in VVJS's public API.
        thumbs.forEach((button) => {
          button.addEventListener('click', () => {
            slideshow.goToSlide?.(Number(button.dataset.acultaAlbumThumb));
          });
        });

        const markCurrent = (current = slideshow.getCurrentSlide?.() || 1) => {
          thumbs.forEach((button) => {
            button.setAttribute('aria-current', Number(button.dataset.acultaAlbumThumb) === current ? 'true' : 'false');
          });
        };
        markCurrent();

        // VVJS publishes slide changes through the VVJ Core event contract.
        // Listen once globally and update only the stage that owns the source.
        once('aculta-album-stage-events', document.documentElement).forEach(() => {
          document.addEventListener('vvj:slideChanged', (event) => {
            const source = event.detail?.source;
            const eventStage = source?.closest?.('[data-aculta-album-stage]');
            if (source?.matches?.('vvjs-slideshow') && eventStage) {
              const current = event.detail.slide || source.getCurrentSlide?.() || 1;
              eventStage.querySelectorAll('[data-aculta-album-thumb]').forEach((button) => {
                button.setAttribute('aria-current', Number(button.dataset.acultaAlbumThumb) === current ? 'true' : 'false');
              });
            }
          });
        });

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
