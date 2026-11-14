const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const behaviorPath = path.join(__dirname, '../../js/unsaved-changes.js');

function createHarness() {
  const documentListeners = new Map();
  const windowListeners = new Map();
  const forms = [{ dataset: { acultaChanged: 'false' } }];
  const document = {
    addEventListener: (name, callback) => documentListeners.set(name, callback),
    querySelectorAll: () => forms,
  };
  const window = {
    addEventListener: (name, callback) => windowListeners.set(name, callback),
  };
  const Drupal = { behaviors: {} };
  let onceCalled = false;
  const once = () => {
    if (onceCalled) {
      return [];
    }
    onceCalled = true;
    return [{}];
  };
  const source = fs.readFileSync(behaviorPath, 'utf8');
  vm.runInNewContext(source, { Drupal, once, document, window });
  Drupal.behaviors.acultaLmsUnsavedChanges.attach(document);
  return { Drupal, document, documentListeners, forms, windowListeners };
}

test('warns before leaving an edited authoring form and clears after submit', () => {
  const { documentListeners, forms, windowListeners } = createHarness();
  const form = forms[0];
  const event = { target: { closest: () => form } };
  documentListeners.get('input')(event);

  let prevented = false;
  const beforeUnload = { preventDefault: () => { prevented = true; } };
  windowListeners.get('beforeunload')(beforeUnload);
  assert.equal(prevented, true);
  assert.equal(beforeUnload.returnValue, '');

  documentListeners.get('submit')(event);
  prevented = false;
  windowListeners.get('beforeunload')({ preventDefault: () => { prevented = true; } });
  assert.equal(prevented, false);
});

test('does not warn while the form is unchanged or for unrelated forms', () => {
  const { documentListeners, forms, windowListeners } = createHarness();
  const unrelated = { dataset: {} };
  documentListeners.get('change')({ target: { closest: () => null } });
  assert.equal(forms[0].dataset.acultaChanged, 'false');
  documentListeners.get('change')({ target: { closest: () => unrelated } });
  assert.equal(unrelated.dataset.acultaChanged, 'true');
  assert.equal(forms[0].dataset.acultaChanged, 'false');

  let prevented = false;
  windowListeners.get('beforeunload')({ preventDefault: () => { prevented = true; } });
  assert.equal(prevented, false);
});

test('attaches global handlers only once across Drupal behavior reattachment', () => {
  const { Drupal, documentListeners, document, windowListeners } = createHarness();
  Drupal.behaviors.acultaLmsUnsavedChanges.attach(document);
  assert.equal(documentListeners.size, 3);
  assert.equal(windowListeners.size, 1);
});
