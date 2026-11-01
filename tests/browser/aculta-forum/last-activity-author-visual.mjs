// Read-only Firefox/X11 visual smoke for the historical author card contract.
// The only fixture is synthetic DOM; no Drupal content or account state is written.
import { chmodSync, mkdirSync, writeFileSync } from 'node:fs';
import { isAbsolute, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const page = process.env.SUITE_PAGE || 'https://growroom420.aculta.toca.net.br/discussoes/novas';
const output = process.env.SUITE_OUT;
const display = process.env.DISPLAY;
const driver = process.env.GECKODRIVER || 'http://127.0.0.1:4448';
if (!output || !isAbsolute(output) || !display) {
  throw new Error('Defina DISPLAY da sessão X11 e SUITE_OUT absoluto fora do checkout.');
}
const pageUrl = new URL(page);
const deploy = JSON.parse(await (await import('node:fs/promises')).readFile(
  resolve(root, 'web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json'),
  'utf8',
));
const allowedHosts = new Set(deploy.domain_transition.entries.map((entry) => entry.test_host));
if (pageUrl.protocol !== 'https:' || !allowedHosts.has(pageUrl.hostname)
  || !['/discussoes/novas', '/discussoes/mais-comentadas', '/discussoes/sem-resposta'].includes(pageUrl.pathname)
  || pageUrl.username || pageUrl.password || pageUrl.search || pageUrl.hash) {
  throw new Error('SUITE_PAGE deve ser listagem pública de tópicos em HTTPS num host de teste declarado pelo Deployer.');
}
if (resolve(output).startsWith(`${root}/`) || resolve(output) === root) {
  throw new Error('As capturas devem ficar fora do checkout.');
}
process.umask(0o077);
mkdirSync(output, { recursive: true, mode: 0o700 });
chmodSync(output, 0o700);

const checks = [];
const check = (name, pass, detail = '') => {
  checks.push({ name, pass: !!pass, detail });
  console.log(`${pass ? 'PASS' : 'FAIL'} ${name}${detail ? ` — ${detail}` : ''}`);
};
let session;
let bidi;
let context;
let bidiId = 0;
const pending = new Map();
const request = async (method, path, body) => {
  const response = await fetch(`${driver}${path}`, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await response.json();
  if (!response.ok || data.value?.error) {
    throw new Error(`${data.value?.error || response.status}: ${data.value?.message || response.statusText}`);
  }
  return data.value;
};
const path = (suffix) => `/session/${session}${suffix}`;
const execute = (script) => request('POST', path('/execute/sync'), { script, args: [] });
const sleep = (milliseconds) => new Promise((done) => setTimeout(done, milliseconds));
const bidiCommand = (method, params = {}) => new Promise((resolveCommand, rejectCommand) => {
  const id = ++bidiId;
  pending.set(id, { resolveCommand, rejectCommand });
  bidi.send(JSON.stringify({ id, method, params }));
});
const screenshot = async (filename) => {
  const encoded = await request('GET', path('/screenshot'));
  writeFileSync(resolve(output, filename), Buffer.from(encoded, 'base64'), { mode: 0o600 });
};

try {
  const created = await request('POST', '/session', {
    capabilities: {
      alwaysMatch: {
        browserName: 'firefox',
        acceptInsecureCerts: true,
        webSocketUrl: true,
        'moz:firefoxOptions': { prefs: { 'network.dns.localDomains': pageUrl.hostname } },
      },
    },
  });
  session = created.sessionId;
  bidi = new WebSocket(created.capabilities.webSocketUrl);
  await new Promise((resolveOpen, rejectOpen) => {
    bidi.addEventListener('open', resolveOpen, { once: true });
    bidi.addEventListener('error', rejectOpen, { once: true });
  });
  bidi.addEventListener('message', (event) => {
    const message = JSON.parse(event.data);
    if (message.id && pending.has(message.id)) {
      const operation = pending.get(message.id);
      pending.delete(message.id);
      if (message.type === 'error') operation.rejectCommand(new Error(`${message.error}: ${message.message}`));
      else operation.resolveCommand(message.result);
    }
  });
  context = (await bidiCommand('browsingContext.getTree', { maxDepth: 0 })).contexts[0].context;
  await request('POST', path('/url'), { url: pageUrl.href });
  await sleep(1500);
  const initial = await execute(`return {
    host: location.hostname, path: location.pathname, title: document.title,
    h1: [...document.querySelectorAll('main h1')].map(e => e.innerText.trim()),
    rows: document.querySelectorAll('.aculta-forum-topic-list-row').length,
    errors: [...document.querySelectorAll('.messages--error,[role=alert]')]
      .map(e => e.innerText.trim()).filter(Boolean).slice(0, 3),
  };`);
  check('Página pública abriu em Firefox ESR/X11 no host e caminho de teste',
    initial.host === pageUrl.hostname && initial.path === pageUrl.pathname, JSON.stringify(initial));
  check('Listagem de tópicos tem título e linhas para revisar', initial.h1.length === 1 && initial.rows > 0,
    JSON.stringify({ h1: initial.h1, rows: initial.rows }));
  check('Sem mensagens de erro visíveis', initial.errors.length === 0, JSON.stringify(initial.errors));

  const injected = await execute(`const row = document.querySelector('.aculta-forum-topic-list-row');
    const meta = row?.querySelector('.aculta-forum-topic-list-row__meta > div:last-of-type');
    if (!meta) return false;
    const fixture = document.createElement('dd');
    fixture.className = 'aculta-forum-topic-list-row__last-author';
    fixture.setAttribute('data-visual-fixture', 'historical-author');
    fixture.innerHTML = '<article class="aculta-author-card aculta-author-card--inline aculta-author-card--author_growroom_historico aculta-author-card--sm">'
      + '<div class="aculta-author-card__identity"><div class="aculta-author-card__name">Autoria de teste-gr</div>'
      + '<span class="aculta-author-card__source">Acervo histórico Growroom</span></div></article>';
    meta.append(fixture);
    return true;`);
  check('Cartão histórico sintético injetado apenas no DOM', injected);

  for (const width of [1280, 390, 320]) {
    await bidiCommand('browsingContext.setViewport', {
      context,
      viewport: { width, height: 900, devicePixelRatio: 1 },
    });
    await sleep(250);
    const metrics = await execute(`const card = document.querySelector('[data-visual-fixture="historical-author"]');
      const name = card?.querySelector('.aculta-author-card__name');
      const source = card?.querySelector('.aculta-author-card__source');
      const rect = card?.getBoundingClientRect();
      return { viewport: innerWidth, document: document.documentElement.scrollWidth,
        name: name?.innerText.trim(), source: source?.innerText.trim(),
        hasProfileLink: !!name?.querySelector('a'),
        left: rect ? Math.round(rect.left) : null, right: rect ? Math.round(rect.right) : null,
        overflow: document.documentElement.scrollWidth > innerWidth + 1 };`);
    check(`Cartão -gr no viewport CSS ${width}px sem overflow horizontal`,
      metrics.viewport === width && metrics.name === 'Autoria de teste-gr'
      && metrics.source === 'Acervo histórico Growroom' && !metrics.hasProfileLink
      && !metrics.overflow && metrics.left !== null && metrics.left >= -1
      && metrics.right <= width + 1, JSON.stringify(metrics));
    await screenshot(`forum-last-activity-${width}.png`);
  }
}
finally {
  if (session) await request('DELETE', `/session/${session}`).catch(() => {});
  if (bidi?.readyState === WebSocket.OPEN) bidi.close();
  const passed = checks.filter((item) => item.pass).length;
  writeFileSync(resolve(output, 'results.json'), JSON.stringify({
    page: pageUrl.href,
    display,
    synthetic_dom_only: true,
    checks,
  }, null, 2), { mode: 0o600 });
  console.log(`Resultado: ${passed}/${checks.length} verificações; capturas em ${output}`);
}

if (checks.some((item) => !item.pass)) process.exitCode = 1;
