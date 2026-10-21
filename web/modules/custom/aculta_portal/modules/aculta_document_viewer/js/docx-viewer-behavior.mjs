/**
 * Behavior do leitor DOCX: monta o viewer isolado em cada contêiner marcado.
 *
 * Só é carregado nas páginas com DOCX autorizado (biblioteca `docx`). Em caso
 * de falha, o link de download do Core, que fica no mesmo contêiner, continua
 * disponível.
 */
import { mountDocxViewer } from './docx-viewer.mjs';

(function (Drupal, once) {
  Drupal.behaviors.acultaDocxViewer = {
    attach(context) {
      once('aculta-docx-viewer', '[data-aculta-docx]', context).forEach((host) => {
        const frameHost = host.querySelector('.aculta-docx-viewer__frame');
        if (!frameHost) {
          return;
        }
        let libs = [];
        try {
          libs = JSON.parse(host.dataset.acultaDocxLibs || '[]');
        } catch (error) {
          libs = [];
        }
        mountDocxViewer(frameHost, {
          url: host.dataset.acultaDocxUrl,
          frameUrl: host.dataset.acultaDocxFrameUrl,
          libs,
        }).then((result) => {
          if (result.status !== 'done') {
            frameHost.hidden = true;
            host.dataset.acultaDocxStatus = result.status;
          }
        });
      });
    },
  };
})(Drupal, once);
