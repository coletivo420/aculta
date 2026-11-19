/**
 * Dismisses a member's site notice and updates its visible unread counter.
 * The delegated Drupal behavior also handles notices loaded through AJAX.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaAlertsDismiss = {
    attach(context) {
      once('acultaAlertsDismissListener', 'html', context).forEach(() => {
        document.addEventListener('click', (event) => {
          if (!(event.target instanceof Element)) {
            return;
          }
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
      });
    },
  };
})(Drupal, once);
