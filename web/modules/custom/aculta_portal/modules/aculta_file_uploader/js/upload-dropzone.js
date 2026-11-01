/**
 * @file
 * Zona de arrastar e soltar sobre o campo de arquivo do Core.
 *
 * O arquivo solto é entregue ao input nativo, que dispara o change. O upload
 * segue o fluxo do managed_file do Core. Sem JavaScript, o campo comum continua.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Comportamento: liga a zona de soltar aos inputs de arquivo.
   */
  Drupal.behaviors.acultaUploadDropZone = {
    attach(context) {
      once('aculta-dropzone', '[data-aculta-dropzone]', context).forEach((zone) => {
        const input = zone.querySelector('input[type="file"]');
        if (!input) {
          return;
        }

        // Anúncio de estado para leitores de tela, sem interferir no layout.
        const status = document.createElement('div');
        status.className = 'aculta-dropzone__status';
        status.setAttribute('role', 'status');
        zone.appendChild(status);

        const over = (event) => {
          event.preventDefault();
          zone.classList.add('is-dragover');
        };
        const leave = () => zone.classList.remove('is-dragover');

        zone.addEventListener('dragenter', over);
        zone.addEventListener('dragover', over);
        zone.addEventListener('dragleave', leave);
        zone.addEventListener('dragend', leave);

        zone.addEventListener('drop', (event) => {
          event.preventDefault();
          leave();
          const files = event.dataTransfer && event.dataTransfer.files;
          if (!files || files.length === 0) {
            return;
          }
          if (files.length > 1) {
            status.textContent = Drupal.t('Envie um arquivo por vez.');
            return;
          }
          // Entrega o arquivo ao input nativo e dispara o fluxo do Core.
          const transfer = new DataTransfer();
          transfer.items.add(files[0]);
          input.files = transfer.files;
          input.dispatchEvent(new Event('change', { bubbles: true }));
          status.textContent = Drupal.t('Arquivo recebido: @name', { '@name': files[0].name });
        });
      });
    },
  };
})(Drupal, once);
