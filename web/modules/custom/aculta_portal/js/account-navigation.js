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
    document.querySelectorAll('[data-portal-account-menu] a, [data-portal-account-accordion] a').forEach((item) => {
      const current = new URL(item.href, window.location.href).pathname === pathname;
      if (current) item.setAttribute('aria-current', 'page');
      else item.removeAttribute('aria-current');
    });
  };

  const focusHeading = () => {
    const heading = document.getElementById('portal-account-section-title');
    if (heading) {
      heading.setAttribute('tabindex', '-1');
      heading.focus();
    }
  };

  // Faz a requisição AJAX do link e troca o conteúdo do destino (wrapper). onDone roda depois da troca.
  const requestInto = (anchor, url, wrapperId, onDone, onError) => {
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
      // Navigation replaces the content directly; do not insert a click
      // spinner. aria-busy remains on the layout for assistive technology.
      progress: { type: 'none' },
      effect: 'none',
    });
    // O success do Drupal resolve depois dos comandos (inclui carregar bibliotecas): o estado novo é lido só então.
    const success = ajax.success.bind(ajax);
    ajax.success = (response, status) => Promise.resolve(success(response, status)).then((result) => {
      if (onDone) onDone();
      return result;
    });
    const failure = ajax.error.bind(ajax);
    ajax.error = (xhr, uri, message) => {
      if (onError) onError();
      return failure(xhr, uri, message);
    };
    ajax.execute();
  };

  // Atualiza o shell para a página nova: seção ativa (um só item), título da seção, breadcrumb e document.title.
  const refreshChrome = (url) => {
    const path = new URL(url, window.location.href).pathname;
    const links = Array.from(document.querySelectorAll('[data-portal-account-menu] a, [data-portal-account-accordion] a'));
    // Item ativo: o link de maior caminho que contém a página (ex.: /pedidos/6 ativa /pedidos).
    let active = null;
    links.forEach((item) => {
      const itemPath = new URL(item.href, window.location.href).pathname;
      const matches = path === itemPath || path.startsWith(itemPath.replace(/\/$/, '') + '/');
      if (matches && (!active || itemPath.length > new URL(active.href, window.location.href).pathname.length)) active = item;
    });
    links.forEach((item) => {
      if (item === active) item.setAttribute('aria-current', 'page');
      else item.removeAttribute('aria-current');
    });
    const body = document.getElementById(BODY_ID);
    // Título e breadcrumb da página vêm do servidor, num bloco oculto do conteúdo (AccountAjaxMainContentRenderer).
    // O fragmento pode estar no corpo (desktop) ou no painel aberto (mobile): procura no documento.
    const chrome = document.querySelector('[data-portal-chrome]');
    const pageTitle = chrome ? chrome.getAttribute('data-portal-page-title') : '';
    const crumbList = chrome?.querySelector('ol');
    const currentCrumbs = document.querySelector('[data-portal-breadcrumb]');
    if (crumbList && currentCrumbs) currentCrumbs.replaceChildren(...crumbList.children);
    chrome?.remove();
    const sectionTitle = active ? active.textContent.trim() : pageTitle;
    const sectionHeading = document.getElementById('portal-account-section-title');
    if (sectionHeading) sectionHeading.textContent = sectionTitle;
    document.title = (pageTitle || sectionTitle) + ' | Conta ACULTA';
  };

  // Navegação de página (menu, abas, pedidos, voltar/avançar): URL no histórico, shell atualizado, foco no título.
  const navigate = (anchor, url, push) => {
    const layout = document.querySelector('[data-portal-account-layout]');
    layout?.setAttribute('aria-busy', 'true');
    // Mensagens da página anterior não podem ficar na página nova.
    document.querySelectorAll('.messages, [data-drupal-messages]').forEach((node) => node.remove());
    // Link dentro de um painel da sanfona (mobile): a troca acontece nesse painel; senão, no corpo da página.
    const panel = anchor.closest('[data-portal-account-accordion-panel]');
    requestInto(anchor, url, panel?.id || BODY_ID, () => {
      layout?.setAttribute('aria-busy', 'false');
      if (push) window.history.pushState({ acultaAccount: true }, '', url);
      refreshChrome(url);
      focusHeading();
    });
  };

  // Sanfona do celular: o painel carrega a seção pelo mesmo framework AJAX. Passados 10 s ou em caso de falha,
  // o painel mostra erro com "Tentar novamente" e link para a página normal (fallback).
  const ACCORDION_TIMEOUT = 10000;

  const showAccordionError = (details, panel) => {
    panel.dataset.loading = '0';
    panel.replaceChildren();
    const box = document.createElement('div');
    box.setAttribute('data-portal-account-error', '');
    box.setAttribute('role', 'alert');
    const message = document.createElement('p');
    message.textContent = Drupal.t('Não foi possível carregar esta seção agora.');
    const retry = document.createElement('button');
    retry.type = 'button';
    retry.setAttribute('data-portal-account-retry', '');
    retry.textContent = Drupal.t('Tentar novamente');
    retry.addEventListener('click', () => {
      panel.dataset.loaded = '0';
      loadAccordionItem(details, true);
    });
    const link = document.createElement('a');
    link.href = panel.dataset.src;
    link.textContent = Drupal.t('Abrir a página desta seção');
    box.append(message, retry, document.createTextNode(' '), link);
    panel.append(box);
  };

  const loadAccordionItem = (details, push = false) => {
    const panel = details.querySelector('[data-portal-account-accordion-panel]');
    if (!panel || panel.dataset.loaded === '1' || panel.dataset.loading === '1') return;
    panel.dataset.loading = '1';
    if (!panel.id) {
      sequence += 1;
      panel.id = 'portal-account-panel-' + sequence;
    }
    // O Drupal.Ajax associa um clique ao elemento e cancela o padrão. Por isso o carregamento usa um marcador oculto
    // dentro do painel, e não o summary: senão o item já carregado não abriria mais pelo toque.
    panel.querySelectorAll(':scope > [data-portal-account-loader]').forEach((node) => node.remove());
    const loader = document.createElement('span');
    loader.hidden = true;
    loader.setAttribute('data-portal-account-loader', '');
    panel.prepend(loader);
    const timer = window.setTimeout(() => {
      if (panel.dataset.loaded !== '1') showAccordionError(details, panel);
    }, ACCORDION_TIMEOUT);
    requestInto(loader, panel.dataset.src, panel.id, () => {
      window.clearTimeout(timer);
      panel.dataset.loaded = '1';
      panel.dataset.loading = '0';
      // Como na navegação de página: URL no histórico, título, menu ativo e breadcrumb da seção.
      if (push) window.history.pushState({ acultaAccount: true }, '', panel.dataset.src);
      refreshChrome(panel.dataset.src);
    }, () => {
      window.clearTimeout(timer);
      showAccordionError(details, panel);
    });
  };

  const setupAccordion = (layout) => {
    if (!mobileQuery.matches) return;
    // No celular o conteúdo vive só na sanfona. A seção ativa, já renderizada pelo servidor, vai para o painel aberto
    // (sem placeholder e sem requisição); a coluna de conteúdo sai do DOM para não duplicar IDs.
    const content = layout.querySelector('[data-portal-account-content]');
    const openPanel = layout.querySelector('[data-portal-account-item][open] [data-portal-account-accordion-panel]');
    // Só o corpo da seção vai para o painel. O título e o cabeçalho da página são compartilhados pelo shell: ficam
    // fora dos painéis, para que a troca de seção nunca altere o título dentro de outra sanfona.
    const body = content?.querySelector('#portal-account-body');
    if (body && openPanel && openPanel.dataset.loaded !== '1') {
      openPanel.replaceChildren(...body.childNodes);
      openPanel.dataset.loaded = '1';
    }
    content?.remove();
    layout.querySelectorAll('[data-portal-account-item]').forEach((details) => {
      if (details.dataset.acultaAccordion === '1') return;
      details.dataset.acultaAccordion = '1';
      // Cada seção abre e fecha de forma independente: abrir uma não fecha as outras. O painel carrega a seção
      // na primeira abertura e mantém o conteúdo ao fechar e reabrir.
      details.addEventListener('toggle', () => {
        if (details.open) loadAccordionItem(details, true);
      });
      if (details.open) loadAccordionItem(details, false);
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
          if (!isInternalLink(anchor) || anchor.closest('[data-portal-account-summary]')) return;
          // Só a navegação de seções e abas usa AJAX; ações dentro de formulários seguem o fluxo normal.
          const inMenu = anchor.closest('[data-portal-account-menu], [data-portal-account-accordion], [data-portal-account-data-nav]');
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

  // Formulários da conta salvos por AJAX: com erro de validação, o foco vai para o primeiro campo com erro.
  Drupal.behaviors.acultaPortalAccountFormErrors = {
    attach(context) {
      once('aculta-portal-account-form-errors', '[id^="portal-account-"]', context).forEach((wrapper) => {
        const invalid = wrapper.querySelector('.form-item--error input, .form-item--error select, .form-item--error textarea, .error[aria-invalid], input.error, select.error, textarea.error');
        if (invalid) {
          // Depois do Drupal.Ajax, que devolve o foco ao botão do envio.
          invalid.focus();
        }
      });
    },
  };
})(Drupal, once);
