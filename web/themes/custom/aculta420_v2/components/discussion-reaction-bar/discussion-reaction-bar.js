/**
 * @file
 * Native popover positioning and state for the integrated reaction palette.
 */
(() => {
  'use strict';

  const placePopover = (trigger) => {
    const scope = trigger.closest('.discussion-reaction-bar__palette-wrapper');
    const popover = scope?.querySelector('[data-reaction-popover]');
    if (!popover || popover.id !== trigger.getAttribute('popovertarget')) {
      return;
    }

    const rect = trigger.getBoundingClientRect();
    const gap = 6;
    const width = popover.offsetWidth || 220;
    const height = popover.offsetHeight || 80;
    const fitsBelow = rect.bottom + gap + height <= window.innerHeight;
    popover.style.position = 'fixed';
    popover.style.margin = '0';
    popover.style.top = `${fitsBelow ? rect.bottom + gap : Math.max(8, rect.top - gap - height)}px`;
    popover.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - width - 8))}px`;
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target?.closest?.('[popovertarget]');
    if (trigger?.closest('[data-discussion-reaction-bar]')) {
      placePopover(trigger);
    }
  }, true);

  document.addEventListener('toggle', (event) => {
    const popover = event.target;
    if (!popover?.matches?.('[data-reaction-popover]')) {
      return;
    }
    const scope = popover.closest('.discussion-reaction-bar__palette-wrapper');
    const trigger = scope?.querySelector(`[popovertarget="${CSS.escape(popover.id)}"]`);
    trigger?.setAttribute('aria-expanded', popover.matches(':popover-open') ? 'true' : 'false');
  }, true);
})();
