/**
 * @file
 * Opens Mercado Pago Checkout Pro in a popup window (modal-like).
 *
 * On form submit we prevent the default and open init_point in a popup so that
 * when the user returns, our intermediate page closes the popup and redirects
 * the opener—only one tab remains open.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.commerceMercadoPagoCheckoutPopup = {
    attach: function (context) {
      var config = drupalSettings.commerceMercadoPago && drupalSettings.commerceMercadoPago.checkoutPopup;
      if (!config || !config.initPoint) {
        return;
      }

      once('mercado-pago-checkout-popup', '.mercado-pago-checkout-popup', context).forEach(function (form) {
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          var w = 1000;
          var h = 840;
          var left = (screen.width - w) / 2;
          var top = (screen.height - h) / 2;
          window.open(
            config.initPoint,
            'mercado_pago_checkout',
            'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes'
          );
          return false;
        });
      });
    }
  };
})(Drupal, drupalSettings, once);
