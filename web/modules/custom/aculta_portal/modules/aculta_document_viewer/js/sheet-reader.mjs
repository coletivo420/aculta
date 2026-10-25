/**
 * Lê uma planilha dentro de um iframe isolado (sandbox sem allow-same-origin).
 *
 * Só recebe matrizes de texto. O conteúdo do arquivo nunca entra na página principal
 * como HTML: o chamador apresenta os valores como texto.
 *
 * @param {object} options
 *   url: URL autorizada da planilha (mesma origem).
 *   format: extensão (xlsx, ods, xls, csv).
 *   frameUrl: URL de sheet-frame.html.
 *   libs: [URL do SheetJS].
 *   timeoutMs: prazo total.
 * @returns {Promise<Array<{title, rows, columns, data, truncated}>>}
 */
const LIMITS = { sheets: 20, rows: 2000, columns: 100, cellText: 500 };

/**
 * Reaplica os limites no pai: a mensagem vem de um iframe e não é confiável.
 */
function clampSheets(sheets) {
  return sheets.slice(0, LIMITS.sheets).map((sheet) => {
    const rows = Math.min(Number(sheet.rows) || 1, LIMITS.rows);
    const columns = Math.min(Number(sheet.columns) || 1, LIMITS.columns);
    const data = (Array.isArray(sheet.data) ? sheet.data : []).slice(0, rows).map((line) => (
      (Array.isArray(line) ? line : []).slice(0, columns).map((cell) => String(cell).slice(0, LIMITS.cellText))
    ));
    const clipped = data.length < (Number(sheet.rows) || 0) || (Number(sheet.columns) || 0) > columns
      || sheet.data.length > rows || sheet.sheets > LIMITS.sheets;
    return {
      title: String(sheet.title || '').slice(0, 255),
      rows,
      columns,
      data,
      truncated: Boolean(sheet.truncated) || clipped,
    };
  });
}

export function readSheetsIsolated({ url, format, frameUrl, libs, timeoutMs = 60000 }) {
  // Caminhos relativos são resolvidos contra a página, não contra este módulo.
  const absoluteFrameUrl = new URL(frameUrl, document.baseURI).href;
  const absoluteLibs = libs.map((lib) => new URL(lib, document.baseURI).href);
  return new Promise((resolve, reject) => {
    const frame = document.createElement('iframe');
    frame.setAttribute('sandbox', 'allow-scripts');
    frame.setAttribute('referrerpolicy', 'no-referrer');
    frame.setAttribute('aria-hidden', 'true');
    frame.hidden = true;
    frame.src = absoluteFrameUrl;
    document.body.appendChild(frame);

    const bufferPromise = fetch(url, { credentials: 'same-origin' }).then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return response.arrayBuffer();
    });
    bufferPromise.catch(() => {});

    let finished = false;
    let timer = null;
    const finish = (error, sheets) => {
      if (finished) return;
      finished = true;
      window.removeEventListener('message', onMessage);
      clearTimeout(timer);
      frame.remove();
      if (error) reject(error);
      else resolve(clampSheets(sheets));
    };

    const onMessage = (event) => {
      if (event.source !== frame.contentWindow) return;
      const message = event.data || {};
      if (message.type === 'ready') {
        frame.contentWindow.postMessage({ type: 'init', libs: absoluteLibs }, '*');
      } else if (message.type === 'libs') {
        if (!message.ok) {
          finish(new Error('libs'));
          return;
        }
        bufferPromise
          .then((buffer) => frame.contentWindow.postMessage({ type: 'read', buffer, format }, '*'))
          .catch((error) => finish(error));
      } else if (message.type === 'sheets') {
        if (!Array.isArray(message.sheets) || message.sheets.length === 0) {
          finish(new Error('sem abas'));
          return;
        }
        finish(null, message.sheets);
      } else if (message.type === 'error') {
        finish(new Error(message.reason || 'erro'));
      }
    };

    window.addEventListener('message', onMessage);
    timer = setTimeout(() => finish(new Error('timeout')), timeoutMs);
  });
}
