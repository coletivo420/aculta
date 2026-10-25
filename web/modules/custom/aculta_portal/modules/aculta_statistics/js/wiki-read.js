(function (Drupal, drupalSettings, once) {
  'use strict';

  /**
   * Envia uma leitura por página de verbete, sem cookie nem dado pessoal.
   *
   * `sendBeacon` não espera resposta nem bloqueia a navegação. Sem ele, usa `fetch` com `keepalive`.
   */
  Drupal.behaviors.acultaWikiRead = {
    attach(context) {
      const settings = drupalSettings.acultaStatistics && drupalSettings.acultaStatistics.wikiRead;
      if (!settings) {
        return;
      }
      once('aculta-wiki-read', 'body', context).forEach(() => {
        if (navigator.sendBeacon && navigator.sendBeacon(settings.url)) {
          return;
        }
        fetch(settings.url, { method: 'POST', keepalive: true, credentials: 'same-origin' });
      });
    },
  };

})(Drupal, drupalSettings, once);
