((Drupal, once) => {
  Drupal.behaviors.donationFlowDesignated = {
    attach(context) {
      const check = once(
        'donationFlowDesignated',
        'input[data-drupal-selector="edit-commerce-donation-pane-field-designated-value"]',
        context,
      ).shift();
      const memorial = context.querySelector(
        'fieldset.checkout-pane-commerce-memorial-pane',
      );
      if (check instanceof HTMLInputElement) {
        // The checkbox is found and not previously processed.
        if (memorial instanceof HTMLElement) {
          const toggle = () => memorial.classList.toggle('hidden');
          // The memorial pane is present on this step.
          toggle(check.checked);
          check.addEventListener('click', (event) => {
            toggle(event.currentTarget.checked);
          });
        } else {
          // The memorial pane is not present on this step.
          const submitButton = context.querySelector(
            'input[data-drupal-selector="edit-actions-next"]',
          );
          if (submitButton instanceof HTMLInputElement) {
            submitButton.dataset.defaultValue = submitButton.value;
            const toggleValue = (on) => {
              const defaultState =
                submitButton.dataset.defaultValue === submitButton.value;
              if (on && defaultState) {
                submitButton.value = Drupal.t('Continue to Dedication');
              } else if (!on && !defaultState) {
                submitButton.value = submitButton.dataset.defaultValue;
              }
            };
            toggleValue(check.checked);
            check.addEventListener('click', (event) => {
              toggleValue(event.currentTarget.checked);
            });
          }
        }
      }
    },
  };
})(Drupal, once);
