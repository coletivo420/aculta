// Authenticated X11 smoke for moving an unpublished Forum test topic.
// The unique Node is created and removed through Drupal Entity API in finally.
import { chmodSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { isAbsolute, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const output = process.env.SUITE_OUT;
const display = process.env.DISPLAY;
const driver = process.env.GECKODRIVER || 'http://127.0.0.1:4444';
const hostname = execFileSync('hostname', [], { encoding: 'utf8' }).trim();
const branch = execFileSync('git', ['branch', '--show-current'], { cwd: root, encoding: 'utf8' }).trim();
const drush = resolve(root, 'vendor/drush/drush/drush.php');
if (!output || !isAbsolute(output) || !display || hostname !== 'toca' || branch !== 'docs/aculta-forum-0.1.0-dev.1'
  || resolve(output).startsWith(`${root}/`) || resolve(output) === root) {
  throw new Error('Exija checkout/branch do servidor de teste, DISPLAY X11 e SUITE_OUT privado fora do repositório.');
}

const deploy = JSON.parse(readFileSync(resolve(root, 'web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json'), 'utf8'));
const entries = deploy.domain_transition.entries;
const mainEntry = entries.find((entry) => entry.domain_id === 'aculta_org');
const accountEntry = entries.find((entry) => entry.domain_id === 'conta_aculta_org');
const testHosts = entries.map((entry) => entry.test_host);
if (!mainEntry?.test_host || !accountEntry?.test_host) throw new Error('Hosts MAIN/ACCOUNT de teste não encontrados no Deployer.');
const uri = `https://${mainEntry.test_host}`;
const accountBase = `https://${accountEntry.test_host}`;
const status = execFileSync('php', [drush, 'status', `--uri=${uri}`], { cwd: root, encoding: 'utf8' });
if (!status.includes(`Site URI         : ${uri}`) || !status.includes('DB name          : aculta_runtime')) {
  throw new Error('Drush não confirmou o Runtime aculta_runtime no host MAIN de teste.');
}

process.umask(0o077);
mkdirSync(output, { recursive: true, mode: 0o700 });
chmodSync(output, 0o700);
const evalDrupal = (code) => execFileSync('php', [drush, 'php:eval', code, `--uri=${uri}`], { cwd: root, encoding: 'utf8', timeout: 60000 });
const marked = (stdout, name) => {
  const line = stdout.split('\n').find((item) => item.startsWith(`${name}:`));
  if (!line) throw new Error(`Drush não retornou o marcador ${name}.`);
  return JSON.parse(line.slice(name.length + 1));
};
const countStateCode = `
$manager = \\Drupal::entityTypeManager();
$count = static fn ($storage, string $key, string $value): int => (int) $storage->getQuery()->accessCheck(FALSE)->condition($key, $value)->count()->execute();
$queue = \\Drupal::queue('aculta_messenger_message_subscribe_event')->numberOfItems();
$admin = \\Drupal\\user\\Entity\\User::load(1);
$domain = \\Drupal::service('aculta_portal.domain_purpose')->getDomain('forum');
$categories = $manager->getStorage('taxonomy_term')->getQuery()->accessCheck(FALSE)->condition('vid', 'aculta_forum_categories')->sort('tid')->execute();
$state = [
  'topics' => $count($manager->getStorage('node'), 'type', 'aculta_forum_topic'),
  'legacy_topics' => $count($manager->getStorage('node'), 'type', 'forum'),
  'discussion_comments' => $count($manager->getStorage('comment'), 'comment_type', 'aculta_discussion_post'),
  'legacy_comments' => $count($manager->getStorage('comment'), 'comment_type', 'comment_forum'),
  'category_ids' => array_slice(array_values($categories), 0, 2),
  'category_count' => count($categories),
  'queue' => (int) $queue,
  'move_permission' => $admin instanceof \\Drupal\\user\\UserInterface && $admin->hasPermission('move aculta forum topics'),
  'domain' => $domain?->id(),
];
print 'ACULTA_STATE:' . json_encode($state, JSON_THROW_ON_ERROR) . PHP_EOL;
`;
const baseline = marked(evalDrupal(countStateCode), 'ACULTA_STATE');
if (baseline.topics !== 0 || baseline.legacy_topics !== 0 || baseline.discussion_comments !== 0 || baseline.legacy_comments !== 0
  || baseline.category_ids.length !== 2 || baseline.category_count < 2 || !baseline.move_permission || !baseline.domain) {
  throw new Error(`Precondições de teste/limpeza não satisfeitas: ${JSON.stringify(baseline)}`);
}

const marker = `QA-MOVER-TOPICO-${Date.now()}-${Math.random().toString(16).slice(2, 8)}`;
const markerPhp = JSON.stringify(marker);
const fixtureCode = `
$marker = ${markerPhp};
$manager = \\Drupal::entityTypeManager();
$terms = $manager->getStorage('taxonomy_term')->loadMultiple([${baseline.category_ids.map((id) => JSON.stringify(String(id))).join(', ')}]);
$domain = \\Drupal::service('aculta_portal.domain_purpose')->getDomain('forum');
if (count($terms) !== 2 || $domain === NULL) { throw new \\RuntimeException('As categorias ou o Domain do Fórum mudaram durante o preflight.'); }
$node = $manager->getStorage('node')->create([
  'type' => 'aculta_forum_topic',
  'title' => $marker,
  'uid' => 1,
  'status' => 0,
  'field_domain_source' => ['target_id' => $domain->id()],
  'field_aculta_categories' => ['target_id' => (int) array_key_first($terms)],
  'field_aculta_discussion' => ['status' => 2],
  'field_autor_legado' => [],
]);
$node->setSyncing(TRUE);
$node->save();
$node->setSyncing(FALSE);
print 'ACULTA_FIXTURE:' . json_encode([
  'nid' => (int) $node->id(),
  'uuid' => $node->uuid(),
  'vid' => (int) $node->getRevisionId(),
  'source' => (int) $node->get('field_aculta_categories')->target_id,
  'destination' => (int) array_key_last($terms),
  'title' => $node->label(),
  'uid' => (int) $node->getOwnerId(),
  'status' => (int) $node->isPublished(),
  'domain' => (string) $node->get('field_domain_source')->target_id,
], JSON_THROW_ON_ERROR) . PHP_EOL;
`;
let fixture;

const checks = [];
const check = (name, pass, detail = '') => {
  checks.push({ name, pass: !!pass, detail });
  console.log(`${pass ? 'PASS' : 'FAIL'} ${name}${detail ? ` — ${detail}` : ''}`);
};
const request = async (method, path, body) => {
  const response = await fetch(`${driver}${path}`, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await response.json();
  if (!response.ok || data.value?.error) throw new Error(`${data.value?.error || response.status}: ${data.value?.message || response.statusText}`);
  return data.value;
};
const wait = (ms) => new Promise((resolveWait) => setTimeout(resolveWait, ms));
const pending = new Map();
let bidiId = 0;
let browserSession;
let bidi;
let context;
const bidiCommand = (method, params = {}) => new Promise((resolveCommand, rejectCommand) => {
  const id = ++bidiId;
  pending.set(id, { resolveCommand, rejectCommand });
  bidi.send(JSON.stringify({ id, method, params }));
});
const sessionPath = (suffix) => `/session/${browserSession}${suffix}`;
const execute = (script) => request('POST', sessionPath('/execute/sync'), { script, args: [] });
const go = async (pathname) => {
  await request('POST', sessionPath('/url'), { url: `${uri}${pathname}` });
  await wait(900);
  return execute(`const nav=performance.getEntriesByType('navigation')[0]; const text=document.body?.innerText||''; return {path:location.pathname,status:nav?.responseStatus||null,title:document.title,h1:document.querySelector('h1')?.innerText||'',main:!!document.querySelector('main'),serverError:/Internal Server Error|The website encountered an unexpected error/i.test(text)};`);
};

try {
  fixture = marked(evalDrupal(fixtureCode), 'ACULTA_FIXTURE');
  writeFileSync(resolve(output, 'preflight.json'), JSON.stringify({ hostname, branch, uri, db: 'aculta_runtime', baseline, fixture }, null, 2), { mode: 0o600 });
  const login = execFileSync('php', [drush, 'uli', '--uid=1', '--no-browser', `--uri=${accountBase}`], { cwd: root, stdio: ['ignore', 'pipe', 'pipe'], timeout: 30000 }).toString().trim().split('\n').pop();
  const created = await request('POST', '/session', {
    capabilities: {
      alwaysMatch: {
        browserName: 'firefox',
        acceptInsecureCerts: true,
        webSocketUrl: true,
        'moz:firefoxOptions': { prefs: { 'network.dns.localDomains': testHosts.join(',') } },
      },
    },
  });
  browserSession = created.sessionId;
  bidi = new WebSocket(created.capabilities.webSocketUrl);
  await new Promise((resolveOpen, rejectOpen) => {
    bidi.addEventListener('open', resolveOpen, { once: true });
    bidi.addEventListener('error', rejectOpen, { once: true });
  });
  bidi.addEventListener('message', (event) => {
    const message = JSON.parse(event.data);
    if (message.id && pending.has(message.id)) {
      const item = pending.get(message.id);
      pending.delete(message.id);
      if (message.type === 'error') item.rejectCommand(new Error(`${message.error}: ${message.message}`));
      else item.resolveCommand(message.result);
    }
  });
  context = (await bidiCommand('browsingContext.getTree', { maxDepth: 0 })).contexts[0].context;
  await bidiCommand('browsingContext.setViewport', { context, viewport: { width: 1024, height: 768 }, devicePixelRatio: 1 });
  await request('POST', sessionPath('/url'), { url: login });
  await wait(1800);
  await request('POST', sessionPath('/url'), { url: `${uri}/` });
  await wait(800);
  const signedIn = await execute(`return location.hostname===${JSON.stringify(mainEntry.test_host)} && !!document.querySelector('body.user-logged-in')`);
  check('Administrador autenticado no MAIN de teste', signedIn);
  if (!signedIn) throw new Error('A sessão administrativa de teste não foi confirmada.');

  let info = await go(`/painel-administrativo/forum/${fixture.nid}/mover-categoria`);
  const form = await execute(`const select=document.querySelector('select[name="destination"]'); const checkbox=document.querySelector('input[name="confirm_move"]'); const options=[...(select?.options||[])].map(o=>({value:o.value,label:o.textContent.trim()})); return {select:!!select,checkbox:!!checkbox,options,title:document.body.innerText.includes(${JSON.stringify(fixture.title)}),h1:document.querySelector('h1')?.innerText||''};`);
  check('Formulário de movimento carrega tópico e categorias elegíveis', info.status === 200 && info.main && !info.serverError && info.path.endsWith('/mover-categoria') && form.select && form.checkbox && form.title && form.options.some((option) => Number(option.value) === fixture.destination), JSON.stringify({info, form}));
  writeFileSync(resolve(output, 'topic-move-form.png'), Buffer.from(await request('GET', sessionPath('/screenshot')), 'base64'), { mode: 0o600 });

  const layout390 = await bidiCommand('browsingContext.setViewport', { context, viewport: { width: 390, height: 844 }, devicePixelRatio: 1 });
  const narrow = await execute(`const main=document.querySelector('main'); return {innerWidth,documentWidth:document.documentElement.scrollWidth,mainWidth:main?.clientWidth||0,mainScroll:main?.scrollWidth||0};`);
  check('Formulário de mover tópico cabe em 390 CSS px', narrow.innerWidth === 390 && narrow.documentWidth <= 391 && narrow.mainScroll <= narrow.mainWidth + 1, JSON.stringify(narrow));
  writeFileSync(resolve(output, 'topic-move-form-390.png'), Buffer.from(await request('GET', sessionPath('/screenshot')), 'base64'), { mode: 0o600 });
  await bidiCommand('browsingContext.setViewport', { context, viewport: { width: 1024, height: 768 }, devicePixelRatio: 1 });

  const submit = await execute(`const form=document.querySelector('form'); const select=form?.querySelector('select[name="destination"]'); const confirm=form?.querySelector('input[name="confirm_move"]'); if(!form||!select||!confirm) return false; select.value=${JSON.stringify(String(fixture.destination))}; select.dispatchEvent(new Event('change',{bubbles:true})); confirm.checked=true; confirm.dispatchEvent(new Event('change',{bubbles:true})); form.requestSubmit(); return true;`);
  check('Form API recebe destino selecionado e confirmação explícita', submit);
  await wait(1400);
  info = await execute(`const nav=performance.getEntriesByType('navigation')[0]; const text=document.body?.innerText||''; return {path:location.pathname,status:nav?.responseStatus||null,h1:document.querySelector('h1')?.innerText||'',success:text.includes('O tópico foi movido para'),serverError:/Internal Server Error|The website encountered an unexpected error/i.test(text)};`);
  check('POST conclui movimento e retorna à seção própria', (!info.status || info.status === 200) && info.path === '/painel-administrativo/forum' && info.success && !info.serverError, JSON.stringify(info));
  writeFileSync(resolve(output, 'topic-move-result.png'), Buffer.from(await request('GET', sessionPath('/screenshot')), 'base64'), { mode: 0o600 });

  const saved = marked(evalDrupal(`
$node = \\Drupal::entityTypeManager()->getStorage('node')->load(${fixture.nid});
$state = $node ? [
  'exists' => TRUE, 'nid' => (int) $node->id(), 'uuid' => $node->uuid(), 'vid' => (int) $node->getRevisionId(),
  'category' => (int) $node->get('field_aculta_categories')->target_id, 'title' => $node->label(),
  'uid' => (int) $node->getOwnerId(), 'status' => (int) $node->isPublished(),
  'domain' => (string) $node->get('field_domain_source')->target_id, 'discussion_status' => (int) $node->get('field_aculta_discussion')->status,
] : ['exists' => FALSE];
print 'ACULTA_MOVED:' . json_encode($state, JSON_THROW_ON_ERROR) . PHP_EOL;
`), 'ACULTA_MOVED');
  check('Node conserva identidade, autoria, publicação e discussion; cria nova revisão', saved.exists && saved.nid === fixture.nid && saved.uuid === fixture.uuid && saved.vid > fixture.vid && saved.category === fixture.destination && saved.title === fixture.title && saved.uid === fixture.uid && saved.status === 0 && saved.domain === fixture.domain && saved.discussion_status === 2, JSON.stringify({ fixture, saved }));
  writeFileSync(resolve(output, 'moved-node.json'), JSON.stringify({ fixture, saved }, null, 2), { mode: 0o600 });
}
catch (error) {
  check('Execução da suíte sem erro inesperado', false, error.message);
}
finally {
  if (browserSession) await request('DELETE', `/session/${browserSession}`).catch(() => {});
  if (bidi?.readyState === WebSocket.OPEN) bidi.close();
  try {
    const cleaned = marked(evalDrupal(`
$storage = \\Drupal::entityTypeManager()->getStorage('node');
$ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'aculta_forum_topic')->condition('title', ${markerPhp})->execute();
foreach ($storage->loadMultiple($ids) as $entity) { if ($entity->label() === ${markerPhp}) { $storage->delete([$entity]); } }
print 'ACULTA_CLEANED:' . json_encode(['removed' => !$storage->getQuery()->accessCheck(FALSE)->condition('type', 'aculta_forum_topic')->condition('title', ${markerPhp})->count()->execute()], JSON_THROW_ON_ERROR) . PHP_EOL;
`), 'ACULTA_CLEANED');
    const after = marked(evalDrupal(countStateCode), 'ACULTA_STATE');
    check('Fixture removida; Fórum, categorias e fila restaurados ao baseline', cleaned.removed && after.topics === baseline.topics && after.legacy_topics === baseline.legacy_topics && after.discussion_comments === baseline.discussion_comments && after.legacy_comments === baseline.legacy_comments && after.category_count === baseline.category_count && after.queue === baseline.queue, JSON.stringify({ cleaned, baseline, after }));
    writeFileSync(resolve(output, 'postflight.json'), JSON.stringify({ baseline, after, cleaned }, null, 2), { mode: 0o600 });
  }
  catch (error) {
    check('Cleanup da fixture e restauração do baseline', false, error.message);
  }
  const passed = checks.filter((item) => item.pass).length;
  writeFileSync(resolve(output, 'results.json'), JSON.stringify({ host: mainEntry.test_host, display, checks, passed, total: checks.length }, null, 2), { mode: 0o600 });
  console.log(`${passed}/${checks.length} verificações; evidências em ${output}.`);
}
if (checks.some((item) => !item.pass)) process.exitCode = 1;
