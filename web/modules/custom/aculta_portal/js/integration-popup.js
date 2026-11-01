/**
 * @file
 * Abre integrações externas num popup centralizado (padrão de retorno do ACULTA).
 *
 * Links com o atributo data-aculta-popup abrem num popup. O retorno da integração
 * (PopupReturnPage) leva esta janela ao destino e fecha o popup. Se o navegador
 * bloquear o popup, o link segue normalmente na mesma aba. Não usar noopener: o
 * retorno precisa de window.opener.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaIntegrationPopup = {
    attach: function (context) {
      once('aculta-integration-popup', '[data-aculta-popup]', context).forEach(function (link) {
        link.addEventListener('click', function (event) {
          var w = 1000;
          var h = 840;
          var left = Math.max(0, (window.screen.width - w) / 2);
          var top = Math.max(0, (window.screen.height - h) / 2);
          var popup = window.open(
            link.href,
            link.getAttribute('data-aculta-popup') || 'aculta_integracao',
            'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes'
          );
          if (popup) {
            event.preventDefault();
            popup.focus();
          }
        });
      });
    }
  };
})(Drupal, once);
