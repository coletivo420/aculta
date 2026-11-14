/**
 * ACULTA Studio Marley: navegação editorial em quatro níveis por HTMX.
 *
 * Cada nível tem URL própria (/marley e seus níveis de área, seção e aba).
 * O link é um link real; o HTMX carrega o fragmento da raiz
 * [data-aculta-studio="root"], troca o elemento e atualiza a URL com
 * hx-push-url. O foco, o título e o anúncio após a troca ficam em
 * studio-htmx.js, do tema. Sem JavaScript, o href abre a página completa.
 *
 * Comportamento próprio do Studio: não usa o shell, o menu nem o
 * account-navigation.js da Minha conta.
 */
(function (Drupal, htmx, once) {
  'use strict';

  const ROOT_ID = 'aculta-studio-root';
  const LINK_SELECTOR = [
    'a[data-aculta-studio="tab"]',
    'a[data-aculta-studio="section"]',
    'a[data-aculta-studio="subtab"]',
  ].join(', ');

  Drupal.behaviors.acultaStudioNavigation = {
    attach(context) {
      once('aculta-studio-navigation', `#${ROOT_ID}`, context).forEach((root) => {
        root.querySelectorAll(LINK_SELECTOR).forEach((link) => {
          if (link.hasAttribute('data-hx-get')) {
            return;
          }
          link.setAttribute('data-hx-get', link.href);
          link.setAttribute('data-hx-trigger', 'click');
          link.setAttribute('data-hx-target', `#${ROOT_ID}`);
          link.setAttribute('data-hx-select', `#${ROOT_ID}`);
          link.setAttribute('data-hx-swap', 'outerHTML');
          link.setAttribute('data-hx-push-url', 'true');
          link.setAttribute('data-hx-sync', 'this:replace');
          link.setAttribute('data-hx-drupal-only-main-content', 'true');
          htmx.process(link);
        });
      });
    },
  };
})(Drupal, htmx, once);
