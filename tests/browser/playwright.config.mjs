import { defineConfig } from '@playwright/test';

const output = process.env.SUITE_OUT;

export default defineConfig({
  testDir: '.',
  testMatch: /playwright\.spec\.mjs$/,
  outputDir: output ? `${output}/test-results` : undefined,
  reporter: [['line'], ['json', { outputFile: output ? `${output}/playwright.json` : 'playwright.json' }]],
  use: {
    browserName: 'firefox',
    headless: true,
    ignoreHTTPSErrors: process.env.ACULTA_BROWSER_ENV === 'local',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  workers: Number(process.env.PLAYWRIGHT_WORKERS || 1),
});
