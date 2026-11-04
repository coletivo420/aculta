(function (Drupal, once) {
  'use strict';

  const desktopTopBar = window.matchMedia('(min-width: 64rem)');
  const topBarSelector = '.top-bar[data-drupal-admin-styles][data-offset-top]';
  const institutionSelector = '.aculta-institution-bar';

  function updateTopBarDisplacement() {
    document.querySelectorAll(topBarSelector).forEach((topBar) => {
      topBar.setAttribute('data-offset-top', desktopTopBar.matches ? '' : '0');
    });
    if (typeof Drupal.displace === 'function') {
      Drupal.displace(true);
    }
  }

  function syncInstitutionBarSpace() {
    const bar = document.querySelector(institutionSelector);
    const spacer = bar?.previousElementSibling;
    if (!bar || !spacer?.matches('.aculta-institution-bar-placeholder')) {
      return;
    }
    spacer.style.blockSize = `${bar.getBoundingClientRect().height}px`;

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
    },
  };
})(Drupal, once);
