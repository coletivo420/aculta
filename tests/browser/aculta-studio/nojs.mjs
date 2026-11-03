// Smoke de navegação direta e sem JavaScript para o shell privado da Conta Studio.
// Executar somente no Runtime de testes com Firefox ESR visível; não submete formulários.
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const PROJECT = fileURLToPath(new URL('../../../', import.meta.url));
const OUT = process.env.SUITE_OUT;
const USER = process.env.STUDIO_USER || 'teste.comum';
const USER_ID = process.env.STUDIO_USER_ID || '5';
const BASE = process.env.STUDIO_LOGIN_URI || 'https://conta.aculta.toca.net.br';
const DRUSH = process.env.STUDIO_DRUSH_ROOT || PROJECT;
const DRIVER = process.env.GECKODRIVER || 'http://127.0.0.1:4445';
if (!OUT) throw new Error('Defina SUITE_OUT fora do repositório.');
if (process.env.DISPLAY !== ':0' || execFileSync('hostname', [], { encoding: 'utf8' }).trim() !== 'toca') throw new Error('O Firefox visível deve ser executado no Runtime de teste (DISPLAY=:0, host toca).');
mkdirSync(OUT, { recursive: true });

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok: !!ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${detail ? ` — ${detail}` : ''}`);
};
async function wd(method, path, body) {
  const response = await fetch(DRIVER + path, { method, headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  const data = await response.json();
  if (!response.ok || data.value?.error) throw new Error(`${data.value?.error || response.status}: ${String(data.value?.message || '').split('\n')[0]}`);
  return data.value;
}
const oneTimeLogin = () => execFileSync('php', ['vendor/drush/drush/drush.php', 'uli', '--no-browser', `--name=${USER}`, `--uri=${BASE}`], { cwd: DRUSH, encoding: 'utf8' }).trim().split('\n').pop();

let sid = '';
try {
  const created = await wd('POST', '/session', { capabilities: { alwaysMatch: {
    browserName: 'firefox', acceptInsecureCerts: true,
    'moz:firefoxOptions': { prefs: { 'javascript.enabled': false, 'network.dns.localDomains': new URL(BASE).hostname } },
  } } });
  sid = created.sessionId;
  const p = (path) => `/session/${sid}${path}`;
  const login = oneTimeLogin();
  await wd('POST', p('/url'), { url: login });
  await new Promise((resolve) => setTimeout(resolve, 1600));
  let current = await wd('GET', p('/url'));
  check('login único conclui sem JavaScript', new URL(current).pathname !== '/user/login', new URL(current).pathname);

  await wd('POST', p('/url'), { url: new URL('/conta/dados', BASE).href });
  await new Promise((resolve) => setTimeout(resolve, 700));
  current = await wd('GET', p('/url'));
  const title = await wd('GET', p('/title'));
  const source = await wd('GET', p('/source'));
  const dataForm = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-account-root] form' }).then(() => true, () => false);
  const accountNav = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-account-root] .studio__subtabs' }).then(() => true, () => false);
  check('rota direta Dados entrega shell e Form API sem JavaScript', new URL(current).pathname === '/conta/dados' && dataForm && accountNav, JSON.stringify({ path: new URL(current).pathname, title, form: dataForm, navigation: accountNav, scriptReferences: (source.match(/<script\b/gi) || []).length }));

  const address = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-account-link][href="/conta/endereco"]' }).then((value) => value[Object.keys(value)[0]], () => null);
  if (address) {
    await wd('POST', p(`/element/${address}/click`), {});
    await new Promise((resolve) => setTimeout(resolve, 700));
    current = await wd('GET', p('/url'));
    const addressForm = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-account-root] form' }).then(() => true, () => false);
    const active = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-account-root] [data-aculta-account-link][aria-current="page"][href="/conta/endereco"]' }).then(() => true, () => false);
    check('link da Conta abre Endereço como navegação HTML normal sem JavaScript', new URL(current).pathname === '/conta/endereco' && addressForm && active, JSON.stringify({ path: new URL(current).pathname, form: addressForm, active }));
  } else check('link da Conta abre Endereço como navegação HTML normal sem JavaScript', false, 'link de Endereço ausente no shell sem JavaScript');

  const back = await wd('POST', p('/back'), {});
  await new Promise((resolve) => setTimeout(resolve, 700));
  current = await wd('GET', p('/url'));
  check('voltar funciona sem JavaScript', new URL(current).pathname === '/conta/dados', new URL(current).pathname);
} catch (error) {
  check('suíte no-JS executa integralmente', false, String(error.message || error));
} finally {
  writeFileSync(join(OUT, 'nojs-results.json'), JSON.stringify(results, null, 2), { mode: 0o600 });
  if (sid) await wd('DELETE', `/session/${sid}`).catch(() => {});
  const failed = results.filter((item) => !item.ok).length;
  console.log(`\n${results.length - failed}/${results.length} verificações no-JS; ${failed} falha(s). Relatório em ${OUT}`);
  process.exit(failed ? 1 : 0);
}
