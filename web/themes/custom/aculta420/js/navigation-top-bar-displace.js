(function (Drupal, once) {
  'use strict';

  const desktopTopBar = window.matchMedia('(min-width: 64rem)');
  const topBarSelector = '.top-bar[data-drupal-admin-styles][data-offset-top]';

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
      });
    },
  };
})(Drupal, once);
