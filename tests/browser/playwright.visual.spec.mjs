// Regressão visual determinística (ver skill aculta-regressao-visual).
// Reaproveita as rotas públicas dos routes.json. Para cada rota:
//   - viewports 320, 390, 768, 1024 e 1440 CSS px;
//   - temas claro e escuro (atributo data-theme em <html>, afirmado antes do screenshot);
//   - sem overflow horizontal;
//   - screenshot comparado com referência em SUITE_OUT. Snapshot faltante FALHA (updateSnapshots "none").
// Referências versionadas só depois de revisão humana. Não use --update-snapshots.
import { expect, test } from '@playwright/test';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const only = (process.env.ACULTA_BROWSER_FRONTS || '').split(',').map((v) => v.trim()).filter(Boolean);
const timeout = Number(process.env.ACULTA_BROWSER_TIMEOUT_MS || 20_000);
const VIEWPORTS = [
  { name: '320', width: 320, height: 800 },
  { name: '390', width: 390, height: 844 },
  { name: '768', width: 768, height: 1024 },
  { name: '1024', width: 1024, height: 768 },
  { name: '1440', width: 1440, height: 900 },
];
const THEMES = ['light', 'dark'];
// Dados voláteis: mascarados para não variar entre execuções.
const VOLATILE = ['[data-volatile]', 'time', '[data-portal-ui="clock"]', 'img[src*="avatar"]'];

function findRoutesFiles(dir, found = []) {
  for (const entry of readdirSync(dir)) {
    if (entry === 'node_modules') continue;
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) findRoutesFiles(full, found);
    else if (entry === 'routes.json') found.push(full);
  }
  return found;
}

const routes = [];
for (const file of findRoutesFiles(root).sort()) {
  const spec = JSON.parse(readFileSync(file, 'utf8'));
  if (only.length && !only.includes(spec.front)) continue;
  const originEnv = spec.origin_env || 'ACULTA_SITE_ORIGIN';
  for (const route of spec.public_routes || []) {
    routes.push({ purpose: spec.purpose, front: spec.front, originEnv, path: route.path });
  }
}

test.describe('regressão visual', () => {
  test.use({ reducedMotion: 'reduce', deviceScaleFactor: 1 });

  for (const item of routes) {
    for (const theme of THEMES) {
      for (const vp of VIEWPORTS) {
        const label = `${item.purpose}/${item.front} ${item.path} ${theme} ${vp.name}px`;
        test(label, async ({ page }) => {
          const origin = process.env[item.originEnv] || '';
          test.skip(!origin, `defina ${item.originEnv} para a regressão visual de ${item.front}`);
          await page.setViewportSize({ width: vp.width, height: vp.height });
          await page.emulateMedia({ colorScheme: theme, reducedMotion: 'reduce' });
          await page.goto(new URL(item.path, origin).toString(), { waitUntil: 'load', timeout });
          await page.evaluate((mode) => document.documentElement.setAttribute('data-theme', mode), theme);
          expect(await page.evaluate(() => document.documentElement.getAttribute('data-theme'))).toBe(theme);
          await page.evaluate(() => document.fonts.ready);

          const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
          expect(overflow, 'overflow horizontal').toBeLessThanOrEqual(0);

          const mask = VOLATILE.map((selector) => page.locator(selector));
          await expect(page).toHaveScreenshot(`${item.front}-${theme}-${vp.name}.png`, {
            fullPage: true,
            animations: 'disabled',
            caret: 'hide',
            mask,
            maxDiffPixelRatio: 0,
          });
        });
      }
    }
  }
});
