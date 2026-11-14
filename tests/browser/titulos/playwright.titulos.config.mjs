import base from '../playwright.config.mjs';

// Frente de títulos ACULTA420 V2: Firefox, desktop 1280/1920 e mobile 390,
// claro e escuro, com capturas versionadas por manifesto.
const output = process.env.SUITE_OUT;

export default {
  ...base,
  testDir: '.',
  testMatch: /playwright\.titulos(?:-a11y)?\.spec\.mjs$/,
  outputDir: output ? `${output}/test-results-titulos` : undefined,
  reporter: [['line'], ['json', { outputFile: output ? `${output}/playwright-titulos.json` : 'playwright-titulos.json' }]],
};
