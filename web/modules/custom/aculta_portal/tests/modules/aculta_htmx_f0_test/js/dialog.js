(function (Drupal, once) {
  Drupal.behaviors.acultaHtmxF0Dialog = {
    attach(context) {
      once('aculta-htmx-f0-dialog', '#htmx-f0-open', context).forEach((trigger) => {
        trigger.addEventListener('click', () => {
          document.querySelector('#htmx-f0-dialog')?.showModal();
        });
      });

      once('aculta-htmx-f0-close', '#htmx-f0-close', context).forEach((button) => {
        button.addEventListener('click', () => {
          document.querySelector('#htmx-f0-dialog')?.close();
        });
      });
    },
  };
})(Drupal, once);
