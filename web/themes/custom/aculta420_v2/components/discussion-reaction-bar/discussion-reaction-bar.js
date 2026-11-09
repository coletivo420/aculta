/**
 * @file
 * Interactivity and delegated HTMX lifecycle for discussion reaction picker popover.
 */
((Drupal, once) => {
  'use strict';

  function closeAllPickers() {
    document.querySelectorAll('.discussion-reaction-bar__picker-container').forEach((container) => {
      if (container.children.length > 0) {
        container.innerHTML = '';
        const wrapper = container.closest('.discussion-reaction-bar__picker-wrapper');
        const trigger = wrapper?.querySelector('[data-reaction-picker-trigger]');
        if (trigger) {
          trigger.setAttribute('aria-expanded', 'false');
        }
      }
    });
  }

  Drupal.behaviors.discussionReactionBar = {
    attach(context) {
      once('discussion-reaction-global', 'html', context).forEach(() => {
        // Toggle / close when clicking trigger if already open:
        document.addEventListener('click', (event) => {
          const trigger = event.target.closest('[data-reaction-picker-trigger]');
          if (trigger) {
            const wrapper = trigger.closest('.discussion-reaction-bar__picker-wrapper');
            const container = wrapper?.querySelector('.discussion-reaction-bar__picker-container');
            if (container && container.children.length > 0) {
              // Already open: clicking again closes it (toggle off)
              event.preventDefault();
              event.stopPropagation();
              container.innerHTML = '';
              trigger.setAttribute('aria-expanded', 'false');
              return;
            }
            // Otherwise let HTMX execute data-hx-get
            trigger.setAttribute('aria-expanded', 'true');
            return;
          }

          // Close when clicking close button:
          const closeBtn = event.target.closest('[data-reaction-picker-close]');
          if (closeBtn) {
            event.preventDefault();
            closeAllPickers();
            return;
          }

          // Close when clicking outside of any open picker wrapper:
          if (!event.target.closest('.discussion-reaction-bar__picker-wrapper')) {
            closeAllPickers();
          }
        });

        // Close on Escape key:
        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape') {
            closeAllPickers();
          }
        });
      });
    },
  };
})(Drupal, once);
