/**
 * Leitor de planilhas (somente leitura): SheetJS interpreta o workbook e o
 * módulo Spreadsheet do Tabulator mostra todas as abas.
 *
 * Regras:
 * - Cada aba do workbook vira uma aba do Spreadsheet, com a coordenada A1 preservada.
 * - A primeira linha não é cabeçalho.
 * - Valores são exibidos como texto formatado pelo SheetJS (`raw: false`); fórmulas
 *   não são recalculadas nem exibidas como código.
 * - Células não são editáveis e usam o formatter `plaintext`.
 * - Limites de linhas e colunas por aba evitam bloqueio do navegador.
 */

const MAX_ROWS = 2000;
const MAX_COLUMNS = 100;

function sheetRange(XLSX, sheet) {
  const range = XLSX.utils.decode_range(sheet['!ref'] || 'A1');
  return {
    rows: Math.min(range.e.r + 1, MAX_ROWS),
    columns: Math.min(range.e.c + 1, MAX_COLUMNS),
    truncated: range.e.r + 1 > MAX_ROWS || range.e.c + 1 > MAX_COLUMNS,
  };
}

function toSheetDefinition(XLSX, name, sheet) {
  const { rows, columns, truncated } = sheetRange(XLSX, sheet);
  const data = XLSX.utils.sheet_to_json(sheet, {
    header: 1,
    raw: false,
    defval: '',
    blankrows: true,
    range: { s: { r: 0, c: 0 }, e: { r: rows - 1, c: columns - 1 } },
  });
  return { title: name, rows: Math.max(rows, 1), columns: Math.max(columns, 1), data, truncated };
}

async function readWorkbook(XLSX, url) {
  const response = await fetch(url, { credentials: 'same-origin' });
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }
  const buffer = await response.arrayBuffer();
  return XLSX.read(buffer, {
    type: 'array',
    cellFormula: false,
    cellHTML: false,
    cellStyles: false,
    cellNF: true,
    dense: false,
    sheetStubs: true,
  });
}

async function mountSpreadsheet(host, frame) {
  const XLSX = window.XLSX;
  if (!XLSX) {
    throw new Error('SheetJS indisponível');
  }
  const workbook = await readWorkbook(XLSX, host.dataset.acultaSheetUrl);
  const definitions = workbook.SheetNames.map((name) => toSheetDefinition(XLSX, name, workbook.Sheets[name]));
  if (definitions.length === 0) {
    throw new Error('Planilha sem abas');
  }

  // Resolve contra a página: import() usaria o caminho relativo ao módulo.
  const tabulatorUrl = new URL(host.dataset.acultaSheetTabulator, document.baseURI).href;
  const { TabulatorFull } = await import(tabulatorUrl);
  const table = new TabulatorFull(frame, {
    spreadsheet: true,
    spreadsheetSheets: definitions.map(({ title, rows, columns, data }) => ({ title, rows, columns, data })),
    spreadsheetSheetTabs: true,
    spreadsheetColumnDefinition: { formatter: 'plaintext', editor: false },
    layout: 'fitDataStretch',
  });
  const truncated = definitions.some((definition) => definition.truncated);
  host.dataset.acultaSheetStatus = truncated ? 'truncated' : 'ok';
  return table;
}

(function (Drupal, once) {
  Drupal.behaviors.acultaSpreadsheetViewer = {
    attach(context) {
      once('aculta-spreadsheet-viewer', '[data-aculta-sheet]', context).forEach((host) => {
        const frame = host.querySelector('.aculta-spreadsheet-viewer__frame');
        if (!frame) {
          return;
        }
        mountSpreadsheet(host, frame).catch(() => {
          frame.hidden = true;
          host.dataset.acultaSheetStatus = 'error';
        });
      });
    },
  };
})(Drupal, once);
