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

      once('aculta420V2UtilityDialog', 'dialog.aculta-utility-dialog', context).forEach((dialog) => {
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
