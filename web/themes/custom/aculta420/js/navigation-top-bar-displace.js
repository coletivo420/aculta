(function (Drupal, once) {
  'use strict';

  const desktopTopBar = window.matchMedia('(min-width: 64rem)');
  const topBarSelector = '.top-bar[data-drupal-admin-styles][data-offset-top]';
  const institutionBarSelector = '.aculta-institution-bar';
  const mobileViewport = window.matchMedia('(max-width: 63.999rem)');
  let institutionBarNaturalTop = null;

  function updateInstitutionBarScrollState() {
    const bar = document.querySelector(institutionBarSelector);
    if (!bar) {
      institutionBarNaturalTop = null;
      document.documentElement.classList.remove('aculta-institution-bar-scrolled');
      document.querySelectorAll('.aculta-institution-bar-placeholder').forEach((placeholder) => placeholder.remove());
      return;
    }

    if (window.scrollY === 0 && !bar.classList.contains('aculta-institution-bar--scroll-fixed')) {
      institutionBarNaturalTop = bar.getBoundingClientRect().top;
    }
    const shouldFix = mobileViewport.matches && window.scrollY > 0;
    const adminActions = document.querySelector('.top-bar[data-drupal-admin-styles]');
    const adminActionHasFocus = adminActions?.contains(document.activeElement) ?? false;
    document.documentElement.classList.toggle(
      'aculta-institution-bar-scrolled',
      shouldFix && !adminActionHasFocus,
    );
    let placeholder = bar.previousElementSibling;
    if (!placeholder?.matches('.aculta-institution-bar-placeholder')) {
      placeholder = null;
    }

    if (shouldFix) {
      const preserveOriginalSlot = (institutionBarNaturalTop ?? bar.getBoundingClientRect().top) <= 1;
      if (preserveOriginalSlot && !placeholder) {
        placeholder = document.createElement('div');
        placeholder.className = 'aculta-institution-bar-placeholder';
        placeholder.setAttribute('aria-hidden', 'true');
        bar.before(placeholder);
      }
      if (preserveOriginalSlot) {
        placeholder.style.blockSize = `${bar.getBoundingClientRect().height}px`;
      } else {
        placeholder?.remove();
      }
      bar.classList.add('aculta-institution-bar--scroll-fixed');
      return;
    }

    bar.classList.remove('aculta-institution-bar--scroll-fixed');
    placeholder?.remove();
  }

  function updateTopBarDisplacement() {
    document.querySelectorAll(topBarSelector).forEach((topBar) => {
      if (desktopTopBar.matches) {
        // The Navigation top-bar is fixed only at its desktop breakpoint.
        topBar.setAttribute('data-offset-top', '');
      } else {
        // In normal flow it must not displace sticky viewport elements.
        topBar.setAttribute('data-offset-top', '0');
      }
    });

    if (typeof Drupal.displace === 'function') {
      Drupal.displace(true);
    }
  }

  Drupal.behaviors.aculta420NavigationTopBarDisplace = {
    attach(context) {
      updateTopBarDisplacement();
      once('aculta420NavigationTopBarDisplaceListener', 'html', context).forEach(() => {
        desktopTopBar.addEventListener('change', updateTopBarDisplacement);
        mobileViewport.addEventListener('change', updateInstitutionBarScrollState);
        window.addEventListener('scroll', updateInstitutionBarScrollState, { passive: true });
        window.addEventListener('resize', updateInstitutionBarScrollState, { passive: true });
        document.addEventListener('focusin', updateInstitutionBarScrollState);
        document.addEventListener('focusout', updateInstitutionBarScrollState);
        updateInstitutionBarScrollState();
      });
    },
  };
})(Drupal, once);
