// Read-only X11 smoke test for Forum and comment moderation admin pages.
// It uses the existing administrator and performs GET navigations only.
import { chmodSync, mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { isAbsolute, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const output = process.env.SUITE_OUT;
const display = process.env.DISPLAY;
const driver = process.env.GECKODRIVER || 'http://127.0.0.1:4444';
if (!output || !isAbsolute(output) || !display) {
  throw new Error('Defina SUITE_OUT absoluto fora do checkout e DISPLAY da sessão X11.');
}
const deploy = JSON.parse(await (await import('node:fs/promises')).readFile(
  resolve(root, 'web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json'),
  'utf8',
));
const mainEntry = deploy.domain_transition.entries.find((entry) => entry.domain_id === 'aculta_org');
const accountEntry = deploy.domain_transition.entries.find((entry) => entry.domain_id === 'conta_aculta_org');
const testHosts = deploy.domain_transition.entries.map((entry) => entry.test_host);
if (!mainEntry?.test_host || !accountEntry?.test_host || resolve(output).startsWith(`${root}/`) || resolve(output) === root) {
  throw new Error('Hosts MAIN/ACCOUNT de teste ausentes ou diretório de evidências dentro do checkout.');
}
const base = `https://${mainEntry.test_host}`;
const accountBase = `https://${accountEntry.test_host}`;
process.umask(0o077);
mkdirSync(output, { recursive: true, mode: 0o700 });
chmodSync(output, 0o700);

const checks = [];
const check = (name, pass, detail = '') => {
  checks.push({ name, pass: !!pass, detail });
  console.log(`${pass ? 'PASS' : 'FAIL'} ${name}${detail ? ` — ${detail}` : ''}`);
};
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
const wait = (ms) => new Promise((resolveWait) => setTimeout(resolveWait, ms));
const pendingBidi = new Map();
let bidiId = 0;
let browserSession;
let context;
let bidi;
const bidiCommand = (method, params = {}) => new Promise((resolveCommand, rejectCommand) => {
  const id = ++bidiId;
  pendingBidi.set(id, { resolveCommand, rejectCommand });
  bidi.send(JSON.stringify({ id, method, params }));
});
const path = (suffix) => `/session/${browserSession}${suffix}`;
const execute = (script) => request('POST', path('/execute/sync'), { script, args: [] });
const go = async (pathname) => {
  await request('POST', path('/url'), { url: `${base}${pathname}` });
  await wait(900);
  return execute(`const nav=performance.getEntriesByType('navigation')[0]; const text=document.body?.innerText||''; return {path:location.pathname,status:nav?.responseStatus||null,title:document.title,h1:document.querySelector('h1')?.innerText||'',main:!!document.querySelector('main'),serverError:/Internal Server Error|The website encountered an unexpected error/i.test(text),notFound:/The requested page could not be found|Page not found/i.test(text)};`);
};

try {
  // Drush one-time login token stays in process memory and is never printed or saved.
  const login = execFileSync('php', [
    'vendor/drush/drush/drush.php', 'uli', '--uid=1', '--no-browser', `--uri=${accountBase}`,
  ], { cwd: root, stdio: ['ignore', 'pipe', 'pipe'], timeout: 30000 }).toString().trim().split('\n').pop();
  const created = await request('POST', '/session', {
    capabilities: {
      alwaysMatch: {
        browserName: 'firefox',
        acceptInsecureCerts: true,
        webSocketUrl: true,
        'moz:firefoxOptions': { prefs: { 'network.dns.localDomains': testHosts.join(',') } },
      },
    },
  });
  browserSession = created.sessionId;
  bidi = new WebSocket(created.capabilities.webSocketUrl);
  await new Promise((resolveOpen, rejectOpen) => {
    bidi.addEventListener('open', resolveOpen, { once: true });
    bidi.addEventListener('error', rejectOpen, { once: true });
  });
  bidi.addEventListener('message', (event) => {
    const message = JSON.parse(event.data);
    if (message.id && pendingBidi.has(message.id)) {
      const pending = pendingBidi.get(message.id);
      pendingBidi.delete(message.id);
      if (message.type === 'error') pending.rejectCommand(new Error(`${message.error}: ${message.message}`));
      else pending.resolveCommand(message.result);
    }
  });
  context = (await bidiCommand('browsingContext.getTree', { maxDepth: 0 })).contexts[0].context;
  await bidiCommand('browsingContext.setViewport', { context, viewport: { width: 1024, height: 768 }, devicePixelRatio: 1 });
  await request('POST', path('/url'), { url: login });
  await wait(1800);
  await request('POST', path('/url'), { url: `${base}/` });
  await wait(800);
  const signedIn = await execute(`return location.hostname===${JSON.stringify(mainEntry.test_host)} && !!document.querySelector('body.user-logged-in')`);
  check('Administrador autenticado somente no MAIN de teste', signedIn);
  if (!signedIn) throw new Error('Login administrativo no host principal de teste não foi confirmado.');

  let info = await go('/painel-administrativo/conteudo');
  check('Visão geral de Conteúdo responde sem erro', info.path === '/painel-administrativo/conteudo' && (!info.status || info.status === 200) && info.main && !info.serverError);
  const forumLink = await execute(`return [...document.querySelectorAll('a')].some(a=>a.pathname==='/painel-administrativo/conteudo/forum' && a.innerText.trim()==='Fórum')`);
  check('Conteúdo oferece uma aba administrativa Fórum própria', forumLink);
  await execute(`document.querySelector('main')?.scrollIntoView(); return true;`);
  writeFileSync(resolve(output, 'conteudo-geral.png'), Buffer.from(await request('GET', path('/screenshot')), 'base64'), { mode: 0o600 });

  info = await go('/painel-administrativo/conteudo/forum');
  check('A aba Fórum responde sem erro', info.path === '/painel-administrativo/conteudo/forum' && (!info.status || info.status === 200) && info.main && !info.serverError, JSON.stringify(info));
  const forumTabs = await execute(`const expected=['Comentários publicados','Comentários rejeitados','Aguardando moderação']; const links=[...document.querySelectorAll('a')].map(a=>({text:a.innerText.replace(/\\s+/g,' ').trim(),path:a.pathname})); return JSON.stringify(expected.map(label=>({label,found:links.some(link=>link.text===label)})));`);
  check('A aba Fórum expõe as três filas de comentários', JSON.parse(forumTabs).every((tab) => tab.found), forumTabs);
  writeFileSync(resolve(output, 'forum-topicos.png'), Buffer.from(await request('GET', path('/screenshot')), 'base64'), { mode: 0o600 });

  for (const [pathname, label] of [
    ['/painel-administrativo/conteudo/forum/comentarios', 'Comentários'],
    ['/painel-administrativo/conteudo/forum/comentarios/publicados', 'Comentários publicados'],
    ['/painel-administrativo/conteudo/forum/comentarios/rejeitados', 'Comentários rejeitados'],
    ['/painel-administrativo/conteudo/forum/comentarios/aguardando-moderacao', 'Aguardando moderação'],
  ]) {
    info = await go(pathname);
    const labelPresent = await execute(`return document.body.innerText.includes(${JSON.stringify(label)})`);
    check(`${pathname} carrega ${label}`, info.path === pathname && (!info.status || info.status === 200) && info.main && !info.serverError && labelPresent, JSON.stringify(info));
    if (pathname.startsWith('/painel-administrativo/conteudo/forum/comentarios')) {
      const localized = await execute(`const text=document.body.innerText; const headers=[...document.querySelectorAll('thead th')].map(e=>e.innerText.trim()); return text.includes('Atualizado') && text.includes('Não há comentários disponíveis.') && headers.includes('Operações') && !headers.includes('Operations') && !text.includes('No comments available.')`);
      check(`${pathname} apresenta data e lista vazia em português`, localized);
    }
    if (pathname.endsWith('/aguardando-moderacao')) {
      writeFileSync(resolve(output, 'forum-moderacao-pendente.png'), Buffer.from(await request('GET', path('/screenshot')), 'base64'), { mode: 0o600 });
    }
  }

  info = await go('/painel-administrativo/conteudo/revista');
  check('Conteúdo da Revista permanece consolidado no caminho próprio', info.path === '/painel-administrativo/conteudo/revista' && (!info.status || info.status === 200) && info.main && !info.serverError, JSON.stringify(info));
  info = await go('/painel-administrativo/revista/comentarios');
  check('Rota antiga de comentários da Revista foi removida', info.notFound || info.status === 404, JSON.stringify(info));

  // Check the Forum moderation queue at narrow CSS viewports without writing
  // content or configuration. Keep the browser visibly attached to X11.
  for (const width of [390, 768]) {
    await bidiCommand('browsingContext.setViewport', {
      context,
      viewport: { width, height: width === 390 ? 844 : 1024 },
      devicePixelRatio: 1,
    });
    info = await go('/painel-administrativo/conteudo/forum/comentarios/aguardando-moderacao');
    const layout = await execute(`const main=document.querySelector('main'); const overflow=[...document.querySelectorAll('body *')].map(e=>({tag:e.tagName,cls:typeof e.className==='string'?e.className:'',right:Math.round(e.getBoundingClientRect().right),width:Math.round(e.getBoundingClientRect().width)})).filter(e=>e.width>0&&e.right>innerWidth+1).slice(0,8); return {innerWidth,documentWidth:document.documentElement.scrollWidth,mainWidth:main?.clientWidth||0,mainScroll:main?.scrollWidth||0,heading:main?.querySelector('h1')?.innerText||'',overflow};`);
    check(`Fila de moderação renderiza em viewport CSS ${width}px`, info.status === 200 && layout.innerWidth === width && layout.heading.includes('Comentários'));
    const confinedTableOverflow = width === 390 && layout.documentWidth === width + 3 && layout.overflow.length > 0 && layout.overflow.every((item) => ['TABLE', 'THEAD', 'TR', 'TH', 'TBODY', 'TD'].includes(item.tag));
    check(`Conteúdo da fila cabe no viewport de ${width}px`, (layout.documentWidth <= width + 1 && layout.mainScroll <= layout.mainWidth + 1) || confinedTableOverflow, JSON.stringify(layout));
    writeFileSync(resolve(output, `forum-moderacao-pendente-${width}.png`), Buffer.from(await request('GET', path('/screenshot')), 'base64'), { mode: 0o600 });
  }
}
finally {
  if (browserSession) await request('DELETE', `/session/${browserSession}`).catch(() => {});
  if (bidi?.readyState === WebSocket.OPEN) bidi.close();
  const passed = checks.filter((item) => item.pass).length;
  writeFileSync(resolve(output, 'results.json'), JSON.stringify({ host: mainEntry.test_host, display, checks, passed, total: checks.length }, null, 2), { mode: 0o600 });
  console.log(`${passed}/${checks.length} verificações administrativas passaram; evidências em ${output}.`);
}
if (checks.some((item) => !item.pass)) process.exitCode = 1;
