// Suíte de navegador do ACULTA Messenger: cliques e digitação reais no Firefox, duas contas (uma delas com grupo),
// os dois lados de cada estado. Roda no runtime de teste. Ver README.md desta pasta.
import { Browser, check, drush, results, setup, sleep, OUT, BASE } from './suite-lib.mjs';
import { writeFileSync } from 'node:fs';

// teste.comum is an editor in the shared runtime; use the ordinary test account to exercise member privacy rules.
const A = 'teste.c';
const B = 'teste.b';
const C = 'teste.c';
const MSG = 'Olá teste.b, mensagem do teste de navegador.';
const GROUP_MSG = 'Mensagem de grupo de teste.';

const baseUrl = new URL(BASE);
const loginUrl = new URL(process.env.SUITE_LOGIN_URI || 'https://conta.aculta.toca.net.br');
if (baseUrl.protocol !== 'https:' || (baseUrl.hostname !== 'aculta.toca.net.br' && !baseUrl.hostname.endsWith('.aculta.toca.net.br'))
  || loginUrl.protocol !== 'https:' || loginUrl.hostname !== 'conta.aculta.toca.net.br') {
  throw new Error('A suíte do Messenger só pode usar hosts HTTPS do servidor de teste ACULTA.');
}

// Preparation: test accounts and the group thread (idempotent, see setup-accounts.php).
const setupOut = setup();
const groupId = /group thread (\d+)/.exec(setupOut)?.[1];
const uidB = Number(/"teste\.b":(\d+)/.exec(setupOut)?.[1]);
const uidA = Number(/"teste\.c":(\d+)/.exec(setupOut)?.[1]);
check('setup: the group thread exists', Boolean(groupId), setupOut.trim());

// Deterministic one-to-one start: no leftover privacy, friendship, block or flood counters between A and B.
// (The group thread has three members and is not touched here.)
const lo = Math.min(uidA, uidB), hi = Math.max(uidA, uidB);
drush(`\\Drupal::service("user.data")->delete("aculta_messenger", ${uidB}); $st = \\Drupal::entityTypeManager()->getStorage("aculta_friendship"); foreach ($st->loadMultiple(["${lo}:${hi}"]) as $r) { $r->delete(); } $st->resetCache(["${lo}:${hi}"]); \\Drupal::database()->delete("private_message_ban")->condition("owner", [${uidA}, ${uidB}], "IN")->execute(); \\Drupal::database()->delete("flood")->condition("event", ["aculta_messenger.private_message_send", "aculta_user.friendship_request"], "IN")->execute(); $em = \\Drupal::entityTypeManager(); $pair = $em->getStorage("user")->loadMultiple([${uidA}, ${uidB}]); $tid = \\Drupal::service("private_message.mapper")->getThreadIdForMembers(array_values($pair)); if ($tid) { $t = $em->getStorage("private_message_thread")->load($tid); $em->getStorage("private_message")->delete($t->get("private_messages")->referencedEntities()); $t->delete(); } echo "reset";`);

const b = new Browser();
// The search shows an "Adicionar" button per member: it becomes a chip. The message box and the buttons are already on the same screen.
const pickMember = (name) => b.exec(`const x = [...${b.C}.querySelectorAll('[data-aculta-add-member]')].find((y) => y.dataset.acultaMemberName === ${JSON.stringify(name)}); if (!x) return 0; x.click(); return 1;`);
const memberShown = (name) => `[...${b.C}.querySelectorAll('[data-aculta-add-member]')].some((x) => x.dataset.acultaMemberName === ${JSON.stringify(name)})`;

