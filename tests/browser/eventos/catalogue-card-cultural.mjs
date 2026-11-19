// Captura visual do catálogo e de uma página de Evento no servidor de desenvolvimento.
// Usa Firefox ESR visível, não autentica e não altera dados.
import { mkdirSync, writeFileSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { createServer } from 'node:net';
import { join } from 'node:path';

const BASE = process.env.EVENTOS_BASE;
const OUT = process.env.SUITE_OUT;
const GECKO = process.env.GECKODRIVER_BIN || 'geckodriver';
const FIREFOX = process.env.FIREFOX || '/usr/bin/firefox-esr';
if (!BASE) throw new Error('Defina EVENTOS_BASE para o host de teste do purpose Eventos.');
if (!OUT) throw new Error('Defina SUITE_OUT para salvar as capturas fora do repositório.');
mkdirSync(OUT, { recursive: true });

const port = await new Promise((resolve, reject) => {
  const server = createServer();
  server.on('error', reject);
  server.listen(0, '127.0.0.1', () => {
    const value = server.address().port;
    server.close(() => resolve(value));
  });
});
const driver = spawn(GECKO, ['--port', String(port)], { stdio: 'ignore' });
const endpoint = `http://127.0.0.1:${port}`;
const request = async (method, path, body) => {
  const response = await fetch(endpoint + path, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  const result = await response.json();
  if (!response.ok || result.value?.error) {
    throw new Error(result.value?.message || `${method} ${path}: ${response.status}`);
  }
  return result.value;
};
const waitFor = async (check, label) => {
  const deadline = Date.now() + 20000;
  while (Date.now() < deadline) {
    if (await check()) return;
    await new Promise((resolve) => setTimeout(resolve, 150));
  }
  throw new Error(`Tempo esgotado aguardando ${label}`);
};

let sessionId;
let bidi;
const results = [];
try {
  await waitFor(async () => {
    try { return Boolean((await request('GET', '/status')).ready); } catch { return false; }
  }, 'geckodriver');
  const created = await request('POST', '/session', {
    capabilities: { alwaysMatch: {
      browserName: 'firefox',
      acceptInsecureCerts: true,
      webSocketUrl: true,
      'moz:firefoxOptions': { binary: FIREFOX, args: ['-no-remote'] },
    } },
  });
  sessionId = created.sessionId;
  const sessionPath = `/session/${sessionId}`;
  const execute = async (script) => request('POST', `${sessionPath}/execute/sync`, { script, args: [] });

  bidi = new WebSocket(created.capabilities.webSocketUrl);
  await new Promise((resolve, reject) => {
    bidi.onopen = resolve;
    bidi.onerror = reject;
  });
  let commandId = 0;
  const commands = new Map();
  bidi.onmessage = ({ data }) => {
    const message = JSON.parse(data);
    if (message.id && commands.has(message.id)) {
      commands.get(message.id)(message);
      commands.delete(message.id);
    }
  };
  const sendBidi = (method, params) => new Promise((resolve, reject) => {
    const id = ++commandId;
    commands.set(id, (message) => message.error ? reject(new Error(message.error)) : resolve(message.result));
    bidi.send(JSON.stringify({ id, method, params }));
  });
  const tree = await sendBidi('browsingContext.getTree', {});
  const context = tree.contexts[0].context;
  const setViewport = (width) => sendBidi('browsingContext.setViewport', {
    context,
    viewport: { width, height: width < 500 ? 844 : 1000 },
  });

  await setViewport(1440);
  await request('POST', `${sessionPath}/url`, { url: `${BASE}/` });
  await waitFor(async () => execute(`return document.querySelectorAll('article[data-component-id="aculta420_v2:card-cultural"]').length > 0;`), 'cartões SDC na listagem');
  const eventPath = await execute(`return document.querySelector('article[data-component-id="aculta420_v2:card-cultural"] a[href^="/eventos/"]')?.getAttribute('href') || '';`);
  if (!eventPath) throw new Error('A listagem não contém link para uma página de Evento.');

  for (const [page, path] of [['listagem', '/'], ['evento', eventPath]]) {
    for (const mode of ['light', 'dark']) {
      for (const width of [320, 1440]) {
        await setViewport(width);
        await request('POST', `${sessionPath}/url`, { url: `${BASE}${path}` });
        await waitFor(async () => execute('return document.readyState === "complete" && Boolean(document.querySelector("main"));'), `${page} carregar`);
        if (page === 'listagem') {
          await waitFor(async () => execute(`return document.querySelectorAll('article[data-component-id="aculta420_v2:card-cultural"]').length > 0;`), 'cartões na listagem');
        }
        await execute(`document.documentElement.setAttribute('data-theme', '${mode}'); return true;`);
        await waitFor(async () => execute(`return document.documentElement.getAttribute('data-theme') === '${mode}';`), `modo ${mode}`);

        // Percorre a página com Tab até o primeiro controle dentro do conteúdo principal.
        let focused = false;
        for (let tab = 0; tab < 45; tab++) {
          const state = await execute('return Boolean(document.activeElement && document.activeElement.closest("main"));');
          if (state) { focused = true; break; }
          await request('POST', `${sessionPath}/actions`, { actions: [{
            type: 'key', id: 'keyboard', actions: [{ type: 'keyDown', value: '\uE004' }, { type: 'keyUp', value: '\uE004' }],
          }] });
        }

        const visual = await execute(`return JSON.stringify((() => {
          const card = document.querySelector('article[data-component-id="aculta420_v2:card-cultural"]');
          const grid = document.querySelector('[data-component-id="aculta420_v2:layout-grid"]');
          const target = document.activeElement;
          const style = card ? getComputedStyle(card) : null;
          const gridStyle = grid ? getComputedStyle(grid) : null;
          return {
            viewport: innerWidth,
            overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            cardCount: document.querySelectorAll('article[data-component-id="aculta420_v2:card-cultural"]').length,
            mainHeading: document.querySelector('main h1')?.innerText.trim() || '',
            mainTextLength: (document.querySelector('main')?.innerText || '').trim().length,
            layoutGrid: Boolean(grid),
            gridCardCount: grid?.querySelectorAll('article[data-component-id="aculta420_v2:card-cultural"]').length || 0,
            gridDisplay: gridStyle?.display || '',
            gridColumns: gridStyle?.gridTemplateColumns || '',
            background: style?.backgroundColor || '',
            borderColor: style?.borderColor || '',
            focusedInMain: Boolean(target && target.closest('main')),
            focusOutline: target ? getComputedStyle(target).outlineStyle : '',
            text: (document.querySelector('main')?.innerText || '').replace(/\\s+/g, ' ').trim().slice(0, 160),
          };
        })());`);
        const info = JSON.parse(visual);
        const listingOk = page !== 'listagem' || (info.cardCount > 0 && info.layoutGrid && info.gridDisplay === 'grid' && info.gridCardCount === info.cardCount && focused && info.focusedInMain);
        const detailOk = page !== 'evento' || (info.mainHeading.length > 0 && info.mainTextLength > 0);
        const ok = info.viewport === width && info.overflow <= 0 && listingOk && detailOk;
        results.push({ page, mode, width, ok, ...info });
        writeFileSync(join(OUT, `${page}-${mode}-${width}.png`), Buffer.from(await request('GET', `${sessionPath}/screenshot`), 'base64'));
        console.log(`${ok ? 'PASS' : 'FAIL'} ${page} ${mode} ${width}px; overflow=${info.overflow}; card-SDC=${info.cardCount}; grid=${info.gridDisplay || 'none'} (${info.gridCardCount}); foco=${info.focusOutline}`);
      }
    }
  }
  writeFileSync(join(OUT, 'resultado.json'), JSON.stringify({ browser: FIREFOX, base: BASE, results }, null, 2));
  if (results.some((result) => !result.ok)) process.exitCode = 1;
}
finally {
  try { if (sessionId) await request('DELETE', `/session/${sessionId}`); } catch {}
  bidi?.close();
  driver.kill('SIGTERM');
}
