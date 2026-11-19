import assert from 'node:assert/strict';
import test from 'node:test';
import postcss from 'postcss';
import { isKeyframeStep } from '../lib/postcss-utils.mjs';

test('identifica quadros de keyframes sem confundi-los com seletores CSS', () => {
  const root = postcss.parse(`
    @keyframes spin { from { opacity: 0; } to { opacity: 1; } }
    @-webkit-keyframes fade { 50% { opacity: .5; } }
    .component { opacity: 1; }
  `);
  const rules = [];
  root.walkRules((rule) => rules.push(rule));

  assert.deepEqual(rules.map(isKeyframeStep), [true, true, true, false]);
});
