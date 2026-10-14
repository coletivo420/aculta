(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaMessengerDrawers = {
    attach(context) {
      once('aculta-messenger-drawer', '[data-aculta-drawer-url]', context).forEach((drawer) => {
        let request;

        drawer.addEventListener('show.bs.offcanvas', () => {
          const content = drawer.querySelector('[data-aculta-drawer-content]');
          const endpoint = drawer.dataset.acultaDrawerUrl;
          if (!content || !endpoint) {
            return;
          }

          request?.abort();
          const activeRequest = new AbortController();
          request = activeRequest;
          content.setAttribute('aria-busy', 'true');

          fetch(endpoint, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: activeRequest.signal,
          })
            .then((response) => {
              if (!response.ok) {
                throw new Error('Drawer request failed.');
              }
              return response.text();
            })
            .then((html) => {
              const template = document.createElement('template');
              template.innerHTML = html;
              content.replaceChildren(template.content.cloneNode(true));
              Drupal.attachBehaviors(content);
            })
            .catch((error) => {
              if (error.name !== 'AbortError') {
                content.replaceChildren(document.createTextNode(drawer.dataset.acultaDrawerLabel || Drupal.t('Não foi possível carregar.')));
              }
            })
            .finally(() => {
              if (request === activeRequest) {
                content.setAttribute('aria-busy', 'false');
              }
            });
        });

        drawer.addEventListener('hide.bs.offcanvas', () => request?.abort());
      });
    },
  };
})(Drupal, once);