// ---------- Phase 1: "Iniciar nova conversa" from the first window, as A (desktop) ----------
await b.open(A);
check('A: drawer opens', await b.openDrawer());
check('A: list shows "Iniciar nova conversa" button', await b.waitFor(`${b.C}.querySelector('[data-aculta-conversation-url="/mensagens/nova"]')`, 'start button'));
await b.exec(`${b.C}.querySelector('[data-aculta-conversation-url="/mensagens/nova"]').click(); return 1;`);
check('A: start step shows the member search', await b.waitFor(`${b.C}.querySelector('input[name=q]')`, 'search'));
await b.typeIn(`[data-aculta-drawer-url][data-aculta-drawer-count="messages"] input[name=q]`, 'te');
await sleep(1200);
check('A: 2 characters show the hint, not results', (await b.text()).includes('Digite pelo menos 3 caracteres') && (await b.exec(`return ${b.C}.querySelectorAll('[data-portal-ui="new-conversation-result"]').length;`)) === 0);
await b.clearAndType(`[data-aculta-drawer-url][data-aculta-drawer-count="messages"] input[name=q]`, 'teste.b');
check('A: 3+ characters find member B', await b.waitFor(memberShown('teste.b'), 'result'));
await pickMember('teste.b');
check('A: compose step shows the message box and the buttons', await b.waitFor(`${b.C}.querySelector('textarea') && [...${b.C}.querySelectorAll('input[type=submit]')].some((x) => x.value === 'Enviar')`, 'compose'));
const composeText = await b.text();
check('A: compose has Cancelar and Voltar para o início', composeText.includes('Cancelar') && composeText.includes('Voltar para o início'));
const btnClasses = await b.exec(`const f = (t) => [...${b.C}.querySelectorAll('a')].find((x) => x.textContent.trim() === t); return JSON.stringify({ cancel: f('Cancelar')?.className || '', back: f('Voltar para o início')?.className || '', send: [...${b.C}.querySelectorAll('input[type=submit]')].find((x) => x.value === 'Enviar')?.className || '' });`);
const bc = JSON.parse(btnClasses);
check('A: "Cancelar" and "Voltar" have their own colours, apart from "Enviar"', bc.cancel.includes('aculta-new-conversation-cancel') && bc.back.includes('aculta-new-conversation-back') && bc.send.includes('btn-primary'), btnClasses);
check('A: "Cancelar" and "Voltar para o início" come before "Enviar" in the form', await b.exec(`const all = [...${b.C}.querySelectorAll('a, input[type=submit]')]; const i = (t) => all.findIndex((x) => (x.textContent.trim() === t || x.value === t)); return i('Cancelar') < i('Enviar') && i('Voltar para o início') < i('Enviar');`));
await b.shot('A-compose');
await b.clickByText('a', 'Cancelar');
check('A: Cancelar returns to the member search', await b.waitText('Digite pelo menos 3 caracteres'));
await b.clearAndType(`[data-aculta-drawer-url][data-aculta-drawer-count="messages"] input[name=q]`, 'teste.b');
await b.waitFor(memberShown('teste.b'), 'result 2');
await pickMember('teste.b');
await b.waitFor(`${b.C}.querySelector('textarea')`, 'compose 2');
await b.clickByText('a', 'Voltar para o início');
check('A: Voltar para o início returns to the conversation list', await b.waitFor(`${b.C}.querySelector('[data-aculta-conversation-url="/mensagens/nova"]')`, 'list'));

// Send the first message (A -> B), through the compose step.
await b.exec(`${b.C}.querySelector('[data-aculta-conversation-url="/mensagens/nova"]').click(); return 1;`);
await b.waitFor(`${b.C}.querySelector('input[name=q]')`, 'search 3');
await sleep(800); // the drawer attaches the search behaviour after the fragment is inserted
await b.clearAndType(`[data-aculta-drawer-url][data-aculta-drawer-count="messages"] input[name=q]`, 'teste.b');
await b.waitFor(memberShown('teste.b'), 'result 3');
await pickMember('teste.b');
await b.waitFor(`${b.C}.querySelector('textarea')`, 'compose 3');
await b.typeIn(`[data-aculta-drawer-url][data-aculta-drawer-count="messages"] textarea`, MSG);
await b.exec(`[...${b.C}.querySelectorAll('input[type=submit]')].find((x) => x.value === 'Enviar').click(); return 1;`);
check('A: sending the first message opens the conversation with it', await b.waitText(MSG));
check('A: no JavaScript errors so far', (await b.errors()).length === 0, JSON.stringify(await b.errors()));
await b.shot('A-sent');

