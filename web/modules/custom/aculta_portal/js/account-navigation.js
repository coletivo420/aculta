(function (Drupal, once) {
  'use strict';

  /**
   * Navegação da Minha conta pelo framework AJAX do Drupal.
   *
   * O link é um Drupal.Ajax: a resposta (AjaxResponse do Core) traz o conteúdo da seção no #portal-account-body
   * e as bibliotecas e drupalSettings da página, e o Drupal faz attachBehaviors. Por isso formulários com #ajax
   * (CEP, Número) e os demais funcionam depois da navegação. Sem recarregar a página: URL (pushState), título,
   * menu ativo e foco acompanham a troca. Voltar e avançar do navegador refazem a mesma requisição.
   */
  const BODY_ID = 'portal-account-body';
  const mobileQuery = window.matchMedia('(max-width: 767.98px)');
  let sequence = 0;

  const withWrapperFormat = (url) => {
    const target = new URL(url, window.location.href);
    target.searchParams.set('_wrapper_format', 'drupal_ajax');
    return target.pathname + target.search;
  };

  const updateMenu = (url) => {
    const pathname = new URL(url, window.location.href).pathname;
    document.querySelectorAll('.portal-account__menu a, .portal-account__accordion a').forEach((item) => {
      const current = new URL(item.href, window.location.href).pathname === pathname;
      if (current) item.setAttribute('aria-current', 'page');
      else item.removeAttribute('aria-current');
    });
  };

  const focusHeading = () => {
    const body = document.getElementById(BODY_ID);
    const heading = body?.querySelector('h1, h2, h3');
    if (heading) {
      heading.setAttribute('tabindex', '-1');
      heading.focus();
    }
  };

  // Faz a requisição AJAX do link e troca o conteúdo do destino (wrapper). onDone roda depois da troca.
  const requestInto = (anchor, url, wrapperId, onDone) => {
    if (!anchor.id) {
      sequence += 1;
      anchor.id = 'portal-account-link-' + sequence;
    }
    const ajax = new Drupal.Ajax(anchor.id, anchor, {
      url: withWrapperFormat(url),
      httpMethod: 'GET',
      event: 'click',
      wrapper: wrapperId,
      method: 'html',
      progress: { type: 'throbber' },
    });
    const success = ajax.success.bind(ajax);
    ajax.success = (response, status) => {
      const result = success(response, status);
      if (onDone) onDone();
      return result;
    };
    ajax.execute();
  };

  // Atualiza o shell para a página nova: seção ativa (um só item), título da seção, breadcrumb e document.title.
  const refreshChrome = (url) => {
    const path = new URL(url, window.location.href).pathname;
    const links = Array.from(document.querySelectorAll('.portal-account__menu a, .portal-account__accordion a'));
    // Item ativo: o link de maior caminho que contém a página (ex.: /pedidos/6 ativa /pedidos).
    let active = null;
    links.forEach((item) => {
      const itemPath = new URL(item.href, window.location.href).pathname;
      const matches = path === itemPath || path.startsWith(itemPath.replace(/\/$/, '') + '/');
      if (matches && (!active || itemPath.length > new URL(active.href, window.location.href).pathname.length)) active = item;
    });
    links.forEach((item) => {
      item.classList.toggle('is-active', item === active);
      if (item === active) item.setAttribute('aria-current', 'page');
      else item.removeAttribute('aria-current');
    });
    const body = document.getElementById(BODY_ID);
    const pageHeading = body?.querySelector('h1, h2, h3')?.textContent.trim() || '';
    const sectionTitle = active ? active.textContent.trim() : pageHeading;
    const sectionHeading = document.getElementById('portal-account-section-title');
    if (sectionHeading) sectionHeading.textContent = sectionTitle;
    const crumbs = document.querySelector('nav[aria-label="breadcrumb"] ol, ol.breadcrumb');
    if (crumbs) {
      const items = [{ text: 'Minha conta', href: '/' }];
      if (active && pageHeading && pageHeading !== sectionTitle) items.push({ text: sectionTitle, href: active.href });
      items.push({ text: pageHeading || sectionTitle, href: null });
      crumbs.replaceChildren(...items.map((item) => {
        const li = document.createElement('li');
        li.className = 'breadcrumb-item' + (item.href ? '' : ' active');
        if (item.href) {
          const a = document.createElement('a');
          a.href = item.href;
          a.textContent = item.text;
          li.append(a);
        }
        else {
          li.setAttribute('aria-current', 'page');
          li.textContent = item.text;
        }
        return li;
      }));
    }
    document.title = (pageHeading || sectionTitle) + ' | Conta ACULTA';
  };

  // Navegação de página (menu, abas, pedidos, voltar/avançar): URL no histórico, shell atualizado, foco no título.
  const navigate = (anchor, url, push) => {
    const layout = document.querySelector('[data-portal-account-layout]');
    layout?.setAttribute('aria-busy', 'true');
    // Mensagens da página anterior não podem ficar na página nova.
    document.querySelectorAll('.messages, [data-drupal-messages]').forEach((node) => node.remove());
    requestInto(anchor, url, BODY_ID, () => {
      layout?.setAttribute('aria-busy', 'false');
      if (push) window.history.pushState({ acultaAccount: true }, '', url);
      refreshChrome(url);
      focusHeading();
    });
  };

  // Sanfona do celular: o painel carrega a seção pelo mesmo framework AJAX.
  const loadAccordionItem = (details) => {
    const panel = details.querySelector('[data-portal-account-accordion-panel]');
    if (!panel || panel.dataset.loaded === '1' || panel.dataset.loading === '1') return;
    panel.dataset.loading = '1';
    if (!panel.id) {
      sequence += 1;
      panel.id = 'portal-account-panel-' + sequence;
    }
    const trigger = details.querySelector('.portal-account__summary');
    requestInto(trigger, panel.dataset.src, panel.id, () => {
      panel.dataset.loaded = '1';
      panel.dataset.loading = '0';
    });
  };

  const setupAccordion = (layout) => {
    if (!mobileQuery.matches) return;
    // No celular o conteúdo vive só na sanfona: a coluna de conteúdo sai do DOM para não duplicar IDs.
    layout.querySelector('[data-portal-account-content]')?.remove();
    layout.querySelectorAll('.portal-account__item').forEach((details) => {
      if (details.dataset.acultaAccordion === '1') return;
      details.dataset.acultaAccordion = '1';
      details.addEventListener('toggle', () => {
        if (!details.open) return;
        layout.querySelectorAll('.portal-account__item[open]').forEach((other) => {
          if (other !== details) other.open = false;
        });
        loadAccordionItem(details);
      });
      if (details.open) loadAccordionItem(details);
    });
  };

  // Links internos da conta (menu, abas, pedidos, detalhes): mesma origem e caminho, sem alvo novo.
  const isInternalLink = (anchor) => {
    if (!anchor || anchor.target === '_blank' || anchor.hasAttribute('download')) return false;
    const target = new URL(anchor.href, window.location.href);
    return target.origin === window.location.origin && !target.pathname.startsWith('/sair');
  };

  Drupal.behaviors.acultaPortalNavigation = {
    attach(context) {
      once('aculta-portal-navigation', '[data-portal-account-layout]', context).forEach((layout) => {
        setupAccordion(layout);
        layout.addEventListener('click', (event) => {
          if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
          const anchor = event.target.closest('a[href]');
          if (!isInternalLink(anchor) || anchor.closest('.portal-account__summary')) return;
          // Só a navegação de seções e abas usa AJAX; ações dentro de formulários seguem o fluxo normal.
          const inMenu = anchor.closest('.portal-account__menu, .portal-account__accordion, [data-portal-account-data-nav]');
          const inBody = anchor.closest('#' + BODY_ID);
          if (!inMenu && !inBody) return;
          const target = new URL(anchor.href, window.location.href);
          if (target.pathname === window.location.pathname && target.search === window.location.search) {
            event.preventDefault();
            return;
          }
          event.preventDefault();
          navigate(anchor, anchor.href, true);
        });
        window.addEventListener('popstate', () => {
          const body = document.getElementById(BODY_ID);
          if (!body) return;
          const anchor = document.createElement('a');
          anchor.href = window.location.href;
          document.body.append(anchor);
          navigate(anchor, window.location.href, false);
          anchor.remove();
        });
      });
    },
  };

  Drupal.behaviors.acultaPortalPhotoEditor = {
    attach(context) {
      once('aculta-portal-photo-editor', '[data-aculta-photo-editor]', context).forEach((editor) => {
        const dialog = editor.querySelector('[data-aculta-photo-dialog]');
        const trigger = editor.querySelector('[data-aculta-photo-open]');
        const closeButton = editor.querySelector('[data-aculta-photo-close]');
        const heading = dialog?.querySelector('#portal-account-photo-title');
        if (!dialog || !trigger || typeof dialog.showModal !== 'function') return;

        // The dialog starts open so its form remains available without JS.
        editor.classList.add('is-enhanced');
        if (dialog.open) dialog.close();

        trigger.addEventListener('click', () => {
          if (!dialog.open) dialog.showModal();
          window.requestAnimationFrame(() => heading?.focus());
        });
        closeButton?.addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => {
          if (event.target === dialog) dialog.close();
        });
        dialog.addEventListener('close', () => trigger.focus());
      });
    },
  };
})(Drupal, once);
