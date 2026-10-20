// Authenticated X11 smoke for the Forum's Views Bulk Operations topic mover.
// Creates two unpublished fixture Nodes and removes only those exact UUIDs.
import { chmodSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { isAbsolute, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const output = process.env.SUITE_OUT;
const display = process.env.DISPLAY;
const driver = process.env.GECKODRIVER || 'http://127.0.0.1:4460';
const hostname = execFileSync('hostname', [], { encoding: 'utf8' }).trim();
const branch = execFileSync('git', ['branch', '--show-current'], { cwd: root, encoding: 'utf8' }).trim();
const drush = resolve(root, 'vendor/drush/drush/drush.php');
if (!output || !isAbsolute(output) || !display || hostname !== 'toca'
  || branch !== 'docs/aculta-forum-0.1.0-dev.1'
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
const marker = `QA-VBO-BULK-${Date.now()}-${Math.random().toString(16).slice(2, 8)}`;
const markerPhp = JSON.stringify(marker);
const stateCode = `
$manager = \\Drupal::entityTypeManager();
$count = static fn ($storage, string $key, string $value): int => (int) $storage->getQuery()->accessCheck(FALSE)->condition($key, $value)->count()->execute();
$categories = $manager->getStorage('taxonomy_term')->getQuery()->accessCheck(FALSE)->condition('vid', 'aculta_forum_categories')->sort('tid')->execute();
$view = \\Drupal::config('views.view.aculta_forum_admin_topics')->getRawData();
$modules = \\Drupal::config('core.extension')->get('module');
$selection = \\Drupal::service('tempstore.private')->get('views_bulk_operations_aculta_forum_admin_topics_page_forum')->get('1');
$selected = is_array($selection['list'] ?? NULL) ? $selection['list'] : [];
$selected_ids = [];
foreach (array_keys($selected) as $key) {
  $decoded = json_decode((string) base64_decode((string) $key, TRUE), TRUE);
  $selected_ids[] = is_array($decoded) ? ($decoded[0] ?? NULL) : NULL;
}
$admin = $manager->getStorage('user')->load(1);
$domain = \\Drupal::service('aculta_portal.domain_purpose')->getDomain('forum');
$state = [
  'topics' => $count($manager->getStorage('node'), 'type', 'aculta_forum_topic'),
  'legacy_topics' => $count($manager->getStorage('node'), 'type', 'forum'),
  'discussion_comments' => $count($manager->getStorage('comment'), 'comment_type', 'aculta_discussion_post'),
  'legacy_comments' => $count($manager->getStorage('comment'), 'comment_type', 'comment_forum'),
  'category_ids' => array_values($categories),
  'category_count' => count($categories),
  'queue' => (int) \\Drupal::queue('aculta_messenger_message_subscribe_event')->numberOfItems(),
  'vbo_enabled' => isset($modules['views_bulk_operations']),
  'vbo_action' => array_filter($view['display']['default']['display_options']['fields']['views_bulk_operations_bulk_form']['selected_actions'] ?? [], static fn (array $action): bool => ($action['action_id'] ?? '') === 'aculta_forum_move_topics') !== [],
  'selection_count' => count($selected),
  'selection_ids' => $selected_ids,
  'exclude_mode' => $selection['exclude_mode'] ?? FALSE,
  'move_permission' => $admin instanceof \\Drupal\\user\\UserInterface && $admin->hasPermission('move aculta forum topics'),
  'domain' => $domain?->id(),
];
print 'ACULTA_STATE:' . json_encode($state, JSON_THROW_ON_ERROR) . PHP_EOL;
`;
const baseline = marked(evalDrupal(stateCode), 'ACULTA_STATE');
if (baseline.topics !== 0 || baseline.legacy_topics !== 0 || baseline.discussion_comments !== 0 || baseline.legacy_comments !== 0
  || baseline.category_ids.length < 2 || baseline.queue !== 0 || baseline.selection_count !== 0
  || !baseline.vbo_enabled || !baseline.vbo_action || !baseline.move_permission || !baseline.domain) {
  throw new Error(`Pré-condições de Runtime/limpeza não satisfeitas: ${JSON.stringify(baseline)}`);
}

const fixtureCode = `
$marker = ${markerPhp};
$manager = \\Drupal::entityTypeManager();
$categories = $manager->getStorage('taxonomy_term')->loadMultiple([${JSON.stringify(String(baseline.category_ids[0]))}, ${JSON.stringify(String(baseline.category_ids[1]))}]);
$domain = \\Drupal::service('aculta_portal.domain_purpose')->getDomain('forum');
if (count($categories) !== 2 || $domain === NULL) { throw new \\RuntimeException('Categorias ou Domain mudaram durante o preflight.'); }
$out = [];
foreach ([1, 2] as $index) {
  $node = $manager->getStorage('node')->create([
    'type' => 'aculta_forum_topic',
    'title' => $marker . '-' . $index,
    'uid' => 1,
    'status' => 0,
    'field_domain_source' => ['target_id' => $domain->id()],
    'field_aculta_categories' => ['target_id' => (int) array_key_first($categories)],
    'field_aculta_discussion' => ['status' => 2],
    'field_autor_legado' => [],
  ]);
  $node->setSyncing(TRUE);
  $node->save();
  $node->setSyncing(FALSE);
  $out[] = [
    'nid' => (int) $node->id(),
    'uuid' => $node->uuid(),
    'revision' => (int) $node->getRevisionId(),
    'source' => (int) $node->get('field_aculta_categories')->target_id,
    'destination' => (int) array_key_last($categories),
    'title' => $node->label(),
    'uid' => (int) $node->getOwnerId(),
    'status' => (int) $node->isPublished(),
    'domain' => (string) $node->get('field_domain_source')->target_id,
    'discussion_status' => (int) $node->get('field_aculta_discussion')->status,
  ];
}
print 'ACULTA_FIXTURES:' . json_encode($out, JSON_THROW_ON_ERROR) . PHP_EOL;
`;
let fixtures = [];
let browserSession;
let bidi;
let browserContext;
let selectionStarted = false;
let actionExecuted = false;
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
const sessionPath = (suffix) => `/session/${browserSession}${suffix}`;
const execute = (script) => request('POST', sessionPath('/execute/sync'), { script, args: [] });
const go = async (pathname) => {
  await request('POST', sessionPath('/url'), { url: `${uri}${pathname}` });
  await wait(900);
  return execute(`const nav=performance.getEntriesByType('navigation')[0]; const text=document.body?.innerText||''; return {path:location.pathname,status:nav?.responseStatus||null,title:document.title,h1:document.querySelector('h1')?.innerText||'',main:!!document.querySelector('main'),serverError:/Internal Server Error|The website encountered an unexpected error/i.test(text)};`);
};
const waitFor = async (script, predicate, description, timeout = 20000) => {
  const until = Date.now() + timeout;
  let last = null;
  while (Date.now() < until) {
    try {
      last = await execute(script);
      if (predicate(last)) return last;
    }
    catch (error) {
      last = { error: error.message };
    }
    await wait(300);
  }
  throw new Error(`Timeout aguardando ${description}: ${JSON.stringify(last)}`);
};
const screenshot = async (name) => {
  writeFileSync(resolve(output, name), Buffer.from(await request('GET', sessionPath('/screenshot')), 'base64'), { mode: 0o600 });
};

try {
  fixtures = marked(evalDrupal(fixtureCode), 'ACULTA_FIXTURES');
  if (fixtures.length !== 2 || fixtures.some((fixture) => fixture.status !== 0 || fixture.source === fixture.destination)) {
    throw new Error(`Fixtures não satisfazem o escopo não publicado e categorias distintas: ${JSON.stringify(fixtures)}`);
  }
  writeFileSync(resolve(output, 'preflight.json'), JSON.stringify({ hostname, branch, uri, db: 'aculta_runtime', baseline, fixtures }, null, 2), { mode: 0o600 });

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
  const pending = new Map();
  let bidiId = 0;
  const bidiCommand = (method, params = {}) => new Promise((resolveCommand, rejectCommand) => {
    const id = ++bidiId;
    pending.set(id, { resolveCommand, rejectCommand });
    bidi.send(JSON.stringify({ id, method, params }));
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
  browserContext = (await bidiCommand('browsingContext.getTree', { maxDepth: 0 })).contexts[0].context;
  await bidiCommand('browsingContext.setViewport', { context: browserContext, viewport: { width: 1280, height: 900 }, devicePixelRatio: 1 });
  await request('POST', sessionPath('/url'), { url: login });
  await wait(1600);

  let page = await go('/painel-administrativo/forum/conteudo');
  const initial = await execute(`const text=document.querySelector('main')?.innerText||''; const form=document.querySelector('#views-form-aculta-forum-admin-topics-page-forum'); return {loggedIn:document.body?.classList.contains('user-logged-in')||false,rows:[...document.querySelectorAll('#views-form-aculta-forum-admin-topics-page-forum tr')].map(row=>row.innerText).filter(row=>row.includes(${JSON.stringify(marker)})),action:form?.querySelector('select[name="action"]')?.options.length||0,checkboxes:form?.querySelectorAll('input.js-vbo-checkbox').length||0,text};`);
  check('Usuário administrativo autenticado acessa a View VBO com as duas fixtures', page.status === 200 && initial.loggedIn && initial.rows.length === 2 && initial.action >= 2 && initial.checkboxes === 2 && !page.serverError, JSON.stringify({ page, initial }));
  await screenshot('forum-topic-move-bulk-view.png');

  for (let index = 0; index < fixtures.length; index++) {
    const selected = await execute(`const form=document.querySelector('#views-form-aculta-forum-admin-topics-page-forum'); const boxes=[...(form?.querySelectorAll('input.js-vbo-checkbox')||[])]; const ids=${JSON.stringify(fixtures.map((fixture) => fixture.nid))}; const box=boxes.find(item=>ids.some(id=>atob(item.value).includes('"'+id+'"')) && !item.checked); if(!box)return {clicked:false,checked:boxes.filter(item=>item.checked).length}; box.click(); return {clicked:true,checked:boxes.filter(item=>item.checked).length};`);
    check(`Selecionar tópico ${index + 1} na interface`, selected.clicked && selected.checked === index + 1, JSON.stringify(selected));
    selectionStarted = true;
    const expectedCount = index + 1;
    await wait(900);
    const summary = await waitFor(
      `const summary=document.querySelector('.vbo-multipage-selector summary'); const selected=[...document.querySelectorAll('input.js-vbo-checkbox:checked')].map(item=>{try{return JSON.parse(atob(item.value))[0]}catch{return null}}); return {summary:summary?.innerText.trim()||'',selected,expected:${JSON.stringify(fixtures.map((fixture) => fixture.nid))}};`,
      (result) => result.selected.length === expectedCount
        && result.selected.every((nid) => result.expected.slice(0, expectedCount).includes(Number(nid)))
        && result.summary.includes(String(expectedCount)),
      `VBO confirmar ${expectedCount} item(ns) pela resposta AJAX`,
    );
    check(`VBO registra ${expectedCount} tópico(s) exato(s) selecionado(s)`, summary.selected.length === expectedCount, JSON.stringify(summary));
  }

  const action = await execute(`const form=document.querySelector('#views-form-aculta-forum-admin-topics-page-forum'); const select=form?.querySelector('select[name="action"]'); const button=form?.querySelector('button[data-vbo="vbo-action"],input[data-vbo="vbo-action"]'); if(!select||!button)return {ready:false}; select.value='0'; select.dispatchEvent(new Event('change',{bubbles:true})); return {ready:true,disabled:button.disabled,label:(button.innerText||button.value||'').trim()};`);
  check('Ação Mover tópicos habilitada para a seleção', action.ready && !action.disabled, JSON.stringify(action));
  await execute(`const form=document.querySelector('#views-form-aculta-forum-admin-topics-page-forum'); form?.querySelector('button[data-vbo="vbo-action"],input[data-vbo="vbo-action"]')?.click(); return true;`);
  page = await waitFor(
    `return {path:location.pathname,title:document.title,text:(document.querySelector('main')?.innerText||'').slice(-1800),destination:!!document.querySelector('select[name="destination_category"]'),markers:[...document.querySelectorAll('main li,main tr')].filter(item=>item.innerText.includes(${JSON.stringify(marker)})).length};`,
    (result) => result.destination || result.path.includes('/views-bulk-operations/configure/'),
    'formulário de configuração da ação VBO',
  );
  const configForm = await execute(`const select=document.querySelector('select[name="destination_category"]'); return {path:location.pathname,title:document.title,hasDestination:!!select,options:[...(select?.options||[])].map(option=>({value:option.value,label:option.textContent.trim()})),text:document.querySelector('main')?.innerText||'',markers:[...document.querySelectorAll('main li')].map(item=>item.innerText).filter(text=>text.includes(${JSON.stringify(marker)})),buttons:[...document.querySelectorAll('main form button[type=submit],main form input[type=submit]')].map(button=>(button.innerText||button.value||'').trim())};`);
  check('Formulário VBO confirma dois tópicos e oferece categorias de destino', configForm.hasDestination && configForm.markers.length === 2 && configForm.options.some((option) => Number(option.value) === fixtures[0].destination), JSON.stringify(configForm));
  await screenshot('forum-topic-move-bulk-configure.png');

  const configured = await execute(`const form=document.querySelector('form#views-bulk-operations-configure-action')||document.querySelector('main form'); const select=form?.querySelector('select[name="destination_category"]'); const button=[...(form?.querySelectorAll('button[type=submit],input[type=submit]')||[])].find(item=>(item.innerText||item.value||'').trim()==='Apply'||(item.innerText||item.value||'').trim()==='Aplicar'); if(!form||!select||!button)return {submitted:false}; select.value=${JSON.stringify(String(fixtures[0].destination))}; select.dispatchEvent(new Event('change',{bubbles:true})); form.requestSubmit(button); return {submitted:true,destination:select.value};`);
  check('Destino explícito escolhido para a categoria pública', configured.submitted && Number(configured.destination) === fixtures[0].destination, JSON.stringify(configured));
  page = await waitFor(
    `return {path:location.pathname,title:document.title,text:document.querySelector('main')?.innerText||'',forms:document.forms.length,markers:[...document.querySelectorAll('main li')].map(item=>item.innerText).filter(text=>text.includes(${JSON.stringify(marker)})).length};`,
    (result) => result.path.includes('/views-bulk-operations/confirm/') || result.text.includes('Confirme a categoria'),
    'tela de confirmação da movimentação em lote',
  );
  const confirmation = await execute(`return {path:location.pathname,title:document.querySelector('main h1')?.innerText||'',text:document.querySelector('main')?.innerText||'',markers:[...document.querySelectorAll('main li')].map(item=>item.innerText).filter(text=>text.includes(${JSON.stringify(marker)})),button:[...document.querySelectorAll('main form button[type=submit],main form input[type=submit]')].map(item=>(item.innerText||item.value||'').trim())};`);
  check('Confirmação lista exatamente os dois tópicos e destino informado', confirmation.markers.length === 2 && confirmation.text.includes('Confirme a categoria de destino') && confirmation.button.some((text) => /Executar ação|Execute action/i.test(text)), JSON.stringify(confirmation));
  await screenshot('forum-topic-move-bulk-confirm.png');

  const confirmed = await execute(`const form=document.querySelector('form#views_bulk_operations_confirm_action')||document.querySelector('main form'); const button=[...(form?.querySelectorAll('button[type=submit],input[type=submit]')||[])].find(item=>/Executar ação|Execute action/i.test((item.innerText||item.value||'').trim())); if(!form||!button)return {submitted:false}; form.requestSubmit(button); return {submitted:true,label:(button.innerText||button.value||'').trim()};`);
  check('Form API recebe a confirmação explícita da operação em lote', confirmed.submitted, JSON.stringify(confirmed));
  if (!confirmed.submitted) throw new Error('A confirmação explícita do VBO não foi submetida.');
  actionExecuted = true;
  page = await waitFor(
    `const text=document.body?.innerText||''; return {path:location.pathname,title:document.title,text:text.slice(-2200),serverError:/Internal Server Error|The website encountered an unexpected error/i.test(text),batch:!!document.querySelector('#progress')||location.pathname.startsWith('/batch')};`,
    (result) => result.path === '/painel-administrativo/forum/conteudo' && !result.batch && !result.serverError,
    'conclusão do Batch API e retorno à View',
    60000,
  );
  check('Batch API termina sem erro e retorna à lista de tópicos', page.path === '/painel-administrativo/forum/conteudo' && !page.serverError, JSON.stringify(page));
  await screenshot('forum-topic-move-bulk-result.png');

  const verifyCode = `
$manager = \\Drupal::entityTypeManager();
$out = [];
foreach (${JSON.stringify(fixtures.map((fixture) => fixture.nid))} as $nid) {
  $node = $manager->getStorage('node')->load($nid);
  $out[] = $node ? [
    'exists' => TRUE,
    'nid' => (int) $node->id(),
    'uuid' => $node->uuid(),
    'revision' => (int) $node->getRevisionId(),
    'category' => (int) $node->get('field_aculta_categories')->target_id,
    'title' => $node->label(),
    'uid' => (int) $node->getOwnerId(),
    'status' => (int) $node->isPublished(),
    'domain' => (string) $node->get('field_domain_source')->target_id,
    'discussion_status' => (int) $node->get('field_aculta_discussion')->status,
  ] : ['exists' => FALSE, 'nid' => (int) $nid];
}
print 'ACULTA_MOVED:' . json_encode($out, JSON_THROW_ON_ERROR) . PHP_EOL;
`;
  const moved = marked(evalDrupal(verifyCode), 'ACULTA_MOVED');
  const validMoves = moved.length === 2 && moved.every((item, index) => item.exists
    && item.nid === fixtures[index].nid && item.uuid === fixtures[index].uuid
    && item.revision > fixtures[index].revision && item.category === fixtures[index].destination
    && item.title === fixtures[index].title && item.uid === fixtures[index].uid
    && item.status === 0 && item.domain === fixtures[index].domain
    && item.discussion_status === fixtures[index].discussion_status);
  check('Nodes mantêm identidade, autoria, estado, domínio e Discussion; nova revisão na categoria destino', validMoves, JSON.stringify({ fixtures, moved }));
  writeFileSync(resolve(output, 'moved-nodes.json'), JSON.stringify({ fixtures, moved }, null, 2), { mode: 0o600 });
}
catch (error) {
  check('Execução do smoke em lote sem erro inesperado', false, error.message);
}
finally {
  if (browserSession) {
    if (selectionStarted && !actionExecuted) {
      await request('POST', sessionPath('/url'), { url: `${uri}/painel-administrativo/forum/conteudo` }).catch(() => {});
      await wait(1000);
      await execute(`const form=document.querySelector('#views-form-aculta-forum-admin-topics-page-forum'); const clear=[...(form?.querySelectorAll('button,input[type=submit]')||[])].find(item=>(item.innerText||item.value||'').trim()==='Clear selection'||(item.innerText||item.value||'').trim()==='Limpar seleção'); if(clear)form.requestSubmit(clear); return !!clear;`).catch(() => false);
      await wait(900);
    }
    await request('DELETE', `/session/${browserSession}`).catch(() => {});
  }
  if (bidi?.readyState === WebSocket.OPEN) bidi.close();
  if (fixtures.length) {
    try {
      const ids = fixtures.map((fixture) => fixture.nid);
      const uuids = fixtures.map((fixture) => fixture.uuid);
      const cleanupCode = `
$ids = ${JSON.stringify(ids)};
$uuids = ${JSON.stringify(uuids)};
$storage = \\Drupal::entityTypeManager()->getStorage('node');
$removed = [];
foreach ($storage->loadMultiple($ids) as $node) {
  $index = array_search((int) $node->id(), $ids, TRUE);
  if ($index !== FALSE && $node->bundle() === 'aculta_forum_topic' && $node->uuid() === $uuids[$index] && str_starts_with($node->label(), ${markerPhp})) {
    $storage->delete([$node]);
    $removed[] = (int) $node->id();
  }
}
print 'ACULTA_CLEANUP:' . json_encode(['removed' => $removed], JSON_THROW_ON_ERROR) . PHP_EOL;
`;
      const cleanup = marked(evalDrupal(cleanupCode), 'ACULTA_CLEANUP');
      const after = marked(evalDrupal(stateCode), 'ACULTA_STATE');
      const expectedRemoved = fixtures.map((fixture) => fixture.nid).sort((a, b) => a - b);
      const actualRemoved = cleanup.removed.sort((a, b) => a - b);
      check('Somente os dois Nodes de fixture foram removidos; baseline e fila restaurados', JSON.stringify(actualRemoved) === JSON.stringify(expectedRemoved)
        && after.topics === baseline.topics && after.legacy_topics === baseline.legacy_topics
        && after.discussion_comments === baseline.discussion_comments && after.legacy_comments === baseline.legacy_comments
        && after.category_count === baseline.category_count && after.queue === baseline.queue
        && after.selection_count === 0, JSON.stringify({ cleanup, baseline, after }));
      writeFileSync(resolve(output, 'postflight.json'), JSON.stringify({ baseline, after, cleanup }, null, 2), { mode: 0o600 });
    }
    catch (error) {
      check('Cleanup exato e restauração do baseline', false, error.message);
    }
  }
  writeFileSync(resolve(output, 'results.json'), JSON.stringify({ host: mainEntry.test_host, display, account: 'uid 1 (administrador de teste; ULI descartável)', method: 'Firefox/X11; seleção VBO AJAX; Form API; Batch API', checks, passed: checks.filter((item) => item.pass).length, total: checks.length }, null, 2), { mode: 0o600 });
  console.log(`${checks.filter((item) => item.pass).length}/${checks.length} verificações; evidências em ${output}.`);
}
if (checks.some((item) => !item.pass)) process.exitCode = 1;
