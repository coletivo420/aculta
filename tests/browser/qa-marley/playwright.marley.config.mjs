import { defineConfig } from '@playwright/test';

// Explicit QA-only browser suite: do not enroll in the generic public
// browser route discovery or run against a shared Runtime implicitly.
export default defineConfig({
  testDir: '.',
  testMatch: 'marley-cgu.spec.mjs',
  timeout: 45_000,
  expect: { timeout: 10_000 },
  reporter: [['list']],
  use: {
    browserName: process.env.ACULTA_BROWSER_ENGINE || 'chromium',
    headless: true,
    ignoreHTTPSErrors: process.env.ACULTA_BROWSER_ENV === 'local',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  workers: 1,
  fullyParallel: false,
});
