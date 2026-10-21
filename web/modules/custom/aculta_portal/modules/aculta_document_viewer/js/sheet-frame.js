/**
 * Leitor isolado de planilhas: só o SheetJS roda aqui (iframe sandbox="allow-scripts",
 * origem opaca). O pai recebe matrizes de texto, já formatadas e limitadas.
 *
 * Protocolo (somente postMessage, origem do pai verificada):
 *   filha -> pai: { type: 'ready' }
 *   pai   -> filha: { type: 'init', libs: [url do SheetJS] }
 *   filha -> pai: { type: 'libs', ok }
 *   pai   -> filha: { type: 'read', buffer, format }
 *   filha -> pai: { type: 'sheets', sheets: [{ title, rows, columns, data, truncated }] } | { type: 'error', reason }
 *
 * Limites aplicados aqui e de novo no pai: linhas e colunas por aba, abas e tamanho de texto por célula.
 */
(function () {
  'use strict';

  var MAX_ROWS = 2000;
  var MAX_COLUMNS = 100;
  var MAX_SHEETS = 20;
  var MAX_CELL_TEXT = 500;

  var pad = function (value) { return String(value).padStart(2, '0'); };
  var numberFormatter = function (options) { return new Intl.NumberFormat('pt-BR', options); };

  function numberOptions(format) {
    format = format || '';
    var decimals = /\.(0+|#+)/.exec(format);
    return {
      minimumFractionDigits: decimals ? decimals[1].replace(/#/g, '').length : 0,
      maximumFractionDigits: decimals ? decimals[1].length : 10,
      useGrouping: format === '' || format.indexOf(',') !== -1
    };
  }

  function formatDate(XLSX, value, format) {
    var parts = XLSX.SSF.parse_date_code(value);
    if (!parts) { return ''; }
    var date = pad(parts.d) + '/' + pad(parts.m) + '/' + String(parts.y).padStart(4, '0');
    var hasTime = parts.H || parts.M || parts.S || /h/i.test(format || '');
    if (!hasTime) { return date; }
    var withSeconds = parts.S > 0 || /:ss/i.test(format || '');
    return date + ' ' + pad(parts.H) + ':' + pad(parts.M) + (withSeconds ? ':' + pad(parts.S) : '');
  }

  function formatCell(XLSX, cell) {
    if (!cell || cell.v === undefined || cell.v === null) { return ''; }
    var text;
    if (cell.t === 'b') {
      text = cell.v ? 'VERDADEIRO' : 'FALSO';
    } else if (cell.t === 'e') {
      text = cell.w || '#ERRO';
    } else if (cell.t === 'n') {
      if (cell.z && XLSX.SSF.is_date(cell.z)) {
        text = formatDate(XLSX, cell.v, cell.z);
      } else if (cell.z && cell.z.indexOf('%') !== -1) {
        text = numberFormatter(Object.assign(numberOptions(cell.z), { style: 'percent' })).format(cell.v);
      } else {
        text = numberFormatter(numberOptions(cell.z)).format(cell.v);
      }
    } else {
      text = String(cell.v);
    }
    return text.length > MAX_CELL_TEXT ? text.slice(0, MAX_CELL_TEXT) + '…' : text;
  }

  function sheetMatrix(XLSX, sheet) {
    var range = XLSX.utils.decode_range(sheet['!ref'] || 'A1');
    var rows = Math.min(range.e.r + 1, MAX_ROWS);
    var columns = Math.min(range.e.c + 1, MAX_COLUMNS);
    var data = [];
    for (var r = 0; r < rows; r++) {
      var line = [];
      for (var c = 0; c < columns; c++) {
        line.push(formatCell(XLSX, sheet[XLSX.utils.encode_cell({ r: r, c: c })]));
      }
      data.push(line);
    }
    return {
      rows: Math.max(rows, 1),
      columns: Math.max(columns, 1),
      data: data,
      truncated: range.e.r + 1 > MAX_ROWS || range.e.c + 1 > MAX_COLUMNS
    };
  }

  function readSheets(buffer, format) {
    var XLSX = window.XLSX;
    var workbook = XLSX.read(buffer, {
      type: 'array',
      raw: format === 'csv',
      cellFormula: false,
      cellHTML: false,
      cellStyles: false,
      cellNF: true,
      dense: false,
      sheetStubs: true
    });
    return workbook.SheetNames.slice(0, MAX_SHEETS).map(function (name) {
      var matrix = sheetMatrix(XLSX, workbook.Sheets[name]);
      matrix.title = String(name).slice(0, 255);
      return matrix;
    });
  }

  function post(message) {
    window.parent.postMessage(message, '*');
  }

  function loadScript(url) {
    return new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.async = false;
      script.src = url;
      script.onload = resolve;
      script.onerror = function () { reject(new Error('script')); };
      document.head.appendChild(script);
    });
  }

  window.addEventListener('message', function (event) {
    if (event.source !== window.parent || !event.data || typeof event.data !== 'object') {
      return;
    }
    var message = event.data;

    if (message.type === 'init' && Array.isArray(message.libs)) {
      message.libs.reduce(function (chain, url) {
        return chain.then(function () { return loadScript(url); });
      }, Promise.resolve())
        .then(function () { post({ type: 'libs', ok: typeof window.XLSX === 'object' }); })
        .catch(function () { post({ type: 'libs', ok: false }); });
      return;
    }

    if (message.type === 'read' && message.buffer) {
      try {
        post({ type: 'sheets', sheets: readSheets(message.buffer, message.format) });
      } catch (error) {
        post({ type: 'error', reason: String(error && error.name) });
      }
    }
  });

  post({ type: 'ready' });
})();
