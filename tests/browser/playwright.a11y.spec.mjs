// Suíte de acessibilidade (frente F da matriz de gates).
// Reaproveita as rotas públicas dos routes.json de cada frente (sem contas novas) e testa:
//   - axe-core com WCAG 2.0/2.1/2.2 A e AA; falha somente em impacto "serious" ou "critical";
//   - reflow em 320 CSS px: sem rolagem horizontal da página;
//   - movimento reduzido: a suíte roda com prefers-reduced-motion ativo.
// Não corrige o tema: achado real vira registro em docs/operations/testing/, não ajuste de teste.
// Não cobre teclado manual, leitor de tela, nem contraste visual: ver skill aculta-acessibilidade.
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const only = (process.env.ACULTA_BROWSER_FRONTS || '').split(',').map((v) => v.trim()).filter(Boolean);
const timeout = Number(process.env.ACULTA_BROWSER_TIMEOUT_MS || 15_000);
const BLOCKING_IMPACTS = new Set(['serious', 'critical']);
const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

function findRoutesFiles(dir, found = []) {
  for (const entry of readdirSync(dir)) {
    if (entry === 'node_modules') continue;
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) findRoutesFiles(full, found);
    else if (entry === 'routes.json') found.push(full);
  }
  return found;
}

function formatViolations(violations) {
  return violations.map((v) => `${v.id} [${v.impact}] ${v.help} (${v.nodes.length} nó(s))`).join('\n');
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

test.describe('acessibilidade', () => {
  test.use({ reducedMotion: 'reduce' });

  for (const item of routes) {
    const label = `${item.purpose}/${item.front} ${item.path}`;

    test(`axe WCAG 2.2 A/AA sem bloqueio: ${label}`, async ({ page }) => {
      const origin = process.env[item.originEnv] || '';
      test.skip(!origin, `defina ${item.originEnv} para testar acessibilidade de ${item.front}`);
      const response = await page.goto(new URL(item.path, origin).toString(), { waitUntil: 'domcontentloaded', timeout });
      expect(response?.status() ?? 0, `status de ${item.path}`).toBeLessThan(400);

      const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze();
      const blocking = results.violations.filter((v) => BLOCKING_IMPACTS.has(v.impact));
      expect(blocking, formatViolations(blocking)).toEqual([]);
    });

    test(`reflow 320 px sem rolagem horizontal: ${label}`, async ({ page }) => {
      const origin = process.env[item.originEnv] || '';
      test.skip(!origin, `defina ${item.originEnv} para testar reflow de ${item.front}`);
      await page.setViewportSize({ width: 320, height: 800 });
      await page.goto(new URL(item.path, origin).toString(), { waitUntil: 'domcontentloaded', timeout });
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      expect(overflow, 'largura excedente em 320 CSS px').toBeLessThanOrEqual(0);
    });
  }
});
