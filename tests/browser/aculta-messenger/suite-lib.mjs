// Biblioteca da suíte de navegador do Messenger: Firefox ESR visível pelo geckodriver (protocolo W3C WebDriver).
// Sem dependências: usa fetch e child_process do Node 22.
//
// Variáveis de ambiente:
//   SUITE_BASE       URL do site de teste (padrão: https://aculta.toca.net.br)
//   SUITE_LOGIN_URI  URI do Drush para o link de login (padrão: https://conta.aculta.toca.net.br)
//   SUITE_OUT        pasta das capturas e do relatório (padrão: ./.suite-out, fora do Git)
//   GECKODRIVER      URL do geckodriver (padrão: http://127.0.0.1:4444)
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

if (process.env.DISPLAY !== ':0') {
  throw new Error('A suíte do Messenger exige Firefox ESR visível em DISPLAY=:0.');
}

export const PROJECT = fileURLToPath(new URL('../../../', import.meta.url));
export const BASE = process.env.SUITE_BASE || 'https://aculta.toca.net.br';
const LOGIN_URI = process.env.SUITE_LOGIN_URI || 'https://conta.aculta.toca.net.br';
export const GD = process.env.GECKODRIVER || 'http://127.0.0.1:4444';
export const OUT = process.env.SUITE_OUT || join(PROJECT, '.suite-out');
mkdirSync(OUT, { recursive: true });
export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

