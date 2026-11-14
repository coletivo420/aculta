/**
 * Counter presentation: badges show 99+ above 99.
 *
 * The number stays in data-count (exact, for scripts and assistive labels). Some updates write the number
 * straight into the badge through AJAX commands, so the text is re-applied whenever data-count changes.
 */
(function (Drupal, once) {
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

  function updateFromHtmx(event) {
    const source = event.detail?.elt;
    const drawer = source instanceof Element ? source.closest('[data-aculta-utility-drawer]') : null;
    if (!drawer) {
      return;
    }
    const type = drawer.dataset.acultaDrawerCount || 'messages';
    const header = type === 'alerts' ? 'X-Aculta-Alerts-Count' : 'X-Aculta-Messages-Count';
    const count = Number.parseInt(event.detail.xhr?.getResponseHeader(header) || '', 10);
    if (!Number.isFinite(count) || count < 0) {
      return;
    }
    const selector = type === 'alerts' ? '[data-aculta-alerts-count]' : '[data-aculta-messenger-count]';
    const counter = document.querySelector(selector);
    if (!counter) {
      return;
    }
    counter.dataset.count = String(count);
    const trigger = counter.closest('button');
    if (!trigger) {
      return;
    }
    const label = type === 'messages'
      ? (count === 0 ? Drupal.t('Abrir mensagens') : count === 1
        ? Drupal.t('1 conversa não lida. Abrir mensagens')
        : Drupal.t('@count conversas não lidas. Abrir mensagens', { '@count': count }))
      : (count === 0 ? Drupal.t('Abrir avisos') : trigger.dataset.acultaCountKind === 'attention'
        ? (count === 1 ? Drupal.t('1 aviso ativo ou não lido. Abrir avisos')
          : Drupal.t('@count avisos ativos ou não lidos. Abrir avisos', { '@count': count }))
        : (count === 1 ? Drupal.t('1 aviso ativo. Abrir avisos')
          : Drupal.t('@count avisos ativos. Abrir avisos', { '@count': count })));
    trigger.setAttribute('aria-label', label);
  }

  Drupal.behaviors.acultaCounters = {
    attach(context) {
      once('acultaCountersObserver', 'html', context).forEach(() => {
        presentAll(document);
        document.addEventListener('htmx:afterSettle', updateFromHtmx);
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
      });
    },
  };
})(Drupal, once);
