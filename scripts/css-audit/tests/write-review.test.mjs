import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createWriteReviewHash, validatePurgeRemovalClassification } from '../lib/write-review.mjs';

const plan = {
  sourceHash: 'css-hash',
  toolVersions: { purgecss: '9.1.0', postcss: '8.5.32' },
  contentGlobs: ['templates/**/*.twig'],
  dynamicClasses: ['z-dynamic', 'a-dynamic'],
  safelist: { standard: ['a'], patterns: [] },
  classification: { sourceHash: 'css-hash', totals: { unreferenced: 1 } },
  files: [{ file: 'a.css', css: '.a{}' }],
};

test('hash de revisão é estável e muda se uma folha podada mudar', () => {
  const first = createWriteReviewHash(plan);
  assert.equal(first, createWriteReviewHash({ ...plan, dynamicClasses: ['a-dynamic', 'z-dynamic'] }));
  assert.notEqual(first, createWriteReviewHash({ ...plan, files: [{ file: 'a.css', css: '.b{}' }] }));
  assert.notEqual(first, createWriteReviewHash({ ...plan, classification: { sourceHash: 'css-hash', totals: { unreferenced: 0 } } }));
});

test('write só aceita classificação atual que cobre todos os seletores sem evidência', () => {
  const report = { files: [{ file: 'a.css', selectorsRemoved: ['.a'] }] };
  const classification = {
    sourceHash: 'css-hash',
    files: [{ file: 'a.css', items: [{ selector: '.a', category: 'unreferenced' }] }],
  };
  assert.deepEqual(validatePurgeRemovalClassification({ classification, report, sourceHash: 'css-hash' }), []);
  classification.files[0].items[0].category = 'runtime';
  assert.ok(validatePurgeRemovalClassification({ classification, report, sourceHash: 'css-hash' }).some((item) => item.includes('evidência')));
});

test('write rejeita classificação antiga ou parcial', () => {
  const report = { files: [{ file: 'a.css', selectorsRemoved: ['.a', '.b'] }] };
  const classification = {
    sourceHash: 'old-hash',
    files: [{ file: 'a.css', items: [{ selector: '.a', category: 'unreferenced' }] }],
  };
  const problems = validatePurgeRemovalClassification({ classification, report, sourceHash: 'css-hash' });
  assert.ok(problems.some((item) => item.includes('desatualizada')));
});
