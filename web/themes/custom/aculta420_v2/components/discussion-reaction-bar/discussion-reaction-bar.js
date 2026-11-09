/**
 * @file
 * Interactivity for discussion reaction picker popover.
 */
((Drupal) => {
  Drupal.behaviors.discussionReactionBar = {
    attach(context) {
      const triggers = context.querySelectorAll('[data-reaction-picker-trigger]');
      triggers.forEach((trigger) => {
        if (trigger.dataset.reactionPickerBound) {
          return;
        }
        trigger.dataset.reactionPickerBound = 'true';

        const wrapper = trigger.closest('.discussion-reaction-bar__picker-wrapper');
        if (!wrapper) return;
        const popup = wrapper.querySelector('[data-reaction-picker-popup]');
        if (!popup) return;

        trigger.addEventListener('click', (e) => {
          e.stopPropagation();
          const isHidden = popup.hasAttribute('hidden');
          if (isHidden) {
            popup.removeAttribute('hidden');
            trigger.setAttribute('aria-expanded', 'true');
          } else {
            popup.setAttribute('hidden', '');
            trigger.setAttribute('aria-expanded', 'false');
          }
        });

        // Close on escape
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && !popup.hasAttribute('hidden')) {
            popup.setAttribute('hidden', '');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus();
          }
        });

        // Close when clicking outside
        document.addEventListener('click', (e) => {
          if (!wrapper.contains(e.target) && !popup.hasAttribute('hidden')) {
            popup.setAttribute('hidden', '');
            trigger.setAttribute('aria-expanded', 'false');
          }
        });
      });
    },
  };
})(Drupal);