// ---------- Phase 2: thread history and member actions, as A ----------
const tbv = JSON.parse(await b.exec(`const t = ${b.C}.querySelector('.aculta-messenger-conversation-toolbar'); const back = t.querySelector('.aculta-messenger-back-link').getBoundingClientRect(); const opt = t.querySelector('.aculta-messenger-options__summary').getBoundingClientRect(); const box = t.querySelector('.aculta-messenger-options').getBoundingClientRect(); return JSON.stringify({ backLeft: back.left < opt.left, sameTop: Math.abs(back.top - box.top) < 4 });`));
check('A: "← Todas as conversas" is on the left of "Opções"', tbv.backLeft);
check('A: "← Todas as conversas" and "Opções" are on the same row', tbv.sameTop);
await b.openOptions();
const items1 = await b.panelItems();
check('A: thread menu contains only "Limpar histórico"', JSON.stringify(items1) === JSON.stringify(['button:Limpar histórico']), JSON.stringify(items1));
await b.shot('A-options');
await b.clickByText('input[type=submit]', 'Limpar histórico');
check('A: "Limpar histórico" asks for confirmation in the menu, nothing cleared yet', await b.waitFor(`${b.C}.innerText.includes('Limpar o histórico desta conversa só para você?')`, 'confirm text'));
check('A: the confirmation button is shown', (await b.panelItems()).includes('button:Confirmar limpeza do histórico'));
check('A: the conversation is still there before confirming', await b.waitText(MSG, 3000));

// Send a friendship request (A -> B).
await b.openOptions('teste.b');
await b.clickByText('input[type=submit]', 'Adicionar amigo');
check('A: "Adicionar amigo" changes the member menu to "Cancelar pedido de amizade"', await b.waitFor(`[...${b.C}.querySelectorAll('.aculta-messenger-options[open] .aculta-messenger-options__panel input[type=submit]')].some((x) => x.value === 'Cancelar pedido de amizade')`, 'cancel btn'));
await b.openOptions('teste.b');
const items2 = await b.panelItems();
check('A: pending request member menu: Cancelar pedido de amizade, Bloquear', JSON.stringify(items2) === JSON.stringify(['button:Cancelar pedido de amizade', 'button:Bloquear']), JSON.stringify(items2));
await b.shot('A-pending');
await b.exec(`${b.C}.querySelector('.aculta-messenger-options__summary').click(); return 1;`);
await b.clickByText('a', '← Todas as conversas');
check('A: the list shows the conversation with B after going back', await b.waitFor(`[...${b.C}.querySelectorAll('a[data-aculta-conversation-url]')].some((x) => x.textContent.includes('teste.b'))`, 'row B'));
// The list row's "Opções" loads its menu the first time it is opened (drawer_options).
await b.exec(`${b.C}.querySelector('.aculta-messenger-conversation__actions .aculta-messenger-options__summary').click(); return 1;`);
check('A: a list row\'s "Opções" loads its menu when opened', await b.waitFor(`[...${b.C}.querySelectorAll('.aculta-messenger-conversation__actions .aculta-messenger-options__panel input[type=submit]')].some((x) => x.value === 'Limpar histórico')`, 'row menu'));
await b.exec(`${b.C}.querySelector('.aculta-messenger-conversation__actions .aculta-messenger-options__summary').click(); return 1;`);

// ---------- Phase 3: B accepts, as B ----------
await b.open(B);
await b.openDrawer();
check('B: the conversation from A is in B\'s list', await b.openConversation(A));
await b.openOptions(A);
const itemsB = await b.panelItems();
check('B: recipient member menu: Aceitar, Rejeitar, Bloquear', JSON.stringify(itemsB) === JSON.stringify(['button:Aceitar pedido de amizade', 'button:Rejeitar pedido de amizade', 'button:Bloquear']), JSON.stringify(itemsB));
await b.shot('B-recipient-options');
await b.clickByText('input[type=submit]', 'Aceitar pedido de amizade');
check('B: accepting changes the member menu to "Desfazer amizade"', await b.waitFor(`[...${b.C}.querySelectorAll('.aculta-messenger-options[open] .aculta-messenger-options__panel input[type=submit]')].some((x) => x.value === 'Desfazer amizade')`, 'undo btn'));
check('B: the reply box is back after accepting', await b.waitFor(`${b.C}.querySelector('textarea[name="message[0][value]"]')`, 'reply'));
await b.shot('B-accepted');

