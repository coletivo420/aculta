// Read-only X11 visual smoke for the Forum panel and its first-level tools.
import { chmodSync, mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { isAbsolute, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const output = process.env.SUITE_OUT;
const display = process.env.DISPLAY;
const driver = process.env.GECKODRIVER || 'http://127.0.0.1:4460';
if (!output || !isAbsolute(output) || !display) {
  throw new Error('Defina SUITE_OUT absoluto fora do checkout e DISPLAY da sessão X11.');
}
const deploy = JSON.parse(await (await import('node:fs/promises')).readFile(
  resolve(root, 'web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json'),
  'utf8',
));
const mainEntry = deploy.domain_transition.entries.find((entry) => entry.domain_id === 'aculta_org');
const accountEntry = deploy.domain_transition.entries.find((entry) => entry.domain_id === 'conta_aculta_org');
const forumEntry = deploy.domain_transition.entries.find((entry) => entry.domain_id === 'forum_aculta_org');
const testHosts = deploy.domain_transition.entries.map((entry) => entry.test_host);
if (!mainEntry?.test_host || !accountEntry?.test_host || !forumEntry?.test_host
  || resolve(output).startsWith(`${root}/`) || resolve(output) === root) {
  throw new Error('Hosts MAIN/ACCOUNT de teste ausentes ou evidências dentro do checkout.');
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
  return execute(`const nav=performance.getEntriesByType('navigation')[0]; const text=document.body?.innerText||''; return {path:location.pathname,status:nav?.responseStatus||null,title:document.title,h1:document.querySelector('main h1, h1')?.innerText||'',main:!!document.querySelector('main'),loggedIn:document.body?.classList.contains('user-logged-in')||false,serverError:/Internal Server Error|The website encountered an unexpected error/i.test(text),viewport:innerWidth,documentWidth:document.documentElement.scrollWidth};`);
};

try {
  // The one-time login URL remains in process memory and is never printed or saved.
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
  await wait(1600);
  let page = await go('/painel-administrativo/forum');
  check('Painel central autenticado responde e não apresenta erro', page.path === '/painel-administrativo/forum'
    && page.status === 200 && page.main && page.loggedIn && !page.serverError
    && page.h1.includes('Painel Growroom420'), JSON.stringify(page));
  check('Visão geral não repete links das abas administrativas no conteúdo', await execute(`
    const text=document.querySelector('main')?.innerText||'';
    return !text.includes('Ferramentas do Fórum')
      && !text.includes('Comentários e moderação');
  `));
  check('Menu lateral mostra os mesmos três itens sob Fórum', await execute(`
    const paths=[...document.querySelectorAll('a[href]')]
      .map(a=>{try{return new URL(a.href,location.href).pathname}catch{return ''}});
    return [
      '/painel-administrativo/forum/conteudo',
      '/painel-administrativo/forum/comentarios',
      '/painel-administrativo/forum/configuracoes',
    ].every(path=>paths.includes(path));
  `));
  check('Abas administrativas Drupal exibem Início e funções de primeiro nível', await execute(`
    const paths=[...document.querySelectorAll('nav.tabs a[href], .tabs a[href]')]
      .map(a=>{try{return new URL(a.href,location.href).pathname}catch{return ''}});
    const expected=[
      '/painel-administrativo/forum',
      '/painel-administrativo/forum/conteudo',
      '/painel-administrativo/forum/comentarios',
      '/painel-administrativo/forum/configuracoes',
    ];
    const labels=[...document.querySelectorAll('nav.tabs a, .tabs a')].map(a=>a.innerText.trim());
    return expected.every(path=>paths.includes(path)) && labels.includes('Início')
      && !paths.some(path=>path.endsWith('/comentarios/publicados')
        || path.endsWith('/comentarios/rejeitados')
        || path.endsWith('/comentarios/aguardando-moderacao'));
  `));
  check('Painel exibe indicadores de categorias e tópicos do Growroom420', await execute(`
    const table=document.querySelector('main table');
    const text=table?.innerText||'';
    return !!table && text.includes('Categorias públicas')
      && text.includes('Tópicos públicos publicados')
      && text.includes('Resumo do fórum público');
  `));
  check('Painel não exibe o texto explicativo reservado à documentação', await execute(`
    const text=document.querySelector('main')?.innerText||'';
    return !text.includes('Como as discussões são organizadas')
      && !['Growroom420:', 'Revista:', 'LMS:', 'Wiki420:'].some(label=>text.includes(label));
  `));
  const publicLinks = await execute(`
    const forumHost=${JSON.stringify(forumEntry.test_host)};
    const links=[...document.querySelectorAll('main a[href]')].map(a=>{
      try { return {raw:a.getAttribute('href'), url:new URL(a.getAttribute('href'),location.href)}; }
      catch { return {raw:a.getAttribute('href'), url:null}; }
    });
    return {
      forum:links.some(item=>item.url?.hostname===forumHost && item.url.pathname==='/'),
      categories:links.some(item=>item.url?.hostname===forumHost && item.url.pathname==='/categorias'),
      links:links.map(item=>({raw:item.raw,host:item.url?.hostname||null,path:item.url?.pathname||null})),
    };
  `);
  check('Links públicos apontam para o propósito Growroom de teste', publicLinks.forum && publicLinks.categories, JSON.stringify(publicLinks));

  for (const tool of [
    '/painel-administrativo/forum/conteudo',
    '/painel-administrativo/forum/comentarios',
    '/painel-administrativo/forum/comentarios/publicados',
    '/painel-administrativo/forum/comentarios/rejeitados',
    '/painel-administrativo/forum/comentarios/aguardando-moderacao',
    '/painel-administrativo/forum/configuracoes',
  ]) {
    page = await go(tool);
    check(`${tool} abre sua função dentro da seção do Fórum`, page.path === tool
      && page.status === 200 && page.main && page.loggedIn && !page.serverError, JSON.stringify(page));
  }
  page = await go('/painel-administrativo/forum/comentarios');
  check('Abas de estado aparecem dentro de Comentários no segundo nível', await execute(`
    const paths=[...document.querySelectorAll('nav.tabs a[href], .tabs a[href]')]
      .map(a=>{try{return new URL(a.href,location.href).pathname}catch{return ''}});
    return [
      '/painel-administrativo/forum/comentarios/publicados',
      '/painel-administrativo/forum/comentarios/rejeitados',
      '/painel-administrativo/forum/comentarios/aguardando-moderacao',
    ].every(path=>paths.includes(path));
  `));

  const redirects = [
    '/painel-administrativo/conteudo/forum',
    '/painel-administrativo/conteudo/forum/comentarios',
    '/painel-administrativo/conteudo/forum/comentarios/publicados',
    '/painel-administrativo/conteudo/forum/comentarios/rejeitados',
    '/painel-administrativo/conteudo/forum/comentarios/aguardando-moderacao',
  ];
  for (const source of redirects) {
    page = await go(source);
    check(`${source} redireciona para o painel central`, page.path === '/painel-administrativo/forum'
      && page.status === 200 && page.main && page.loggedIn && !page.serverError);
  }

  // Entity-conversion move routes require real Node/Comment records. This
  // read-only smoke deliberately creates no fixtures and leaves those routes
  // to the authenticated HTTP/RouteProvider checks recorded in the matrix.
  for (const width of [390, 768, 1024]) {
    await bidiCommand('browsingContext.setViewport', {
      context,
      viewport: { width, height: width === 390 ? 844 : 768 },
      devicePixelRatio: 1,
    });
    page = await go('/painel-administrativo/forum');
    check(`Painel renderiza em viewport CSS ${width}px`, page.path === '/painel-administrativo/forum'
      && page.status === 200 && page.main && page.loggedIn && !page.serverError && page.viewport === width);
    check(`Painel não causa overflow horizontal em ${width}px`, page.documentWidth <= width + 1,
      JSON.stringify({ viewport: page.viewport, documentWidth: page.documentWidth }));
    const screenshot = await request('GET', path('/screenshot'));
    writeFileSync(resolve(output, `forum-admin-central-${width}.png`), Buffer.from(screenshot, 'base64'), { mode: 0o600 });
  }
}
finally {
  if (browserSession) await request('DELETE', `/session/${browserSession}`).catch(() => {});
  if (bidi?.readyState === WebSocket.OPEN) bidi.close();
  const passed = checks.filter((item) => item.pass).length;
  writeFileSync(resolve(output, 'results.json'), JSON.stringify({
    host: mainEntry.test_host,
    display,
    account: 'uid 1 (administrador de teste; ULI descartável)',
    method: 'GET-only contra o Runtime de teste',
    checks,
    passed,
    total: checks.length,
  }, null, 2), { mode: 0o600 });
  console.log(`${passed}/${checks.length} verificações; evidências privadas em ${output}.`);
}
if (checks.some((item) => !item.pass)) process.exitCode = 1;
