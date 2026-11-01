'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../js/messenger-drawers.js'), 'utf8');

async function checkCounter(type, kind, count, expected) {
  const handlers = {};
  const trigger = {
    dataset: { acultaCountKind: kind },
    setAttribute(name, value) { this[name] = value; },
  };
  const counter = { dataset: {}, closest: () => trigger };
  const content = { setAttribute() {}, replaceChildren() {}, querySelectorAll: () => [] };
  const drawer = {
    dataset: { acultaDrawerCount: type, acultaDrawerUrl: '/preview' },
    querySelector: () => content,
    addEventListener: (name, callback) => { handlers[name] = callback; },
  };
  const document = {
    addEventListener() {},
    querySelector: () => counter,
    createElement: (name) => name === 'template'
      ? { content: { cloneNode: () => ({}) } }
      : { dataset: {}, setAttribute() {} },
  };
  const Drupal = {
    behaviors: {},
    t: (text, values = {}) => Object.entries(values).reduce((value, [key, replacement]) => value.replace(key, replacement), text),
    attachBehaviors() {},
    detachBehaviors() {},
  };
  const once = (id) => id === 'aculta-messenger-drawer' ? [drawer] : [];
  const fetch = async () => ({ ok: true, headers: { get: () => String(count) }, text: async () => '' });
  vm.runInNewContext(source, { Drupal, once, drupalSettings: {}, document, fetch, AbortController, window: {} });
  Drupal.behaviors.acultaMessengerDrawers.attach(document);
  handlers['show.bs.offcanvas']();
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(counter.dataset.count, String(count));
  assert.equal(counter.textContent, count > 99 ? '99+' : String(count));
  assert.equal(trigger['aria-label'], expected);
}

(async () => {
  await checkCounter('messages', 'active', 0, 'Abrir mensagens');
  await checkCounter('messages', 'active', 1, '1 conversa não lida. Abrir mensagens');
  await checkCounter('messages', 'active', 101, '101 conversas não lidas. Abrir mensagens');
  await checkCounter('alerts', 'active', 0, 'Abrir avisos');
  await checkCounter('alerts', 'active', 1, '1 aviso ativo. Abrir avisos');
  await checkCounter('alerts', 'attention', 101, '101 avisos ativos ou não lidos. Abrir avisos');
  process.stdout.write('PASS: 6 atualizações de contador e rótulo\n');
})().catch((error) => { console.error(error); process.exitCode = 1; });
