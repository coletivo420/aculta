/**
 * ACULTA Studio: navegação em quatro níveis pelo framework AJAX do Drupal.
 *
 * Cada nível tem URL própria (/studio, /studio/{área}, /studio/{área}/{seção}, /studio/{área}/{seção}/{aba}).
 * O link é um Drupal.Ajax que recebe a página do Studio em drupal_ajax e substitui a raiz
 * [data-aculta-studio="root"]. A URL muda com pushState; voltar e avançar refazem a troca; o foco vai para
 * o título do nível novo; a região [data-aculta-studio="live"] (aria-live) anuncia carregamento e falhas.
 * Se o AJAX falhar, a navegação segue pela própria URL do link (sem JavaScript, tudo continua funcionando).
 * Comportamento próprio do Studio: não usa o shell, o menu nem o account-navigation.js da Minha conta.
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

  const announce = (root, message) => {
    const live = root?.querySelector('[data-aculta-studio="live"]');
    if (live) live.textContent = message;
  };

  const focusHeading = (root) => {
    const heading = root?.querySelector('[data-aculta-studio="heading"]');
    if (heading) {
      heading.setAttribute('tabindex', '-1');
      heading.focus();
    }
  };

  // Troca a raiz do Studio pela resposta do nível pedido. Em falha, segue pela URL comum do link.
  const navigate = (anchor, url, push) => {
    const root = document.getElementById(ROOT_ID);
    if (!anchor.id) {
      sequence += 1;
      anchor.id = 'aculta-studio-link-' + sequence;
    }
    announce(root, Drupal.t('Carregando…'));
    const ajax = new Drupal.Ajax(anchor.id, anchor, {
      url: withWrapperFormat(url),
      httpMethod: 'GET',
      event: 'click',
      wrapper: ROOT_ID,
      method: 'replaceWith',
      progress: { type: 'none' },
      effect: 'none',
    });
    const success = ajax.success.bind(ajax);
    ajax.success = (response, status) => Promise.resolve(success(response, status)).then((result) => {
      if (push) window.history.pushState({ acultaStudio: true }, '', url);
      const updated = document.getElementById(ROOT_ID);
      announce(updated, '');
      focusHeading(updated);
      return result;
    });
    const failure = ajax.error.bind(ajax);
    ajax.error = (xhr, uri, message) => {
      announce(document.getElementById(ROOT_ID), Drupal.t('Não foi possível carregar esta área. Abrindo a página completa.'));
      window.location.assign(url);
      return failure(xhr, uri, message);
    };
    ajax.execute();
  };

  // Um único listener de voltar/avançar, mesmo depois de muitas trocas de raiz.
  let historyListening = false;

  Drupal.behaviors.acultaStudioNavigation = {
    attach(context) {
      if (!historyListening) {
        historyListening = true;
        window.addEventListener('popstate', () => {
          const current = document.getElementById(ROOT_ID);
          if (current && (window.location.pathname.startsWith('/ganjalab') || window.location.pathname.startsWith('/studio'))) navigate(current, window.location.href, false);
        });
      }
      once('aculta-studio-navigation', `#${ROOT_ID}`, context).forEach((root) => {
        root.addEventListener('click', (event) => {
          if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
          const anchor = event.target.closest('a[data-aculta-studio="tab"], a[data-aculta-studio="section"], a[data-aculta-studio="subtab"]');
          if (!anchor) return;
          const target = new URL(anchor.href, window.location.href);
          if (target.pathname === window.location.pathname) return;
          event.preventDefault();
          navigate(anchor, anchor.href, true);
        });
      });
    },
  };

})(Drupal, once);
