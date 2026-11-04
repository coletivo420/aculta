(function (Drupal, once) {
  'use strict';

  const selector = '[data-aculta-unsaved-changes]';

  Drupal.behaviors.acultaLmsUnsavedChanges = {
    attach() {
      if (!once('aculta-lms-unsaved-changes', 'html').length) {
        return;
      }

      document.addEventListener('input', markChanged, true);
      document.addEventListener('change', markChanged, true);
      document.addEventListener('submit', clearSubmittedForm, true);
      window.addEventListener('beforeunload', warnBeforeLeave);
    },
  };

  function markChanged(event) {
    const form = event.target.closest?.(selector);
    if (form) {
      form.dataset.acultaChanged = 'true';
    }
  }

  function clearSubmittedForm(event) {
    const form = event.target.closest?.(selector);
    if (form) {
      form.dataset.acultaChanged = 'false';
    }
  }

  function warnBeforeLeave(event) {
    const hasChanges = Array.from(document.querySelectorAll(selector))
      .some((form) => form.dataset.acultaChanged === 'true');
    if (hasChanges) {
      event.preventDefault();
      event.returnValue = '';
    }
  }
})(Drupal, once);
