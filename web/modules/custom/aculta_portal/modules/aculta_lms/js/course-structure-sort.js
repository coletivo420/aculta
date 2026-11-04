(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaLmsCourseStructureSort = {
    attach(context) {
      once('aculta-lms-course-sort', '[data-aculta-sort-list]', context).forEach((list) => {
        const formId = list.dataset.acultaSortForm;
        const form = formId ? document.getElementById(formId) : null;
        const orderInput = form?.querySelector('[data-aculta-sort-order]');
        const liveRegion = document.createElement('div');
        liveRegion.className = 'visually-hidden';
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        list.parentNode.insertBefore(liveRegion, list.nextSibling);

        if (!form || !orderInput) {
          return;
        }

        form.hidden = true;
        let draggedItem = null;

        const directItems = () => Array.from(list.children)
          .filter((element) => element.tagName === 'LI')
          .map((wrapper) => ({
            wrapper,
            item: wrapper.querySelector('[data-aculta-sort-item]'),
          }))
          .filter(({ item }) => item !== null);

        const synchronizeOrder = () => {
          const ids = directItems().map(({ item }) => item.dataset.acultaSortItem);
          orderInput.value = ids.join(',');
          orderInput.dispatchEvent(new Event('input', { bubbles: true }));
          form.hidden = false;
          liveRegion.textContent = Drupal.t('A nova ordem foi alterada. Salve para aplicá-la.');
        };

        list.querySelectorAll('[data-aculta-sort-handle]').forEach((handle) => {
          handle.addEventListener('dragstart', (event) => {
            draggedItem = handle.closest('[data-aculta-sort-item]')?.closest('li') || null;
            if (!draggedItem) {
              event.preventDefault();
              return;
            }
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', draggedItem.querySelector('[data-aculta-sort-item]').dataset.acultaSortItem);
            draggedItem.classList.add('is-dragging');
          });

          handle.addEventListener('dragend', () => {
            draggedItem?.classList.remove('is-dragging');
            draggedItem = null;
          });
        });

        list.addEventListener('dragover', (event) => {
          if (!draggedItem) {
            return;
          }
          const target = event.target.closest('li');
          if (!target || target.parentElement !== list || target === draggedItem) {
            return;
          }
          event.preventDefault();
          event.dataTransfer.dropEffect = 'move';
        });

        list.addEventListener('drop', (event) => {
          if (!draggedItem) {
            return;
          }
          const target = event.target.closest('li');
          if (!target || target.parentElement !== list || target === draggedItem) {
            return;
          }
          event.preventDefault();
          const bounds = target.getBoundingClientRect();
          const after = event.clientY > bounds.top + bounds.height / 2;
          list.insertBefore(draggedItem, after ? target.nextSibling : target);
          synchronizeOrder();
        });
      });
    },
  };
})(Drupal, once);
