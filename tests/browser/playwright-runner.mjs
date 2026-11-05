import { expect, test } from '@playwright/test';

const fronts = (process.env.ACULTA_BROWSER_FRONTS || process.env.ACULTA_BROWSER_FRONT || 'aculta-smoke')
  .split(',').map((value) => value.trim()).filter(Boolean);
const environment = process.env.ACULTA_BROWSER_ENV || 'desenvolvimento';
const origin = process.env.ACULTA_SITE_ORIGIN;
const rawUrls = process.env.ACULTA_BROWSER_URLS || origin || '';
const urls = rawUrls.split(',').map((url) => url.trim()).filter(Boolean);

if (!origin && urls.length === 0) {
  console.error('PRÉ-REQUISITO AUSENTE: defina ACULTA_SITE_ORIGIN ou ACULTA_BROWSER_URLS.');
  process.exitCode = 2;
}

if (urls.length > 0) {
  for (const front of fronts) {
    for (const url of urls) {
      test(`${front} — ${environment} — ${url}`, async ({ page }) => {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: Number(process.env.ACULTA_BROWSER_TIMEOUT_MS || 15_000) });
        await expect(page.locator('body')).toBeVisible();
        await expect(page).toHaveURL(url);
      });
    }
  }
}
