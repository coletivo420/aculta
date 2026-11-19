/**
 * @file
 * Accessible behavior for the isolated color mode switcher SDC.
 */
(function (Drupal, once) {
  'use strict';

  const MODES = ['light', 'auto', 'dark'];
  const STORAGE_KEY = 'aculta-color-mode';
  const SELECTOR = '[data-color-mode-switcher]';
  let saveQueue = Promise.resolve();
  let latestSave = 0;
  let confirmedSiteMode = null;
  let csrfTokenPromise = null;

  function t(message) {
    return Drupal.t(message);
  }

  function validMode(value) {
    return MODES.includes(value) ? value : null;
  }

  function isPopoverOpen(popover) {
    return typeof popover.showPopover === 'function'
      ? popover.matches(':popover-open')
      : !popover.hidden;
  }

  function iconTemplate(root, mode) {
    const template = root.querySelector(`[data-mode-icon="${mode}"]`);
    return template?.content?.firstElementChild?.cloneNode(true) ?? null;
  }

  function updateMarkup(root, mode) {
    root.dataset.mode = mode;
    root.querySelectorAll('[data-color-mode-option]').forEach((option) => {
      option.checked = option.value === mode;
    });
    const trigger = root.querySelector('[data-color-mode-trigger]');
    if (!trigger) {
      return;
    }
    const icon = iconTemplate(root, mode);
    const currentIcon = trigger.querySelector('.aculta-icon');
    if (icon && currentIcon) {
      currentIcon.replaceWith(icon);
    }
    const label = root.querySelector(`[data-color-mode-option][value="${mode}"]`)?.closest('label')?.innerText.trim() || mode;
    trigger.setAttribute('aria-label', `${trigger.dataset.labelPrefix}: ${label}`);
  }

  function setPreviewAppearance(root, mode) {
    const appearance = root.dataset.appearance;
    if (mode === 'auto' && appearance === 'auto') {
      root.removeAttribute('data-theme');
      return;
    }
    root.setAttribute('data-theme', mode === 'auto' ? appearance : mode);
  }

  function applySiteMode(mode) {
    const html = document.documentElement;
    html.setAttribute('data-color-preference', mode);
    html.setAttribute('data-theme', mode === 'auto'
      ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
      : mode);
    document.dispatchEvent(new CustomEvent('aculta:color-mode-preference-change', { detail: { mode } }));
  }

  function localMode() {
    try {
      return validMode(window.localStorage.getItem(STORAGE_KEY));
    }
    catch {
      return null;
    }
  }

  function saveLocalMode(mode) {
    try {
      window.localStorage.setItem(STORAGE_KEY, mode);
    }
    catch {
      // The current page remains usable when storage is disabled.
    }
  }

  function announce(root, message) {
    const status = root.querySelector('[data-color-mode-status]');
    if (status) {
      status.textContent = t(message);
    }
  }

  function positionPopover(root) {
    const trigger = root.querySelector('[data-color-mode-trigger]');
    const popover = root.querySelector('[data-color-mode-popover]');
    if (!trigger || !popover || !isPopoverOpen(popover)) {
      return;
    }
    const anchor = trigger.getBoundingClientRect();
    const panel = popover.getBoundingClientRect();
    const gutter = 8;
    const preferred = root.dataset.alignment === 'start' ? anchor.left : anchor.right - panel.width;
    const left = Math.max(gutter, Math.min(preferred, window.innerWidth - panel.width - gutter));
    const below = anchor.bottom + gutter;
    const top = below + panel.height <= window.innerHeight - gutter
      ? below
      : Math.max(gutter, anchor.top - panel.height - gutter);
    popover.style.setProperty('--color-mode-popover-left', `${left}px`);
    popover.style.setProperty('--color-mode-popover-top', `${top}px`);
  }

  function closePopover(root, returnFocus) {
    const trigger = root.querySelector('[data-color-mode-trigger]');
    const popover = root.querySelector('[data-color-mode-popover]');
    if (!trigger || !popover) {
      return;
    }
    if (typeof popover.hidePopover === 'function' && isPopoverOpen(popover)) {
      popover.hidePopover();
    }
    else {
      popover.hidden = true;
    }
    trigger.setAttribute('aria-expanded', 'false');
    if (returnFocus) {
      trigger.focus();
    }
  }

  function initializePopover(root) {
    const trigger = root.querySelector('[data-color-mode-trigger]');
    const popover = root.querySelector('[data-color-mode-popover]');
    if (!trigger || !popover) {
      return;
    }
    const nativePopover = typeof popover.showPopover === 'function';
    if (!nativePopover && !isPopoverOpen(popover)) {
      popover.hidden = true;
      trigger.removeAttribute('popovertarget');
      trigger.addEventListener('click', () => {
        popover.hidden = !popover.hidden;
        trigger.setAttribute('aria-expanded', String(!popover.hidden));
        positionPopover(root);
      });
      document.addEventListener('pointerdown', (event) => {
        if (!root.contains(event.target) && !popover.hidden) {
          popover.hidden = true;
          trigger.setAttribute('aria-expanded', 'false');
        }
      });
    }
    popover.addEventListener('toggle', (event) => {
      const open = event.newState === 'open';
      trigger.setAttribute('aria-expanded', String(open));
      if (open) {
        positionPopover(root);
      }
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && isPopoverOpen(popover)) {
        window.requestAnimationFrame(() => trigger.focus());
      }
    }, true);
    window.addEventListener('resize', () => positionPopover(root), { passive: true });
    window.addEventListener('scroll', () => positionPopover(root), { passive: true, capture: true });
    if (root.dataset.initiallyOpen === 'true' && nativePopover && !isPopoverOpen(popover)) {
      popover.showPopover();
    }
    trigger.setAttribute('aria-expanded', String(isPopoverOpen(popover)));
  }

  async function csrfToken() {
    if (!csrfTokenPromise) {
      const base = drupalSettings.path?.baseUrl ?? '/';
      csrfTokenPromise = fetch(`${base}session/token`, {
        credentials: 'same-origin',
        cache: 'no-store',
      }).then((response) => {
        if (!response.ok) {
          throw new Error('csrf');
        }
        return response.text();
      }).catch((error) => {
        csrfTokenPromise = null;
        throw error;
      });
    }
    return csrfTokenPromise;
  }

  async function persist(mode) {
    const settings = drupalSettings.acultaPortal?.colorMode;
    if (settings?.authenticated !== true || !settings.updateUrl) {
      throw new Error('persist');
    }
    const token = await csrfToken();
    const response = await fetch(settings.updateUrl, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-Token': token,
      },
      body: JSON.stringify({ mode }),
    });
    const result = await response.json().catch(() => null);
    if (!response.ok || result?.saved !== true || result.mode !== mode) {
      throw new Error('persist');
    }
    return result.mode;
  }

  function syncSiteInstances(mode) {
    document.querySelectorAll(`${SELECTOR}[data-context="site"]`).forEach((root) => updateMarkup(root, mode));
  }

  function chooseMode(root, mode) {
    if (!validMode(mode) || mode === root.dataset.mode) {
      return;
    }
    const prior = root.dataset.mode;
    updateMarkup(root, mode);
    if (root.dataset.context !== 'site') {
      setPreviewAppearance(root, mode);
      closePopover(root, true);
      return;
    }

    applySiteMode(mode);
    syncSiteInstances(mode);
    closePopover(root, true);
    const settings = drupalSettings.acultaPortal?.colorMode;
    if (settings?.authenticated === true) {
      const sequence = ++latestSave;
      announce(root, 'Salvando preferência de modo de cor…');
      saveQueue = saveQueue.then(async () => {
        try {
          confirmedSiteMode = await persist(mode);
          if (sequence === latestSave) {
            announce(root, 'Preferência de modo de cor salva.');
          }
        }
        catch {
          if (sequence === latestSave) {
            const rollback = confirmedSiteMode ?? prior;
            applySiteMode(rollback);
            syncSiteInstances(rollback);
            announce(root, 'Não foi possível salvar. A preferência anterior foi restaurada.');
          }
        }
      });
    }
    else {
      saveLocalMode(mode);
      confirmedSiteMode = mode;
      announce(root, 'Preferência de modo de cor aplicada neste navegador.');
    }
  }

  Drupal.behaviors.colorModeSwitcher = {
    attach(context) {
      once('color-mode-switcher', SELECTOR, context).forEach((root) => {
        let mode = validMode(root.dataset.mode) ?? 'auto';
        if (root.dataset.context === 'site') {
          const settings = drupalSettings.acultaPortal?.colorMode;
          if (settings?.authenticated !== true) {
            mode = localMode() ?? mode;
          }
          confirmedSiteMode = validMode(document.documentElement.dataset.colorPreference) ?? mode;
          applySiteMode(mode);
        }
        updateMarkup(root, mode);
        initializePopover(root);
        root.querySelectorAll('[data-color-mode-option]').forEach((radio) => {
          radio.addEventListener('change', () => chooseMode(root, radio.value));
        });
        document.addEventListener('aculta:color-mode-preference-change', (event) => {
          if (root.dataset.context === 'site' && validMode(event.detail?.mode)) {
            updateMarkup(root, event.detail.mode);
          }
        });
      });
    },
  };
})(Drupal, once);
