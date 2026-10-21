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
  check('abas internas da seção (Todos, Rascunhos, Em revisão, Publicados, Arquivados)', d.subtabs >= 5, `${d.subtabs} aba(s)`);
  check('sem rolagem horizontal em 1280 px', !d.overflow);
  await shot('01-desktop');

  // Navegação AJAX: clicar na aba mantém a página (sem recarga) e atualiza a URL.
  const startPath = await exec(`return window.location.pathname;`);
  await exec(`document.querySelector('[data-aculta-studio="section"]').click(); return 1;`);
  await sleep(2500);
  const after = await exec(`return window.__marker || null;`);
  check('clique no submenu troca o conteúdo sem recarregar a página', after === 'sem-recarga');
  check('seção ativa marcada por aria-current', await exec(`return !!document.querySelector('[data-aculta-studio="section"][aria-current="page"]');`));
  check('URL própria do nível (caminho /studio/área/seção)', (await exec(`return window.location.pathname;`)).startsWith('/studio/'));
  check('região aria-live presente', await exec(`return !!document.querySelector('[data-aculta-studio="live"][aria-live="polite"]');`));
  await exec(`history.back(); return 1;`);
  await sleep(2500);
  check('voltar do navegador devolve o caminho anterior', (await exec(`return window.location.pathname;`)) === startPath, `${startPath} -> ${await exec(`return window.location.pathname;`)}`);

  await setViewport(390, 844);
  await exec(`location.reload(); return 1;`);
  await waitFor(`document.querySelector('[data-aculta-studio="tabs"]')`, 'abas no celular');
  await sleep(1000);
  const m = JSON.parse(await exec(`return JSON.stringify({ w: window.innerWidth, overflow: document.documentElement.scrollWidth > window.innerWidth })`));
  check('celular em 390 px (viewport emulado)', m.w === 390, `innerWidth=${m.w}`);
  // Em caso de estouro, o relatório traz os elementos que passam da largura da tela (causa, não só o sintoma).
  const offenders = m.overflow ? await exec(`return [...document.querySelectorAll('body *')].filter((e) => e.getBoundingClientRect().right > window.innerWidth + 1 && !e.closest('.offcanvas, [data-aculta-studio="listing-wrap"]')).slice(0, 6).map((e) => e.tagName + '.' + String(e.className).slice(0, 50) + ' ' + Math.round(e.getBoundingClientRect().right));`) : [];
  check('sem rolagem horizontal em 390 px', !m.overflow, offenders.join(' | '));
  await shot('02-celular');

  // Teclado: o link de uma seção recebe foco e Enter (tecla real do WebDriver) troca o nível pela URL dele.
  await wd('POST', p('/url'), { url: PAGE.replace(/\/studio.*$/, '') + '/studio/revista/criar' });
  await waitFor(`document.querySelector('[data-aculta-studio="subtab"]')`, 'abas da seção');
  const subLink = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-studio="subtab"]' });
  const subId = Object.values(subLink)[0];
  await wd('POST', p('/execute/sync'), { script: "document.querySelector('[data-aculta-studio=\"subtab\"]').focus(); return 1;", args: [] });
  await wd('POST', p(`/element/${subId}/value`), { text: '\uE007' });
  await sleep(2500);
  const kbPath = await exec(`return window.location.pathname;`);
  check('Enter no teclado troca a aba pela URL dela', kbPath === '/studio/revista/criar/nova-publicacao' || kbPath.startsWith('/studio/revista/criar/'), kbPath);

  // Contraste WCAG: texto do Studio contra o fundo efetivo, com as cores computadas pelo navegador (claro e escuro).
  const contrastScript = `
    const parse = (c) => (c.match(/[\\d.]+/g) || []).slice(0, 3).map(Number);
    const lum = ([r, g, b]) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
    const bgOf = (el) => { for (let e = el; e; e = e.parentElement) { const c = getComputedStyle(e).backgroundColor; const m = c.match(/rgba?\\(([^)]+)\\)/); if (m && !/rgba\\([^)]*,\\s*0\\)$/.test(c)) return parse(c); } return [255, 255, 255]; };
    const items = [...document.querySelectorAll('[data-aculta-studio="tab"],[data-aculta-studio="section"],[data-aculta-studio="subtab"],[data-aculta-studio="action"],[data-aculta-studio="heading"]')];
    return items.map((el) => { const fg = parse(getComputedStyle(el).color); const bg = bgOf(el); const L1 = lum(fg), L2 = lum(bg); const ratio = (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05); return Math.round(ratio * 100) / 100; });
  `;
  await wd('POST', p('/url'), { url: PAGE.replace(/\/studio.*$/, '') + '/studio/revista/criar' });
  await waitFor(`document.querySelector('[data-aculta-studio="subtab"]')`, 'contraste (claro)');
  const contrastLight = JSON.parse(JSON.stringify(await exec(contrastScript)));
  const minLight = Math.min(...contrastLight);
  check('contraste mínimo do texto do Studio no modo claro (WCAG AA, 4,5:1)', minLight >= 4.5, `mínimo ${minLight}:1 em ${contrastLight.length} elemento(s)`);

  // Paridade claro/escuro: mesma geometria nos dois modos. A preferência da conta de teste é trocada e restaurada.
  const boxesFor = () => exec(`return JSON.stringify([...document.querySelectorAll('[data-aculta-studio="tab"],[data-aculta-studio="section"],[data-aculta-studio="subtab"],[data-aculta-studio="action"],[data-aculta-studio="panel"]')].map((e) => { const r = e.getBoundingClientRect(); return [Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height)]; }));`);
  await wd('POST', p('/url'), { url: PAGE.replace(/\/studio.*$/, '') + '/studio/revista/criar' });
  await waitFor(`document.querySelector('[data-aculta-studio="subtab"]')`, 'abas (claro)');
  const lightBoxes = JSON.parse(await boxesFor());
  const lightMode = await exec(`return document.documentElement.getAttribute('data-bs-theme') || 'none';`);
  const drushRun = (code) => execFileSync('php', ['vendor/drush/drush/drush.php', 'php:eval', code], { cwd: PROJECT }).toString().trim();
  const original = drushRun(`echo json_encode(\\Drupal::service('aculta_portal.color_mode_preference')->forUser(5));`);
  drushRun(`\\Drupal::service('aculta_portal.color_mode_preference')->save(5, 'dark'); echo 'ok';`);
  await wd('POST', p('/refresh'), {});
  await waitFor(`document.querySelector('[data-aculta-studio="subtab"]')`, 'abas (escuro)');
  await sleep(800);
  const darkBoxes = JSON.parse(await boxesFor());
  const contrastDark = JSON.parse(JSON.stringify(await exec(contrastScript)));
  check('contraste mínimo do texto do Studio no modo escuro (WCAG AA, 4,5:1)', Math.min(...contrastDark) >= 4.5, `mínimo ${Math.min(...contrastDark)}:1 em ${contrastDark.length} elemento(s)`);
  const darkMode = await exec(`return document.documentElement.getAttribute('data-bs-theme') || 'none';`);
  if (original === 'null' || original === '') { drushRun(`\\Drupal::service('user.data')->delete('aculta_portal', 5, 'color_mode'); echo 'ok';`); } else { drushRun(`\\Drupal::service('aculta_portal.color_mode_preference')->save(5, json_decode('${original}')); echo 'ok';`); }
  check('modo escuro aplicado à página', darkMode !== lightMode, `claro=${lightMode} escuro=${darkMode}`);
  check('mesma geometria nos modos claro e escuro', JSON.stringify(lightBoxes) === JSON.stringify(darkBoxes), `${lightBoxes.length} elemento(s) comparado(s)`);

  // Fila de moderação do Fórum: comentários de teste aguardando moderação aparecem para a conta.
  await wd('POST', p('/url'), { url: PAGE.replace(/\/studio.*$/, '') + '/studio/forum/moderacao/fila' });
  await waitFor(`document.querySelector('[data-aculta-studio="listing"]')`, 'fila do Fórum');
  const queueRows = await exec(`return [...document.querySelectorAll('[data-aculta-studio="listing"] tr')].filter((r) => r.querySelector('td')).length;`);
  check('fila de moderação do Fórum lista comentários aguardando moderação', queueRows >= 1, `${queueRows} linha(s)`);

  // Listagem da Revista na aba Publicados: tabela com as linhas que a conta pode ver.
  await wd('POST', p('/url'), { url: PAGE.replace(/\/studio.*$/, '') + '/studio/revista/publicacoes/publicados' });
  await waitFor(`document.querySelector('[data-aculta-studio="listing"]')`, 'listagem');
  const rows = await exec(`return document.querySelectorAll('[data-aculta-studio="listing"] tbody tr').length;`);
  check('listagem da Revista na aba Publicados com linhas', rows >= 1, `${rows} linha(s)`);
  const editLinks = await exec(`return [...document.querySelectorAll('[data-aculta-studio="listing"] a')].filter((a) => (a.getAttribute('href') || '').includes('/node/') && (a.getAttribute('href') || '').endsWith('/edit')).length;`);
  check('edição de publicação pelo formulário do dono (domínio principal)', editLinks >= 1, `${editLinks} link(s)`);
  const unnamed = await exec(`return [...document.querySelectorAll('[data-aculta-studio="tab"],[data-aculta-studio="section"],[data-aculta-studio="subtab"],[data-aculta-studio="action"]')].filter((a) => !(a.textContent || '').trim()).length;`);
  check('todo link do Studio tem nome acessível (texto)', unnamed === 0, `${unnamed} sem nome`);
  const groupsOk = await exec(`return !!document.querySelector('[data-aculta-studio="tabs"][aria-label]') && !!document.querySelector('[data-aculta-studio="live"][role="status"]');`);
  check('áreas com nome (aria-label) e região de status (role=status, aria-live)', groupsOk);
  await shot('03-listagem-revista');

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
