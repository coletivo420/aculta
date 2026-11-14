import base from './playwright.config.mjs';

// Config da regressão visual. Herda o runner base e acrescenta:
// - updateSnapshots 'none': snapshot faltante ou divergente falha; nunca é atualizado sozinho;
// - referências e saídas em SUITE_OUT, fora do repositório, até revisão humana;
// - um único projeto de navegador para reduzir variação entre motores.
const output = process.env.SUITE_OUT;

export default {
  ...base,
  testMatch: /playwright\.visual\.spec\.mjs$/,
  // 'missing' só com ACULTA_VISUAL_BASELINE=1, para gerar candidatas em SUITE_OUT para revisão.
  // Nunca 'all': atualização de referência divergente exige revisão humana.
  updateSnapshots: process.env.ACULTA_VISUAL_BASELINE === '1' && output ? 'missing' : 'none',
  snapshotPathTemplate: output
    ? `${output}/snapshots/{testFilePath}/{arg}{ext}`
    : '{testDir}/__snapshots__/{testFilePath}/{arg}{ext}',
  outputDir: output ? `${output}/test-results-visual` : undefined,
  reporter: [['line'], ['json', { outputFile: output ? `${output}/playwright-visual.json` : 'playwright-visual.json' }]],
  expect: {
    toHaveScreenshot: { maxDiffPixelRatio: 0, animations: 'disabled' },
  },
};
