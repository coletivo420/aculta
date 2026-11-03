// Exercício de gravação do avatar/corte exclusivamente em conta sintética descartável.
// Não submete aceite de Termos nem acessa dados de usuários reais.
import { chmodSync, mkdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { join, relative, resolve, sep } from 'node:path';

const PROJECT = fileURLToPath(new URL('../../../', import.meta.url));
const BASE = process.env.BASE;
const OUT_VALUE = process.env.SUITE_OUT;
const CREDENTIALS = process.env.STUDIO_TEST_CREDENTIALS;
const IMAGE = process.env.STUDIO_AVATAR_FILE;
const GD = process.env.GECKODRIVER || 'http://127.0.0.1:4455';
if (!BASE || !OUT_VALUE || !CREDENTIALS || !IMAGE) {
  console.error('Defina BASE, SUITE_OUT, STUDIO_TEST_CREDENTIALS e STUDIO_AVATAR_FILE.');
  process.exit(2);
}

function outsideProject(value, label) {
  const absolute = resolve(value);
  const rel = relative(PROJECT, absolute);
  if (rel === '' || (rel !== '..' && !rel.startsWith('..' + sep))) {
    throw new Error(`${label} precisa ficar fora do repositório.`);
  }
  return absolute;
}

const OUT = outsideProject(OUT_VALUE, 'SUITE_OUT');
const credentialsPath = outsideProject(CREDENTIALS, 'STUDIO_TEST_CREDENTIALS');
const imagePath = outsideProject(IMAGE, 'STUDIO_AVATAR_FILE');
const credentialsStat = statSync(credentialsPath);
if ((credentialsStat.mode & 0o077) !== 0) {
  throw new Error('O arquivo externo de credenciais precisa ter permissão 0600.');
}
const credentials = readFileSync(credentialsPath, 'utf8');
const username = credentials.match(/^username=(.+)$/m)?.[1]?.trim() || '';
if (!/^studio_p1_test_[a-z0-9_]+$/.test(username)) {
  throw new Error('A conta deve usar o prefixo reservado studio_p1_test_.');
}
const image = readFileSync(imagePath);
if (!image.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]))) {
  throw new Error('STUDIO_AVATAR_FILE precisa ser um PNG válido.');
}
mkdirSync(OUT, { recursive: true, mode: 0o700 });
chmodSync(OUT, 0o700);

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok: !!ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`);
  return !!ok;
};
const defer = (name, detail) => {
  results.push({ name, status: 'DEFERRED', detail });
  console.log(`DEFERRED  ${name} — ${detail}`);
};
const sleep = (ms) => new Promise((resolveSleep) => setTimeout(resolveSleep, ms));
const waitFor = async (fn, timeout = 15000) => {
  const start = Date.now();
  while (Date.now() - start < timeout) {
    if (await fn()) return true;
    await sleep(250);
  }
  return false;
};

function accountState() {
  const code = '$p=getenv("STUDIO_TEST_CREDENTIALS"); $s=file_get_contents($p); preg_match("/^username=(.*)$/m", $s, $m); $us=\\Drupal::entityTypeManager()->getStorage("user")->loadByProperties(["name"=>$m[1]]); $u=$us?reset($us):NULL; if (!$u) { echo json_encode(["found"=>FALSE]); return; } $fid=(int)($u->get("user_picture")->target_id??0); $f=$fid?\\Drupal::entityTypeManager()->getStorage("file")->load($fid):NULL; echo json_encode(["found"=>TRUE,"uid"=>(int)$u->id(),"active"=>$u->isActive(),"roles"=>array_values($u->getRoles()),"terms"=>\\Drupal::service("aculta_user.aculta_user_agreement_ledger")->hasAcceptedAcultaUserAgreement((int)$u->id(),"aculta_user_agreement_terms"),"fid"=>$fid,"permanent"=>$f?->isPermanent()??FALSE,"owner"=>$f?(int)$f->getOwnerId():0]);';
  const raw = execFileSync('php', [
    'vendor/drush/drush/drush.php', 'php:eval', code,
  ], { cwd: PROJECT, env: { ...process.env, STUDIO_TEST_CREDENTIALS: credentialsPath } }).toString();
  const json = raw.match(/\{[^\n]*\}\s*$/)?.[0];
  if (!json) throw new Error('Não foi possível ler o estado da conta sintética.');
  return JSON.parse(json);
}

async function main() {
  const before = accountState();
  const expectedOnlyRole = before.roles?.length === 1 && before.roles[0] === 'authenticated';
  check('a conta sintética está ativa e tem somente authenticated', before.found && before.active && expectedOnlyRole);
  check('Termos não foram aceitos nem serão submetidos pelo teste', before.found && before.terms === false);
  if (!before.found || !before.active || !expectedOnlyRole || before.terms !== false) return;

  const drushLogin = execFileSync('php', [
    'vendor/drush/drush/drush.php', 'uli', '--no-browser', `--name=${username}`, `--uri=${BASE}`,
  ], { cwd: PROJECT }).toString().trim().split('\n').pop();
  const create = await fetch(`${GD}/session`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ capabilities: { alwaysMatch: {
      browserName: 'firefox', acceptInsecureCerts: true,
      'moz:firefoxOptions': { prefs: { 'network.dns.localDomains': new URL(BASE).hostname } },
    } } }),
  });
  const created = await create.json();
  const sid = created.value?.sessionId;
  if (!sid) throw new Error('Firefox ESR não iniciou pelo geckodriver.');
  const p = (path) => `/session/${sid}${path}`;
  async function wd(method, path, body) {
    const response = await fetch(GD + path, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: body ? JSON.stringify(body) : undefined,
    });
    const value = await response.json();
    if (value.value?.error) throw new Error(value.value.error);
    return value.value;
  }
  const js = (script) => wd('POST', p('/execute/sync'), { script, args: [] });
  const shot = async (name) => {
    const value = await wd('GET', p('/screenshot'));
    writeFileSync(join(OUT, `${name}.png`), Buffer.from(value, 'base64'), { mode: 0o600 });
  };
  try {
    await wd('POST', p('/url'), { url: drushLogin });
    await sleep(1500);
    await wd('POST', p('/url'), { url: new URL('/perfil', BASE).toString() });
    const profileLoaded = await waitFor(() => js(`return !!document.querySelector('main') && !document.body.innerText.includes('Página não encontrada')`));
    check('Perfil abre com a sessão sintética', profileLoaded);
    if (!profileLoaded) return;
    await shot('01-perfil-antes');

    const photoLink = await js(`return document.querySelector('a[href*="/avatar/foto"]')?.href || ''`);
    check('ação de foto/avatar autorizada está disponível', !!photoLink);
    if (!photoLink) return;
    await wd('POST', p('/execute/sync'), {
      script: 'document.querySelector(\'a[href*="/avatar/foto"]\').click(); return true;', args: [],
    });
    const photoDialog = await waitFor(() => js(`return !!document.querySelector('.ui-dialog [data-aculta-user-picture]')`));
    check('formulário original do owner abre no diálogo', photoDialog);
    if (!photoDialog) return;
    const fileInput = await wd('POST', p('/element'), { using: 'css selector', value: '.ui-dialog input[type="file"]' });
    const inputId = fileInput['element-6066-11e4-a52e-4f735466cecf'];
    await wd('POST', p(`/element/${inputId}/value`), { text: imagePath, value: [...imagePath] });

    const cropOpened = await waitFor(() => js(`return !!document.querySelector('.ui-dialog [data-portal-ui="avatar-crop-dialog"]')`), 20000);
    check('upload sintético abre a etapa de corte do owner', cropOpened);
    if (!cropOpened) return;
    await shot('02-recorte-avatar');
    const cropReady = await js(`
      const f = document.querySelector('.ui-dialog [data-portal-ui="avatar-crop-dialog"]');
      return {
        submit: !!f.querySelector('input[type="submit"], button[type="submit"]'),
        humanChallenge: f.innerText.includes('Verificação de segurança'),
        termsDialog: document.body.innerText.includes('Aceite dos termos')
      };
    `);
    check('interface de recorte oferece ação para aplicar', cropReady.submit);
    if (!cropReady.submit) return;
    if (cropReady.humanChallenge || cropReady.termsDialog) {
      if (cropReady.humanChallenge) check('desafio de verificação humana está visível antes da gravação', true);
      if (cropReady.termsDialog) check('diálogo de aceite permanece visível sem aceite gravado', true);
      const reasons = [
        cropReady.humanChallenge ? 'desafio de verificação humana' : '',
        cropReady.termsDialog ? 'diálogo de Termos não aceitos' : '',
      ].filter(Boolean).join(' e ');
      defer('persistência final de avatar e recorte', `${reasons} bloqueia a submissão; o teste não contorna as barreiras nem envia o formulário.`);
    }
    else {
      await wd('POST', p('/execute/sync'), {
        script: 'document.querySelector(\'.ui-dialog [data-portal-ui="avatar-crop-dialog"] input[type="submit"], .ui-dialog [data-portal-ui="avatar-crop-dialog"] button[type="submit"]\').click(); return true;',
        args: [],
      });
      const saved = await waitFor(() => js(`return !document.querySelector('.ui-dialog [data-portal-ui="avatar-crop-dialog"]') && document.body.innerText.includes('Foto do perfil atualizada')`), 20000);
      check('Form API aplica e confirma o recorte no perfil sintético', saved);
      if (saved) await shot('03-perfil-com-avatar-sintetico');
    }
  }
  finally {
    await wd('DELETE', p('')).catch(() => {});
  }

  const after = accountState();
  if (!results.some((result) => result.status === 'DEFERRED')) {
    check('foto personalizada ficou permanente e pertence à mesma conta sintética',
      after.found && after.uid === before.uid && after.fid > 0 && after.fid !== before.fid && after.permanent && after.owner === after.uid);
  }
  else {
    check('avatar persistido permaneceu sem alteração após o bloqueio de acesso', after.found && after.uid === before.uid && after.fid === before.fid);
  }
  check('nenhum aceite de Termos foi gravado', after.found && after.terms === false);
  check('papéis da conta sintética permaneceram inalterados', after.found && JSON.stringify(after.roles) === JSON.stringify(before.roles));
}

try {
  await main();
}
catch (error) {
  check('fluxo de upload e recorte do avatar', false, error.message || 'falha não detalhada');
}
finally {
  chmodSync(OUT, 0o700);
  writeFileSync(join(OUT, 'results.json'), JSON.stringify(results, null, 2), { mode: 0o600 });
}
const deferred = results.filter((result) => result.status === 'DEFERRED').length;
const checked = results.length - deferred;
const failed = results.filter((result) => result.ok === false).length;
console.log(`\n${checked - failed}/${checked} verificações aprovadas; ${deferred} DEFERRED.`);
process.exit(failed ? 1 : 0);
