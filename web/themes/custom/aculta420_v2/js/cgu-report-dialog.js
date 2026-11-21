(function (Drupal, once) {
  'use strict';

  /**
   * Restores keyboard focus after CGU's Form API form is loaded into a popup.
   *
   * The generic ACULTA dialog owns opening/closing and focus trapping.
   * This behavior only moves focus when the authorized CGU form arrives.
   */
  Drupal.behaviors.acultaCguReportDialog = {
    attach(context) {
      once('acultaCguReportDialog', 'dialog[data-aculta-cgu-report-dialog]', context).forEach((dialog) => {
        dialog.addEventListener('htmx:afterSwap', (event) => {
          if (!dialog.open || !(event.target instanceof Element)
            || !event.target.closest('[data-aculta-cgu-report-body]')) {
            return;
          }
          const form = dialog.querySelector('form[data-aculta-cgu-report-form]');
          if (!(form instanceof HTMLFormElement)) {
            return;
          }
          const focusTarget = form.querySelector('[aria-invalid="true"]')
            || form.querySelector('select[name="reason"]')
            || form.querySelector('textarea[name="description"]');
          if (focusTarget instanceof HTMLElement) {
            focusTarget.focus();
          }
        });
      });
    },
  };
})(Drupal, once);
