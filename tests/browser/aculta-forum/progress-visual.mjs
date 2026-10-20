// X11 visual regression checks for the shared discussion progress controls.
// Only synthetic DOM messages are added; this script never submits a form.
import { chmodSync, mkdirSync, writeFileSync } from 'node:fs';
import { dirname, isAbsolute, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const page = process.env.SUITE_PAGE;
const output = process.env.SUITE_OUT;
const display = process.env.DISPLAY;
const driver = process.env.GECKODRIVER || 'http://127.0.0.1:4444';
if (!page || !output || !isAbsolute(output) || !display) {
  throw new Error('Defina SUITE_PAGE, SUITE_OUT absoluto fora do checkout e DISPLAY da sessão X11.');
}
const pageUrl = new URL(page);
const deploy = JSON.parse(await (await import('node:fs/promises')).readFile(
  resolve(root, 'web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json'),
  'utf8',
));
const allowedHosts = new Set(deploy.domain_transition.entries.map((entry) => entry.test_host));
if (!allowedHosts.has(pageUrl.hostname) || !['https:', 'http:'].includes(pageUrl.protocol)
  || pageUrl.username || pageUrl.password || pageUrl.search || pageUrl.hash) {
  throw new Error('SUITE_PAGE precisa usar um host de teste declarado pelo ACULTA Deployer.');
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
let context;
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
const path = (suffix) => `/session/${session}${suffix}`;
const execute = (script, args = []) => request('POST', path('/execute/sync'), { script, args });
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
    if (message.id && bidiPending.has(message.id)) {
      const pending = bidiPending.get(message.id);
      bidiPending.delete(message.id);
      if (message.type === 'error') pending.rejectCommand(new Error(`${message.error}: ${message.message}`));
      else pending.resolveCommand(message.result);
    }
  });
  context = (await bidiCommand('browsingContext.getTree', { maxDepth: 0 })).contexts[0].context;
  await request('POST', path('/url'), { url: pageUrl.href });
  await sleep(1500);
  check('Página de teste carregou em Firefox com interface X11', await execute(`return location.hostname === ${JSON.stringify(pageUrl.hostname)} && !!document.querySelector('[data-aculta-discussion-thread]')`));

  check('A biblioteca de progresso foi carregada', await execute(`return !!Drupal?.behaviors?.acultaDiscussionProgress && typeof once === 'function'`));
  await execute(`
    const source = document.querySelector('[data-aculta-discussion-thread]');
    const thread = document.createElement('div');
    thread.setAttribute('data-aculta-discussion-thread', '');
    thread.className = 'field--name-field-aculta-discussion';
    for (let i = 1; i <= 6; i++) {
      const message = document.createElement('article');
      message.id = 'aculta-visual-comment-' + i;
      message.className = 'js-comment aculta-discussion-comment';
      const body = document.createElement('div');
      body.className = 'aculta-discussion-comment__body';
      body.textContent = 'Mensagem visual sintética ' + i + '. Texto de revisão de responsividade, hierarquia e navegação da discussão.';
      message.append(body);
      thread.append(message);
    }
    source.append(thread);
    window.__acultaTestThread = thread;
    Drupal.behaviors.acultaDiscussionProgress.attach(source);
    return thread.querySelectorAll('article.js-comment[id]').length;
  `);
  check('Renderiza controles de progresso com seis mensagens DOM sintéticas', await execute(`const t=window.__acultaTestThread; return t.querySelector('[data-aculta-discussion-progress]')?.querySelectorAll('button').length === 5 && t.querySelector('[data-aculta-discussion-range]')?.max === '5'`));
  check('A ação Não lidas começa oculta', await execute(`return window.__acultaTestThread.querySelector('[data-aculta-discussion-unread]').hidden`));
  await execute(`document.querySelector('#aculta-visual-comment-4').classList.add('new'); return true;`);
  await sleep(100);
  check('History .new revela a ação Não lidas', await execute(`return !window.__acultaTestThread.querySelector('[data-aculta-discussion-unread]').hidden`));
  await execute(`window.__acultaTestThread.querySelector('[data-aculta-discussion-unread]').click(); return true;`);
  check('Não lidas anuncia a primeira mensagem marcada', await execute(`const a = window.__acultaTestThread.querySelector('[data-aculta-discussion-announcement]'); return a.textContent.includes('Mensagem 4 de 6') && a.getAttribute('aria-live') === 'polite'`));
  await execute(`document.querySelector('#aculta-visual-comment-4').classList.remove('new'); return true;`);
  await sleep(100);
  check('A ação Não lidas some quando o marcador desaparece', await execute(`return window.__acultaTestThread.querySelector('[data-aculta-discussion-unread]').hidden`));

  // Restore the visible unread state so the target size and placement are measured.
  await execute(`document.querySelector('#aculta-visual-comment-4').classList.add('new'); return true;`);
  await sleep(100);
  for (const width of [320, 360, 390, 500, 900]) {
    await bidiCommand('browsingContext.setViewport', { context, viewport: { width, height: 844 }, devicePixelRatio: 1 });
    const baseline = await execute(`const t=window.__acultaTestThread; const parent=t.parentElement; t.remove(); const viewport=innerWidth; const offenders=[...document.querySelectorAll('body *')].map(e=>{const r=e.getBoundingClientRect(),s=getComputedStyle(e);return {tag:e.tagName,id:e.id,className:typeof e.className==='string'?e.className.slice(0,80):'',left:Math.round(r.left),right:Math.round(r.right),width:Math.round(r.width),display:s.display,position:s.position,overflow:s.overflowX}}).filter(e=>e.display!=='none'&&e.width>0&&e.right>viewport+1).sort((a,b)=>b.right-a.right).slice(0,5); const metrics={viewport,document:document.documentElement.scrollWidth,body:document.body.scrollWidth,offenders}; parent.append(t); return metrics;`);
    await execute(`const t=window.__acultaTestThread; const top=t.querySelector('[data-aculta-discussion-progress]').getBoundingClientRect().top + window.scrollY; window.scrollTo(0, Math.max(0, top - 80)); return true;`);
    await sleep(200);
    const metrics = await execute(`const t=window.__acultaTestThread; const nav=t.querySelector('[data-aculta-discussion-progress]'); const slider=nav.querySelector('[data-aculta-discussion-range]'); const unread=nav.querySelector('[data-aculta-discussion-unread]'); return {viewport:innerWidth,document:document.documentElement.scrollWidth,body:document.body.scrollWidth,nav:Math.round(nav.getBoundingClientRect().width),navLeft:Math.round(nav.getBoundingClientRect().left),navRight:Math.round(nav.getBoundingClientRect().right),slider:Math.round(slider.getBoundingClientRect().width),touch:Math.round(unread.getBoundingClientRect().height),status:nav.querySelector('[data-aculta-discussion-status]').innerText};`);
    check(`Viewport CSS exato ${width}px; os controles não ampliam overflow`, metrics.viewport === width && metrics.navLeft >= -1 && metrics.navRight <= width + 1 && metrics.document <= baseline.document + 1 && metrics.body <= baseline.body + 1, JSON.stringify({ ...metrics, baseline }));
    if (width <= 500) {
      check(`Alvo de toque Não lidas ≥44px a ${width}px`, metrics.touch >= 44, `${metrics.touch}px`);
    }
    if (width === 390 || width === 900) {
      for (const mode of ['light', 'dark']) {
        await execute(`document.documentElement.setAttribute('data-bs-theme', ${JSON.stringify(mode)}); return true;`);
        await sleep(100);
        const name = `forum-progress-${width}-${mode}`;
        const png = await request('GET', path('/screenshot'));
        writeFileSync(resolve(output, `${name}.png`), Buffer.from(png, 'base64'), { mode: 0o600 });
        const colors = await execute(`const e=window.__acultaTestThread.querySelector('[data-aculta-discussion-progress]'); return {background:getComputedStyle(e).backgroundColor,text:getComputedStyle(e.querySelector('button')).color,width:innerWidth};`);
        check(`Captura X11 ${mode} ${width}px legível`, colors.width > 0 && colors.background !== 'rgba(0, 0, 0, 0)', JSON.stringify(colors));
      }
    }
  }
}
finally {
  if (session) await request('DELETE', `/session/${session}`).catch(() => {});
  if (bidi?.readyState === WebSocket.OPEN) bidi.close();
  const passed = checks.filter((item) => item.pass).length;
  const resultFile = resolve(output, 'results.json');
  writeFileSync(resultFile, JSON.stringify({ page: pageUrl.href, display, checks, passed, total: checks.length }, null, 2), { mode: 0o600 });
  console.log(`${passed}/${checks.length} verificações visuais passaram. Evidências em ${dirname(resultFile)}.`);
}
if (checks.some((item) => !item.pass)) process.exitCode = 1;