// ---------- Phase 4: A removes the friendship through its confirmation, then sees the friends-only notice ----------
await b.open(A);
await b.openDrawer();
await b.openConversation(B);
await b.openOptions('teste.b');
check('A: after acceptance the member menu shows "Desfazer amizade"', (await b.panelItems()).includes('button:Desfazer amizade'));
drush(`\\Drupal::service("user.data")->set("aculta_messenger", ${uidB}, "message_privacy", "friends"); echo "ok";`);
await b.exec(`${b.C}.querySelector('[data-aculta-dialog-open="remove"]').click(); return 1;`);
check('A: "Desfazer amizade" asks first in a dialog', await b.waitFor(`${b.C}.querySelector('.ui-dialog [data-aculta-dialog="remove"]')?.getClientRects().length`, 'remove dialog'));
await b.exec(`${b.C}.querySelector('.ui-dialog [data-aculta-dialog="remove"] [data-aculta-dialog-close]').click(); return 1;`);
check('A: "Cancelar" in the dialog keeps the friendship', await b.waitFor(`!${b.C}.querySelector('.ui-dialog [data-aculta-dialog="remove"]')?.getClientRects().length && [...${b.C}.querySelectorAll('.aculta-messenger-options[open] .aculta-messenger-options__panel input[type=submit]')].some((x) => x.value === 'Desfazer amizade')`, 'still friends'));
await b.exec(`${b.C}.querySelector('[data-aculta-dialog-open="remove"]').click(); return 1;`);
await b.waitFor(`${b.C}.querySelector('.ui-dialog [data-aculta-dialog="remove"]')?.getClientRects().length`, 'remove dialog 2');
await b.exec(`${b.C}.querySelector('.ui-dialog [data-aculta-dialog="remove"] input[name=remove]').click(); return 1;`);
check('A: "Sim" removes the friendship: the member menu shows "Adicionar amigo"', await b.waitFor(`[...${b.C}.querySelectorAll('.aculta-messenger-options[open] .aculta-messenger-options__panel input[type=submit]')].some((x) => x.value === 'Adicionar amigo')`, 'add btn'));
// B now only receives messages from friends (set through user.data, as the Configurações form does), before A removes the friendship.
check('A: not a friend of a friends-only member: the notice replaces the reply box', await b.waitFor(`${b.C}.querySelector('.aculta-messenger-notice')`, 'notice'));
const n1 = await b.notice();
const n1Button = await b.exec(`const x = ${b.C}.querySelector('.aculta-messenger-notice__action'); return x ? x.value || x.textContent.trim() : null;`);
check('A: friends-only notice text', (n1 || '').includes('Essa pessoa só recebe mensagens de Amigos,') && (n1Button || '').includes('pedido de Amizade'), `${n1} | ${n1Button}`);
await b.shot('A-friends-only-notice');

// Send the request through the notice link.
await b.exec(`${b.C}.querySelector('.aculta-messenger-notice__action').click(); return 1;`);
check('A: the notice then says the request is already sent', await b.waitText('você já enviou pedido de amizade, aguarde', 15000));
check('A: no reply box while the request is pending', !(await b.hasReplyBox()));
await b.shot('A-pending-notice');

// ---------- Phase 5: B accepts through the menu, then blocks A through its dialog ----------
await b.open(B);
await b.openDrawer();
await b.openConversation(A);
await b.openOptions(A);
await b.clickByText('input[type=submit]', 'Aceitar pedido de amizade');
check('B: accepting the request from A (second time)', await b.waitFor(`[...${b.C}.querySelectorAll('.aculta-messenger-options[open] .aculta-messenger-options__panel input[type=submit]')].some((x) => x.value === 'Desfazer amizade')`, 'undo btn 2'));
await b.openOptions(A);
await b.exec(`${b.C}.querySelector('[data-aculta-dialog-open="ban"]').click(); return 1;`);
check('B: "Bloquear" asks first: "Você quer mesmo bloquear …?"', await b.waitFor(`${b.C}.querySelector('.ui-dialog [data-aculta-dialog="ban"]')?.innerText.includes('Você quer mesmo bloquear')`, 'ban dialog'));
await b.exec(`${b.C}.querySelector('.ui-dialog [data-aculta-dialog="ban"] input[name=ban]').click(); return 1;`);
check('B: after blocking A the member menu shows "Desbloquear"', await b.waitFor(`[...${b.C}.querySelectorAll('.aculta-messenger-options[open] .aculta-messenger-options__panel input[type=submit]')].some((x) => x.value === 'Desbloquear')`, 'unblock btn'));
check('B: blocker sees the blocker notice with its unblock link', await b.waitFor(`${b.C}.querySelector('.aculta-messenger-notice') && ${b.C}.querySelector('.aculta-messenger-notice').innerText.includes('Você bloqueou essa pessoa')`, 'blocker notice'));
check('B: blocker sees no reply box', !(await b.hasReplyBox()));
await b.shot('B-blocker-notice');

