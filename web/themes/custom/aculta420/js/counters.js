/**
 * Counter presentation: badges show 99+ above 99.
 *
 * The number stays in data-count (exact, for scripts and assistive labels). Some updates write the number
 * straight into the badge through AJAX commands, so the text is re-applied whenever data-count changes.
 */
(function () {
  'use strict';

  const LIMIT = 99;
  const SELECTOR = '[data-aculta-cart-count], [data-aculta-messenger-count], [data-aculta-alerts-count]';

  function present(counter) {
    const value = Number.parseInt(counter.dataset.count || '', 10);
    if (!Number.isFinite(value)) {
      return;
    }
    const text = value > LIMIT ? `${LIMIT}+` : String(value);
    if (counter.textContent !== text) {
      counter.textContent = text;
    }
  }

  function presentAll(root) {
    root.querySelectorAll(SELECTOR).forEach(present);
  }

  document.addEventListener('DOMContentLoaded', () => presentAll(document));
  presentAll(document);

  new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.type === 'attributes' && mutation.target.matches(SELECTOR)) {
        present(mutation.target);
      }
      else if (mutation.type === 'childList') {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType === Node.ELEMENT_NODE) {
            if (node.matches(SELECTOR)) {
              present(node);
            }
            presentAll(node);
          }
        });
      }
    });
  }).observe(document.body || document.documentElement, {
    attributes: true,
    attributeFilter: ['data-count'],
    childList: true,
    subtree: true,
  });
})();
