/**
 * Caixa de status do carrinho (módulo aculta_portal): botão de fechar.
 *
 * Comportamento só. A aparência da caixa é do tema do site; sem o tema o botão continua funcionando.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaPortalCartStatus = {
    attach(context) {
      once('portal-cart-status', '[data-portal-cart-status-close]', context).forEach((button) => {
        button.addEventListener('click', () => {
          button.closest('[data-portal-cart-status]')?.remove();
        });
      });
    },
  };
})(Drupal, once);
