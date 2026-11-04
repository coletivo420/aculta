((Drupal, once) => {
  Drupal.behaviors.acultaAccountMenu = {
    attach(context) {
      once('aculta-account-menu', '.aculta-account-menu__details', context).forEach((details) => {
        const summary = details.querySelector('.aculta-account-menu__summary');
        if (!summary) return;

        details.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && details.open) {
            details.open = false;
            summary.focus();
          }
        });

        document.addEventListener('pointerdown', (event) => {
          if (details.open && !details.contains(event.target)) details.open = false;
        });
      });
    },
  };
})(Drupal, once);
