// Teste com leitor de tela real (Orca) em estados autenticados, focado nos
// botões do SDC aculta420_v2:button. Confere pelo log de depuração do Orca
// (--debug-file) o nome acessível anunciado de cada botão/link visível e exige
// que nenhum botão visível fique sem nome acessível.
//
// Requisitos (ver tests/browser/transversal/aculta-botoes-a11y/README.md):
// - Orca ativo na sessão gráfica (DISPLAY=:0) com --debug-file fora do repo;
// - geckodriver acessível em GECKODRIVER (padrão http://127.0.0.1:4455);
// - SUITE_OUT fora do repositório;
// - conta de teste administrativa (ACULTA_ADMIN_TEST_UID, padrão 257).
//
// Não envia formulários nem grava conteúdo: só navega, foca botões e lê o log.
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join, relative, resolve, sep } from 'node:path';

const PROJECT = fileURLToPath(new URL('../../../../', import.meta.url));
const DEPLOY = JSON.parse(readFileSync(join(PROJECT, 'web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json'), 'utf8'));
const HOSTS = Object.fromEntries(DEPLOY.domain_transition.entries.map((item) => [item.domain_id, item.test_host]));
const MAIN = `https://${HOSTS.aculta_org}`;
const ACCOUNT = `https://${HOSTS.conta_aculta_org}`;
const EVENTOS = `https://${HOSTS.eventos_aculta_org}`;
const GD = process.env.GECKODRIVER || 'http://127.0.0.1:4455';
const ORCA_LOG = process.env.ORCA_LOG;
const OUT_VALUE = process.env.SUITE_OUT;
const ADMIN_UID = process.env.ACULTA_ADMIN_TEST_UID || '257';
if (!OUT_VALUE) { console.error('Defina SUITE_OUT fora do repositório.'); process.exit(2); }
if (!ORCA_LOG) { console.error('Defina ORCA_LOG com o caminho do --debug-file do Orca.'); process.exit(2); }
const OUT = resolve(OUT_VALUE);
const outRelative = relative(PROJECT, OUT);
if (outRelative === '' || (outRelative !== '..' && !outRelative.startsWith('..' + sep))) {
  console.error('SUITE_OUT precisa ficar fora do repositório.'); process.exit(2);
}
const ORCA_LOG_PATH = resolve(ORCA_LOG);
const logRelative = relative(PROJECT, ORCA_LOG_PATH);
if (logRelative === '' || (logRelative !== '..' && !logRelative.startsWith('..' + sep))) {
  console.error('ORCA_LOG precisa ficar fora do repositório.'); process.exit(2);
}
mkdirSync(OUT, { recursive: true, mode: 0o700 });

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
const oneTimeLogin = () => execFileSync('php', ['vendor/drush/drush/drush.php', 'uli', '/', '--no-browser', `--uid=${ADMIN_UID}`, `--uri=${ACCOUNT}`], { cwd: PROJECT }).toString().trim().split('\n').pop();
const logSize = () => { try { return readFileSync(ORCA_LOG_PATH, 'utf8').length; } catch { return 0; } };
const logSince = (from) => readFileSync(ORCA_LOG_PATH, 'utf8').slice(from);

const PAGES = [
  { name: 'conta', url: `${ACCOUNT}/` },
  { name: 'eventos', url: `${EVENTOS}/culturas` },
  { name: 'admin-redes', url: `${MAIN}/painel-administrativo/aculta/redes-sociais` },
  { name: 'admin-caminhos', url: `${MAIN}/painel-administrativo/aculta/caminhos` },
];

const { sessionId: sid } = await wd('POST', '/session', { capabilities: { alwaysMatch: { browserName: 'firefox', acceptInsecureCerts: true } } });
const p = (x) => `/session/${sid}${x}`;
const exec = (script) => wd('POST', p('/execute/sync'), { script, args: [] });

try {
  await wd('POST', p('/url'), { url: oneTimeLogin() });
  await sleep(2500);

  for (const page of PAGES) {
    const start = logSize();
    await wd('POST', p('/url'), { url: page.url });
    await sleep(3500);
    const buttons = await exec(`return Array.from(document.querySelectorAll('.button'))
      .filter((e) => e.getClientRects().length)
      .map((e) => ({
        tag: e.tagName,
        text: (e.innerText || e.value || '').trim().slice(0, 80),
        aria: e.getAttribute('aria-label') || '',
        // Em <input type="submit"> o nome acessível vem de value, não de innerText.
        name: (e.getAttribute('aria-label') || e.value || e.innerText || '').trim().slice(0, 80),
        disabled: e.hasAttribute('disabled') || e.getAttribute('aria-disabled') === 'true',
      }));`);
    check(`${page.name}: há botões do SDC visíveis`, buttons.length > 0, `${buttons.length} botão(ões)`);
    const unnamed = buttons.filter((b) => b.name === '');
    check(`${page.name}: todo botão visível tem nome acessível`, unnamed.length === 0, unnamed.map((b) => b.tag).join(', '));

    for (let i = 0; i < buttons.length; i++) {
      await exec(`var b = Array.from(document.querySelectorAll('.button')).filter((e) => e.getClientRects().length)[${i}];
        if (b) { b.scrollIntoView({block:'center'}); b.focus(); } return b ? 1 : 0;`);
      await sleep(900);
    }
    const spoken = logSince(start);
    const missing = buttons.filter((b) => b.name !== '' && !spoken.includes(`'${b.name.replace(/'/g, "\\'")}'`));
    check(`${page.name}: Orca anuncia o nome de cada botão/link`, missing.length === 0,
      missing.length ? `sem anúncio: ${missing.map((b) => b.name).join(' | ')}` : `${buttons.length}/${buttons.length}`);
    writeFileSync(join(OUT, `orca-buttons-${page.name}.txt`), spoken, { mode: 0o600 });
    writeFileSync(join(OUT, `buttons-${page.name}.json`), JSON.stringify(buttons, null, 2), { mode: 0o600 });
  }
} catch (error) {
  check('execução do teste com Orca', false, String(error.message || error));
} finally {
  await wd('DELETE', p('')).catch(() => {});
  const failed = results.filter((r) => !r.ok).length;
  writeFileSync(join(OUT, 'results.json'), JSON.stringify(results, null, 2), { mode: 0o600 });
  console.log(`\n${results.length - failed}/${results.length} verificações OK. Saída em ${OUT}`);
  process.exit(failed ? 1 : 0);
}
