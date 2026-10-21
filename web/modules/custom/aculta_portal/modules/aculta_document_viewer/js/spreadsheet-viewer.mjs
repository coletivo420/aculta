/**
 * Leitor de planilhas (somente leitura).
 *
 * O SheetJS roda num iframe isolado (sheet-frame.js). Este módulo recebe matrizes de
 * texto já formatadas e as apresenta com o Tabulator em dois modos:
 * - Planilha: módulo Spreadsheet, uma aba por planilha, coordenadas A1, primeira linha como dado.
 * - Tabela: uma aba por vez como registros, primeira linha como cabeçalho, ordenação e filtros.
 *
 * Os valores são sempre texto (formatter `plaintext`, sem editor). Nenhuma fórmula é recalculada.
 */
import { setupDocumentChrome } from './document-chrome.mjs';
import { readSheetsIsolated } from './sheet-reader.mjs';

const translate = (text) => (window.Drupal && typeof Drupal.t === 'function' ? Drupal.t(text) : text);

function columnLetter(index) {
  let letters = '';
  let number = index + 1;
  while (number > 0) {
    const rest = (number - 1) % 26;
    letters = String.fromCharCode(65 + rest) + letters;
    number = Math.floor((number - 1) / 26);
  }
  return letters;
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

function tableOptions(definition) {
  const [headerRow = [], ...body] = definition.data;
  const seen = new Map();
  const columns = headerRow.map((text, index) => {
    const base = String(text).trim() || `Coluna ${columnLetter(index)}`;
    const count = (seen.get(base) || 0) + 1;
    seen.set(base, count);
    return {
      title: count > 1 ? `${base} (${count})` : base,
      // Título em texto puro: o Tabulator usaria innerHTML sem este formatador.
      titleFormatter: 'plaintext',
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
    placeholder: translate('Nenhum registro nesta aba.'),
  };
}

function renderModes(toolbar, sheetTitles, onChange) {
  const group = document.createElement('div');
  group.dataset.acultaDocumentPart = 'modes';
  group.setAttribute('role', 'group');
  group.setAttribute('aria-label', translate('Modo de leitura'));
  const buttons = [
    ['sheet', translate('Planilha')],
    ['table', translate('Tabela')],
  ].map(([mode, label]) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.acultaDocumentPart = 'mode';
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
  select.setAttribute('aria-label', translate('Aba exibida no modo Tabela'));
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
  const chrome = setupDocumentChrome(host, frame);
  chrome.setStatus(translate('Carregando planilha…'));

  const definitions = await readSheetsIsolated({
    url: host.dataset.acultaSheetUrl,
    format: host.dataset.acultaSheetFormat,
    frameUrl: new URL(host.dataset.acultaSheetFrameUrl, document.baseURI).href,
    libs: JSON.parse(host.dataset.acultaSheetLibs || '[]').map((lib) => new URL(lib, document.baseURI).href),
  });

  // Resolve contra a página: import() usaria o caminho relativo ao módulo.
  const tabulatorUrl = new URL(host.dataset.acultaSheetTabulator, document.baseURI).href;
  const { TabulatorFull } = await import(tabulatorUrl);

  let table = null;
  const show = (mode, sheetIndex = 0) => {
    if (table) {
      table.destroy();
    }
    frame.textContent = '';
    table = mode === 'sheet'
      ? new TabulatorFull(frame, spreadsheetOptions(definitions))
      : new TabulatorFull(frame, tableOptions(definitions[sheetIndex]));
  };
  renderModes(chrome.toolbar, definitions.map((definition) => definition.title), (mode, index) => show(mode, index));
  show('sheet');

  const truncated = definitions.some((definition) => definition.truncated);
  host.dataset.acultaSheetStatus = truncated ? 'truncated' : 'ok';
  host.dataset.acultaSheetCount = String(definitions.length);
  chrome.setStatus(truncated ? translate('A planilha foi limitada a 2000 linhas e 100 colunas por aba.') : '');
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
