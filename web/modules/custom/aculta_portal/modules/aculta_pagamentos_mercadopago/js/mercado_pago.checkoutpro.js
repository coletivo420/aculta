/**
 * @file
 * Legacy script for Mercado Pago Wallet/checkout on a form with id
 * commerce-mercado-pago-form. Not used by the current redirect/popup flow;
 * kept for potential future Wallet Brick (SDK) integration.
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.offsiteForm = {

    attach: function (context) {

      once('mp-processed', '#commerce-mercado-pago-form', context).forEach(
        function () {

          var data = drupalSettings.commerceMercadoPago;

          var preference_id = data.preference_id;
          var public_key = data.public_key;

          const mp = new MercadoPago(public_key);

          mp.checkout({
            preference: {
              id: preference_id
            },
            autoOpen: true
          });

          window.addEventListener("message", (event) => {
            if (!event.origin.includes("mercadopago.com")) {
              return;
            }
            // Handle specific events like 'success', 'failure' if needed.
          }, false);
        }

      );

    }

  };

}(jQuery, Drupal, drupalSettings));
