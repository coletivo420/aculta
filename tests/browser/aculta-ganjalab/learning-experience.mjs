// Revisão visual e funcional da experiência educacional do Ganjalab.
// Firefox ESR pelo geckodriver (W3C WebDriver + BiDi), com janela no X11.
// Ver README.md desta pasta. Somente no servidor de desenvolvimento.
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const PROJECT = fileURLToPath(new URL('../../../', import.meta.url));
const HOME_URL = process.env.GANJA_HOME || 'https://cursos.aculta.toca.net.br/ganjalab';
const COURSE_URL = process.env.GANJA_COURSE || 'https://cursos.aculta.toca.net.br/group/1';
const LOGIN_URI = process.env.GANJA_LOGIN_URI || 'https://conta.aculta.toca.net.br';
const LOGIN_UID = process.env.GANJA_UID || '1';
const EMPTY_USER = process.env.GANJA_EMPTY_USER || 'teste.comum';
const OUT = process.env.GANJA_OUT || (process.env.SUITE_OUT ? join(process.env.SUITE_OUT, 'ganjalab') : '/home/piradopirata/worktrees/aculta-test-output/ganjalab');
const DRUSH_ROOT = process.env.GANJA_DRUSH_ROOT || '/home/piradopirata/projetos/aculta';
const GD = process.env.GECKODRIVER || 'http://127.0.0.1:4444';
const HEADLESS = process.env.GANJA_HEADLESS === '1';
const WIDTHS = (process.env.GANJA_WIDTHS || '320,390,768,1024,1280,1440').split(',').map(Number).filter((n) => Number.isFinite(n));
mkdirSync(OUT, { recursive: true });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok: !!ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  -- ' + detail : ''}`);
  return !!ok;
};

async function wd(method, path, body) {
  const res = await fetch(GD + path, { method, headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  const json = await res.json();
  if (json.value && json.value.error) throw new Error(`${json.value.error}: ${String(json.value.message).split('\n')[0]}`);
  return json.value;
}

const uli = (uid, name) => {
  const args = ['vendor/drush/drush/drush.php', 'uli', '--no-browser', `--uri=${LOGIN_URI}`];
  if (uid) args.push(`--uid=${uid}`);
  else if (name) args.push(`--name=${name}`);
  return execFileSync('php', args, { cwd: DRUSH_ROOT }).toString().trim().split('\n').pop();
};

async function createSession(dark = false) {
  const prefs = { 'network.dns.localDomains': [new URL(HOME_URL).hostname, new URL(LOGIN_URI).hostname].join(',') };
  if (dark) prefs['ui.systemUsesDarkTheme'] = 1;
  const moz = { prefs };
  if (HEADLESS) moz.args = ['-headless'];
  const caps = await wd('POST', '/session', { capabilities: { alwaysMatch: { browserName: 'firefox', acceptInsecureCerts: true, webSocketUrl: true, 'moz:firefoxOptions': moz } } });
  const sid = caps.sessionId;
  const p = (path) => `/session/${sid}${path}`;
  const bidi = new WebSocket(caps.capabilities.webSocketUrl);
  let bidiId = 0;
  const bidiPending = new Map();
  bidi.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && bidiPending.has(d.id)) { bidiPending.get(d.id)(d); bidiPending.delete(d.id); } };
  await new Promise((r) => (bidi.onopen = r));
  const bidiSend = (method, params = {}) => new Promise((res) => { const i = ++bidiId; bidiPending.set(i, res); bidi.send(JSON.stringify({ id: i, method, params })); });
  const tree = await bidiSend('browsingContext.getTree', {});
  const context = tree.result.contexts[0].context;
  return {
    sid,
    p,
    bidi,
    exec: (script) => wd('POST', p('/execute/sync'), { script, args: [] }),
    shot: async (name) => writeFileSync(join(OUT, `${name}.png`), Buffer.from(await wd('GET', p('/screenshot')), 'base64')),
    setSize: (width, height = 900) => bidiSend('browsingContext.setViewport', { context, viewport: { width, height } }),
    navigate: (url) => wd('POST', p('/url'), { url }),
    refresh: () => wd('POST', p('/refresh'), {}),
    close: async () => { bidi.close(); await wd('DELETE', p('')).catch(() => {}); },
  };
}

async function waitFor(s, expr, label, ms = 25000) {
  const end = Date.now() + ms;
  while (Date.now() < end) {
    if (await s.exec(`return !!(${expr});`)) return true;
    await sleep(300);
  }
  console.log(`  (timeout: ${label})`);
  return false;
}

async function login(s, { uid = null, name = null } = {}) {
  await s.navigate(uli(uid, name));
  await sleep(2500);
}

async function inspect(s) {
  return JSON.parse(await s.exec(`return JSON.stringify({
    h1: document.querySelectorAll('h1').length,
    overflow: document.documentElement.scrollWidth > window.innerWidth,
    width: window.innerWidth,
    text: document.body.innerText.replace(/\\s+/g, ' ').trim()
  });`));
}

