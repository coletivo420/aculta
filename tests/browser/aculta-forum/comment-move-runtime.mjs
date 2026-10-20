// Authenticated X11 smoke for the Forum comment mover on the test Runtime.
// Creates one temporary pending Comment and two public topics, submits the
// Form API confirmation, verifies preserved state, then removes every fixture.
import { chmodSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { isAbsolute, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const output = process.env.SUITE_OUT;
const display = process.env.DISPLAY;
const driver = process.env.GECKODRIVER || 'http://127.0.0.1:4444';
const mutationPermit = process.env.ALLOW_RUNTIME_MUTATION;
const mainHost = execFileSync('hostname', [], { encoding: 'utf8' }).trim();
const branch = execFileSync('git', ['branch', '--show-current'], { cwd: root, encoding: 'utf8' }).trim();
const drush = resolve(root, 'vendor/drush/drush/drush.php');

if (mutationPermit !== 'MOVE-COMMENT-FIXTURE-ON-TEST-RUNTIME' || !output || !isAbsolute(output) || !display) {
  throw new Error('Exija ALLOW_RUNTIME_MUTATION=MOVE-COMMENT-FIXTURE-ON-TEST-RUNTIME, SUITE_OUT absoluto e DISPLAY X11.');
}
if (mainHost !== 'toca' || branch !== 'docs/aculta-forum-0.1.0-dev.1' || resolve(output).startsWith(`${root}/`) || resolve(output) === root) {
  throw new Error('O roteiro só pode rodar no checkout esperado do servidor de teste e salvar evidências fora dele.');
}

const deploy = JSON.parse(readFileSync(resolve(root, 'web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json'), 'utf8'));
const entries = deploy.domain_transition.entries;
const mainEntry = entries.find((entry) => entry.domain_id === 'aculta_org');
const accountEntry = entries.find((entry) => entry.domain_id === 'conta_aculta_org');
const testHosts = entries.map((entry) => entry.test_host);
if (!mainEntry?.test_host || !accountEntry?.test_host) {
  throw new Error('Os hosts MAIN/ACCOUNT não correspondem ao mapa de teste do Deployer.');
}
const uri = `https://${mainEntry.test_host}`;
const accountBase = `https://${accountEntry.test_host}`;
const drushStatus = execFileSync('php', [drush, 'status', `--uri=${uri}`], { cwd: root, encoding: 'utf8' });
if (!drushStatus.includes('Site URI         : https://aculta.toca.net.br') || !drushStatus.includes('DB name          : aculta_runtime')) {
  throw new Error('Drush não confirmou o Runtime aculta_runtime no URI de teste esperado.');
}

process.umask(0o077);
mkdirSync(output, { recursive: true, mode: 0o700 });
chmodSync(output, 0o700);

const evalDrupal = (code) => execFileSync('php', [drush, 'php:eval', code, `--uri=${uri}`], { cwd: root, encoding: 'utf8', timeout: 60000 });
const parseMarker = (stdout, marker) => {
  const line = stdout.split('\n').find((item) => item.startsWith(`${marker}:`));
  if (!line) throw new Error(`Drush não retornou o marcador ${marker}.`);
  return JSON.parse(line.slice(marker.length + 1));
};
const countsCode = `
$node = \\Drupal::entityTypeManager()->getStorage('node');
$comment = \\Drupal::entityTypeManager()->getStorage('comment');
$count = static function ($storage, string $key, string $value): int { return (int) $storage->getQuery()->accessCheck(FALSE)->condition($key, $value)->count()->execute(); };
$terms = \\Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()->accessCheck(FALSE)->condition('vid', 'aculta_forum_categories')->count()->execute();
$config_storage = \\Drupal::service('config.storage');
$user = \\Drupal\\user\\Entity\\User::load(1);
$notice = \\Drupal::entityTypeManager()->getStorage('aculta_aviso')->getQuery()->accessCheck(FALSE)->count()->execute();
$domain = \\Drupal::service('aculta_portal.domain_purpose')->getDomain('forum');
$state = [
  'forum_topics' => $count($node, 'type', 'aculta_forum_topic'),
  'legacy_forum_topics' => $count($node, 'type', 'forum'),
  'discussion_posts' => $count($comment, 'comment_type', 'aculta_discussion_post'),
  'legacy_forum_comments' => $count($comment, 'comment_type', 'comment_forum'),
  'all_shared_field_comments' => $count($comment, 'field_name', 'field_aculta_discussion'),
  'forum_categories' => (int) $terms,
  'notices' => (int) $notice,
  'subscribe_queue' => (int) \\Drupal::queue('aculta_messenger_message_subscribe_event')->numberOfItems(),
  'forum_settings_exist' => $config_storage->exists('aculta_forum.settings'),
  'forum_node_legacy_field' => $config_storage->exists('field.field.node.aculta_forum_topic.field_autor_legado'),
  'forum_comment_legacy_field' => $config_storage->exists('field.field.comment.aculta_discussion_post.field_autor_legado'),
  'move_permission_uid1' => $user instanceof \\Drupal\\user\\UserInterface && $user->hasPermission('move aculta forum comments'),
  'forum_domain' => $domain?->id(),
];
print 'ACULTA_STATE:' . json_encode($state, JSON_THROW_ON_ERROR) . PHP_EOL;
`;
const baseline = parseMarker(evalDrupal(countsCode), 'ACULTA_STATE');
writeFileSync(resolve(output, 'preflight.json'), JSON.stringify({ host: mainHost, branch, uri, db: 'aculta_runtime', baseline }, null, 2), { mode: 0o600 });
if (baseline.forum_topics !== 0 || baseline.legacy_forum_topics !== 0 || baseline.discussion_posts !== 0 || baseline.legacy_forum_comments !== 0 || baseline.all_shared_field_comments !== 0 || baseline.forum_settings_exist || !baseline.forum_node_legacy_field || !baseline.forum_comment_legacy_field || !baseline.move_permission_uid1 || !baseline.forum_domain || baseline.forum_categories < 1 || baseline.subscribe_queue !== 0) {
  throw new Error(`Precondições de isolamento/recuperação não satisfeitas: ${JSON.stringify(baseline)}`);
}

const marker = `QAM77-${Math.random().toString(16).slice(2, 8)}`;
const fixtureCode = `
$config_storage = \\Drupal::service('config.storage');
if ($config_storage->exists('aculta_forum.settings')) { throw new \\RuntimeException('O config do mover apareceu durante o preflight; nenhuma alteração foi feita.'); }
$configuration = \\Drupal::configFactory()->getEditable('aculta_forum.settings');
$configuration->set('comment_move_enabled', TRUE)->save();
$nodes = [];
$comments = [];
$cleanup = static function () use (&$comments, &$nodes, $config_storage): void {
  $comment_storage = \\Drupal::entityTypeManager()->getStorage('comment');
  foreach (array_reverse($comments) as $cid) { try { $entity = $comment_storage->load($cid); if ($entity) { $comment_storage->delete([$entity]); } } catch (\\Throwable $exception) { print 'ACULTA_CLEANUP_ERROR:comment:' . $exception->getMessage() . PHP_EOL; } }
  $node_storage = \\Drupal::entityTypeManager()->getStorage('node');
  foreach ($nodes as $nid) { try { $entity = $node_storage->load($nid); if ($entity) { $node_storage->delete([$entity]); } } catch (\\Throwable $exception) { print 'ACULTA_CLEANUP_ERROR:node:' . $exception->getMessage() . PHP_EOL; } }
  if ($config_storage->exists('aculta_forum.settings')) { \\Drupal::configFactory()->getEditable('aculta_forum.settings')->delete(); }
};
try {
  $term_ids = \\Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()->accessCheck(FALSE)->condition('vid', 'aculta_forum_categories')->sort('tid')->range(0, 1)->execute();
  $term_id = (int) reset($term_ids);
  $domain = \\Drupal::service('aculta_portal.domain_purpose')->getDomain('forum');
  if ($term_id <= 0 || $domain === NULL) { throw new \\RuntimeException('Categoria ou Domain canônico do Fórum ausente.'); }
  $node_storage = \\Drupal::entityTypeManager()->getStorage('node');
  foreach (['origem', 'destino'] as $label) {
    $node = $node_storage->create([
      'type' => 'aculta_forum_topic',
      'title' => '${marker} ' . $label,
      'uid' => 0,
      'status' => 1,
      'field_aculta_categories' => ['target_id' => $term_id],
      'field_aculta_discussion' => ['status' => 2],
    ]);
    // A test topic must not enqueue public publication notices or award badges.
    $node->setSyncing(TRUE);
    $node->save();
    $node->setSyncing(FALSE);
    $nodes[] = (int) $node->id();
    if ((string) $node->get('field_domain_source')->target_id !== (string) $domain->id() || !$node->hasField('field_autor_legado') || !$node->get('field_autor_legado')->isEmpty()) {
      throw new \\RuntimeException('Tópico temporário não respeitou o Domain público ou o campo histórico vazio.');
    }
  }
  $comment = \\Drupal::entityTypeManager()->getStorage('comment')->create([
    'entity_type' => 'node',
    'entity_id' => $nodes[0],
    'field_name' => 'field_aculta_discussion',
    'comment_type' => 'aculta_discussion_post',
    'uid' => 1,
    'status' => 0,
    'subject' => '${marker} comentário pendente',
    'comment_body' => ['value' => '${marker} conteúdo QA', 'format' => 'plain_text'],
  ]);
  $comment->save();
  $comments[] = (int) $comment->id();
  $reply_specs = [
    ['parent_index' => 0, 'label' => 'resposta ramo A', 'rejected' => TRUE],
    ['parent_index' => 1, 'label' => 'resposta aninhada ramo A', 'rejected' => FALSE],
    ['parent_index' => 0, 'label' => 'resposta ramo B', 'rejected' => FALSE],
  ];
  $followings = \\Drupal::entityTypeManager()->getStorage('flagging')->getQuery()
    ->accessCheck(FALSE)
    ->condition('flag_id', 'seguir_topico')
    ->condition('entity_type', 'node')
    ->condition('entity_id', $nodes, 'IN')
    ->count()
    ->execute();
  if ((int) $followings !== 0) { throw new \\RuntimeException('Tópicos de fixture não podem ter seguidores.'); }
  foreach ($reply_specs as $spec) {
    $reply = \\Drupal::entityTypeManager()->getStorage('comment')->create([
      'entity_type' => 'node',
      'entity_id' => $nodes[0],
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
      'uid' => 5,
      'pid' => $comments[$spec['parent_index']],
      'status' => 0,
      'subject' => '${marker} ' . $spec['label'],
      'comment_body' => ['value' => '${marker} conteúdo QA ' . $spec['label'], 'format' => 'plain_text'],
    ]);
    if (!$reply->hasField('field_aculta_rejected')) { throw new \\RuntimeException('Campo de moderação rejeitada ausente na fixture.'); }
    $reply->set('field_aculta_rejected', $spec['rejected']);
    $reply->save();
    $comments[] = (int) $reply->id();
  }
  print 'ACULTA_FIXTURE:' . json_encode(['marker' => '${marker}', 'source_nid' => $nodes[0], 'destination_nid' => $nodes[1], 'cids' => $comments], JSON_THROW_ON_ERROR) . PHP_EOL;
}
catch (\\Throwable $exception) {
  try { $cleanup(); } catch (\\Throwable $cleanup_exception) { print 'ACULTA_CLEANUP_ERROR:' . $cleanup_exception->getMessage() . PHP_EOL; }
  throw $exception;
}
`;

let fixture;
let browserSession;
let bidi;
let context;
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
const pendingBidi = new Map();
let bidiId = 0;
const bidiCommand = (method, params = {}) => new Promise((resolveCommand, rejectCommand) => {
  const id = ++bidiId;
  pendingBidi.set(id, { resolveCommand, rejectCommand });
  bidi.send(JSON.stringify({ id, method, params }));
});
const path = (suffix) => `/session/${browserSession}${suffix}`;
const execute = (script) => request('POST', path('/execute/sync'), { script, args: [] });

try {
  fixture = parseMarker(execFileSync('php', [drush, 'php:eval', fixtureCode, `--uri=${uri}`], { cwd: root, encoding: 'utf8', timeout: 60000 }), 'ACULTA_FIXTURE');
  execFileSync('php', [drush, 'cr', `--uri=${uri}`], { cwd: root, stdio: 'ignore', timeout: 60000 });

  // The one-time link is kept in memory and generated for ACCOUNT before MAIN.
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
    if (message.id && pendingBidi.has(message.id)) {
      const pending = pendingBidi.get(message.id);
      pendingBidi.delete(message.id);
      if (message.type === 'error') pending.rejectCommand(new Error(`${message.error}: ${message.message}`));
      else pending.resolveCommand(message.result);
    }
  });
  context = (await bidiCommand('browsingContext.getTree', { maxDepth: 0 })).contexts[0].context;
  await bidiCommand('browsingContext.setViewport', { context, viewport: { width: 1024, height: 768 }, devicePixelRatio: 1 });
  await request('POST', path('/url'), { url: login });
  await wait(1600);
  await request('POST', path('/url'), { url: `${uri}/` });
  await wait(700);
  const signedIn = await execute(`return location.hostname===${JSON.stringify(mainEntry.test_host)} && !!document.querySelector('body.user-logged-in')`);
  check('uid 1 autenticado no host MAIN de teste', signedIn);
  if (!signedIn) throw new Error('A sessão administrativa não foi confirmada no MAIN de teste.');

  const rootCid = fixture.cids[0];
  const route = `/painel-administrativo/conteudo/forum/comentario/${rootCid}/mover`;
  for (const width of [390, 768]) {
    await bidiCommand('browsingContext.setViewport', {
      context,
      viewport: { width, height: width === 390 ? 844 : 1024 },
      devicePixelRatio: 1,
    });
    await request('POST', path('/url'), { url: `${uri}${route}` });
    await wait(800);
    const responsive = await execute(`const main=document.querySelector('main'); return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,mainWidth:main?.clientWidth||0,mainScroll:main?.scrollWidth||0,heading:main?.querySelector('h1')?.innerText||'',destination:!!document.querySelector('select[name="destination"]'),confirm:!!document.querySelector('input[name="confirm_move"]')};`);
    check(`Formulário de mover comentário renderiza em ${width}px`, responsive.width === width && responsive.heading === 'Mover comentário do fórum' && responsive.destination && responsive.confirm);
    check(`Formulário não amplia horizontalmente em ${width}px`, responsive.documentWidth <= width + 1 && responsive.mainScroll <= responsive.mainWidth + 1, JSON.stringify(responsive));
    writeFileSync(resolve(output, `forum-comment-move-form-${width}.png`), Buffer.from(await request('GET', path('/screenshot')), 'base64'), { mode: 0o600 });
  }
  await bidiCommand('browsingContext.setViewport', { context, viewport: { width: 1024, height: 768 }, devicePixelRatio: 1 });
  await request('POST', path('/url'), { url: `${uri}${route}` });
  await wait(900);
  const before = await execute(`const text=document.body.innerText; const select=document.querySelector('select[name="destination"]'); const check=document.querySelector('input[name="confirm_move"]'); return {path:location.pathname,title:document.title,heading:document.querySelector('h1')?.innerText||'',hasMarker:text.includes(${JSON.stringify(fixture.marker)}),subtreeCount:text.includes('serão movidos 4 comentários, incluindo'),wrongCount:/serão movidos [123] comentários/.test(text),destination:!!select&&[...select.options].some(o=>o.value===${JSON.stringify(String(fixture.destination_nid))}),confirm:!!check,csrf:!!document.querySelector('input[name="form_token"]'),bodyError:/Internal Server Error|The website encountered an unexpected error/i.test(text)};`);
  check('Formulário informa os quatro Comments da árvore ramificada, destino e CSRF', before.path === route && before.hasMarker && before.subtreeCount && !before.wrongCount && before.destination && before.confirm && before.csrf && !before.bodyError, JSON.stringify(before));
  writeFileSync(resolve(output, 'forum-comment-move-form.png'), Buffer.from(await request('GET', path('/screenshot')), 'base64'), { mode: 0o600 });
  if (!(before.path === route && before.destination && before.confirm && before.csrf && !before.bodyError)) throw new Error('Formulário não ficou pronto para envio.');

  const submitted = await execute(`const form=document.querySelector('select[name="destination"]')?.form; const select=document.querySelector('select[name="destination"]'); const confirm=document.querySelector('input[name="confirm_move"]'); const button=form?.querySelector('input[type="submit"],button[type="submit"]'); if(!form||!select||!confirm||!button) return false; select.value=${JSON.stringify(String(fixture.destination_nid))}; select.dispatchEvent(new Event('change',{bubbles:true})); confirm.checked=true; confirm.dispatchEvent(new Event('change',{bubbles:true})); form.requestSubmit(button); return true;`);
  check('Form API recebeu destino e confirmação explícita', submitted);
  if (!submitted) throw new Error('Não foi possível enviar a confirmação pela Form API.');
  await wait(1600);
  const afterSubmit = await execute(`const text=document.body.innerText; const nav=performance.getEntriesByType('navigation')[0]; return {path:location.pathname,title:document.title,status:nav?.responseStatus||null,success:text.includes('4 comentários foram movidos.'),serverError:/Internal Server Error|The website encountered an unexpected error/i.test(text)};`);
  check('Submissão CSRF movimentou a árvore pela interface', afterSubmit.success && !afterSubmit.serverError, JSON.stringify(afterSubmit));
  writeFileSync(resolve(output, 'forum-comment-move-result.png'), Buffer.from(await request('GET', path('/screenshot')), 'base64'), { mode: 0o600 });

  const verifyCode = `
$comment_storage = \\Drupal::entityTypeManager()->getStorage('comment');
$loaded = $comment_storage->loadMultiple([${fixture.cids.join(',')}]);
$source = \\Drupal::entityTypeManager()->getStorage('node')->load(${fixture.source_nid});
$destination = \\Drupal::entityTypeManager()->getStorage('node')->load(${fixture.destination_nid});
$count_on = static function (int $nid): int { return (int) \\Drupal::entityTypeManager()->getStorage('comment')->getQuery()->accessCheck(FALSE)->condition('entity_type', 'node')->condition('entity_id', $nid)->condition('field_name', 'field_aculta_discussion')->count()->execute(); };
$rows = [];
foreach ([${fixture.cids.join(',')}] as $cid) { $item = $loaded[$cid] ?? NULL; $rows[] = ['cid' => (int) ($item?->id() ?? 0), 'entity_id' => (int) ($item?->getCommentedEntityId() ?? 0), 'uid' => (int) ($item?->getOwnerId() ?? 0), 'status' => $item?->isPublished(), 'rejected' => (bool) ($item?->get('field_aculta_rejected')->value ?? FALSE), 'subject' => $item?->getSubject(), 'body' => $item?->get('comment_body')->value, 'pid' => (int) ($item?->get('pid')->target_id ?? 0), 'thread' => (string) ($item?->getThread() ?? '')]; }
$state = ['comments' => $rows, 'source_exists' => $source instanceof \\Drupal\\node\\NodeInterface, 'destination_exists' => $destination instanceof \\Drupal\\node\\NodeInterface, 'source_count' => $count_on(${fixture.source_nid}), 'destination_count' => $count_on(${fixture.destination_nid})];
print 'ACULTA_MOVE_RESULT:' . json_encode($state, JSON_THROW_ON_ERROR) . PHP_EOL;
`;
  const moved = parseMarker(evalDrupal(verifyCode), 'ACULTA_MOVE_RESULT');
  const expected = [
    { uid: 1, subject: `${fixture.marker} comentário pendente`, body: `${fixture.marker} conteúdo QA`, pid: 0, status: false, rejected: false },
    { uid: 5, subject: `${fixture.marker} resposta ramo A`, body: `${fixture.marker} conteúdo QA resposta ramo A`, pid: fixture.cids[0], status: false, rejected: true },
    { uid: 5, subject: `${fixture.marker} resposta aninhada ramo A`, body: `${fixture.marker} conteúdo QA resposta aninhada ramo A`, pid: fixture.cids[1], status: false, rejected: false },
    { uid: 5, subject: `${fixture.marker} resposta ramo B`, body: `${fixture.marker} conteúdo QA resposta ramo B`, pid: fixture.cids[0], status: false, rejected: false },
  ];
  const movedCorrectly = moved.source_exists && moved.destination_exists
    && moved.comments.length === expected.length
    && moved.comments.every((item, index) => Number(item.cid) === fixture.cids[index]
      && Number(item.entity_id) === fixture.destination_nid
      && Number(item.uid) === expected[index].uid
      && item.status === expected[index].status
      && item.rejected === expected[index].rejected
      && item.subject === expected[index].subject
      && item.body === expected[index].body
      && Number(item.pid) === expected[index].pid
      && item.thread !== '')
    && moved.comments.slice(1).every((item) => item.thread.startsWith(moved.comments[0].thread.replace(/\/$/, '') + '.'))
    && moved.source_count === 0
    && moved.destination_count === expected.length;
  check('Raiz ramificada preservou CIDs, autoria, conteúdo, pendência/rejeição, relações pid/thread e destino', movedCorrectly, JSON.stringify(moved));
}
finally {
  if (browserSession) await request('DELETE', `/session/${browserSession}`).catch(() => {});
  if (bidi?.readyState === WebSocket.OPEN) bidi.close();
  if (fixture) {
    // Remove comments before hosts. Unrelated config changes are preserved.
    const safeCleanup = `
$config_storage = \\Drupal::service('config.storage');
$errors = [];
try { $comment_storage = \\Drupal::entityTypeManager()->getStorage('comment'); foreach (array_reverse([${fixture.cids.join(',')}]) as $cid) { $comment = $comment_storage->load($cid); if ($comment) { $comment_storage->delete([$comment]); } } } catch (\\Throwable $e) { $errors[] = 'comments:' . $e->getMessage(); }
try { $node_storage = \\Drupal::entityTypeManager()->getStorage('node'); foreach ([${fixture.source_nid}, ${fixture.destination_nid}] as $nid) { $node = $node_storage->load($nid); if ($node) { $node_storage->delete([$node]); } } } catch (\\Throwable $e) { $errors[] = 'nodes:' . $e->getMessage(); }
$active = \\Drupal::config('aculta_forum.settings')->get();
if ($active === ['comment_move_enabled' => TRUE]) { \\Drupal::configFactory()->getEditable('aculta_forum.settings')->delete(); }
elseif ($config_storage->exists('aculta_forum.settings')) { $errors[] = 'config_changed_concurrently_preserved'; }
print 'ACULTA_CLEANUP:' . json_encode(['errors' => $errors, 'config_exists' => $config_storage->exists('aculta_forum.settings')], JSON_THROW_ON_ERROR) . PHP_EOL;
`;
    try {
      const outputText = evalDrupal(safeCleanup);
      const cleanup = parseMarker(outputText, 'ACULTA_CLEANUP');
      if (cleanup.errors.length > 0 || cleanup.config_exists) throw new Error(JSON.stringify(cleanup));
      execFileSync('php', [drush, 'cr', `--uri=${uri}`], { cwd: root, stdio: 'ignore', timeout: 60000 });
    }
    catch (error) {
      console.error(`CLEANUP REQUIRED; fixture CIDs/NIDs ${fixture.cids.join(',')}/${fixture.source_nid}/${fixture.destination_nid}: ${error.message}`);
      checks.push({ name: 'limpeza e restauração do estado', pass: false, detail: error.message });
    }
  }
  let finalState;
  try {
    finalState = parseMarker(evalDrupal(countsCode), 'ACULTA_STATE');
    const restored = finalState.forum_topics === baseline.forum_topics
      && finalState.legacy_forum_topics === baseline.legacy_forum_topics
      && finalState.discussion_posts === baseline.discussion_posts
      && finalState.legacy_forum_comments === baseline.legacy_forum_comments
      && finalState.all_shared_field_comments === baseline.all_shared_field_comments
      && finalState.forum_categories === baseline.forum_categories
      && finalState.notices === baseline.notices
      && finalState.subscribe_queue === baseline.subscribe_queue
      && finalState.forum_node_legacy_field === baseline.forum_node_legacy_field
      && finalState.forum_comment_legacy_field === baseline.forum_comment_legacy_field
      && !finalState.forum_settings_exist;
    check('Runtime restaurado: fórum vazio, avisos e categorias preservados, config ausente', restored, JSON.stringify(finalState));
  }
  catch (error) {
    check('Runtime restaurado e recontado via Entity API', false, error.message);
  }
  writeFileSync(resolve(output, 'results.json'), JSON.stringify({ host: mainEntry.test_host, display, fixture, baseline, finalState: finalState || null, checks, passed: checks.filter((item) => item.pass).length, total: checks.length }, null, 2), { mode: 0o600 });
  console.log(`${checks.filter((item) => item.pass).length}/${checks.length} verificações de movimentação passaram; evidências em ${output}.`);
}
if (checks.some((item) => !item.pass)) process.exitCode = 1;
