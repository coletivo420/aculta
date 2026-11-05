/**
 * @file
 * Keep Bootstrap modal drawers outside positioned headers and stacking contexts.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaUtilityDrawerPlacement = {
    attach(context) {
      once('aculta-utility-drawer-placement', '[data-aculta-utility-drawer]', context)
        .forEach((drawer) => {
          if (drawer.parentElement !== document.body) {
            document.body.append(drawer);
          }
        });
    },
  };
})(Drupal, once);