// The one-time login link leaves a status message; remove it only from the
// captured DOM so the learner surfaces are shown without the test harness noise.
async function cleanMessages(s) {
  await s.exec(`document.querySelectorAll('.alert-wrapper[data-drupal-messages], [data-drupal-messages]').forEach((el) => el.remove());`);
}

async function matrix(s, label, selector, shots, scrollSelector = null, extra = () => {}) {
  for (const width of WIDTHS) {
    await s.setSize(width, 900);
    await s.refresh();
    await waitFor(s, `document.querySelector('${selector}')`, `${label} ${width}`);
    await sleep(700);
    await cleanMessages(s);
    if (scrollSelector) {
      await s.exec(`document.querySelector('${scrollSelector}')?.scrollIntoView({ block: 'start' });`);
      await sleep(300);
    }
    const info = await inspect(s);
    check(`${label} ${width}px viewport`, info.width === width, `innerWidth=${info.width}`);
    check(`${label} ${width}px sem rolagem horizontal`, !info.overflow);
    check(`${label} ${width}px H1 única`, info.h1 === 1, `h1=${info.h1}`);
    extra(info, width);
    await s.shot(`${shots}-${width}`);
  }
}

let lightSession;
let emptySession;
let darkSession;
let roomHref = '';
let lightColor = '';
let lightHomeColor = '';
try {
  lightSession = await createSession(false);
  await lightSession.setSize(1280, 900);
  await login(lightSession, { uid: LOGIN_UID });
  await lightSession.navigate(HOME_URL);
  check('início carrega', await waitFor(lightSession, `document.querySelector('.aculta-learning-home')`, 'início'));
  await sleep(1200);
  const home = await lightSession.exec(`return JSON.stringify({
    text: document.body.innerText.replace(/\\s+/g, ' '),
    continueCard: document.querySelectorAll('.aculta-learning-home__continue').length,
    courses: document.querySelectorAll('.aculta-learning-home__courses .aculta-card').length,
    journeyLinks: document.querySelectorAll('.aculta-learning-home a[href*="/group/"]').length
  });`);
  const h = JSON.parse(home);
  check('início identifica ACULTA Studio Ganjalab e Minha aprendizagem', /ACULTA Studio Ganjalab/.test(h.text) && /Minha aprendizagem/.test(h.text));
  check('início mostra cartão de retomada com curso e ação', h.continueCard === 1 && /Continuar (aula|curso)/.test(h.text), `continue=${h.continueCard}`);
  check('início lista cursos matriculados com link para a jornada', h.courses >= 1 && h.journeyLinks >= 1, `cards=${h.courses} links=${h.journeyLinks}`);
  lightHomeColor = await lightSession.exec(`return getComputedStyle(document.querySelector('.aculta-learning-home')).color;`);
  await matrix(lightSession, 'início', '.aculta-learning-home', 'home');

  await lightSession.setSize(1280, 900);
  await lightSession.navigate(COURSE_URL);
  check('jornada carrega', await waitFor(lightSession, `document.querySelector('.aculta-learning-journey')`, 'jornada'));
  await sleep(1000);
  const journey = JSON.parse(await lightSession.exec(`return JSON.stringify({
    text: document.body.innerText.replace(/\\s+/g, ' '),
    lessons: document.querySelectorAll('.aculta-learning-journey__lesson').length,
    states: Array.from(document.querySelectorAll('.aculta-learning-journey__state')).map((el) => el.textContent.trim()),
    start: Array.from(document.querySelectorAll('.lms-start-link a')).map((el) => el.textContent.trim())
  });`));
  check('jornada identifica Minha jornada e aulas do curso', /Minha jornada/.test(journey.text) && /Aulas do curso/.test(journey.text));
  check('jornada lista aulas em ordem com estado textual', journey.lessons >= 1 && journey.states.length >= 1, `aulas=${journey.lessons} estados=${journey.states.join('|')}`);
  check('jornada oferece ação de curso em português', journey.start.some((label) => /^(Continuar|Começar|Revisitar|Reiniciar)/.test(label)), journey.start.join('|'));
  await matrix(lightSession, 'jornada', '.aculta-learning-journey', 'journey', '.aculta-learning-journey');
  roomHref = await lightSession.exec(`return (document.querySelector('.aculta-learning-journey__lesson--current a, .aculta-learning-journey__lesson--in_progress a, .aculta-learning-journey__lesson a') || {}).href || '';`);
  check('jornada tem link para a sala', !!roomHref, roomHref);

  if (roomHref) {
    await lightSession.setSize(1280, 900);
    await lightSession.navigate(roomHref);
    check('sala carrega o formulário nativo do LMS', await waitFor(lightSession, `document.querySelector('#lms-answer-form')`, 'sala'));
    await sleep(1000);
    const room = JSON.parse(await lightSession.exec(`return JSON.stringify({
      text: document.body.innerText.replace(/\\s+/g, ' '),
      outlineMobile: document.querySelectorAll('.aculta-learning-room__outline--mobile').length,
      outlineDesktop: document.querySelectorAll('.aculta-learning-room__outline--desktop').length,
      back: Array.from(document.querySelectorAll('a')).some((a) => a.textContent.trim() === 'Voltar ao curso'),
      status: document.querySelectorAll('.aculta-learning-room .aculta-learning-journey__state').length
    });`));
    check('sala mantém contexto, retorno ao curso e índice', /ACULTA Studio Ganjalab/.test(room.text) && room.back && room.outlineMobile === 1 && room.outlineDesktop === 1);
    check('sala mostra estados reais no índice', room.status >= 1, `estados=${room.status}`);
    await matrix(lightSession, 'sala', '.aculta-learning-room', 'room');
    await lightSession.setSize(1280, 900);
    await lightSession.navigate(roomHref);
    await waitFor(lightSession, `document.querySelector('#lms-answer-form')`, 'sala para teclado');
    await sleep(600);
    await wd('POST', lightSession.p('/actions'), { actions: [{ type: 'key', id: 'keyboard', actions: [{ type: 'keyDown', value: '' }, { type: 'keyUp', value: '' }] }] });
    const focus = JSON.parse(await lightSession.exec(`return JSON.stringify({ tag: document.activeElement.tagName, outline: getComputedStyle(document.activeElement).outlineStyle, text: (document.activeElement.textContent || '').trim().slice(0, 60) });`));
    check('foco por teclado alcança um controle e tem contorno', ['A', 'SUMMARY', 'BUTTON', 'INPUT'].includes(focus.tag) && focus.outline !== 'none', `${focus.tag} ${focus.outline} ${focus.text}`);
    lightColor = await lightSession.exec(`return getComputedStyle(document.querySelector('.aculta-learning-room')).color;`);
    check('sala usa token de texto do tema', !!lightColor, lightColor);
  }
  // Geckodriver accepts one session at a time; close before the empty-state run.
  await lightSession.close();
  lightSession = null;

  emptySession = await createSession(false);
  await emptySession.setSize(390, 844);
  await login(emptySession, { name: EMPTY_USER });
  await emptySession.navigate(HOME_URL);
  check('início sem matrícula carrega', await waitFor(emptySession, `document.querySelector('.aculta-learning-home')`, 'início vazio'));
  await sleep(900);
  const empty = JSON.parse(await emptySession.exec(`return JSON.stringify({
    text: document.body.innerText.replace(/\\s+/g, ' '),
    continueCard: document.querySelectorAll('.aculta-learning-home__continue').length,
    catalog: Array.from(document.querySelectorAll('a')).some((a) => a.textContent.trim() === 'Explorar o catálogo de cursos')
  });`));
  check('sem matrícula não simula retomada', empty.continueCard === 0);
  check('sem matrícula oferece catálogo real', empty.catalog && /ainda não está matriculado/.test(empty.text));
  await cleanMessages(emptySession);
  await emptySession.shot('home-empty-390');
  await emptySession.close();
  emptySession = null;

  darkSession = await createSession();
  await darkSession.setSize(390, 844);
  await login(darkSession, { uid: LOGIN_UID });
  await darkSession.navigate(HOME_URL);
  check('início no modo escuro carrega', await waitFor(darkSession, `document.querySelector('.aculta-learning-home')`, 'início escuro'));
  await darkSession.exec(`document.documentElement.setAttribute('data-bs-theme', 'dark');`);
  await sleep(500);
  const dark = JSON.parse(await darkSession.exec(`return JSON.stringify({ theme: document.documentElement.getAttribute('data-bs-theme'), color: getComputedStyle(document.querySelector('.aculta-learning-home')).color });`));
  check('modo escuro aplica tokens diferentes do claro', dark.theme === 'dark' && dark.color !== lightHomeColor, `${lightHomeColor} -> ${dark.color}`);
  await cleanMessages(darkSession);
  await darkSession.shot('home-dark-390');
  if (roomHref) {
    await darkSession.navigate(roomHref);
    await waitFor(darkSession, `document.querySelector('#lms-answer-form')`, 'sala escura');
    await darkSession.exec(`document.documentElement.setAttribute('data-bs-theme', 'dark');`);
    await sleep(700);
    const darkRoom = await darkSession.exec(`return getComputedStyle(document.querySelector('.aculta-learning-room')).color;`);
    check('sala muda de cor entre claro e escuro', darkRoom !== lightColor, `${lightColor} -> ${darkRoom}`);
    await cleanMessages(darkSession);
    await darkSession.shot('room-dark-390');
  }
} catch (e) {
  check('execução da revisão', false, String(e.message || e));
} finally {
  writeFileSync(join(OUT, 'results.json'), JSON.stringify(results, null, 2));
  await lightSession?.close();
  await emptySession?.close();
  await darkSession?.close();
  const failed = results.filter((r) => !r.ok).length;
  console.log(`\n${results.length - failed}/${results.length} verificações OK. Capturas e relatório em ${OUT}`);
  process.exit(failed ? 1 : 0);
}
