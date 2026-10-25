/// S-R4: own-profile smoke/regression; Firefox ESR visible on X11 test runtime.
/// Read-only: no form submission, no account/config updates, no production access.
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const base = (process.env.STUDIO_PROFILE_BASE || 'https://conta.aculta.toca.net.br').replace(/\/$/, '');
const drushUri = process.env.STUDIO_PROFILE_DRUSH_URI || base;
const driver = process.env.GECKODRIVER || 'http://127.0.0.1:4444';
const out = process.env.SUITE_OUT || '/tmp/aculta-studio-profile-s-r4';
const users = [process.env.STUDIO_PROFILE_USER_A, process.env.STUDIO_PROFILE_USER_B];
const sentinel = [process.env.STUDIO_PROFILE_SENTINEL_A, process.env.STUDIO_PROFILE_SENTINEL_B];
const expectations = [process.env.STUDIO_PROFILE_PUBLIC_A, process.env.STUDIO_PROFILE_PUBLIC_B];
const results = [];
const check = (name, ok, details = '') => {
  results.push({ name, status: ok === null ? 'SKIP' : ok ? 'PASS' : 'FAIL', details });
  console.log(`${results.at(-1).status} ${name}${details ? ` (${details})` : ''}`);
};

if (process.env.DISPLAY !== ':0') throw new Error('DISPLAY=:0 e Firefox ESR visível são obrigatórios.');
if (!/^https?:\/\/(?:[a-z0-9-]+\.)*aculta\.toca\.net\.br(?::\d+)?$/i.test(base)
  && !/^https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?$/i.test(base)) {
  throw new Error('Permitidos apenas hosts de teste *.aculta.toca.net.br e loopback local.');
}
if (new URL(drushUri).origin !== new URL(base).origin) {
  throw new Error('STUDIO_PROFILE_DRUSH_URI deve ter a mesma origem de STUDIO_PROFILE_BASE.');
}
if (!users[0] || !users[1] || users[0] === users[1]) {
  throw new Error('Defina STUDIO_PROFILE_USER_A/B com duas contas distintas de TESTE.');
}
mkdirSync(out, { recursive: true });

