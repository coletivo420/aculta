(function (Drupal, once) {
  'use strict';

  /**
   * Fecha o diálogo aberto (nativo ou jQuery UI), sem jQuery novo.
   */
  function closeTopDialog() {
    const nativeDialogs = [...document.querySelectorAll('dialog[open]')];
    if (nativeDialogs.length > 0) {
      nativeDialogs[nativeDialogs.length - 1].close();
      return;
    }
    const jqueryDialogs = [...document.querySelectorAll('.ui-dialog')]
      .filter((dialog) => dialog.getClientRects().length > 0);
    const dialog = jqueryDialogs[jqueryDialogs.length - 1];
    const close = dialog?.querySelector('.ui-dialog-titlebar-close');
    if (close instanceof HTMLElement) {
      close.click();
    }
  }

  // HX-Trigger do formulário dentro do diálogo: o evento chega ao documento
  // mesmo quando o fragmento trocado já saiu do DOM.
  document.addEventListener('aculta:close-dialog', closeTopDialog);

  // Atualiza contador e rótulo do carrinho a partir do HX-Trigger.
  document.addEventListener('aculta:cart-updated', (event) => {
    const detail = event.detail || {};
    const count = String(detail.count ?? '');
    if (count !== '') {
      document.querySelectorAll('[data-aculta-cart-count]').forEach((element) => {
        element.textContent = Number.parseInt(count, 10) > 99 ? '99+' : count;
        element.setAttribute('data-count', count);
      });
    }
    if (detail.label) {
      document.querySelectorAll('[data-aculta-cart-trigger]').forEach((element) => {
        element.setAttribute('aria-label', detail.label);
      });
    }
  });

  Drupal.behaviors.acultaHtmxDialogs = {
    attach(context) {
      once('acultaHtmxDialogClose', '[data-aculta-dialog-close]', context).forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          closeTopDialog();
        });
      });
    },
  };
})(Drupal, once);
