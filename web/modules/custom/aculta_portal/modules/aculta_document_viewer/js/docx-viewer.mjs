/**
 * Monta o visualizador DOCX: busca os bytes autorizados e os envia a um iframe
 * isolado (sandbox sem allow-same-origin). O pai não lê o conteúdo renderizado.
 *
 * @param {HTMLElement} host
 *   Contêiner onde o iframe é criado.
 * @param {object} options
 *   url: URL autorizada do DOCX (Media/File, mesma origem).
 *   frameUrl: URL de docx-frame.html.
 *   libs: URLs de jszip.min.js e docx-preview.min.js, nessa ordem.
 *   timeoutMs: prazo para concluir a renderização.
 * @returns {Promise<{status: 'done'|'error'|'timeout'|'unsupported', reason?: string}>}
 */
export function mountDocxViewer(host, { url, frameUrl, libs, timeoutMs = 60000 }) {
  return new Promise((resolve) => {
    // A página filha resolve caminhos a partir de docx/; por isso as URLs vão absolutas.
    const absoluteLibs = libs.map((lib) => new URL(lib, document.baseURI).href);
    const frame = document.createElement('iframe');
    frame.setAttribute('sandbox', 'allow-scripts');
    frame.setAttribute('title', 'Documento DOCX');
    frame.setAttribute('referrerpolicy', 'no-referrer');
    frame.dataset.acultaDocumentPart = 'document';
    frame.src = frameUrl;
    host.appendChild(frame);

    const bufferPromise = fetch(url, { credentials: 'same-origin' }).then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return response.arrayBuffer();
    });
    // Evita rejeição não tratada enquanto o iframe ainda não respondeu.
    bufferPromise.catch(() => {});

    let finished = false;
    const finish = (status, reason) => {
      if (finished) return;
      finished = true;
      window.removeEventListener('message', onMessage);
      clearTimeout(timer);
      // Mantém a página isolada depois do sucesso para o conteúdo renderizado
      // continuar visível. Em falha/timeout, encerra o processamento no iframe.
      if (status !== 'done') {
        frame.remove();
      }
      resolve(reason ? { status, reason } : { status });
    };

    const onMessage = (event) => {
      if (event.source !== frame.contentWindow) return;
      const message = event.data || {};
      if (message.type === 'ready') {
        frame.contentWindow.postMessage({ type: 'init', libs: absoluteLibs }, '*');
      } else if (message.type === 'libs') {
        if (!message.ok) {
          finish('unsupported');
          return;
        }
        bufferPromise
          .then((buffer) => frame.contentWindow.postMessage({ type: 'render', buffer }, '*'))
          .catch((error) => finish('error', String(error && error.name)));
      } else if (message.type === 'done') {
        finish('done');
      } else if (message.type === 'error') {
        finish('error', [message.reason, message.detail].filter(Boolean).join(': ') || 'desconhecido');
      }
    };

    window.addEventListener('message', onMessage);
    const timer = setTimeout(() => finish('timeout'), timeoutMs);
  });
}
