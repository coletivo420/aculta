/**
 * Renderizador isolado do DOCX (iframe com sandbox="allow-scripts", sem allow-same-origin).
 *
 * Protocolo com a página pai (somente via postMessage, origem opaca):
 *   filha -> pai: { type: 'ready' }
 *   pai   -> filha: { type: 'init', libs: [url jszip, url docx-preview] }
 *   filha -> pai: { type: 'libs', ok: boolean }
 *   pai   -> filha: { type: 'render', buffer: ArrayBuffer }
 *   filha -> pai: { type: 'done' } | { type: 'error', reason: string }
 *
 * O conteúdo renderizado nunca é lido pelo pai. A filha aceita mensagens
 * apenas de window.parent.
 */
(function () {
  'use strict';

  var container = document.getElementById('aculta-docx-container');

  function post(message) {
    window.parent.postMessage(message, '*');
  }

  function loadScript(url) {
    return new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.async = false;
      script.src = url;
      script.onload = resolve;
      script.onerror = function () { reject(new Error('script ' + url)); };
      document.head.appendChild(script);
    });
  }

  function render(buffer) {
    container.textContent = '';
    var renderOptions = {
      renderAltChunks: false,
      experimental: false,
      inWrapper: true,
      ignoreLastRenderedPageBreak: true
    };
    return window.docx.renderAsync(new Blob([buffer]), container, null, renderOptions);
  }

  window.addEventListener('message', function (event) {
    if (event.source !== window.parent || !event.data || typeof event.data !== 'object') {
      return;
    }
    var message = event.data;

    if (message.type === 'init' && Array.isArray(message.libs)) {
      // Em sequência: scripts injetados são async por padrão, e o UMD do
      // docx-preview captura o global JSZip no momento da execução.
      message.libs.reduce(function (chain, url) {
        return chain.then(function () { return loadScript(url); });
      }, Promise.resolve())
        .then(function () {
          var ok = typeof window.docx === 'object' && typeof window.docx.renderAsync === 'function';
          post({ type: 'libs', ok: ok });
        })
        .catch(function () {
          post({ type: 'libs', ok: false });
        });
      return;
    }

    if (message.type === 'render' && message.buffer) {
      render(message.buffer)
        .then(function () { post({ type: 'done' }); })
        .catch(function (error) {
          post({ type: 'error', reason: String(error && error.name), detail: String(error && error.message).slice(0, 200) });
        });
    }
  });

  post({ type: 'ready' });
})();
