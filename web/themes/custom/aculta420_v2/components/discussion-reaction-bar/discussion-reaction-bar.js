/**
 * @file
 * Interactivity and delegated HTMX lifecycle for discussion reaction picker popover.
 */
((Drupal, once) => {
  'use strict';

  let activeTrigger = null;

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
    if (activeTrigger) {
      activeTrigger.focus();
      activeTrigger = null;
    }
  }

  // Posicionamento dos popovers (+ e seletor): junto ao botão clicado, em qualquer barra da página,
  // inclusive as trocadas por HTMX (não depende de Drupal.attachBehaviors).
  const placePopover = (trigger) => {
    const scope = trigger.closest('.discussion-reaction-bar__list, .discussion-reaction-bar__picker-wrapper');
    const pop = scope ? scope.querySelector('[data-reaction-popover]') : null;
    if (!pop || pop.id !== trigger.getAttribute('popovertarget')) {
      return;
    }
    const r = trigger.getBoundingClientRect();
    const gap = 6;
    const width = pop.offsetWidth || 220;
    const fitsBelow = r.bottom + gap + (pop.offsetHeight || 80) <= window.innerHeight;
    pop.style.position = 'fixed';
    pop.style.margin = '0';
    pop.style.top = `${fitsBelow ? r.bottom + gap : Math.max(8, r.top - gap - (pop.offsetHeight || 80))}px`;
    pop.style.left = `${Math.max(8, Math.min(r.left, window.innerWidth - width - 8))}px`;
  };
  document.addEventListener('click', (event) => {
    const trigger = event.target && event.target.closest ? event.target.closest('[popovertarget]') : null;
    if (trigger && trigger.closest('[data-discussion-reaction-bar]')) {
      placePopover(trigger);
    }
  }, true);

  Drupal.behaviors.discussionReactionBar = {
    attach(context) {
      once('discussion-reaction-global', 'html', context).forEach(() => {
        // Intercepta antes do HTMX (captura) se o picker já estiver aberto para fechar sem nova requisição:
        document.addEventListener('click', (event) => {
          const trigger = event.target && event.target.closest ? event.target.closest('[data-reaction-picker-trigger]') : null;
          if (trigger) {
            const wrapper = trigger.closest('.discussion-reaction-bar__picker-wrapper');
            const container = wrapper?.querySelector('.discussion-reaction-bar__picker-container');
            if (container && container.children.length > 0) {
              // Already open: clicking again closes it (toggle off)
              event.preventDefault();
              event.stopPropagation();
              container.innerHTML = '';
              trigger.setAttribute('aria-expanded', 'false');
              activeTrigger = null;
              return;
            }
            // Close any other open pickers before opening this one
            closeAllPickers();
            activeTrigger = trigger;
            trigger.setAttribute('aria-expanded', 'true');
            return;
          }

          // Close when clicking close button:
          const closeBtn = event.target && event.target.closest ? event.target.closest('[data-reaction-picker-close]') : null;
          if (closeBtn) {
            event.preventDefault();
            closeAllPickers();
            return;
          }

          // Close when clicking outside of any open picker wrapper:
          if (!event.target || !event.target.closest || !event.target.closest('.discussion-reaction-bar__picker-wrapper')) {
            closeAllPickers();
          }
        }, true);

        // Close on Escape key:
        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape') {
            closeAllPickers();
          }
        });

        // Instant search and keyboard navigation in emoji popover:
        document.addEventListener('input', (event) => {
          const searchInput = event.target && event.target.matches ? (event.target.matches('[data-reaction-picker-search]') ? event.target : null) : null;
          if (!searchInput) {
            return;
          }
          const popup = searchInput.closest('[data-reaction-picker-popup]');
          if (!popup) {
            return;
          }
          const query = searchInput.value.trim().toLowerCase();
          const categories = popup.querySelectorAll('[data-reaction-category]');
          let totalVisible = 0;

          categories.forEach((cat) => {
            const buttons = cat.querySelectorAll('.discussion-reaction-bar__palette-btn');
            let catVisible = 0;
            buttons.forEach((btn) => {
              const name = btn.getAttribute('data-emoji-name') || '';
              const keywords = btn.getAttribute('data-emoji-keywords') || '';
              const match = !query || name.includes(query) || keywords.includes(query);
              const form = btn.closest('.discussion-reaction-bar__popup-form');
              if (form) {
                form.style.display = match ? 'inline' : 'none';
              }
              if (match) {
                catVisible++;
              }
            });
            cat.style.display = catVisible > 0 ? '' : 'none';
            totalVisible += catVisible;
          });

          const emptyEl = popup.querySelector('.discussion-reaction-bar__empty');
          if (emptyEl) {
            emptyEl.hidden = totalVisible > 0;
          }
        });

        document.addEventListener('toggle', (event) => {
          const pop = event.target;
          if (!pop || !pop.matches || !pop.matches('[data-reaction-popover]')) {
            return;
          }
          const scope = pop.closest('.discussion-reaction-bar__list, .discussion-reaction-bar__picker-wrapper');
          const trigger = scope ? scope.querySelector(`[popovertarget="${CSS.escape(pop.id)}"]`) : null;
          if (trigger) {
            trigger.setAttribute('aria-expanded', pop.matches(':popover-open') ? 'true' : 'false');
          }
        }, true);

        // Set focus on search input when HTMX populates container:
        document.addEventListener('htmx:afterSwap', (event) => {
          if (event.target && event.target.classList && event.target.classList.contains('discussion-reaction-bar__picker-container')) {
            const search = event.target.querySelector('[data-reaction-picker-search]');
            if (search) {
              search.focus();
            }
          }
        });
      });
    },
  };
})(Drupal, once);
