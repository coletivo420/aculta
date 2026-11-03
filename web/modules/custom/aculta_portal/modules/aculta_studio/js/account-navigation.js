(function (Drupal, once) {
  'use strict';

  const ROOT = '[data-aculta-account-root]';
  let sequence = 0;
  let requestVersion = 0;
  let popstateBound = false;
  let navigationOpen = true;
  let timeoutHandle = 0;
  const eligible = (event, link) => !event.defaultPrevented && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey && !link.target && !link.hasAttribute('download') && link.origin === window.location.origin;
  const announce = (message) => {
    const live = document.querySelector(`${ROOT} [data-aculta-account-live]`);
    if (live) live.textContent = message;
  };
  const chrome = () => {
    const metadata = document.querySelector('[data-portal-chrome]');
    if (!metadata) return;
    const title = metadata.getAttribute('data-portal-page-title') || document.title;
    document.title = `${title} | Conta ACULTA`;
    const crumb = metadata.querySelector('ol');
    const target = document.querySelector('[data-portal-breadcrumb]');
    if (crumb && target) target.replaceChildren(...Array.from(crumb.children, (node) => node.cloneNode(true)));
    metadata.remove();
    const heading = document.querySelector(`${ROOT} h1, ${ROOT} h2`);
    if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus(); }
  };
  const load = (link, url, push) => {
    const root = document.querySelector(ROOT);
    if (!root) { window.location.assign(url); return; }
    const version = ++requestVersion;
    window.clearTimeout(timeoutHandle);
    if (!link.id) link.id = `aculta-account-link-${++sequence}`;
    root.setAttribute('aria-busy', 'true');
    announce(Drupal.t('Carregando seção…'));
    const requestUrl = new URL(url, window.location.href);
    requestUrl.searchParams.set('_wrapper_format', 'drupal_ajax');
    const ajax = new Drupal.Ajax(link.id, link, {
      url: requestUrl.pathname + requestUrl.search,
      httpMethod: 'GET', event: 'click', wrapper: root.id || '', method: 'replaceWith',
      progress: { type: 'none' }, effect: 'none',
    });
    const success = ajax.success.bind(ajax);
    ajax.success = (response, status) => {
      window.clearTimeout(timeoutHandle);
      if (version !== requestVersion) return Promise.resolve();
      return Promise.resolve(success(response, status)).then((result) => {
        if (version !== requestVersion) return result;
        const currentRoot = document.querySelector(ROOT);
        currentRoot?.removeAttribute('aria-busy');
        const navigation = currentRoot?.querySelector('[data-portal-ui="studio-account-navigation"]');
        if (navigation) navigation.open = navigationOpen;
        if (push) history.pushState({ acultaAccount: true }, '', url);
        document.querySelectorAll('[data-aculta-account-link]').forEach((item) => {
          if (new URL(item.href).pathname === new URL(url, location.href).pathname) item.setAttribute('aria-current', 'page');
          else item.removeAttribute('aria-current');
        });
        chrome();
        announce('');
        return result;
      });
    };
    const failure = ajax.error.bind(ajax);
    ajax.error = (xhr, uri, message) => {
      window.clearTimeout(timeoutHandle);
      if (version !== requestVersion) return;
      const currentRoot = document.querySelector(ROOT);
      currentRoot?.removeAttribute('aria-busy');
      announce(Drupal.t('Não foi possível carregar a seção. Use o link para abrir a página completa.'));
      if (currentRoot) {
        let retry = currentRoot.querySelector('[data-aculta-account-retry]');
        if (!retry) {
          retry = document.createElement('button'); retry.type = 'button'; retry.dataset.acultaAccountRetry = '';
          retry.textContent = Drupal.t('Tentar novamente'); currentRoot.append(retry);
        }
        retry.onclick = () => load(link, url, push);
      }
      return failure(xhr, uri, message);
    };
    ajax.execute();
    timeoutHandle = window.setTimeout(() => {
      if (version !== requestVersion) return;
      requestVersion++;
      const currentRoot = document.querySelector(ROOT);
      currentRoot?.removeAttribute('aria-busy');
      announce(Drupal.t('A solicitação demorou demais. Tente novamente ou abra a página completa.'));
      const retry = currentRoot?.querySelector('[data-aculta-account-retry]');
      if (retry) retry.hidden = false;
      else if (currentRoot) {
        const fallback = document.createElement('a');
        fallback.href = url;
        fallback.textContent = Drupal.t('Abrir seção');
        fallback.dataset.acultaAccountFallback = '';
        currentRoot.append(fallback);
      }
    }, 15000);
  };
  Drupal.behaviors.acultaStudioAccountNavigation = {
    attach(context) {
      once('aculta-studio-account-root', ROOT, context).forEach((root) => {
        if (!root.id) root.id = 'aculta-studio-account-root';
        const navigation = root.querySelector('[data-portal-ui="studio-account-navigation"]');
        if (navigation) {
          navigation.open = navigationOpen;
          navigation.addEventListener('toggle', () => { navigationOpen = navigation.open; });
        }
        root.addEventListener('click', (event) => {
          const link = event.target.closest('a[data-aculta-account-link]');
          if (!link || !eligible(event, link) || link.getAttribute('aria-current') === 'page') return;
          event.preventDefault();
          load(link, link.href, true);
        });
      });
      if (!popstateBound) {
        popstateBound = true;
        window.addEventListener('popstate', () => {
          const root = document.querySelector(ROOT);
          if (!root) return;
          const link = Array.from(root.querySelectorAll('a[data-aculta-account-link]')).find((item) => new URL(item.href).pathname === location.pathname);
          if (link) load(link, location.href, false); else location.reload();
        });
      }
    },
  };
})(Drupal, once);
