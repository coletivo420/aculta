import { expect, test } from '@playwright/test';

const defaultFronts = [
  'aculta-avatar', 'aculta-calendar-admin', 'aculta-calendar-interactive', 'aculta-calendar-piloto',
  'aculta-conta-raiz', 'aculta-content-admin', 'aculta-cpf-checkout', 'aculta-document-viewer',
  'aculta-editor', 'aculta-events', 'aculta-faq', 'aculta-forum', 'aculta-forum-lms', 'aculta-frete',
  'aculta-gallery', 'aculta-game', 'aculta-ganjalab', 'aculta-htmx-f1', 'aculta-image-admin',
  'aculta-links', 'aculta-lms', 'aculta-lms-resources', 'aculta-maps', 'aculta-mercadopago',
  'aculta-messenger', 'aculta-patcher', 'aculta-portal-cep', 'aculta-qrcode', 'aculta-secrets-manager',
  'aculta-statistics', 'aculta-studio', 'aculta-theme-mobile-first', 'aculta-uploader', 'aculta-visual',
  'aculta-wiki', 'aculta420-v2-card-accent', 'aculta420-v2-data-table', 'aculta420-v2-empty-state',
  'aculta420-v2-forum', 'aculta420-v2-navigation', 'aculta420-v2-wiki', 'eventos', 'revista-color',
  'revista-editorial',
];
const fronts = (process.env.ACULTA_BROWSER_FRONTS || process.env.ACULTA_BROWSER_FRONT || defaultFronts.join(','))
  .split(',').map((value) => value.trim()).filter(Boolean);
const environment = process.env.ACULTA_BROWSER_ENV || 'desenvolvimento';
const origin = process.env.ACULTA_SITE_ORIGIN;
const rawUrls = process.env.ACULTA_BROWSER_URLS || origin || '';
const urls = rawUrls.split(',').map((url) => url.trim()).filter(Boolean);

test.describe.configure({ mode: 'parallel' });

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
