// Read-only Firefox/X11 visual checks for Growroom420 category views.
import { chmodSync, mkdirSync, writeFileSync } from 'node:fs';
import { isAbsolute, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const page = process.env.SUITE_PAGE;
const output = process.env.SUITE_OUT;
const display = process.env.DISPLAY;
const driver = process.env.GECKODRIVER || 'http://127.0.0.1:4448';
if (!page || !output || !isAbsolute(output) || !display) {
  throw new Error('Defina SUITE_PAGE, SUITE_OUT absoluto fora do checkout e DISPLAY da sessão X11.');
}

const pageUrl = new URL(page);
const deploy = JSON.parse(await (await import('node:fs/promises')).readFile(
  resolve(root, 'web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json'),
  'utf8',
));
const allowedHosts = new Set(deploy.domain_transition.entries.map((entry) => entry.test_host));
if (!allowedHosts.has(pageUrl.hostname) || pageUrl.protocol !== 'https:'
  || !['/', '/categorias'].includes(pageUrl.pathname)
  || pageUrl.username || pageUrl.password || pageUrl.search || pageUrl.hash) {
  throw new Error('SUITE_PAGE deve ser / ou /categorias, HTTPS e usar host de teste declarado pelo ACULTA Deployer.');
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
let bidiId = 0;
const bidiPending = new Map();
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
const sessionPath = (suffix) => `/session/${session}${suffix}`;
const execute = (script) => request('POST', sessionPath('/execute/sync'), { script, args: [] });
const key = (value) => request('POST', sessionPath('/actions'), {
  actions: [{
    type: 'key',
    id: 'forum-keyboard-review',
    actions: [{ type: 'keyDown', value }, { type: 'keyUp', value }],
  }],
});
const sleep = (ms) => new Promise((resolveSleep) => setTimeout(resolveSleep, ms));
const bidiCommand = (method, params = {}) => new Promise((resolveCommand, rejectCommand) => {
  const id = ++bidiId;
  bidiPending.set(id, { resolveCommand, rejectCommand });
  bidi.send(JSON.stringify({ id, method, params }));
});

try {
  const created = await request('POST', '/session', {
    capabilities: {
      alwaysMatch: {
        browserName: 'firefox',
        acceptInsecureCerts: false,
        webSocketUrl: true,
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
    if (message.id && bidiPending.has(message.id)) {
      const pending = bidiPending.get(message.id);
      bidiPending.delete(message.id);
      if (message.type === 'error') pending.rejectCommand(new Error(`${message.error}: ${message.message}`));
      else pending.resolveCommand(message.result);
    }
  });
  const context = (await bidiCommand('browsingContext.getTree', { maxDepth: 0 })).contexts[0].context;
  await request('POST', sessionPath('/url'), { url: pageUrl.href });
  await sleep(1600);

  const initial = await execute(`return {
    host: location.hostname,
    path: location.pathname,
    title: document.title,
    h1: [...document.querySelectorAll('h1')].map(e => e.innerText.trim()),
    main: !!document.querySelector('main, [role="main"]'),
    categories: document.querySelectorAll('.aculta-forum-category').length,
    topics: document.querySelectorAll('article.aculta-forum-topic, .aculta-forum-topic-list-row').length,
  };`);
  check('Página pública abriu em Firefox/X11 no host e caminho esperado',
    initial.host === pageUrl.hostname && initial.path === pageUrl.pathname, JSON.stringify(initial));
  check('Existe um título principal e região de conteúdo', initial.h1.length === 1 && initial.main, JSON.stringify(initial));
  check('A listagem apresenta categorias sem conteúdo de tópicos', initial.categories > 0 && initial.topics === 0, JSON.stringify(initial));
  check('A página não apresenta bundle do Fórum contrib legado',
    await execute(`return !document.querySelector('.node--type-forum, .node--type-comment-forum')`));

  await bidiCommand('browsingContext.setViewport', { context, viewport: { width: 390, height: 844, devicePixelRatio: 1 } });
  await key('\uE004');
  const skipLink = await execute(`
    const active = document.activeElement;
    const rect = active.getBoundingClientRect();
    const style = getComputedStyle(active);
    return {
      tag: active.tagName,
      href: active.getAttribute('href'),
      text: active.innerText.trim(),
      focusVisible: active.matches(':focus-visible'),
      rendered: style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0,
    };
  `);
  check('Primeiro Tab revela link visível para pular ao conteúdo',
    skipLink.tag === 'A' && skipLink.href === '#main-content'
    && skipLink.focusVisible && skipLink.rendered, JSON.stringify(skipLink));
  const skipScreenshot = await request('GET', sessionPath('/screenshot'));
  const skipFilename = `forum-index-${pageUrl.pathname === '/' ? 'home' : 'categorias'}-skip-link-390.png`;
  writeFileSync(resolve(output, skipFilename), Buffer.from(skipScreenshot, 'base64'), { mode: 0o600 });
  await key('\uE007');
  await sleep(100);
  const skipDestination = await execute(`return {
    hash: location.hash,
    activeId: document.activeElement.id,
    mainTabindex: document.getElementById('main-content')?.getAttribute('tabindex'),
  };`);
  check('Enter no link de salto move o foco para a região principal',
    skipDestination.hash === '#main-content'
    && skipDestination.activeId === 'main-content'
    && skipDestination.mainTabindex === '-1', JSON.stringify(skipDestination));

  for (const width of [390, 768, 1024]) {
    await bidiCommand('browsingContext.setViewport', { context, viewport: { width, height: 844 }, devicePixelRatio: 1 });
    await sleep(250);
    const metrics = await execute(`
      const main = document.querySelector('main, [role="main"]');
      const rect = main?.getBoundingClientRect();
      const viewport = innerWidth;
      const cards = [...document.querySelectorAll('.aculta-forum-category')].map(e => {
        const r=e.getBoundingClientRect();
        return {left:Math.round(r.left),right:Math.round(r.right),width:Math.round(r.width)};
      });
      const outside = cards.filter(r => r.left < -1 || r.right > viewport + 1);
      return {viewport, document:document.documentElement.scrollWidth, body:document.body.scrollWidth,
        mainLeft:rect ? Math.round(rect.left) : null, mainRight:rect ? Math.round(rect.right) : null,
        cards:cards.length, outside:outside.slice(0,4)};
    `);
    check(`Viewport CSS ${width}px mantém categorias no enquadramento`,
      metrics.viewport === width && metrics.mainLeft >= -1 && metrics.mainRight <= width + 1
      && metrics.document <= width + 1 && metrics.outside.length === 0, JSON.stringify(metrics));

    for (const mode of ['light', 'dark']) {
      await execute(`document.documentElement.setAttribute('data-bs-theme', ${JSON.stringify(mode)}); return true;`);
      await sleep(120);
      const screenshot = await request('GET', sessionPath('/screenshot'));
      const filename = `forum-index-${pageUrl.pathname === '/' ? 'home' : 'categorias'}-${width}-${mode}.png`;
      writeFileSync(resolve(output, filename), Buffer.from(screenshot, 'base64'), { mode: 0o600 });
      const visual = await execute(`return {
        mode:document.documentElement.getAttribute('data-bs-theme'), width:innerWidth,
        cards:document.querySelectorAll('.aculta-forum-category').length,
        topicCards:document.querySelectorAll('article.aculta-forum-topic, .aculta-forum-topic-list-row').length,
      };`);
      check(`Captura ${mode} ${width}px mantém a listagem categorial`,
        visual.mode === mode && visual.width === width && visual.cards > 0 && visual.topicCards === 0,
        JSON.stringify(visual));
    }
  }
}
finally {
  if (session) await request('DELETE', `/session/${session}`).catch(() => {});
  if (bidi?.readyState === WebSocket.OPEN) bidi.close();
  const passed = checks.filter((item) => item.pass).length;
  writeFileSync(resolve(output, 'results.json'), JSON.stringify({ page: pageUrl.href, display, checks }, null, 2), { mode: 0o600 });
  console.log(`Resultado: ${passed}/${checks.length} verificações; capturas em ${output}`);
}

if (checks.some((item) => !item.pass)) process.exitCode = 1;
