import { test } from 'node:test';
import assert from 'node:assert/strict';
import { validateAuditArtifacts } from '../lib/report-integrity.mjs';

function fixture() {
  const currentCss = [{ name: 'a.css', raw: '.a{}' }];
  const totals = {
    files: 1,
    originalBytes: 4,
    retainedBytes: 0,
    removedBytes: 4,
    originalSelectors: 1,
    retainedSelectors: 0,
    removedSelectors: 1,
    addedSelectors: 0,
    purgecssRejectedSelectors: 0,
  };
  const toolVersions = { purgecss: '9.1.0', postcss: '8.5.32', stylelint: '14.16.1' };
  const generatedAt = '2026-11-13T00:00:00.000Z';
  const report = {
    generatedAt,
    mode: 'audit',
    sourceHash: 'current-hash',
    toolVersions,
    contentGlobs: ['templates/**/*.twig'],
    dynamicClasses: ['dynamic-class'],
    safelist: { standard: ['dynamic-class'], patterns: [], static: [] },
    totals,
    files: [{
      file: 'a.css',
      originalBytes: 4,
      retainedBytes: 0,
      removedBytes: 4,
      originalSelectors: 1,
      retainedSelectors: 0,
      removedSelectors: 1,
      addedSelectors: 0,
      purgecssRejectedSelectors: 0,
      selectorsRemoved: ['.a'],
      selectorsAdded: [],
      purgecssRejected: [],
    }],
  };
  const manifest = {
    generatedAt,
    sourceHash: 'current-hash',
    toolVersions,
    totals: { ...totals },
    files: ['a.css'],
  };
  return {
    manifest,
    report,
    currentCss,
    stagedCssByName: new Map([['a.css', '']]),
    currentHash: 'current-hash',
    expectedDynamicClasses: ['dynamic-class'],
    expectedContentGlobs: ['templates/**/*.twig'],
    expectedSafelist: { standard: ['dynamic-class'], patterns: [], static: [] },
    expectedToolVersions: { ...toolVersions },
  };
}

test('aceita relatório completo com seletores removidos e reescritos reconciliados', () => {
  assert.deepEqual(validateAuditArtifacts(fixture()), []);
});

test('falha quando detalhes por arquivo estão ausentes apesar do manifesto válido', () => {
  const input = fixture();
  input.report.files = [];
  assert.ok(validateAuditArtifacts(input).some((problem) => problem.includes('contagem de arquivos')));
});

test('falha quando CSS podado está ausente', () => {
  const input = fixture();
  input.stagedCssByName.clear();
  assert.ok(validateAuditArtifacts(input).some((problem) => problem.includes('CSS podado de auditoria')));
});

test('falha quando lista de seletores foi omitida do relatório', () => {
  const input = fixture();
  delete input.report.files[0].selectorsRemoved;
  assert.ok(validateAuditArtifacts(input).some((problem) => problem.includes('lista de seletores removidos')));
});

test('falha quando classes dinâmicas ou hash do relatório estão desatualizados', () => {
  const input = fixture();
  input.report.dynamicClasses = [];
  input.manifest.sourceHash = 'old-hash';
  const problems = validateAuditArtifacts(input);
  assert.ok(problems.some((problem) => problem.includes('classes dinâmicas')));
  assert.ok(problems.some((problem) => problem.includes('sourceHash')));
});

test('falha quando entradas de PurgeCSS ou versões de ferramentas mudaram', () => {
  const input = fixture();
  input.report.contentGlobs = ['old/**/*.twig'];
  input.report.toolVersions.stylelint = 'old';
  const problems = validateAuditArtifacts(input);
  assert.ok(problems.some((problem) => problem.includes('fontes de conteúdo')));
  assert.ok(problems.some((problem) => problem.includes('versões de ferramentas')));
});

test('falha quando totais de seletores não reconciliam reescritas e remoções', () => {
  const input = fixture();
  input.report.files[0].addedSelectors = 1;
  input.report.files[0].selectorsAdded = ['.b'];
  const problems = validateAuditArtifacts(input);
  assert.ok(problems.some((problem) => problem.includes('identidade de seletores')));
});
