(function (Drupal, htmx, once) {
  'use strict';

  const desktopTopBar = window.matchMedia('(min-width: 64rem)');
  const topBarSelector = '.top-bar[data-drupal-admin-styles][data-offset-top]';
  const institutionSelector = '.aculta-institution-bar';
  const adminSidebarSelector = '#admin-toolbar.admin-toolbar';

  function updateTopBarDisplacement() {
    document.querySelectorAll(topBarSelector).forEach((topBar) => {
      topBar.setAttribute('data-offset-top', desktopTopBar.matches ? '' : '0');
    });
  }

  function syncInstitutionBarSpace() {
    const bar = document.querySelector(institutionSelector);
    const spacer = bar?.previousElementSibling;
    const adminSidebar = document.querySelector(adminSidebarSelector);
    const rootStyle = document.documentElement.style;
    if (!bar) {
      return;
    }
    const barHeight = bar.getBoundingClientRect().height;
    rootStyle.setProperty('--aculta-institution-bar-height', `${barHeight}px`);
    let adminSidebarInlineSize = 0;
    if (adminSidebar && getComputedStyle(adminSidebar).display !== 'none') {
      const sidebarRect = adminSidebar.getBoundingClientRect();
      const direction = getComputedStyle(document.documentElement).direction;
      adminSidebarInlineSize = direction === 'rtl' ? window.innerWidth - sidebarRect.left : sidebarRect.right;
    }
    rootStyle.setProperty('--aculta-admin-sidebar-inline-size', `${Math.max(0, adminSidebarInlineSize)}px`);
    rootStyle.setProperty('--aculta-admin-main-inline-size', `${Math.max(0, window.innerWidth - adminSidebarInlineSize)}px`);
    const adminToolbarBar = document.querySelector('.toolbar-oriented .toolbar-bar');
    if (adminToolbarBar) {
      const adminToolbarBarHeight = adminToolbarBar.getBoundingClientRect().height;
      if (adminToolbarBarHeight > 0) {
        rootStyle.setProperty('--aculta-admin-toolbar-bar-height', `${adminToolbarBarHeight}px`);
      }
    }
    if (spacer?.matches('.aculta-institution-bar-placeholder')) {
      spacer.style.blockSize = `${barHeight}px`;
    }

    const adminBar = document.querySelector('.top-bar[data-drupal-admin-styles]');
    let offset = 0;
    if (adminBar) {
      const rect = adminBar.getBoundingClientRect();
      const isViewportFixed = getComputedStyle(adminBar).position === 'fixed';
      if (desktopTopBar.matches && isViewportFixed) {
        offset = rect.bottom;
      }
      else if (rect.top <= 0 && rect.bottom > 0) {
        offset = rect.bottom;
      }
    }
    rootStyle.setProperty('--aculta-institution-bar-offset', `${Math.max(0, offset)}px`);
  }

  function isLoginPopupUrl(value) {
    try {
      const url = new URL(value, window.location.href);
      return url.origin === window.location.origin && /\/entrar\/janela\/?$/.test(url.pathname);
    }
    catch (error) {
      return false;
    }
  }

  function isSearchPopupUrl(value) {
    try {
      const url = new URL(value, window.location.href);
      return url.origin === window.location.origin && /\/busca\/popup\/?$/.test(url.pathname);
    }
    catch (error) {
      return false;
    }
  }

  function showLoginError(content, message, linkUrl, linkLabel) {
    const notice = document.createElement('p');
    notice.setAttribute('role', 'alert');
    notice.textContent = message;
    const fallback = document.createElement('a');
    fallback.href = linkUrl;
    fallback.textContent = linkLabel || Drupal.t('Continuar para a página de acesso');
    content.replaceChildren(notice, fallback);
    content.setAttribute('aria-busy', 'false');
  }

  function createLoginDialog(bar) {
    const template = bar.querySelector('[data-aculta-login-dialog-template]')
      || document.querySelector('[data-aculta-login-dialog-template]');
    const fragment = template?.content.cloneNode(true);
    const dialog = fragment?.querySelector('[data-aculta-login-dialog]');
    if (!dialog) {
      return null;
    }
    document.body.appendChild(dialog);
    const content = dialog.querySelector('[data-aculta-login-content]');
    const close = dialog.querySelector('[data-aculta-login-close]');
    let returnFocus = null;
    let activeTrigger = null;

    close?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        dialog.close();
      }
    });
    dialog.addEventListener('close', () => {
      if (activeTrigger) {
        htmx.trigger(activeTrigger, 'htmx:abort');
        activeTrigger = null;
      }
      content.replaceChildren();
      returnFocus?.focus();
    });
    document.addEventListener('htmx:responseError', (event) => {
      if (event.detail?.target !== content) {
        return;
      }
      showLoginError(content, Drupal.t('Não foi possível carregar o acesso agora.'), activeTrigger?.href || window.location.href);
    });
    document.addEventListener('htmx:sendError', (event) => {
      if (event.detail?.target !== content) {
        return;
      }
      showLoginError(content, Drupal.t('Não foi possível carregar o acesso agora.'), activeTrigger?.href || window.location.href);
    });
    document.addEventListener('htmx:afterSettle', (event) => {
      if (event.detail?.target !== content) {
        return;
      }
      content.setAttribute('aria-busy', 'false');
      content.querySelector('[aria-invalid="true"], .form-item--error input, input:not([type="hidden"]):not([disabled])')?.focus();
    });

    return {
      dialog,
      content,
      open(link) {
        returnFocus = link;
        activeTrigger = link;
        link.setAttribute('data-hx-get', link.href);
        link.setAttribute('data-hx-trigger', 'aculta:login:open');
        link.setAttribute('data-hx-target', '#aculta-login-content');
        link.setAttribute('data-hx-select', '#aculta-login-popup');
        link.setAttribute('data-hx-swap', 'innerHTML');
        link.setAttribute('data-hx-sync', 'body:replace');
        link.setAttribute('data-hx-drupal-only-main-content', 'true');
        htmx.process(link);
        content.textContent = Drupal.t('Carregando formulário de acesso…');
        content.setAttribute('aria-busy', 'true');
        dialog.showModal();
        htmx.trigger(link, 'aculta:login:open');
      },
    };
  }

  function createSearchDialog(bar) {
    const template = bar.querySelector('[data-aculta-search-dialog-template]')
      || document.querySelector('[data-aculta-search-dialog-template]');
    const fragment = template?.content.cloneNode(true);
    const dialog = fragment?.querySelector('[data-aculta-search-dialog]');
    if (!dialog) {
      return null;
    }
    document.body.appendChild(dialog);
    const content = dialog.querySelector('[data-aculta-search-content]');
    const close = dialog.querySelector('[data-aculta-search-close]');
    let returnFocus = null;
    let activeTrigger = null;

    close?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        dialog.close();
      }
    });
    dialog.addEventListener('close', () => {
      if (activeTrigger) {
        htmx.trigger(activeTrigger, 'htmx:abort');
        activeTrigger = null;
      }
      content.replaceChildren();
      returnFocus?.focus();
    });
    document.addEventListener('htmx:responseError', (event) => {
      if (event.detail?.target !== content) {
        return;
      }
      showLoginError(
        content,
        Drupal.t('Não foi possível carregar a busca agora.'),
        activeTrigger?.href || window.location.href,
        Drupal.t('Continuar para a página de busca'),
      );
    });
    document.addEventListener('htmx:sendError', (event) => {
      if (event.detail?.target !== content) {
        return;
      }
      showLoginError(
        content,
        Drupal.t('Não foi possível carregar a busca agora.'),
        activeTrigger?.href || window.location.href,
        Drupal.t('Continuar para a página de busca'),
      );
    });
    document.addEventListener('htmx:afterSettle', (event) => {
      if (event.detail?.target !== content) {
        return;
      }
      content.setAttribute('aria-busy', 'false');
      content.querySelector('input[type="search"]')?.focus();
    });

    return {
      dialog,
      content,
      open(link) {
        returnFocus = link;
        activeTrigger = link;
        link.setAttribute('data-hx-get', link.href);
        link.setAttribute('data-hx-trigger', 'aculta:search:open');
        link.setAttribute('data-hx-target', '#aculta-search-content');
        link.setAttribute('data-hx-select', 'form[data-portal-ui="search-popup"]');
        link.setAttribute('data-hx-swap', 'innerHTML');
        link.setAttribute('data-hx-sync', 'body:replace');
        link.setAttribute('data-hx-drupal-only-main-content', 'true');
        htmx.process(link);
        content.textContent = Drupal.t('Carregando busca…');
        content.setAttribute('aria-busy', 'true');
        dialog.showModal();
        htmx.trigger(link, 'aculta:search:open');
      },
    };
  }

  Drupal.behaviors.acultaInstitutionBar = {
    attach(context) {
      once('aculta420V2InstitutionBarListener', 'html', context).forEach(() => {
        updateTopBarDisplacement();
        syncInstitutionBarSpace();
        desktopTopBar.addEventListener('change', updateTopBarDisplacement);
        desktopTopBar.addEventListener('change', syncInstitutionBarSpace);
        window.addEventListener('scroll', syncInstitutionBarSpace, { passive: true });
        window.addEventListener('resize', syncInstitutionBarSpace, { passive: true });
        if ('ResizeObserver' in window) {
          const observer = new ResizeObserver(syncInstitutionBarSpace);
          const bar = document.querySelector(institutionSelector);
          const adminSidebar = document.querySelector(adminSidebarSelector);
          if (bar) {
            observer.observe(bar);
          }
          if (adminSidebar) {
            observer.observe(adminSidebar);
            adminSidebar.addEventListener('transitionend', syncInstitutionBarSpace, { passive: true });
          }
        }
      });

      once('aculta420V2AccountActions', institutionSelector, context).forEach((bar) => {
        const accountMenu = bar.querySelector('.aculta-account-menu');
        const loginDialog = accountMenu ? createLoginDialog(bar) : null;
        accountMenu?.querySelectorAll('a[href]').forEach((link) => {
          if (!loginDialog || !isLoginPopupUrl(link.href)) {
            return;
          }
          link.classList.remove('use-ajax');
          link.removeAttribute('data-dialog-type');
          link.removeAttribute('data-dialog-options');
          link.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            loginDialog.open(link);
          });
        });

        const searchDialog = createSearchDialog(bar);
        bar.querySelectorAll('a[data-portal-ui="search-utility"][href]').forEach((link) => {
          if (!isSearchPopupUrl(link.href)) {
            return;
          }
          link.classList.remove('use-ajax');
          link.removeAttribute('data-dialog-type');
          link.removeAttribute('data-dialog-options');
          link.addEventListener('click', (event) => {
            if (!searchDialog) {
              return;
            }
            event.preventDefault();
            event.stopPropagation();
            searchDialog.open(link);
          });
        });
      });
    },
  };
})(Drupal, htmx, once);
