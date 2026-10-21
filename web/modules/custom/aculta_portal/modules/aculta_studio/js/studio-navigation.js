/**
 * ACULTA Studio: troca de área (abas superiores) pelo framework AJAX do Drupal.
 *
 * Três níveis de navegação (área, seção do submenu e aba da seção), todos pelo mesmo framework.
 * Comportamento próprio do Studio: não usa o shell, o menu nem o account-navigation.js da Minha conta.
 * O link de uma aba é um Drupal.Ajax que recebe a página do Studio (drupal_ajax) e substitui a raiz
 * [data-aculta-studio="root"] pelo mesmo framework. URL (pushState), aba ativa e foco acompanham a troca.
 * Voltar e avançar refazem a requisição. Sem JavaScript, as abas são links normais para a página completa.
 */
(function (Drupal, once) {
  'use strict';

  const ROOT_ID = 'aculta-studio-root';
  let sequence = 0;

  const withWrapperFormat = (url) => {
    const target = new URL(url, window.location.href);
    target.searchParams.set('_wrapper_format', 'drupal_ajax');
    return target.pathname + target.search;
  };

  // Troca a raiz do Studio pela resposta da área pedida. onDone roda depois que o Drupal aplicou os comandos.
  const requestInto = (anchor, url, onDone) => {
    if (!anchor.id) {
      sequence += 1;
      anchor.id = 'aculta-studio-link-' + sequence;
    }
    const ajax = new Drupal.Ajax(anchor.id, anchor, {
      url: withWrapperFormat(url),
      httpMethod: 'GET',
      event: 'click',
      wrapper: ROOT_ID,
      method: 'replaceWith',
      progress: { type: 'throbber' },
      effect: 'none',
    });
    const success = ajax.success.bind(ajax);
    ajax.success = (response, status) => Promise.resolve(success(response, status)).then((result) => {
      if (onDone) onDone();
      return result;
    });
    ajax.execute();
  };

  const navigate = (anchor, url, push) => {
    requestInto(anchor, url, () => {
      if (push) window.history.pushState({ acultaStudio: true }, '', url);
      const root = document.getElementById(ROOT_ID);
      root?.querySelector('[data-aculta-studio="heading"]')?.setAttribute('tabindex', '-1');
      root?.querySelector('[data-aculta-studio="heading"]')?.focus();
    });
  };

  Drupal.behaviors.acultaStudioNavigation = {
    attach(context) {
      once('aculta-studio-navigation', `#${ROOT_ID}`, context).forEach((root) => {
        root.addEventListener('click', (event) => {
          if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
          const anchor = event.target.closest('a[data-aculta-studio="tab"], a[data-aculta-studio="section"], a[data-aculta-studio="subtab"]');
          if (!anchor) return;
          event.preventDefault();
          const target = new URL(anchor.href, window.location.href);
          if (target.pathname === window.location.pathname && target.search === window.location.search) return;
          navigate(anchor, anchor.href, true);
        });
        window.addEventListener('popstate', () => {
          navigate(root, window.location.href, false);
        });
      });
    },
  };

})(Drupal, once);
