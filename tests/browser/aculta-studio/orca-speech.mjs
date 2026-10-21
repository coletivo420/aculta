// Teste com leitor de tela real (Orca) no Firefox do X11: navega pelo Studio só com teclado e confere o que o
// Orca anuncia, pelo log de depuração dele (--debug-file). Requer o Orca em execução na sessão gráfica.
// Ver README.md desta pasta. Somente no servidor de teste, com a conta de teste.
import { readFileSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const PROJECT = fileURLToPath(new URL('../../../', import.meta.url));
const PAGE = process.env.STUDIO_PAGE || 'https://conta.aculta.toca.net.br/studio/revista/publicacoes/publicados';
const LOGIN_URI = process.env.STUDIO_LOGIN_URI || 'https://conta.aculta.toca.net.br';
const GD = process.env.GECKODRIVER || 'http://127.0.0.1:4455';
const ORCA_LOG = process.env.ORCA_LOG;
const OUT = process.env.STUDIO_OUT || join(PROJECT, '.suite-out', 'studio-orca');
if (!ORCA_LOG) { console.error('Defina ORCA_LOG com o caminho do --debug-file do Orca.'); process.exit(2); }
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const results = [];
const check = (name, ok, detail = '') => { results.push({ name, ok: !!ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  -- ' + detail : ''}`); return !!ok; };
async function wd(method, path, body) {
  const res = await fetch(GD + path, { method, headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  const json = await res.json();
  if (json.value && json.value.error) throw new Error(`${json.value.error}: ${String(json.value.message).split('\n')[0]}`);
  return json.value;
}
const oneTimeLogin = () => execFileSync('php', ['vendor/drush/drush/drush.php', 'uli', '--name=teste.comum', `--uri=${LOGIN_URI}`], { cwd: PROJECT }).toString().trim().split('\n').pop();
const logSize = () => { try { return readFileSync(ORCA_LOG, 'utf8').length; } catch { return 0; } };
const logSince = (from) => readFileSync(ORCA_LOG, 'utf8').slice(from);
const key = (text) => ({ actions: [{ type: 'key', id: 'k', actions: [{ type: 'keyDown', value: text }, { type: 'keyUp', value: text }] }] });

const { sessionId: sid } = await wd('POST', '/session', { capabilities: { alwaysMatch: { browserName: 'firefox', acceptInsecureCerts: true, 'moz:firefoxOptions': { prefs: { 'network.dns.localDomains': 'aculta.toca.net.br,conta.aculta.toca.net.br' } } } } });
const p = (x) => `/session/${sid}${x}`;
const exec = (script) => wd('POST', p('/execute/sync'), { script, args: [] });
const press = (text) => wd('POST', p('/actions'), key(text));
try {
  await wd('POST', p('/url'), { url: oneTimeLogin() });
  await sleep(2500);
  const start = logSize();
  await wd('POST', p('/url'), { url: PAGE });
  await sleep(5000);
  await exec(`document.querySelector('[data-aculta-studio="tab"]')?.focus(); return 1;`);
  for (let i = 0; i < 6; i++) { await press(''); await sleep(900); }
  const spoken = logSince(start);
  check('Orca registrou anúncios durante a navegação por Tab', spoken.length > 0, `${spoken.length} caracteres no log`);
  await press('');
  await sleep(2500);
  const afterEnter = logSince(start);
  check('Enter no teclado disparou novo anúncio do Orca', afterEnter.length > spoken.length, `+${afterEnter.length - spoken.length} caracteres`);
  writeFileSync(join(OUT, 'orca-speech.txt'), afterEnter);
  // O Orca gera a fala do foco com o nome acessível de cada área (link) e do título da seção (heading).
  const names = ['Revista', 'Fórum', 'Cursos', 'Galerias', 'Projetos', 'Wiki'].filter((n) => afterEnter.includes(`[link: '${n}']`));
  const heading = afterEnter.includes("[heading: 'Publicações']");
  check('o Orca gera a fala de áreas do Studio pelo nome acessível', names.length >= 3, `${names.length} área(s): ${names.join(', ')}`);
  check('o Orca gera a fala do título da seção (heading)', heading);
} catch (e) {
  check('execução do teste com Orca', false, String(e.message || e));
} finally {
  await wd('DELETE', p('')).catch(() => {});
  const failed = results.filter((r) => !r.ok).length;
  console.log(`\n${results.length - failed}/${results.length} verificações OK. Fala do Orca em ${OUT}/orca-speech.txt`);
  process.exit(failed ? 1 : 0);
}
