(function (Drupal, drupalSettings, once) {
  'use strict';

  const desktopTopBar = window.matchMedia('(min-width: 64rem)');
  const topBarSelector = '.top-bar[data-drupal-admin-styles][data-offset-top]';
  const institutionSelector = '.aculta-institution-bar';

  function updateTopBarDisplacement() {
    document.querySelectorAll(topBarSelector).forEach((topBar) => {
      topBar.setAttribute('data-offset-top', desktopTopBar.matches ? '' : '0');
    });
  }

  function syncInstitutionBarSpace() {
    const bar = document.querySelector(institutionSelector);
    const spacer = bar?.previousElementSibling;
    if (!bar) {
      return;
    }
    const barHeight = bar.getBoundingClientRect().height;
    document.documentElement.style.setProperty('--aculta-institution-bar-height', `${barHeight}px`);
    const adminToolbarBar = document.querySelector('.toolbar-oriented .toolbar-bar');
    if (adminToolbarBar) {
      const adminToolbarBarHeight = adminToolbarBar.getBoundingClientRect().height;
      if (adminToolbarBarHeight > 0) {
        document.documentElement.style.setProperty('--aculta-admin-toolbar-bar-height', `${adminToolbarBarHeight}px`);
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
    document.documentElement.style.setProperty('--aculta-institution-bar-offset', `${Math.max(0, offset)}px`);
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

  function showLoginError(content, message, linkUrl) {
    const notice = document.createElement('p');
    notice.setAttribute('role', 'alert');
    notice.textContent = message;
    const fallback = document.createElement('a');
    fallback.href = linkUrl;
    fallback.textContent = Drupal.t('Continuar para a página de acesso');
    content.replaceChildren(notice, fallback);
    content.setAttribute('aria-busy', 'false');
  }

  function createLoginDialog(bar) {
    const template = bar.querySelector('[data-aculta-login-dialog-template]');
    const fragment = template?.content.cloneNode(true);
    const dialog = fragment?.querySelector('[data-aculta-login-dialog]');
    if (!dialog) {
      return null;
    }
    document.body.appendChild(dialog);
    const content = dialog.querySelector('[data-aculta-login-content]');
    const close = dialog.querySelector('[data-aculta-login-close]');
    let returnFocus = null;

    close?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        dialog.close();
      }
    });
    dialog.addEventListener('close', () => {
      content.replaceChildren();
      returnFocus?.focus();
    });

    dialog.addEventListener('submit', async (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement) || form.dataset.portalUi !== 'login-popup') {
        return;
      }
      event.preventDefault();
      await submitLoginForm(form, content, dialog);
    });

    return {
      dialog,
      content,
      open(link) {
        returnFocus = link;
        content.textContent = Drupal.t('Carregando formulário de acesso…');
        content.setAttribute('aria-busy', 'true');
        dialog.showModal();
        loadLoginForm(link.href, content, dialog);
      },
    };
  }

  function createSearchDialog(bar) {
    const template = bar.querySelector('[data-aculta-search-dialog-template]');
    const fragment = template?.content.cloneNode(true);
    const dialog = fragment?.querySelector('[data-aculta-search-dialog]');
    if (!dialog) {
      return null;
    }
    document.body.appendChild(dialog);
    const content = dialog.querySelector('[data-aculta-search-content]');
    const close = dialog.querySelector('[data-aculta-search-close]');
    let returnFocus = null;

    close?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        dialog.close();
      }
    });
    dialog.addEventListener('close', () => {
      content.replaceChildren();
      returnFocus?.focus();
    });

    return {
      dialog,
      content,
      open(link) {
        returnFocus = link;
        content.textContent = Drupal.t('Carregando busca…');
        content.setAttribute('aria-busy', 'true');
        dialog.showModal();
        loadSearchForm(link.href, content);
      },
    };
  }

  async function loadSearchForm(value, content) {
    const url = new URL(value, window.location.href);
    if (url.origin !== window.location.origin) {
      showLoginError(content, Drupal.t('O endereço de busca não pertence a este site.'), value);
      return;
    }
    try {
      const response = await fetch(url.href, {
        credentials: 'same-origin',
        headers: { Accept: 'text/html' },
      });
      if (!response.ok) {
        throw new Error('Search form request failed.');
      }
      const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
      const form = responseDocument.querySelector('form[data-portal-ui="search-popup"]');
      if (!form) {
        throw new Error('Search form markup is missing.');
      }
      content.replaceChildren(document.importNode(form, true));
      content.setAttribute('aria-busy', 'false');
      Drupal.attachBehaviors(content, drupalSettings);
      content.querySelector('input[type="search"]')?.focus();
    }
    catch (error) {
      showLoginError(content, Drupal.t('Não foi possível carregar a busca agora.'), url.href);
    }
  }

  async function loadLoginForm(value, content, dialog) {
    const url = new URL(value, window.location.href);
    if (url.origin !== window.location.origin) {
      showLoginError(content, Drupal.t('O endereço de acesso não pertence a este site.'), value);
      return;
    }
    try {
      const response = await fetch(url.href, {
        credentials: 'same-origin',
        headers: { Accept: 'text/html' },
      });
      if (!response.ok || response.redirected && new URL(response.url).origin !== window.location.origin) {
        throw new Error('Login form request failed.');
      }
      if (response.redirected && !isLoginPopupUrl(response.url)) {
        window.location.assign(response.url);
        return;
      }
      const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
      const wrapper = documentFragment.querySelector('#aculta-login-popup');
      if (!wrapper || !wrapper.querySelector('form[data-portal-ui="login-popup"]')) {
        throw new Error('Login form markup is missing.');
      }
      content.replaceChildren(document.importNode(wrapper, true));
      content.setAttribute('aria-busy', 'false');
      content.querySelector('input:not([type="hidden"]):not([disabled])')?.focus();
    }
    catch (error) {
      showLoginError(content, Drupal.t('Não foi possível carregar o acesso agora.'), url.href);
    }
  }

  async function submitLoginForm(form, content, dialog) {
    const submitter = document.activeElement instanceof HTMLButtonElement
      ? document.activeElement
      : form.querySelector('button[type="submit"], input[type="submit"]');
    const formData = new FormData(form);
    if (submitter?.name && !formData.has(submitter.name)) {
      formData.append(submitter.name, submitter.value);
    }
    const url = new URL(form.action || window.location.href, window.location.href);
    if (url.origin !== window.location.origin) {
      showLoginError(content, Drupal.t('O endereço de acesso não pertence a este site.'), window.location.href);
      return;
    }
    url.searchParams.set('ajax_form', '1');
    url.searchParams.set('_wrapper_format', 'drupal_ajax');
    url.searchParams.set('_drupal_ajax', '1');
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => {
      control.disabled = true;
    });
    content.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(url.href, {
        body: formData,
        credentials: 'same-origin',
        headers: {
          Accept: 'application/vnd.drupal-ajax',
          'X-Requested-With': 'XMLHttpRequest',
        },
        method: 'POST',
      });
      if (!response.ok) {
        throw new Error('Login submission failed.');
      }
      const commands = await response.json();
      for (const command of commands) {
        if (command.command === 'redirect' && typeof command.url === 'string') {
          const destination = new URL(command.url, window.location.href);
          if (destination.origin === window.location.origin) {
            dialog.close();
            window.location.assign(destination.href);
            return;
          }
        }
        if (command.command === 'update_build_id') {
          const buildId = content.querySelector('input[name="form_build_id"]');
          if (buildId && command.new) {
            buildId.value = command.new;
          }
        }
        if (command.command === 'insert'
          && (command.selector === null || command.selector === undefined || command.selector === '#aculta-login-popup')
          && typeof command.data === 'string') {
          const current = content.querySelector('#aculta-login-popup');
          const fragment = document.createElement('template');
          fragment.innerHTML = command.data;
          const replacement = fragment.content.querySelector('#aculta-login-popup');
          if (current && replacement && (!command.method || command.method === 'replaceWith')) {
            current.replaceWith(replacement);
            content.querySelector('[aria-invalid="true"], .form-item--error input')?.focus();
            content.setAttribute('aria-busy', 'false');
            return;
          }
        }
      }
      throw new Error('Login response did not contain a supported command.');
    }
    catch (error) {
      showLoginError(content, Drupal.t('Não foi possível concluir o acesso. Tente novamente.'), form.action);
    }
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
          if (bar) {
            observer.observe(bar);
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
          // Portal keeps its Drupal/AJAX link contract for other themes;
          // V2 enhances this one with the native Fetch implementation.
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
})(Drupal, drupalSettings, once);