export const results = [];
export function check(name, ok, detail = '') {
  results.push({ name, ok: !!ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  -- ' + detail : ''}`);
  return !!ok;
}

async function wd(method, path, body) {
  const res = await fetch(GD + path, { method, headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  const json = await res.json();
  if (json.value && json.value.error) throw new Error(`${json.value.error}: ${json.value.message.split('\n')[0]}`);
  return json.value;
}

// One-time login link for an account of the test runtime (the Drush user login command, no password is stored).
export function oneTimeLogin(name) {
  return execFileSync('php', ['vendor/drush/drush/drush.php', 'uli', '--no-browser', `--name=${name}`, `--uri=${LOGIN_URI}`], { cwd: PROJECT, timeout: 30_000 }).toString().trim().split('\n').pop();
}

// Runs PHP on the test runtime (Drush php:eval). The code is written by the suite, never taken from a page.
export function drush(code) {
  return execFileSync('php', ['vendor/drush/drush/drush.php', 'php:eval', code], { cwd: PROJECT }).toString().trim();
}

export function setup() {
  return execFileSync('php', ['vendor/drush/drush/drush.php', 'scr', 'tests/browser/aculta-messenger/setup-accounts.php'], { cwd: PROJECT }).toString();
}

export class Browser {
  constructor() { this.sid = null; this.width = 1280; this.height = 800; this.openOptionsLabel = null; }

  async open(name, { width = 1280, height = 800 } = {}) {
    await this.close();
    this.width = width; this.height = height;
    const host = new URL(BASE).hostname;
    const { sessionId } = await wd('POST', '/session', { capabilities: { alwaysMatch: { browserName: 'firefox', acceptInsecureCerts: true, 'moz:firefoxOptions': { prefs: { 'network.dns.localDomains': `${host},conta.${host}` } } } } });
    this.sid = sessionId;
    await wd('POST', this.p('/window/rect'), { width, height });
    await wd('POST', this.p('/url'), { url: oneTimeLogin(name) });
    await sleep(2000);
    await wd('POST', this.p('/url'), { url: BASE + '/' });
    await sleep(1800);
    const loginState = await this.exec(`const u = new URL(location.href); return JSON.stringify({ host: u.host, path: u.pathname, messengerTrigger: !!document.querySelector('.aculta-messenger-trigger'), loginForm: !!document.querySelector('form.user-login-form'), testAccountVisible: document.body.innerText.includes(${JSON.stringify(name)}) });`);
    console.log(`Browser session ${name}: ${loginState}`);
    if (!await this.waitFor(`document.querySelector('.aculta-messenger-trigger')`, `Messenger access for ${name}`, 10000)) {
      throw new Error(`A conta de teste ${name} não iniciou sessão com acesso ao Messenger.`);
    }
    await this.exec(`window.__errs = []; window.addEventListener('error', (e) => window.__errs.push(String(e.message))); window.addEventListener('unhandledrejection', (e) => window.__errs.push('REJ ' + String(e.reason))); window.__f = []; const __of = window.fetch; window.fetch = (...a) => __of(...a).then((r) => { const u = new URL(String(a[0]), location.href); window.__f.push(String(a[1] && a[1].method || 'GET') + ' ' + r.status + ' ' + u.pathname + ' token=' + u.searchParams.has('token') + ' keys=' + (a[1] && a[1].body && a[1].body.keys ? [...a[1].body.keys()].join(',') : '-')); return r; }); return 1;`);
    return this;
  }

  p(path) { return `/session/${this.sid}${path}`; }
  async exec(script) { return wd('POST', this.p('/execute/sync'), { script, args: [] }); }
  async go(url) { await this.exec(`window.location.href = ${JSON.stringify(url)}; return 1;`); await sleep(2000); }
  async close() { if (this.sid) { await wd('DELETE', this.p('')).catch(() => {}); this.sid = null; } }
  async shot(name) { writeFileSync(join(OUT, `${name}.png`), Buffer.from(await wd('GET', this.p('/screenshot')), 'base64')); }
  async errors() { return this.exec(`return (window.__errs || []).slice(0, 10);`); }

  // The messenger drawer's body.
  get C() { return `document.querySelector('[data-aculta-drawer-url][data-aculta-drawer-count="messages"] [data-aculta-drawer-content]')`; }

  async waitFor(expr, label, timeoutMs = 15000) {
    const end = Date.now() + timeoutMs;
    while (Date.now() < end) {
      if (await this.exec(`return !!(${expr});`)) return true;
      await sleep(250);
    }
    console.log(`  (timeout waiting for: ${label})`);
    return false;
  }

  async openDrawer() {
    await this.exec(`document.querySelector('.aculta-messenger-trigger').click(); return 1;`);
    return this.waitFor(`${this.C}`, 'drawer');
  }

  async text() { return this.exec(`return ${this.C} ? ${this.C}.innerText.replace(/\\s+/g,' ') : '';`); }

  // Opens a conversation row by the peer's name (list view).
  async openConversation(peer) {
    const ok = await this.waitFor(`[...${this.C}.querySelectorAll('a[data-aculta-conversation-url]')].some((x) => x.textContent.includes(${JSON.stringify(peer)}))`, 'conversation row');
    if (!ok) return false;
    await this.exec(`[...${this.C}.querySelectorAll('a[data-aculta-conversation-url]')].find((x) => x.textContent.includes(${JSON.stringify(peer)})).click(); return 1;`);
    return this.waitFor(`${this.C}.querySelector('.aculta-messenger-conversation-toolbar')`, 'toolbar');
  }

  // Opens a conversation row by the thread id (for group threads, whose row shows the last author).
  async openThread(threadId) {
    const ok = await this.waitFor(`${this.C}.querySelector('a[data-aculta-conversation-url][data-aculta-thread-id="${threadId}"]')`, 'thread row');
    if (!ok) return false;
    await this.exec(`${this.C}.querySelector('a[data-aculta-conversation-url][data-aculta-thread-id="${threadId}"]').click(); return 1;`);
    return this.waitFor(`${this.C}.querySelector('[data-aculta-conversation-view][data-aculta-thread-id="${threadId}"]')`, `thread ${threadId}`);
  }

  async clickByText(selector, text) {
    return this.exec(`const el = [...${this.C}.querySelectorAll(${JSON.stringify(selector)})].find((x) => x.textContent.trim() === ${JSON.stringify(text)} || x.value === ${JSON.stringify(text)}); if (!el) return 0; el.click(); return 1;`);
  }

  async waitText(text, timeoutMs = 15000) {
    return this.waitFor(`${this.C}.innerText.includes(${JSON.stringify(text)})`, text, timeoutMs);
  }

  async waitGone(text, timeoutMs = 15000) {
    return this.waitFor(`!${this.C}.innerText.includes(${JSON.stringify(text)})`, 'gone:' + text, timeoutMs);
  }

  // The items of the open menu, as "button:Label" or "link:Label".
  async panelItems() {
    const menu = this.openOptionsLabel === null
      ? `${this.C}.querySelector('.aculta-messenger-options[open]')`
      : `[...${this.C}.querySelectorAll('.aculta-messenger-options[open]')].find((item) => item.querySelector('.aculta-messenger-options__summary')?.textContent.trim() === ${JSON.stringify(this.openOptionsLabel)})`;
    return this.exec(`const menu = ${menu}; return JSON.stringify([...(menu?.querySelectorAll('.aculta-messenger-options__panel a, .aculta-messenger-options__panel input[type=submit]:not([data-aculta-dialog-close])') || [])].filter((e) => e.getClientRects().length > 0).map(e => (e.tagName === 'A' ? 'link:' : 'button:') + (e.value || e.textContent.trim())));`).then(JSON.parse);
  }

  // Opens the "Opções" menu, unless it is already open (a result keeps it open).
  async openOptions(memberName = null) {
    this.openOptionsLabel = memberName;
    const find = memberName === null
      ? `${this.C}.querySelector('.aculta-messenger-options')`
      : `[...${this.C}.querySelectorAll('.aculta-messenger-options')].find((item) => item.querySelector('.aculta-messenger-options__summary')?.textContent.trim() === ${JSON.stringify(memberName)})`;
    await this.exec(`const d = ${find}; if (d && !d.open) { d.querySelector('.aculta-messenger-options__summary').click(); } return 1;`);
    const ready = memberName === null ? `!!(d && d.open)` : `!!(d && d.open && d.querySelector('.aculta-messenger-options__panel form[data-aculta-drawer-action]'))`;
    return this.waitFor(`(() => { const d = ${find}; return ${ready}; })()`, memberName === null ? 'options open' : `options open for ${memberName}`);
  }

  async notice() {
    return this.exec(`const n = ${this.C}.querySelector('.aculta-messenger-notice'); return n ? n.innerText.replace(/\\s+/g,' ').trim() : null;`);
  }

  async hasReplyBox() {
    return this.exec(`return !!${this.C}.querySelector('textarea[name="message[0][value]"]');`);
  }

  // Types into an element found by CSS selector (real key events, so input listeners run).
  async typeIn(selector, text) {
    const el = await wd('POST', this.p('/element'), { using: 'css selector', value: selector });
    await wd('POST', this.p(`/element/${Object.values(el)[0]}/value`), { text });
  }

  // Sends one real keyboard action through W3C WebDriver (for example, Escape: \uE00C).
  async pressKey(key) {
    await wd('POST', this.p('/actions'), { actions: [{ type: 'key', id: 'messenger-keyboard', actions: [{ type: 'keyDown', value: key }, { type: 'keyUp', value: key }] }] });
  }

  async clearAndType(selector, text) {
    await this.exec(`const i = document.querySelector(${JSON.stringify(selector)}); if (i) { i.focus(); i.value = ''; i.dispatchEvent(new Event('input', { bubbles: true })); } return 1;`);
    await this.typeIn(selector, text);
  }

}
