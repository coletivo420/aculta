// Revisão visual e funcional do formulário de resposta com arquivo do LMS (política de upload, 0.1.0-dev.80).
// Firefox pelo geckodriver (protocolo W3C WebDriver), com janela no X11 (DISPLAY) ou headless com LMS_HEADLESS=1.
// Ver README.md desta pasta. Somente no servidor de teste; a preparação do cenário é feita à parte.
//
// Variáveis:
//   LMS_PAGE          URL da atividade de teste (obrigatória), ex.: https://cursos.aculta.toca.net.br/curso/.../atividade
//   LMS_LOGIN_URI     URI do Drush para o link de login (padrão: https://conta.aculta.toca.net.br)
//   LMS_USER          conta de teste (padrão: teste.comum)
//   LMS_OUT           pasta das capturas e do relatório (padrão: ./.suite-out/lms-upload)
//   LMS_HEADLESS      1 para headless (padrão: janela no X11)
//   GECKODRIVER       URL do geckodriver (padrão: http://127.0.0.1:4444)
//   LMS_FILE_OK       arquivo permitido (padrão: fixtures gerada em LMS_OUT)
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const PROJECT = fileURLToPath(new URL('../../../', import.meta.url));
const PAGE = process.env.LMS_PAGE;
if (!PAGE) { console.error('Defina LMS_PAGE com a URL da atividade de teste.'); process.exit(2); }
const LOGIN_URI = process.env.LMS_LOGIN_URI || 'https://conta.aculta.toca.net.br';
const USER = process.env.LMS_USER || 'teste.comum';
const OUT = process.env.LMS_OUT || join(PROJECT, '.suite-out', 'lms-upload');
const GD = process.env.GECKODRIVER || 'http://127.0.0.1:4444';
const HEADLESS = process.env.LMS_HEADLESS === '1';
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

const moz = { prefs: { 'network.dns.localDomains': [new URL(PAGE).hostname, new URL(LOGIN_URI).hostname].join(',') } };
if (HEADLESS) moz.args = ['-headless'];
const { value: session } = await wd('POST', '/session', { capabilities: { alwaysMatch: { browserName: 'firefox', acceptInsecureCerts: true, webSocketUrl: true, 'moz:firefoxOptions': moz } } }).then((v) => ({ value: { sessionId: v.sessionId, webSocketUrl: v.capabilities.webSocketUrl } }));
const sid = session.sessionId;
const p = (path) => `/session/${sid}${path}`;
const exec = (script) => wd('POST', p('/execute/sync'), { script, args: [] });
const shot = async (name) => writeFileSync(join(OUT, `${name}.png`), Buffer.from(await wd('GET', p('/screenshot')), 'base64'));
// Viewport emulation through WebDriver BiDi (browsingContext.setViewport): the window itself cannot go below 500 px.
const bidi = new WebSocket(session.webSocketUrl);
let bidiId = 0; const bidiPending = new Map();
bidi.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && bidiPending.has(d.id)) { bidiPending.get(d.id)(d); bidiPending.delete(d.id); } };
await new Promise((r) => (bidi.onopen = r));
const bidiSend = (method, params = {}) => new Promise((res) => { const i = ++bidiId; bidiPending.set(i, res); bidi.send(JSON.stringify({ id: i, method, params })); });
const tree = await bidiSend('browsingContext.getTree', {});
const context = tree.result.contexts[0].context;
const setSize = async (width, height) => { await bidiSend('browsingContext.setViewport', { context, viewport: { width, height } }); };
async function waitFor(expr, label, ms = 20000) {
  const end = Date.now() + ms;
  while (Date.now() < end) { if (await exec(`return !!(${expr});`)) return true; await sleep(300); }
  console.log(`  (timeout: ${label})`); return false;
}
async function findEl(css) {
  const el = await wd('POST', p('/element'), { using: 'css selector', value: css });
  return Object.values(el)[0];
}

try {
  await setSize(1280, 900);
  await wd('POST', p('/url'), { url: oneTimeLogin() });
  await sleep(2500);
  await wd('POST', p('/url'), { url: PAGE });
  check('página da atividade carrega', await waitFor(`document.querySelector('#lms-answer-form')`, 'formulário de resposta'));
  await exec(`window.__errs = []; window.addEventListener('error', (e) => window.__errs.push(String(e.message)));`);
  await sleep(1500);

  const info = JSON.parse(await exec(`return JSON.stringify({
    text: document.body.innerText.replace(/\\s+/g,' '),
    hasFile: !!document.querySelector('input[name="files[file_upload]"]'),
    overflow: document.documentElement.scrollWidth > window.innerWidth,
    description: (document.querySelector('.form-item-file-upload .description, #edit-file-upload--description')||{}).innerText || ''
  })`));
  check('pede arquivo e mostra as extensões do teto (png pdf)', info.hasFile && /extens\S* permitid\S*: png pdf\./i.test(info.description), info.description || '');
  check('não mostra exe no texto de extensões', !/\bexe\b/.test(info.description), '');
  check('sem rolagem horizontal em 1280 px', !info.overflow);
  await shot('01-desktop-formulario');

  await setSize(390, 844);
  await wd('POST', p('/refresh'), {});
  const vw = await exec('return window.innerWidth;');
  check('viewport emulado em 390 px', vw === 390, `innerWidth=${vw}`);
  await waitFor(`document.querySelector('#lms-answer-form')`, 'formulário no celular');
  await sleep(800);
  const mobile = JSON.parse(await exec(`return JSON.stringify({ overflow: document.documentElement.scrollWidth > window.innerWidth, width: window.innerWidth })`));
  check('sem rolagem horizontal em 390 px (celular)', !mobile.overflow, `viewport ${mobile.width}px`);
  await shot('02-celular-formulario');
  await setSize(1280, 900);
  await sleep(800);

  // Envio do arquivo bloqueado (.exe): deve ser recusado com a mensagem do teto.
  const exe = join(OUT, 'bloqueado.exe');
  writeFileSync(exe, 'MZ-teste-bloqueado');
  await (await findEl('input[name="files[file_upload]"]'))
    && wd('POST', p(`/element/${await findEl('input[name="files[file_upload]"]')}/value`), { text: exe });
  const errShown = await waitFor(`/permitidos: png,? pdf/.test(document.body.innerText)`, 'mensagem de extensão', 25000);
  check('.exe é recusado com a mensagem do teto (png, pdf)', errShown);
  await shot('03-exe-recusado');

  // Envio permitido (PNG): deve ser aceito e aparecer no formulário.
  const png = join(OUT, 'teste-imagem.png');
  writeFileSync(png, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=', 'base64'));
  await sleep(1500);
  const input = await findEl('input[name="files[file_upload]"]');
  if (input) await wd('POST', p(`/element/${input}/value`), { text: png });
  const pngShown = await waitFor(`document.body.innerText.includes('teste-imagem')`, 'arquivo aceito', 25000);
  check('PNG permitido é aceito e aparece no formulário', pngShown);
  await shot('04-png-aceito');

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
