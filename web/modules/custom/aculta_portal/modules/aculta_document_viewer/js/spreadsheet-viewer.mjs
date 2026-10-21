import { setupDocumentChrome } from './document-chrome.mjs';

const translate = (text) => (window.Drupal && typeof Drupal.t === 'function' ? Drupal.t(text) : text);

/**
 * Leitor de planilhas (somente leitura): SheetJS interpreta o arquivo e o
 * Tabulator mostra as abas em dois modos.
 *
 * - Planilha: módulo Spreadsheet do Tabulator, uma aba por planilha, coordenadas
 *   A1 preservadas, a primeira linha é dado (não cabeçalho), sem edição.
 * - Tabela: uma aba por vez como registros, a primeira linha vira cabeçalho, com
 *   ordenação e filtros. Continua somente leitura e não altera o arquivo.
 *
 * Formatação em pt-BR: números com vírgula decimal, datas `dd/mm/aaaa` a partir
 * dos componentes da data (sem depender do fuso do navegador), percentuais e
 * valores lógicos traduzidos. Fórmulas mostram o valor armazenado; nada é recalculado.
 */

const MAX_ROWS = 2000;
const MAX_COLUMNS = 100;
const pad = (value) => String(value).padStart(2, '0');

/**
 * Lê casas decimais e agrupamento do código de formato da célula (ex.: `#,##0.00`).
 * Sem código de formato, usa até 10 casas e agrupamento de milhar.
 */
function numberOptions(format = '') {
  const decimals = /\.(0+|#+)/.exec(format);
  return {
    minimumFractionDigits: decimals ? decimals[1].replace(/#/g, '').length : 0,
    maximumFractionDigits: decimals ? decimals[1].length : 10,
    useGrouping: format === '' || format.includes(','),
  };
}

function formatDate(XLSX, value, format = '') {
  const parts = XLSX.SSF.parse_date_code(value);
  if (!parts) {
    return '';
  }
  const date = `${pad(parts.d)}/${pad(parts.m)}/${String(parts.y).padStart(4, '0')}`;
  const hasTime = parts.H || parts.M || parts.S || /h/i.test(format);
  if (!hasTime) {
    return date;
  }
  const withSeconds = parts.S > 0 || /:ss/i.test(format);
  const time = `${pad(parts.H)}:${pad(parts.M)}${withSeconds ? `:${pad(parts.S)}` : ''}`;
  return `${date} ${time}`;
}

function formatCell(XLSX, cell) {
  if (!cell || cell.v === undefined || cell.v === null) {
    return '';
  }
  if (cell.t === 'b') {
    return cell.v ? 'VERDADEIRO' : 'FALSO';
  }
  if (cell.t === 'e') {
    return cell.w || '#ERRO';
  }
  if (cell.t === 'n') {
    if (cell.z && XLSX.SSF.is_date(cell.z)) {
      return formatDate(XLSX, cell.v, cell.z);
    }
    if (cell.z && cell.z.includes('%')) {
      return new Intl.NumberFormat('pt-BR', { ...numberOptions(cell.z), style: 'percent' }).format(cell.v);
    }
    return new Intl.NumberFormat('pt-BR', numberOptions(cell.z)).format(cell.v);
  }
  return String(cell.v);
}

function sheetMatrix(XLSX, sheet) {
  const range = XLSX.utils.decode_range(sheet['!ref'] || 'A1');
  const rows = Math.min(range.e.r + 1, MAX_ROWS);
  const columns = Math.min(range.e.c + 1, MAX_COLUMNS);
  const data = [];
  for (let r = 0; r < rows; r++) {
    const line = [];
    for (let c = 0; c < columns; c++) {
      line.push(formatCell(XLSX, sheet[XLSX.utils.encode_cell({ r, c })]));
    }
    data.push(line);
  }
  return {
    data,
    rows: Math.max(rows, 1),
    columns: Math.max(columns, 1),
    truncated: range.e.r + 1 > MAX_ROWS || range.e.c + 1 > MAX_COLUMNS,
  };
}

async function readWorkbook(XLSX, url, format) {
  const response = await fetch(url, { credentials: 'same-origin' });
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }
  const buffer = await response.arrayBuffer();
  return XLSX.read(buffer, {
    type: 'array',
    // CSV é texto: o SheetJS não deve converter "25.6%" em número.
    raw: format === 'csv',
    cellFormula: false,
    cellHTML: false,
    cellStyles: false,
    cellNF: true,
    dense: false,
    sheetStubs: true,
  });
}

function spreadsheetOptions(definitions) {
  return {
    spreadsheet: true,
    spreadsheetSheets: definitions.map(({ title, rows, columns, data }) => ({ title, rows, columns, data })),
    spreadsheetSheetTabs: true,
    spreadsheetColumnDefinition: { formatter: 'plaintext', editor: false },
    layout: 'fitDataStretch',
  };
}

function tableOptions(XLSX, definition) {
  const [headerRow = [], ...body] = definition.data;
  const seen = new Map();
  const columns = headerRow.map((text, index) => {
    const base = String(text).trim() || `Coluna ${XLSX.utils.encode_col(index)}`;
    const count = (seen.get(base) || 0) + 1;
    seen.set(base, count);
    return {
      title: count > 1 ? `${base} (${count})` : base,
      field: `c${index}`,
      headerFilter: 'input',
      sorter: 'string',
      formatter: 'plaintext',
      editor: false,
    };
  });
  const records = body
    .filter((line) => line.some((value) => value !== ''))
    .map((line) => Object.fromEntries(line.map((value, index) => [`c${index}`, value])));
  return {
    data: records,
    columns,
    layout: 'fitDataStretch',
    pagination: 'local',
    paginationSize: 50,
    placeholder: 'Nenhum registro nesta aba.',
  };
}

function renderModes(toolbar, sheetTitles, onChange) {
  const group = document.createElement('div');
  group.dataset.acultaDocumentPart = 'modes';
  group.setAttribute('role', 'group');
  group.setAttribute('aria-label', 'Modo de leitura');
  const modes = [
    ['sheet', 'Planilha'],
    ['table', 'Tabela'],
  ];
  const buttons = modes.map(([mode, label]) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.acultaDocumentPart = 'mode';
    button.dataset.acultaSheetMode = mode;
    button.textContent = label;
    button.setAttribute('aria-pressed', mode === 'sheet' ? 'true' : 'false');
    button.addEventListener('click', () => {
      buttons.forEach((other) => other.setAttribute('aria-pressed', other === button ? 'true' : 'false'));
      select.hidden = mode !== 'table';
      onChange(mode, Number(select.value));
    });
    group.appendChild(button);
    return button;
  });
  const select = document.createElement('select');
  select.dataset.acultaDocumentPart = 'sheet-select';
  select.setAttribute('aria-label', 'Aba exibida no modo Tabela');
  select.hidden = true;
  sheetTitles.forEach((title, index) => {
    const option = document.createElement('option');
    option.value = String(index);
    option.textContent = title;
    select.appendChild(option);
  });
  select.addEventListener('change', () => onChange('table', Number(select.value)));
  group.appendChild(select);
  toolbar.append(group);
}

