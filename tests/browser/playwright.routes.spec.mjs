// Runner padrão de cobertura por frente. Descobre cada routes.json sob
// tests/browser/<purpose>/<frente>/ e testa somente as rotas públicas declaradas.
// Formato do routes.json: ver tests/browser/README.md ("Cobertura por frente").
import { expect, test } from '@playwright/test';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const only = (process.env.ACULTA_BROWSER_FRONTS || '').split(',').map((v) => v.trim()).filter(Boolean);
const timeout = Number(process.env.ACULTA_BROWSER_TIMEOUT_MS || 15_000);

function findRoutesFiles(dir, found = []) {
  for (const entry of readdirSync(dir)) {
    if (entry === 'node_modules') continue;
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) findRoutesFiles(full, found);
    else if (entry === 'routes.json') found.push(full);
  }
  return found;
}

for (const file of findRoutesFiles(root).sort()) {
  const spec = JSON.parse(readFileSync(file, 'utf8'));
  if (only.length && !only.includes(spec.front)) continue;
  const originEnv = spec.origin_env || 'ACULTA_SITE_ORIGIN';
  const origin = process.env[originEnv] || '';

  test.describe(`${spec.purpose}/${spec.front}`, () => {
    for (const route of spec.public_routes) {
      test(`${route.path}`, async ({ page }) => {
        test.skip(!origin, `defina ${originEnv} para testar ${spec.front}`);
        const url = new URL(route.path, origin).toString();
        const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout });
        expect(response?.status() ?? 0, `status de ${url}`).toBeLessThan(400);
        await expect(page.locator('body')).toBeVisible();
      });
    }
  });
}
