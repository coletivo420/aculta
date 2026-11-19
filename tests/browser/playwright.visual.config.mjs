import base from './playwright.config.mjs';

// Config da regressão visual. Herda o runner base e acrescenta:
// - updateSnapshots 'none': snapshot faltante ou divergente falha; nunca é atualizado sozinho;
// - saídas temporárias em SUITE_OUT; baselines aprovadas continuam versionadas;
// - candidatas novas em SUITE_OUT somente no modo de baseline revisável;
// - um único projeto de navegador para reduzir variação entre motores.
const output = process.env.SUITE_OUT;
const baselineMode = process.env.ACULTA_VISUAL_BASELINE === '1' && Boolean(output);

export default {
  ...base,
  testMatch: /playwright\.visual\.spec\.mjs$/,
  // SUITE_OUT guarda artefatos; referências aprovadas só mudam de modo controlado.
  // Candidatas ausentes vão para lá, sem substituir ou regravar snapshots existentes.
  updateSnapshots: baselineMode ? 'missing' : 'none',
  snapshotPathTemplate: baselineMode
    ? `${output}/snapshots/{testFilePath}/{arg}{ext}`
    : '{testDir}/__snapshots__/{testFilePath}/{arg}{ext}',
  outputDir: output ? `${output}/test-results-visual` : undefined,
  reporter: [['line'], ['json', { outputFile: output ? `${output}/playwright-visual.json` : 'playwright-visual.json' }]],
  expect: {
    toHaveScreenshot: { maxDiffPixelRatio: 0, animations: 'disabled' },
  },
};
