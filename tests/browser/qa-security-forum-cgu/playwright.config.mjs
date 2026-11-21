import { defineConfig } from '@playwright/test';

// Separate suite: never auto-enrolled in the general Runtime checks.
export default defineConfig({
  testDir: '.',
  testMatch: 'sfc01-sfc02.spec.mjs',
  reporter: [['list']],
  workers: 1,
  fullyParallel: false,
  timeout: 45_000,
  use: {
    browserName: process.env.ACULTA_BROWSER_ENGINE || 'chromium',
    headless: true,
    ignoreHTTPSErrors: process.env.ACULTA_BROWSER_ENV === 'local',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
});
