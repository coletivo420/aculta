/**
 * Navegação da Minha Conta do Studio por HTMX.
 *
 * Os links são reais; o HTMX carrega o fragmento da raiz
 * [data-aculta-account-root], troca o elemento e atualiza a URL com
 * hx-push-url. O título e a trilha são atualizados a partir do fragmento
 * após a troca; foco e anúncios ficam em studio-htmx.js, do tema. Sem
 * JavaScript, o href abre a página completa.
 */
(function (Drupal, htmx, once) {
  'use strict';

  const ROOT = '[data-aculta-account-root]';
  const LINK = `${ROOT} a[data-aculta-account-link]`;
  let navigationOpen = true;

  const chrome = () => {
    const metadata = document.querySelector('[data-portal-chrome]');
    if (!metadata) {
      return;
    }
    const title = metadata.getAttribute('data-portal-page-title') || document.title;
    document.title = `${title} | Conta ACULTA`;
    metadata.remove();
  };

  Drupal.behaviors.acultaStudioAccountNavigation = {
    attach(context) {
      once('aculta-studio-account-root', ROOT, context).forEach((root) => {
        if (!root.id) {
          root.id = 'aculta-studio-account-root';
        }
        const navigation = root.querySelector('[data-portal-ui="studio-account-navigation"]');
        if (navigation) {
          navigation.open = navigationOpen;
          navigation.addEventListener('toggle', () => {
            navigationOpen = navigation.open;
          });
        }
      });

      document.querySelectorAll(LINK).forEach((link) => {
        if (link.hasAttribute('data-hx-get')) {
          return;
        }
        link.setAttribute('data-hx-get', link.href);
        link.setAttribute('data-hx-trigger', 'click');
        link.setAttribute('data-hx-target', ROOT);
        link.setAttribute('data-hx-select', ROOT);
        link.setAttribute('data-hx-swap', 'outerHTML');
        link.setAttribute('data-hx-push-url', 'true');
        link.setAttribute('data-hx-sync', 'this:replace');
        link.setAttribute('data-hx-drupal-only-main-content', 'true');
        htmx.process(link);
      });
    },
  };

  once('aculta-studio-account-settle', 'html', document).forEach(() => {
    document.body.addEventListener('htmx:afterSettle', (event) => {
      const target = event.detail?.target;
      if (target instanceof Element && target.matches(ROOT)) {
        chrome();
      }
    });
  });
})(Drupal, htmx, once);