async function wd(method, path, data) {
  const response = await fetch(driver + path, {
    method, headers: { 'content-type': 'application/json' },
    body: data === undefined ? undefined : JSON.stringify(data),
  });
  const value = (await response.json()).value;
  if (!response.ok || value?.error) throw new Error(`WebDriver ${method} ${path}: ${value?.error || response.status}`);
  return value;
}
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
function loginUrl(username) {
  // The unique one-time link stays in memory, never in logs or report files.
  const url = execFileSync('php', [
    'vendor/drush/drush/drush.php', 'uli', '--no-browser', `--name=${username}`, `--uri=${drushUri}`,
  ], { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim().split('\n').at(-1);
  if (!url?.startsWith('http')) throw new Error('Drush uli não retornou URL válida.');
  return url;
}
async function session(jsEnabled = true) {
  const host = new URL(base).hostname;
  const value = await wd('POST', '/session', { capabilities: { alwaysMatch: {
    browserName: 'firefox', acceptInsecureCerts: true, webSocketUrl: true,
    'moz:firefoxOptions': { prefs: { 'network.dns.localDomains': host, 'javascript.enabled': jsEnabled } },
  } } });
  const id = value.sessionId;
  const endpoint = path => `/session/${id}${path}`;
  return {
    endpoint,
    get: url => wd('POST', endpoint('/url'), { url }),
    eval: script => wd('POST', endpoint('/execute/sync'), { script, args: [] }),
    source: () => wd('GET', endpoint('/source')),
    screenshot: async name => writeFileSync(join(out, `${name}.png`), Buffer.from(await wd('GET', endpoint('/screenshot')), 'base64')),
    close: async () => wd('DELETE', endpoint('')).catch(() => {}),
    wsUrl: value.capabilities.webSocketUrl,
  };
}
async function viewport(browser, width) {
  const socket = new WebSocket(browser.wsUrl);
  await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
  try {
    let seq = 0;
    const request = (method, params) => new Promise((resolve, reject) => {
      const id = ++seq;
      const timeout = setTimeout(() => reject(new Error(`BiDi timeout: ${method}`)), 10000);
      socket.onmessage = event => {
        const data = JSON.parse(event.data);
        if (data.id !== id) return;
        clearTimeout(timeout);
        if (data.error) reject(new Error(`BiDi ${data.error}`)); else resolve(data.result);
      };
      socket.send(JSON.stringify({ id, method, params }));
    });
    const tree = await request('browsingContext.getTree', {});
    const context = tree.contexts[0].context;
    await request('browsingContext.setViewport', { context, viewport: { width, height: width < 500 ? 844 : 900 } });
  } finally { socket.close(); }
}
const stateJs = `return JSON.stringify((() => {
  const root = document.querySelector('.aculta-profile-editor');
  const identity = root?.querySelector('.aculta-profile-identity');
  return { path: location.pathname, ok: !!root, identity: !!identity,
    bio: !!root?.querySelector('.aculta-profile-bio'), social: !!root?.querySelector('.aculta-profile-social'),
    name: identity?.querySelector('.aculta-profile-identity__name')?.textContent.trim() || '',
    photo: !!identity?.querySelector('.aculta-profile-identity__avatar img'),
    publicLink: identity?.querySelector('.aculta-profile-identity__public-link a')?.getAttribute('href') || null,
    links: [...(root?.querySelectorAll('a[href]') || [])].map(a => a.getAttribute('href')),
    overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
    width: window.innerWidth, missingLabel: [...(root?.querySelectorAll('a') || [])].some(a => !(a.textContent || a.getAttribute('aria-label') || '').trim()),
    focusable: !!root?.querySelector('a[href]'),
  };
})());`;

try {
  for (let accountIndex = 0; accountIndex < 2; accountIndex++) {
    const label = accountIndex === 0 ? 'A' : 'B';
    const browser = await session();
    try {
      await browser.get(loginUrl(users[accountIndex]));
      await browser.get(`${base}/studio/perfil`);
      const state = JSON.parse(await browser.eval(stateJs));
      check(`${label} editor em rota correta`, state.path === '/studio/perfil' && state.ok);
      check(`${label} quatro SDCs de Perfil`, state.ok && state.identity && state.bio && state.social);
      check(`${label} links com nome acessível`, !state.missingLabel && state.focusable);
      check(`${label} ações do owner preservadas`, ['/studio/perfil/apelido', '/studio/perfil/bio', '/studio/perfil/privacidade', '/studio/perfil/redes/instagram'].every(path => state.links.some(href => href?.includes(path))));
      if (expectations[accountIndex] === 'yes' || expectations[accountIndex] === 'no') {
        check(`${label} perfil público conforme fixture`, !!state.publicLink === (expectations[accountIndex] === 'yes'));
      } else check(`${label} perfil público conforme fixture`, null, 'STUDIO_PROFILE_PUBLIC_A/B não configurado');
      const html = await browser.source();
      if (sentinel[0] && sentinel[1] && sentinel[0] !== sentinel[1]) {
        check(`${label} dados da própria fixture`, html.includes(sentinel[accountIndex]));
        check(`${label} isolamento entre sessões`, !html.includes(sentinel[1 - accountIndex]));
      } else check(`${label} isolamento entre sessões`, null, 'sentinelas distintas não configuradas');
      for (const width of [320, 375, 390, 768, 1024, 1440]) {
        await viewport(browser, width);
        await sleep(200);
        const geometry = JSON.parse(await browser.eval(stateJs));
        check(`${label} reflow ${width}px`, geometry.width === width && !geometry.overflow, `viewport=${geometry.width}`);
        if ([320, 390, 1440].includes(width)) await browser.screenshot(`perfil-${label}-${width}`);
      }
      await viewport(browser, 390);
      for (const mode of ['light', 'dark']) {
        await browser.eval(`document.documentElement.setAttribute('data-bs-theme', '${mode}'); return true;`);
        await sleep(300);
        const state = JSON.parse(await browser.eval(stateJs));
        check(`${label} modo ${mode} sem overflow`, !state.overflow);
        await browser.screenshot(`perfil-${label}-${mode}`);
      }
      check(`${label} sem nome de conta usado como fallback`, !/[@]/.test(state.name), 'triagem heurística; confirmar fixture sem apelido');
    } finally { await browser.close(); }
  }
  const noJs = await session(false);
  try {
    await noJs.get(loginUrl(users[0]));
    await noJs.get(`${base}/studio/perfil`);
    const html = await noJs.source();
    check('sem JavaScript SDC do Perfil no HTML', html.includes('aculta-profile-editor') && html.includes('aculta-profile-identity'));
    await noJs.get(`${base}/studio/perfil/apelido`);
    const formHtml = await noJs.source();
    check('sem JavaScript formulário original carregado', /<form\b/i.test(formHtml) && !/access denied|acesso negado/i.test(formHtml));
  } finally { await noJs.close(); }
} catch (error) {
  check('execução da suíte', false, `${error.name || 'Error'}: ${error.message?.split('\n')[0] || 'falha'}`);
} finally {
  writeFileSync(join(out, 'results.json'), JSON.stringify({
    note: 'Evidência de Runtime de teste; imagens privadas ficam apenas em SUITE_OUT.',
    results,
    totals: Object.fromEntries(['PASS', 'FAIL', 'SKIP'].map(status => [status, results.filter(r => r.status === status).length])),
  }, null, 2));
  process.exitCode = results.some(r => r.status === 'FAIL') ? 1 : results.some(r => r.status === 'SKIP') ? 3 : 0;
}
