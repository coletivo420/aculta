import { defineConfig } from '@playwright/test';

// Run only in an isolated QA installation with synthetic accounts.
export default defineConfig({
  testDir: '.',
  testMatch: 'we-sativa-moderation.spec.mjs',
  reporter: [['list']],
  workers: 1,
  fullyParallel: false,
  use: {
    browserName: 'chromium',
    headless: true,
    ignoreHTTPSErrors: process.env.ACULTA_BROWSER_ENV === 'local',
  },
});
