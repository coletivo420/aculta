(function (Drupal, once) {
  'use strict';

  const announcementId = 'aculta-studio-htmx-announcement';

  function firstMatch(root, selector) {
    if (root instanceof Element && root.matches(selector)) {
      return root;
    }
    return root.querySelector?.(selector) || null;
  }

  function focusAfterSwap(target) {
    const element = firstMatch(target, '[data-aculta-focus], h1');
    if (!element) {
      return null;
    }
    if (!element.hasAttribute('tabindex') && !element.matches('a[href], button, input, select, textarea, [tabindex]')) {
      element.setAttribute('tabindex', '-1');
    }
    element.focus();
    return element;
  }

  function announceSwap(target, title, heading) {
    if (target.closest('[aria-live]')) {
      return;
    }
    let region = document.getElementById(announcementId);
    if (!region) {
      region = document.createElement('div');
      region.id = announcementId;
      region.className = 'visually-hidden';
      region.setAttribute('role', 'status');
      region.setAttribute('aria-live', 'polite');
      region.setAttribute('aria-atomic', 'true');
      document.body.append(region);
    }

    const label = title || heading?.textContent?.trim() || target.getAttribute('aria-label') || '';
    if (label !== '') {
      region.textContent = '';
      window.requestAnimationFrame(() => {
        region.textContent = Drupal.t('@label atualizado.', { '@label': label });
      });
    }
  }

  Drupal.behaviors.acultaStudioHtmx = {
    attach(context) {
      once('acultaStudioHtmxLifecycle', 'html', context).forEach(() => {
        document.body.addEventListener('htmx:afterSettle', (event) => {
          const target = event.detail?.target;
          if (!(target instanceof Element)) {
            return;
          }
          const titleElement = firstMatch(target, '[data-aculta-title]');
          const title = titleElement?.getAttribute('data-aculta-title')?.trim();
          if (title) {
            document.title = title;
          }
          const heading = focusAfterSwap(target);
          announceSwap(target, title, heading);
        });
      });
    },
  };
})(Drupal, once);
