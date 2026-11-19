import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';

const ROOT = process.cwd();
const ORIGIN = process.env.STUDIO_LOGIN_URI || 'https://conta.aculta.toca.net.br';
const USER = process.env.STUDIO_USER || 'teste.comum';
const USER_ID = Number(process.env.STUDIO_USER_ID || '5');
const OUT = process.env.SUITE_OUT;
const PHASE = process.env.STUDIO_CAPTURE_PHASE || 'before';
const GD = process.env.GECKODRIVER || 'http://127.0.0.1:4444';
if (!OUT) throw new Error('Defina SUITE_OUT fora do repositório.');
if (process.env.DISPLAY !== ':0') throw new Error('Firefox ESR precisa estar visível em DISPLAY=:0.');
const origin = new URL(ORIGIN);
if (!origin.hostname.endsWith('.aculta.toca.net.br')) throw new Error('A captura só pode usar o servidor de testes ACULTA.');
const output = join(OUT, `studio-account-routes-${PHASE}`);
mkdirSync(output, { recursive: true });

const sessionResponse = await fetch(`${GD}/session`, {
  method: 'POST',
  headers: { 'content-type': 'application/json' },
  body: JSON.stringify({ capabilities: { alwaysMatch: {
    browserName: 'firefox',
    acceptInsecureCerts: true,
    webSocketUrl: true,
    'moz:firefoxOptions': { prefs: { 'network.dns.localDomains': origin.hostname } },
  } } }),
});
const sessionEnvelope = await sessionResponse.json();
const created = sessionEnvelope.value;
if (!sessionResponse.ok || created?.error) throw new Error(`Firefox ESR não iniciou: ${created?.error || sessionResponse.status}`);
const id = created.sessionId;
const bidi = new WebSocket(created.capabilities.webSocketUrl);
await new Promise((resolve, reject) => {
  bidi.addEventListener('open', resolve, { once: true });
  bidi.addEventListener('error', reject, { once: true });
});
let sequence = 0;
const pending = new Map();
bidi.addEventListener('message', (event) => {
  const message = JSON.parse(event.data);
  const resolve = pending.get(message.id);
  if (resolve) { pending.delete(message.id); resolve(message); }
});
const bidiSend = (method, params = {}) => new Promise((resolve) => {
  const requestId = ++sequence;
  pending.set(requestId, resolve);
  bidi.send(JSON.stringify({ id: requestId, method, params }));
});
const contextTree = await bidiSend('browsingContext.getTree');
const context = contextTree.result.contexts[0].context;
const wd = async (method, path, body) => {
  const response = await fetch(`${GD}/session/${id}${path}`, {
    method,
    headers: body === undefined ? undefined : { 'content-type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await response.json();
  if (!response.ok || data.value?.error) throw new Error(`${data.value?.error || response.status}: ${String(data.value?.message || '').split('\n')[0]}`);
  return data.value;
};
const execute = async (script, args = []) => wd('POST', '/execute/sync', { script, args });
const drush = (code) => execFileSync('php', [
  'vendor/drush/drush/drush.php', 'php:eval', code, `--uri=${ORIGIN}`,
], { cwd: ROOT, env: { ...process.env, STUDIO_USER_ID: String(USER_ID) }, encoding: 'utf8' }).trim();
const originalMode = drush(`echo json_encode(\\Drupal::service('aculta_portal.color_mode_preference')->forUser((int) getenv('STUDIO_USER_ID')));`);
const setMode = (mode) => drush(`\\Drupal::service('aculta_portal.color_mode_preference')->save((int) getenv('STUDIO_USER_ID'), '${mode}'); echo 'ok';`);
const login = execFileSync('php', [
  'vendor/drush/drush/drush.php', 'uli', '--no-browser', `--name=${USER}`, `--uri=${ORIGIN}`,
], { cwd: ROOT, encoding: 'utf8' }).trim().split('\n').at(-1);
const waitFor = async (script, label) => {
  const until = Date.now() + 20000;
  while (Date.now() < until) {
    if (await execute(`return !!(${script});`)) return;
    await new Promise((resolve) => setTimeout(resolve, 250));
  }
  throw new Error(`Timeout aguardando ${label}.`);
};
const setViewport = async (width) => {
  const result = await bidiSend('browsingContext.setViewport', { context, viewport: { width, height: 1000 } });
  if (result.error) throw new Error(`Firefox recusou viewport ${width}px: ${result.error}`);
  const actual = await execute('return innerWidth;');
  if (Number(actual) !== width) throw new Error(`Firefox aplicou ${actual}px; esperado ${width}px.`);
};
const screenshot = async (name) => {
  const base64 = await wd('GET', '/screenshot');
  writeFileSync(join(output, `${name}.png`), Buffer.from(base64, 'base64'));
};
const results = [];
try {
  await wd('POST', '/url', { url: login });
  await waitFor('document.readyState === "complete"', 'login ULI');
  for (const mode of ['light', 'dark']) {
    setMode(mode);
    for (const path of ['/conta', '/meu-perfil']) {
      await wd('POST', '/url', { url: new URL(path, origin).href });
      const ready = path === '/conta'
        ? 'document.querySelector("[data-aculta-account-root]")'
        : 'document.querySelector("[data-portal-ui=studio-profile-actions]")';
      await waitFor(ready, path);
      for (const width of [320, 1440]) {
        await setViewport(width);
        await execute(`const root=document.querySelector('[data-aculta-navigation]');const toggle=root?.querySelector('[data-aculta-navigation-toggle]');const panel=root?.querySelector('[data-aculta-navigation-panel]');if(toggle?.getAttribute('aria-expanded')==='true'||panel?.classList.contains('is-open'))root?.querySelector('[data-aculta-navigation-close]')?.click();if(panel?.classList.contains('is-open')){panel.classList.remove('is-open');toggle?.setAttribute('aria-expanded','false');}return true;`);
        await waitFor(`matchMedia('(min-width: 68.75rem)').matches || !document.querySelector('[data-aculta-navigation-panel]') || getComputedStyle(document.querySelector('[data-aculta-navigation-panel]')).visibility === 'hidden'`, 'menu móvel fechado');
        const state = await execute(`return JSON.stringify({path:location.pathname,title:document.title,mode:document.documentElement.getAttribute('data-theme'),width:innerWidth,pageWidth:document.documentElement.scrollWidth,accountRoot:!!document.querySelector('[data-aculta-account-root]'),profileActions:!!document.querySelector('[data-portal-ui="studio-profile-actions"]'),profileRoot:!!document.querySelector('[data-aculta-studio-profile-root]'),dashboard:!!document.querySelector('.aculta-account-entry'),menuOpen:!!document.querySelector('[data-aculta-navigation-panel].is-open')});`);
        const parsed = JSON.parse(state);
        if (parsed.path !== path || parsed.pageWidth > width || parsed.mode !== mode || parsed.menuOpen) throw new Error(`Estado visual inválido: ${JSON.stringify(parsed)}`);
        results.push(parsed);
        await screenshot(`${path.slice(1)}-${mode}-${width}`);
        if (width === 320 && mode === 'light') {
          const focusSelector = path === '/conta'
            ? '[data-aculta-account-root]'
            : '[data-portal-ui="studio-profile-actions"]';
          let focused = false;
          for (let tab = 0; tab < 30 && !focused; tab += 1) {
            await wd('POST', '/actions', { actions: [{ type: 'key', id: 'studio-keyboard', actions: [{ type: 'keyDown', value: '\uE004' }, { type: 'keyUp', value: '\uE004' }] }] });
            focused = await execute(`return !!document.activeElement?.closest('${focusSelector}');`);
          }
          if (!focused) throw new Error(`Tab não alcançou um controle em ${path}.`);
          const focus = await execute(`const el=document.activeElement;const rect=el.getBoundingClientRect();return JSON.stringify({path:location.pathname,tag:el.tagName,text:(el.innerText||el.textContent||'').trim().slice(0,80),href:el.getAttribute('href'),outline:getComputedStyle(el).outlineStyle,outlineWidth:getComputedStyle(el).outlineWidth,x:Math.round(rect.x),y:Math.round(rect.y),width:Math.round(rect.width),height:Math.round(rect.height)});`);
          const focusState = JSON.parse(focus);
          if (focusState.outline !== 'solid' || parseFloat(focusState.outlineWidth) < 2) throw new Error(`Foco de teclado insuficiente: ${JSON.stringify(focusState)}`);
          const focusName = `${path.slice(1)}-light-320-focus`;
          writeFileSync(join(output, `${focusName}.json`), JSON.stringify(focusState, null, 2));
          await screenshot(focusName);
        }
      }
    }
  }
  writeFileSync(join(output, 'results.json'), JSON.stringify(results, null, 2));
  console.log(`Capturas e medidas salvas em ${output}`);
}
finally {
  try {
    if (originalMode === 'null' || originalMode === '') drush(`\\Drupal::service('user.data')->delete('aculta_portal', (int) getenv('STUDIO_USER_ID'), 'color_mode'); echo 'ok';`);
    else drush(`\\Drupal::service('aculta_portal.color_mode_preference')->save((int) getenv('STUDIO_USER_ID'), json_decode('${originalMode}')); echo 'ok';`);
  }
  finally {
    bidi.close();
    await fetch(`${GD}/session/${id}`, { method: 'DELETE' });
  }
}
