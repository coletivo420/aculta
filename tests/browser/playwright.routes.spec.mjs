// Runner padrão de cobertura por frente. Descobre cada routes.json sob
// tests/browser/<purpose>/<frente>/ e testa:
//   - public_routes: status abaixo de 400 e body visível;
//   - auth (opcional): login de teste com a conta indicada, e auth_routes depois do login.
// Formato do routes.json: ver tests/browser/README.md ("Cobertura por frente").
import { expect, test } from '@playwright/test';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { login } from './auth-login.mjs';

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

async function expectPageOk(page, url) {
  const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout });
  expect(response?.status() ?? 0, `status de ${url}`).toBeLessThan(400);
  await expect(page.locator('body')).toBeVisible();
}

for (const file of findRoutesFiles(root).sort()) {
  const spec = JSON.parse(readFileSync(file, 'utf8'));
  if (only.length && !only.includes(spec.front)) continue;
  const originEnv = spec.origin_env || 'ACULTA_SITE_ORIGIN';
  const origin = process.env[originEnv] || '';
  const authHost = process.env[spec.auth?.host_env || 'CONTA_HOST'] || origin;

  test.describe(`${spec.purpose}/${spec.front}`, () => {
    for (const route of spec.public_routes) {
      test(`${route.path}`, async ({ page }) => {
        test.skip(!origin, `defina ${originEnv} para testar ${spec.front}`);
        await expectPageOk(page, new URL(route.path, origin).toString());
      });
    }

    if (spec.auth) {
      test(`login de teste (${spec.auth.account})`, async ({ page }) => {
        test.skip(!authHost, `defina ${spec.auth.host_env || 'CONTA_HOST'} para o login de ${spec.front}`);
        await login(page, authHost, spec.auth.account);
      });

      for (const route of spec.auth_routes || []) {
        test(`autenticado ${route.path}`, async ({ page }) => {
          test.skip(!origin, `defina ${originEnv} para testar ${spec.front}`);
          await login(page, authHost, spec.auth.account);
          await expectPageOk(page, new URL(route.path, origin).toString());
        });
      }
    }
  });
}
