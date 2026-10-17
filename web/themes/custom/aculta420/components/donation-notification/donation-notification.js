((Drupal, once) => {
  Drupal.behaviors.donationFlowNotification = {
    attach(context) {
      const check = once(
        'donationFlowNotification',
        'input[name="commerce_memorial_pane[field_notify][value]"]',
        context,
      ).shift();
      const recipient = context.querySelector(
        'fieldset.commerce-memorial-pane--recipient',
      );
      if (check instanceof HTMLElement && recipient instanceof HTMLElement) {
        check.addEventListener('click', () => {
          recipient.classList.toggle('hidden');
        });
      }
    },
  };
})(Drupal, once);