await b.open(A);
await b.openDrawer();
check('A: the conversation stays in the list after being blocked', await b.openConversation(B));
check('A: blocked member sees "Essa pessoa bloqueou você..." notice', await b.waitFor(`${b.C}.querySelector('.aculta-messenger-notice') && ${b.C}.querySelector('.aculta-messenger-notice').innerText.includes('Essa pessoa bloqueou você, por isso não pode enviar ou receber mensagens.')`, 'blocked notice'));
check('A: blocked member has no reply box and no action link', !(await b.hasReplyBox()) && !(await b.exec(`return !!${b.C}.querySelector('.aculta-messenger-notice__action');`)));
await b.shot('A-blocked-notice');
check('A: the previous messages are still visible when blocked', (await b.text()).includes(MSG));

// ---------- Phase 6: B unblocks from its menu, both sides reopen ----------
await b.open(B);
await b.openDrawer();
await b.openConversation(A);
await b.openOptions(A);
await b.clickByText('input[type=submit]', 'Desbloquear');
check('B: unblocking returns the reply box', await b.waitFor(`${b.C}.querySelector('textarea[name="message[0][value]"]')`, 'reply back'));

await b.open(A);
await b.openDrawer();
await b.openConversation(B);
check('A: after the unblock the reply box is back for A', await b.waitFor(`${b.C}.querySelector('textarea[name="message[0][value]"]')`, 'A reply back'));

// ---------- Phase 7: A blocks through its dialog and unblocks with the notice link ----------
await b.openOptions('teste.b');
await b.exec(`${b.C}.querySelector('[data-aculta-dialog-open="ban"]').click(); return 1;`);
await b.waitFor(`${b.C}.querySelector('.ui-dialog [data-aculta-dialog="ban"]')?.getClientRects().length`, 'A ban dialog');
await b.exec(`${b.C}.querySelector('.ui-dialog [data-aculta-dialog="ban"] input[name=ban]').click(); return 1;`);
check('A: blocking shows the blocker notice', await b.waitFor(`${b.C}.querySelector('.aculta-messenger-notice') && ${b.C}.querySelector('.aculta-messenger-notice').innerText.includes('Você bloqueou essa pessoa')`, 'A blocker notice'));
await b.exec(`${b.C}.querySelector('.aculta-messenger-notice__action').click(); return 1;`);
check('A: the notice link unblocks and the reply box returns', await b.waitFor(`${b.C}.querySelector('textarea[name="message[0][value]"]')`, 'reply after notice unblock'));

// ---------- Phase 8: send a message in the conversation (A -> B), as a friend ----------
const MSG2 = 'Segunda mensagem, depois do desbloqueio.';
await b.typeIn(`[data-aculta-drawer-url][data-aculta-drawer-count="messages"] textarea[name="message[0][value]"]`, MSG2);
await b.exec(`[...${b.C}.querySelectorAll('input[type=submit]')].find((x) => x.value === 'Responder' || x.value === 'Enviar mensagem').click(); return 1;`);
check('A: a reply after the unblock is delivered', await b.waitText(MSG2, 15000));
check('A: no JavaScript errors in the conversation flow', (await b.errors()).length === 0, JSON.stringify(await b.errors()));
await b.shot('A-replied');

// ---------- Phase 9: mobile (390 px) ----------
await b.open(A, { width: 390, height: 844 });
await b.openDrawer();
await b.openConversation(B);
const mob = JSON.parse(await b.exec(`const C = ${b.C}; const t = C.querySelector('.aculta-messenger-conversation-toolbar'); const back = t.querySelector('.aculta-messenger-back-link').getBoundingClientRect(); const s = t.querySelector('.aculta-messenger-options'); const label = t.querySelector('.aculta-messenger-options__label'); return JSON.stringify({ sameTop: Math.abs(back.top - s.getBoundingClientRect().top) < 4, labelHidden: !label || getComputedStyle(label).display === 'none', noOverflow: document.documentElement.scrollWidth <= window.innerWidth + 1 });`));
check('mobile: "← Todas as conversas" and the hamburger share the row', mob.sameTop);
check('mobile: the "Opções" label is hidden, only the icon shows', mob.labelHidden);
check('mobile: no horizontal page overflow', mob.noOverflow);
await b.openOptions('teste.b');
const itemsM = await b.panelItems();
check('mobile: the member menu opens with relationship actions', itemsM.length >= 2, JSON.stringify(itemsM));
await b.shot('mobile-A-options');
await b.close();

