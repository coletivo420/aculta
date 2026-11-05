(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaUtilityDialogs = {
    attach(context) {
      once('aculta420V2UtilityDialogTrigger', '[data-aculta-dialog-trigger]', context).forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
          const dialogId = trigger.getAttribute('aria-controls');
          const dialog = dialogId ? document.getElementById(dialogId) : null;
          if (!(dialog instanceof HTMLDialogElement)) {
            return;
          }
          event.preventDefault();
          if (!dialog.open) {
            dialog.showModal();
            trigger.setAttribute('aria-expanded', 'true');
            dialog.dispatchEvent(new CustomEvent('aculta:drawer:open'));
          }
        });
      });

      once('aculta420V2UtilityDialog', 'dialog[data-aculta-utility-drawer]', context).forEach((dialog) => {
        dialog.addEventListener('keydown', (event) => {
          if (event.key !== 'Tab') {
            return;
          }

          const focusable = [...dialog.querySelectorAll(
            'a[href], area[href], button, input:not([type="hidden"]), select, summary, textarea, [contenteditable="true"], [tabindex]:not([tabindex="-1"])',
          )].filter((element) => element.getClientRects().length > 0 && !element.matches(':disabled'));
          if (focusable.length === 0) {
            event.preventDefault();
            dialog.focus();
            return;
          }

          const first = focusable[0];
          const last = focusable[focusable.length - 1];
          if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog)) {
            event.preventDefault();
            last.focus();
          }
          else if (!event.shiftKey && (document.activeElement === last || document.activeElement === dialog)) {
            event.preventDefault();
            first.focus();
          }
        });
        dialog.addEventListener('click', (event) => {
          if (event.target === dialog) {
            dialog.close();
          }
        });
        dialog.addEventListener('close', () => {
          document.querySelectorAll(`[aria-controls="${CSS.escape(dialog.id)}"]`).forEach((trigger) => {
            trigger.setAttribute('aria-expanded', 'false');
          });
        });
      });

      once('aculta420V2UtilityDialogClose', '[data-aculta-dialog-close]', context).forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          button.closest('dialog')?.close();
        });
      });
    },
  };
})(Drupal, once);
