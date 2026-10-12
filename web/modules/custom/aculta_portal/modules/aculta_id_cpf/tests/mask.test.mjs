import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const code = readFileSync(new URL('../js/document-mask.js', import.meta.url), 'utf8');

function harness(type, value) {
  const listeners = {};
  const input = {
    value,
    selectionStart: value.length,
    selectionEnd: value.length,
    getAttribute: () => type,
    addEventListener(event, callback) {
      (listeners[event] ??= []).push(callback);
    },
    setSelectionRange(start, end) {
      this.selectionStart = start;
      this.selectionEnd = end;
    },
  };
  const claimed = new WeakSet();
  const Drupal = { behaviors: {} };
  const once = () => claimed.has(input) ? [] : (claimed.add(input), [input]);
  runInNewContext(code, { Drupal, once });
  const attach = () => Drupal.behaviors.acultaDocumentMask.attach({});
  attach();
  return {
    input,
    attach,
    listeners,
    emit(event, detail = {}) { (listeners[event] ?? []).forEach((callback) => callback(detail)); },
  };
}

test('CPF preserves leading zeroes and applies the complete mask', () => {
  assert.equal(harness('cpf', '01234567890').input.value, '012.345.678-90');
});

test('CNPJ preserves letters and normalizes ASCII lowercase', () => {
  assert.equal(harness('cnpj', '12abc34501de35').input.value, '12.ABC.345/01DE-35');
});

test('partial input and paste are formatted by the behavior', () => {
  const h = harness('cpf', '5299');
  assert.equal(h.input.value, '529.9');
  h.input.value = '52998224725';
  h.input.selectionStart = h.input.selectionEnd = 11;
  h.emit('input');
  assert.equal(h.input.value, '529.982.247-25');
  assert.equal(h.input.selectionStart, 14);
});

test('invalid, Unicode and excessive input is never repaired or truncated', () => {
  for (const [type, value] of [
    ['cpf', '52998224725!'], ['cpf', '529982247250'], ['cpf', '529A98224725'],
    ['cpf', '５２９９８２２４７２５'], ['cpf', '52998224725\n'],
    ['cnpj', '12ABC34501DE3A'], ['cnpj', '12ABC34501DE350'],
  ]) {
    assert.equal(harness(type, value).input.value, value);
  }
});

test('repeated Drupal attach does not duplicate listeners', () => {
  const h = harness('cpf', '');
  h.attach();
  h.attach();
  assert.equal(h.listeners.input.length, 1);
  assert.equal(h.listeners.blur.length, 1);
});

test('selection is preserved by significant-character position', () => {
  const h = harness('cpf', '');
  h.input.value = '52998224725';
  h.input.selectionStart = 4;
  h.input.selectionEnd = 7;
  h.emit('input');
  assert.equal(h.input.selectionStart, 5);
  assert.equal(h.input.selectionEnd, 9);
});

test('composition is not interrupted and is formatted when complete', () => {
  const h = harness('cnpj', '');
  h.input.value = '12abc34501de35';
  h.emit('input', { isComposing: true });
  assert.equal(h.input.value, '12abc34501de35');
  h.emit('compositionend');
  assert.equal(h.input.value, '12.ABC.345/01DE-35');
});

test('deleting a separator puts the cursor before it instead of jumping ahead', () => {
  const h = harness('cpf', '');
  h.input.value = '529982.247-25';
  h.input.selectionStart = h.input.selectionEnd = 3;
  h.emit('input');
  assert.equal(h.input.value, '529.982.247-25');
  assert.equal(h.input.selectionStart, 3);
});
