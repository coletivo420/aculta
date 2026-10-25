// Read-only X11 visual checks for the public Growroom420 search page.
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
  || pageUrl.pathname !== '/pesquisar' || pageUrl.username || pageUrl.password || pageUrl.hash) {
  throw new Error('SUITE_PAGE deve ser /pesquisar, HTTPS e usar host de teste declarado pelo ACULTA Deployer.');
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
const path = (suffix) => `/session/${session}${suffix}`;
const execute = (script) => request('POST', path('/execute/sync'), { script, args: [] });
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
  await request('POST', path('/url'), { url: pageUrl.href });
  await sleep(1800);
  check('A busca pública abriu no host de teste em Firefox/X11', await execute(`return location.hostname === ${JSON.stringify(pageUrl.hostname)} && location.pathname === '/pesquisar'`));
  check('A tela apresenta um único título principal', await execute(`return document.querySelectorAll('main h1, h1').length === 1 && document.querySelector('h1')?.innerText.includes('Pesquisar discussões')`));
  check('O formulário de busca e o filtro de categoria estão presentes', await execute(`return !!document.querySelector('form input[name="keys"]') && !!document.querySelector('form select[name="categoria"]') && !!document.querySelector('form button[type="submit"], form input[type="submit"]')`));
  check('A busca GET de somente leitura não apresenta CAPTCHA', await execute(`return !document.querySelector('[name*="captcha" i], iframe[src*="turnstile" i], .cf-turnstile')`));
  check('A consulta sem conteúdo mostra estado vazio e link de categorias', await execute(`return document.body.innerText.includes('Nenhuma discussão corresponde') && [...document.querySelectorAll('a')].some(a => new URL(a.href).pathname === '/categorias')`));

  for (const width of [390, 768, 1024]) {
    await bidiCommand('browsingContext.setViewport', { context, viewport: { width, height: 844 }, devicePixelRatio: 1 });
    await sleep(200);
    const metrics = await execute(`
      const form = document.querySelector('form input[name="keys"]')?.closest('form');
      const rect = form?.getBoundingClientRect();
      const viewport = innerWidth;
      const overflow = [...document.querySelectorAll('body *')]
        .map(e => { const r=e.getBoundingClientRect(), s=getComputedStyle(e); return {id:e.id, className:typeof e.className==='string'?e.className.slice(0,70):'', right:Math.round(r.right), width:Math.round(r.width), display:s.display, position:s.position}; })
        .filter(e => e.display!=='none' && e.width>0 && e.right>viewport+1)
        .sort((a,b)=>b.right-a.right).slice(0,4);
      return {viewport, document:document.documentElement.scrollWidth, body:document.body.scrollWidth,
        formLeft:rect ? Math.round(rect.left) : null, formRight:rect ? Math.round(rect.right) : null,
        query:document.querySelector('input[name="keys"]')?.value, overflow};
    `);
    check(`Viewport CSS ${width}px mantém formulário dentro da tela`, metrics.viewport === width && metrics.formLeft >= -1 && metrics.formRight <= width + 1, JSON.stringify(metrics));
    for (const mode of ['light', 'dark']) {
      await execute(`document.documentElement.setAttribute('data-bs-theme', ${JSON.stringify(mode)}); return true;`);
      await sleep(100);
      const screenshot = await request('GET', path('/screenshot'));
      const filename = `forum-search-${width}-${mode}.png`;
      writeFileSync(resolve(output, filename), Buffer.from(screenshot, 'base64'), { mode: 0o600 });
      const colors = await execute(`
        const button=document.querySelector('input[type="submit"]');
        const style=getComputedStyle(button);
        const rgb=value=>value.match(/[\\d.]+/g).slice(0,3).map(Number);
        const linear=value=>{value/=255;return value<=0.04045?value/12.92:((value+0.055)/1.055)**2.4;};
        const luminance=value=>{const [r,g,b]=rgb(value).map(linear);return 0.2126*r+0.7152*g+0.0722*b;};
        const fg=luminance(style.color), bg=luminance(style.backgroundColor);
        return {mode:document.documentElement.getAttribute('data-bs-theme'), width:innerWidth,
          buttonDisabled:button.disabled, buttonColor:style.color, buttonBackground:style.backgroundColor,
          buttonContrast:(Math.max(fg,bg)+0.05)/(Math.min(fg,bg)+0.05)};
      `);
      check(`Captura visual ${mode} em ${width}px`, colors.mode === mode && colors.width === width, JSON.stringify(colors));
      check(`Contraste do botão da busca ${mode} ≥4,5:1 em ${width}px`, colors.buttonContrast >= 4.5, JSON.stringify(colors));
    }
  }
}
finally {
  if (session) await request('DELETE', `/session/${session}`).catch(() => {});
  if (bidi?.readyState === WebSocket.OPEN) bidi.close();
  const passed = checks.filter((item) => item.pass).length;
  writeFileSync(resolve(output, 'results.json'), JSON.stringify({ page: pageUrl.href, display, checks, passed, total: checks.length }, null, 2), { mode: 0o600 });
  console.log(`${passed}/${checks.length} verificações visuais passaram. Evidências privadas: ${output}.`);
}
if (checks.some((item) => !item.pass)) process.exitCode = 1;