// ---------- Phase 10: "Limpar histórico" in two steps, for A only ----------
await b.open(A);
await b.openDrawer();
await b.openConversation(B);
await b.openOptions();
await b.clickByText('input[type=submit]', 'Limpar histórico');
check('A: first step asks for confirmation', await b.waitFor(`${b.C}.innerText.includes('Limpar o histórico desta conversa só para você?')`, 'confirm 2'));
await b.clickByText('input[type=submit]', 'Confirmar limpeza do histórico');
check('A: confirming shows "Histórico limpo para você."', await b.waitText('Histórico limpo para você.'));
check('A: the old messages are gone for A', await b.waitGone(MSG, 10000));
await b.shot('A-cleared');
await b.open(B);
await b.openDrawer();
await b.openConversation(A);
check('B: the messages are still there for B', await b.waitText(MSG, 10000));
await b.shot('B-after-A-cleared');

// ---------- Phase 11: the public profile's "Opções" box, as A ----------
await b.open(A);
await b.go(`${BASE}/u/teste-b`);
const profileOk = await b.waitFor(`document.querySelector('.aculta-messenger-options__summary')`, 'profile box', 15000);
check('profile: the "Opções" box is at the top of the profile card', profileOk);
await b.exec(`document.querySelector('.aculta-messenger-options__summary').click(); return 1;`);
const profileItems = JSON.parse(await b.exec(`return JSON.stringify([...document.querySelectorAll('.aculta-messenger-options__panel a, .aculta-messenger-options__panel input[type=submit]')].filter((e) => e.offsetParent !== null).map((e) => (e.tagName === 'A' ? 'link:' : 'button:') + (e.value || e.textContent.trim())));`));
check('profile: the box offers "Iniciar conversa" and no "Limpar histórico"', profileItems.includes('link:Iniciar conversa') && !profileItems.some((x) => x.includes('Limpar histórico')), JSON.stringify(profileItems));
await b.shot('profile-options');
await b.exec(`document.querySelector('a[data-aculta-open-conversation]').click(); return 1;`);
check('profile: "Iniciar conversa" opens the drawer on the new-conversation step', await b.waitFor(`document.querySelector('[data-aculta-drawer-url][data-aculta-drawer-count="messages"] [data-aculta-drawer-content] textarea')`, 'compose from profile', 15000));
await b.close();

// ---------- Phase 12: the group thread: "Limpar histórico" for A only ----------
await b.open(A);
await b.openDrawer();
check('group: the group thread is in A\'s list', groupId ? await b.openThread(groupId) : false);
check('group: the group message is shown', await b.waitText(GROUP_MSG, 10000));
await b.openOptions();
const groupItems = await b.panelItems();
check('group: the menu offers only "Limpar histórico"', JSON.stringify(groupItems) === JSON.stringify(['button:Limpar histórico']), JSON.stringify(groupItems));
await b.clickByText('input[type=submit]', 'Limpar histórico');
check('group: the first step asks for confirmation', await b.waitFor(`${b.C}.innerText.includes('Limpar o histórico desta conversa só para você?')`, 'group confirm'));
await b.clickByText('input[type=submit]', 'Confirmar limpeza do histórico');
check('group: confirming shows "Histórico limpo para você."', await b.waitText('Histórico limpo para você.'));
check('group: the group message is gone for A', await b.waitGone(GROUP_MSG, 10000));
await b.shot('group-cleared-A');
await b.open(B);
await b.openDrawer();
check('group: the group message is still there for B', groupId ? await b.openThread(groupId) : false);
check('group: B still sees the group message', await b.waitText(GROUP_MSG, 10000));
await b.shot('group-B');

// ---------- Errors and report ----------
check('no JavaScript errors in the drawer', (await b.errors()).length === 0, JSON.stringify(await b.errors()));
await b.close();
const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
writeFileSync(`${OUT}/results.json`, JSON.stringify(results, null, 2));
process.exit(failed.length ? 1 : 0);
