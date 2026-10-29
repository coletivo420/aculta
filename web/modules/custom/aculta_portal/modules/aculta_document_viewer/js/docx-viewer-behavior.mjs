/**
 * Behavior do leitor DOCX: monta o viewer isolado em cada contêiner marcado.
 *
 * Só é carregado nas páginas com DOCX autorizado (biblioteca `docx`). Em caso
 * de falha, o link de download do Core, que fica no mesmo contêiner, continua
 * disponível.
 */
import { mountDocxViewer } from './docx-viewer.mjs';
import { setupDocumentChrome } from './document-chrome.mjs';

const translate = (text) => (Drupal && typeof Drupal.t === 'function' ? Drupal.t(text) : text);

(function (Drupal, once) {
  Drupal.behaviors.acultaDocxViewer = {
    attach(context) {
      once('aculta-docx-viewer', '[data-aculta-docx]', context).forEach((host) => {
        const frameHost = host.querySelector('[data-aculta-document-part="frame"]');
        if (!frameHost) {
          return;
        }
        let libs = [];
        try {
          libs = JSON.parse(host.dataset.acultaDocxLibs || '[]');
        } catch (error) {
          libs = [];
        }
        let renderOptions = {};
        try {
          const parsed = JSON.parse(host.dataset.acultaDocxRenderOptions || '{}');
          if (parsed && typeof parsed === 'object') {
            renderOptions = parsed;
          }
        } catch (error) {
          renderOptions = {};
        }
        const chrome = setupDocumentChrome(host, frameHost);
        chrome.setStatus(translate('Carregando documento…'));
        mountDocxViewer(frameHost, {
          url: host.dataset.acultaDocxUrl,
          frameUrl: host.dataset.acultaDocxFrameUrl,
          libs,
          renderOptions,
        }).then((result) => {
          host.dataset.acultaDocxStatus = result.status;
          if (result.status === 'done') {
            chrome.setStatus('');
            return;
          }
          frameHost.hidden = true;
          chrome.setStatus(translate('Não foi possível exibir este arquivo aqui. Use o link de download abaixo.'));
        });
      });
    },
  };
})(Drupal, once);
