// Teste com leitor de tela real (Orca) no Firefox do X11: navega pelo Studio só com teclado e confere o que o
// Orca anuncia, pelo log de depuração dele (--debug-file). Requer o Orca em execução na sessão gráfica.
// Ver README.md desta pasta. Somente no servidor de teste, com a conta de teste.
import { chmodSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { execFileSync, spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { join, relative, resolve, sep } from 'node:path';

const PROJECT = fileURLToPath(new URL('../../../', import.meta.url));
const BASE = process.env.BASE;
if (!BASE) { console.error('Defina BASE com a origem do Runtime de teste.'); process.exit(2); }
const PAGE = process.env.STUDIO_PAGE || new URL('/marley/revista/publicacoes/publicados', BASE).toString();
const LOGIN_URI = process.env.STUDIO_LOGIN_URI || BASE;
const GD = process.env.GECKODRIVER || 'http://127.0.0.1:4455';
const ORCA_LOG = process.env.ORCA_LOG;
const OUT_VALUE = process.env.SUITE_OUT || process.env.STUDIO_OUT;
if (!OUT_VALUE) { console.error('Defina SUITE_OUT fora do repositório.'); process.exit(2); }
if (!ORCA_LOG) { console.error('Defina ORCA_LOG com o caminho do --debug-file do Orca.'); process.exit(2); }
const OUT = resolve(OUT_VALUE);
const outRelative = relative(PROJECT, OUT);
if (outRelative === '' || (outRelative !== '..' && !outRelative.startsWith('..' + sep))) {
  console.error('SUITE_OUT precisa ficar fora do repositório.');
  process.exit(2);
}
const ORCA_LOG_PATH = resolve(ORCA_LOG);
const logRelative = relative(PROJECT, ORCA_LOG_PATH);
if (logRelative === '' || (logRelative !== '..' && !logRelative.startsWith('..' + sep))) {
  console.error('ORCA_LOG precisa ficar fora do repositório.');
  process.exit(2);
}
mkdirSync(OUT, { recursive: true, mode: 0o700 });
chmodSync(OUT, 0o700);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const results = [];
const check = (name, ok, detail = '') => { results.push({ name, ok: !!ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  -- ' + detail : ''}`); return !!ok; };
async function wd(method, path, body) {
  const res = await fetch(GD + path, { method, headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  const json = await res.json();
  if (json.value && json.value.error) throw new Error(`${json.value.error}: ${String(json.value.message).split('\n')[0]}`);
  return json.value;
}
const oneTimeLogin = () => execFileSync('php', ['vendor/drush/drush/drush.php', 'uli', '--no-browser', '--name=teste.comum', `--uri=${LOGIN_URI}`], { cwd: PROJECT }).toString().trim().split('\n').pop();
const logSize = () => { try { return readFileSync(ORCA_LOG_PATH, 'utf8').length; } catch { return 0; } };
const logSince = (from) => readFileSync(ORCA_LOG_PATH, 'utf8').slice(from);
// Grava a saída de áudio (monitor do sink padrão) durante a navegação e mede o nível, para comprovar som.
const audioLevel = (file) => {
  const sink = execFileSync('pactl', ['get-default-sink']).toString().trim();
  return { sink, file, proc: spawn('parecord', ['--device=' + sink + '.monitor', '--file-format=wav', file]) };
};
const rmsOf = (file) => execFileSync('python3', ['-c', `import sys,wave,struct,math
w=wave.open(sys.argv[1],'rb');f=w.readframes(w.getnframes());w.close()
s=struct.unpack('<%dh'%(len(f)//2),f)
print(round(math.sqrt(sum(x*x for x in s)/len(s)),1) if s else 0)`, file]).toString().trim();
const key = (text) => ({ actions: [{ type: 'key', id: 'k', actions: [{ type: 'keyDown', value: text }, { type: 'keyUp', value: text }] }] });

const { sessionId: sid } = await wd('POST', '/session', { capabilities: { alwaysMatch: { browserName: 'firefox', acceptInsecureCerts: true, 'moz:firefoxOptions': { prefs: { 'network.dns.localDomains': 'aculta.toca.net.br,conta.aculta.toca.net.br' } } } } });
const p = (x) => `/session/${sid}${x}`;
const exec = (script) => wd('POST', p('/execute/sync'), { script, args: [] });
const press = (text) => wd('POST', p('/actions'), key(text));
try {
  await wd('POST', p('/url'), { url: oneTimeLogin() });
  await sleep(2500);
  const rec = audioLevel(join(OUT, 'studio-audio.wav'));
  const start = logSize();
  await wd('POST', p('/url'), { url: PAGE });
  await sleep(5000);
  const expected = await exec(`return {
    areas: Array.from(document.querySelectorAll('a[data-aculta-studio="tab"]'))
      .filter((link) => link.getClientRects().length)
      .map((link) => link.getAttribute('aria-label') || link.innerText.trim())
      .filter(Boolean),
    heading: (document.querySelector('[data-aculta-studio="heading"]') || document.querySelector('main h1'))?.innerText.trim() || ''
  };`);
  await exec(`document.querySelector('[data-aculta-studio="tab"]')?.focus(); return 1;`);
  for (let i = 0; i < 6; i++) { await press(''); await sleep(900); }
  const spoken = logSince(start);
  check('Orca registrou anúncios durante a navegação por Tab', spoken.length > 0, `${spoken.length} caracteres no log`);
  await press('');
  await sleep(2500);
  const afterEnter = logSince(start);
  check('Enter no teclado disparou novo anúncio do Orca', afterEnter.length > spoken.length, `+${afterEnter.length - spoken.length} caracteres`);
  writeFileSync(join(OUT, 'orca-speech.txt'), afterEnter, { mode: 0o600 });
  await sleep(500);
  rec.proc.kill();
  const rms = Number(rmsOf(rec.file));
  check('a saída de áudio do sistema tem sinal durante a fala do Orca', rms > 50, `RMS ${rms} (silêncio = 0)`);
  // O Orca gera a fala do foco com o nome acessível de cada área (link) e do título da seção (heading).
  const names = expected.areas.filter((name) => afterEnter.includes(`[link: '${name}']`));
  const heading = expected.heading !== '' && afterEnter.includes(`[heading: '${expected.heading}']`);
  check('o Orca gera a fala de todas as áreas disponíveis pelo nome acessível', expected.areas.length > 0 && names.length === expected.areas.length, `${names.length}/${expected.areas.length} área(s): ${names.join(', ')}`);
  check('o Orca gera a fala do título atual da seção (heading)', heading, expected.heading || 'nenhum heading encontrado');
} catch (e) {
  check('execução do teste com Orca', false, String(e.message || e));
} finally {
  await wd('DELETE', p('')).catch(() => {});
  const failed = results.filter((r) => !r.ok).length;
  console.log(`\n${results.length - failed}/${results.length} verificações OK. Fala do Orca em ${OUT}/orca-speech.txt`);
  writeFileSync(join(OUT, 'results.json'), JSON.stringify(results, null, 2), { mode: 0o600 });
  process.exit(failed ? 1 : 0);
}
