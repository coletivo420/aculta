// Revisão visual e de navegação do ACULTA Studio (/studio no domínio conta), no Firefox pelo geckodriver.
// Confere: seção "Estúdio" no shell da conta, abas e painel, navegação AJAX (sem recarregar a página),
// largura de celular emulada pelo BiDi (390 px) e ausência de erros de JavaScript.
// Ver README.md desta pasta. Somente no servidor de teste, com sessão de conta de teste.
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const PROJECT = fileURLToPath(new URL('../../../', import.meta.url));
const PAGE = process.env.STUDIO_PAGE || 'https://conta.aculta.toca.net.br/studio';
const LOGIN_URI = process.env.STUDIO_LOGIN_URI || 'https://conta.aculta.toca.net.br';
const USER = process.env.STUDIO_USER || 'teste.comum';
const OUT = process.env.STUDIO_OUT || join(PROJECT, '.suite-out', 'studio');
const GD = process.env.GECKODRIVER || 'http://127.0.0.1:4444';
const HEADLESS = process.env.STUDIO_HEADLESS === '1';
mkdirSync(OUT, { recursive: true });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const results = [];
const check = (name, ok, detail = '') => { results.push({ name, ok: !!ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  -- ' + detail : ''}`); return !!ok; };

async function wd(method, path, body) {
  const res = await fetch(GD + path, { method, headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  const json = await res.json();
  if (json.value && json.value.error) throw new Error(`${json.value.error}: ${String(json.value.message).split('\n')[0]}`);
  return json.value;
}
const oneTimeLogin = () => execFileSync('php', ['vendor/drush/drush/drush.php', 'uli', `--name=${USER}`, `--uri=${LOGIN_URI}`], { cwd: PROJECT }).toString().trim().split('\n').pop();

const moz = { prefs: { 'network.dns.localDomains': [new URL(PAGE).hostname].join(',') } };
if (HEADLESS) moz.args = ['-headless'];
const { value: session } = await wd('POST', '/session', { capabilities: { alwaysMatch: { browserName: 'firefox', acceptInsecureCerts: true, webSocketUrl: true, 'moz:firefoxOptions': moz } } }).then((v) => ({ value: { sessionId: v.sessionId, webSocketUrl: v.capabilities.webSocketUrl } }));
const sid = session.sessionId;
const p = (path) => `/session/${sid}${path}`;
const exec = (script, args = []) => wd('POST', p('/execute/sync'), { script, args });
const shot = async (name) => writeFileSync(join(OUT, `${name}.png`), Buffer.from(await wd('GET', p('/screenshot')), 'base64'));

const bidi = new WebSocket(session.webSocketUrl);
let bid = 0; const bpend = new Map();
bidi.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && bpend.has(d.id)) { bpend.get(d.id)(d); bpend.delete(d.id); } };
await new Promise((r) => (bidi.onopen = r));
const bsend = (method, params = {}) => new Promise((res) => { const i = ++bid; bpend.set(i, res); bidi.send(JSON.stringify({ id: i, method, params })); });
const tree = await bsend('browsingContext.getTree', {});
const context = tree.result.contexts[0].context;
const setViewport = (width, height) => bsend('browsingContext.setViewport', { context, viewport: { width, height } });
async function waitFor(expr, label, ms = 20000) {
  const end = Date.now() + ms;
  while (Date.now() < end) { if (await exec(`return !!(${expr});`)) return true; await sleep(300); }
  console.log(`  (timeout: ${label})`); return false;
}

try {
  await setViewport(1280, 900);
  await wd('POST', p('/url'), { url: oneTimeLogin() });
  await sleep(2500);
  await wd('POST', p('/url'), { url: PAGE });
  check('página do Estúdio carrega logada', await waitFor(`document.querySelector('[data-aculta-studio="tabs"]')`, 'abas'));
  await exec(`window.__errs = []; window.addEventListener('error', (e) => window.__errs.push(String(e.message))); window.__marker = 'sem-recarga';`);
  await sleep(1000);

  const d = JSON.parse(await exec(`return JSON.stringify({
    root: !!document.querySelector('[data-aculta-studio="root"]'),
    accountShell: !!document.getElementById('portal-account-body') || !!document.querySelector('[data-portal-account-menu]'),
    areas: document.querySelectorAll('[data-aculta-studio="tab"]').length,
    sidebar: document.querySelectorAll('[data-aculta-studio="section"]').length,
    subtabs: document.querySelectorAll('[data-aculta-studio="subtab"]').length,
    actions: [...document.querySelectorAll('[data-aculta-studio="action"]')].map((a) => a.textContent.trim()),
    overflow: document.documentElement.scrollWidth > window.innerWidth
  })`));
  check('página própria do Studio (sem shell nem menu da conta)', d.root && !d.accountShell);
  check('áreas em abas superiores', d.areas >= 1, `${d.areas} área(s)`);
  check('submenu à esquerda com as seções da área', d.sidebar >= 1, `${d.sidebar} seção(ões)`);
  check('abas próprias da seção', d.subtabs >= 1, `${d.subtabs} aba(s)`);
  check('ação "Nova publicação" no painel', d.actions.includes('Nova publicação'), d.actions.join(' | '));
  check('sem rolagem horizontal em 1280 px', !d.overflow);
  await shot('01-desktop');

  // Navegação AJAX: clicar na aba mantém a página (sem recarga) e atualiza a URL.
  await exec(`document.querySelector('[data-aculta-studio="section"]').click(); return 1;`);
  await sleep(2500);
  const after = await exec(`return window.__marker || null;`);
  check('clique no submenu troca o conteúdo sem recarregar a página', after === 'sem-recarga');
  check('seção ativa marcada por aria-current', await exec(`return !!document.querySelector('[data-aculta-studio="section"][aria-current="page"]');`));
  check('URL acompanha a seção', (await exec(`return window.location.search;`)).includes('secao='));

  await setViewport(390, 844);
  await exec(`location.reload(); return 1;`);
  await waitFor(`document.querySelector('[data-aculta-studio="tabs"]')`, 'abas no celular');
  await sleep(1000);
  const m = JSON.parse(await exec(`return JSON.stringify({ w: window.innerWidth, overflow: document.documentElement.scrollWidth > window.innerWidth })`));
  check('celular em 390 px (viewport emulado)', m.w === 390, `innerWidth=${m.w}`);
  check('sem rolagem horizontal em 390 px', !m.overflow);
  await shot('02-celular');

  const errs = await exec(`return (window.__errs || []).slice(0, 10);`);
  check('sem erros de JavaScript na página', errs.length === 0, errs.join(' | '));
} catch (e) {
  check('execução da revisão', false, String(e.message || e));
} finally {
  writeFileSync(join(OUT, 'results.json'), JSON.stringify(results, null, 2));
  bidi.close();
  await wd('DELETE', p('')).catch(() => {});
  const failed = results.filter((r) => !r.ok).length;
  console.log(`\n${results.length - failed}/${results.length} verificações OK. Capturas e relatório em ${OUT}`);
  process.exit(failed ? 1 : 0);
}
