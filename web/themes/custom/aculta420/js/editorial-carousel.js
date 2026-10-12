/**
 * @file
 * Focus integration for the home editorial carousel, including Drupal AJAX.
 */
(function (Drupal, once) {
  'use strict';

  // VVJB 2.0 has no focus pause. Use its public API; no custom slide timer.
  Drupal.behaviors.acultaEditorialFocus = {
    attach(context) {
      const pauseWhenFocused = (carousel) => {
        // VVJ hydrates lazily. The native pause API needs initialized markup.
        if (carousel.contains(document.activeElement) && carousel.querySelector('.vvjb-inner')?.dataset.vvjbInitialized === 'true') {
          carousel.pause?.();
        }
      };
      once('aculta-editorial-ready', document.documentElement).forEach(() => {
        document.addEventListener('vvj:ready', (event) => {
          const carousel = event.detail?.source;
          if (carousel?.matches(':is(.view-home-editorial-highlights, .view-aculta-social-projects) vvjb-carousel')) {
            pauseWhenFocused(carousel);
          }
        });
      });
      once('aculta-editorial-focus', ':is(.view-home-editorial-highlights, .view-aculta-social-projects) vvjb-carousel', context).forEach((carousel) => {
        // VVJ 2.0 returns the browser's ViewTransition without observing ready.
        // Rapid navigation intentionally skips an older transition. Handle that
        // cancellation locally; all slide logic and animation remain native.
        if (typeof carousel.withTransition === 'function') {
          const nativeTransition = carousel.withTransition.bind(carousel);
          carousel.withTransition = (callback) => {
            const transition = nativeTransition(callback);
            transition?.ready.catch((error) => {
              if (error.name !== 'AbortError') {
                throw error;
              }
            });
            return transition;
          };
        }
        carousel.addEventListener('focusin', () => pauseWhenFocused(carousel));
        // Keep it paused after keyboard interaction until the visitor presses Play.
        // This also avoids resuming a deliberately paused or reduced-motion carousel.
      });
      // The VVJB template hard-codes the region name in English. Translate it here
      // so the accessible name matches the site language; the label is in the locale.
      once('aculta-editorial-label', ':is(.view-home-editorial-highlights, .view-aculta-social-projects) vvjb-carousel[aria-label="Carousel"]', context).forEach((carousel) => {
        carousel.setAttribute('aria-label', Drupal.t('Carousel'));
      });
    },
  };
})(Drupal, once);
