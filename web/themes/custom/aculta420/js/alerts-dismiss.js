/**
 * "×" on a site notice: the link is a flag link (the native per-member dismissal). The script keeps the count the
 * member sees in step: it lowers data-count (counters.js presents the number) and the label of the bar's button.
 * Delegated at the document, so notices loaded later (by the drawer or by a Drupal AJAX command) work too.
 */
(function (Drupal) {
  'use strict';

  document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-aculta-alert-dismiss]');
    if (!link) {
      return;
    }
    const counter = document.querySelector('[data-aculta-alerts-count]');
    const current = Number.parseInt(counter?.dataset.count || '0', 10);
    if (counter && Number.isFinite(current) && current > 0) {
      const next = current - 1;
      counter.dataset.count = String(next);
      const trigger = counter.closest('button');
      if (trigger) {
        trigger.setAttribute('aria-label', next === 1
          ? Drupal.t('1 aviso ativo ou não lido. Abrir avisos')
          : Drupal.t('@count avisos ativos ou não lidos. Abrir avisos', { '@count': next }));
      }
    }
    link.closest('li')?.remove();
  });

})(Drupal);