async function mountSpreadsheet(host, frame) {
  const XLSX = window.XLSX;
  if (!XLSX) {
    throw new Error('SheetJS indisponível');
  }
  const chrome = setupDocumentChrome(host, frame);
  chrome.setStatus(translate('Carregando planilha…'));
  const workbook = await readWorkbook(XLSX, host.dataset.acultaSheetUrl, host.dataset.acultaSheetFormat);
  const definitions = workbook.SheetNames.map((name) => ({ title: name, ...sheetMatrix(XLSX, workbook.Sheets[name]) }));
  if (definitions.length === 0) {
    throw new Error('Planilha sem abas');
  }
  // Resolve contra a página: import() usaria o caminho relativo ao módulo.
  const tabulatorUrl = new URL(host.dataset.acultaSheetTabulator, document.baseURI).href;
  const { TabulatorFull } = await import(tabulatorUrl);

  let table = null;
  const show = (mode, sheetIndex = 0) => {
    if (table) {
      table.destroy();
    }
    frame.textContent = '';
    if (mode === 'sheet') {
      table = new TabulatorFull(frame, spreadsheetOptions(definitions));
    }
    else {
      table = new TabulatorFull(frame, tableOptions(XLSX, definitions[sheetIndex]));
    }
  };
  renderModes(chrome.toolbar, definitions.map((definition) => definition.title), (mode, index) => show(mode, index));
  show('sheet');
  host.dataset.acultaSheetStatus = definitions.some((definition) => definition.truncated) ? 'truncated' : 'ok';
  chrome.setStatus(definitions.some((definition) => definition.truncated) ? translate('A planilha foi limitada a 2000 linhas e 100 colunas por aba.') : '');
  host.dataset.acultaSheetCount = String(definitions.length);
  return table;
}

(function (Drupal, once) {
  Drupal.behaviors.acultaSpreadsheetViewer = {
    attach(context) {
      once('aculta-spreadsheet-viewer', '[data-aculta-sheet]', context).forEach((host) => {
        const frame = host.querySelector('[data-aculta-document-part="frame"]');
        if (!frame) {
          return;
        }
        mountSpreadsheet(host, frame).catch(() => {
          frame.hidden = true;
          host.dataset.acultaSheetStatus = 'error';
          const status = host.querySelector('[data-aculta-document-control="status"]');
          if (status) {
            status.textContent = translate('Não foi possível exibir esta planilha aqui. Use o link de download abaixo.');
            status.hidden = false;
          }
        });
      });
    },
  };
})(Drupal, once);
