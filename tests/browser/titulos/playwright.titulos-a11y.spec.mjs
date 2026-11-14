import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

/**
 * Contraste e semântica dos títulos ACULTA420 V2 (skill aculta-acessibilidade).
 *
 * axe-core restrito ao H1 renderizado pelo SDC heading: regras WCAG 2.0/2.1/2.2
 * A e AA dentro do título, em claro e escuro. Achados serious/critical bloqueiam.
 * A automação não substitui revisão manual de teclado e leitor de tela.
 */

const SITES = [
  { key: 'portal', url: 'https://aculta.toca.net.br/' },
  { key: 'revista', url: 'https://revista.aculta.toca.net.br/' },
  { key: 'wiki', url: 'https://wiki420.aculta.toca.net.br/' },
  { key: 'eventos', url: 'https://eventos.aculta.toca.net.br/' },
  { key: 'forum', url: 'https://growroom420.aculta.toca.net.br/' },
  { key: 'loja', url: 'https://loja.aculta.toca.net.br/' },
  { key: 'cursos', url: 'https://cursos.aculta.toca.net.br/' },
];

const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const BLOCKING = new Set(['serious', 'critical']);

for (const site of SITES) {
  for (const mode of ['light', 'dark']) {
    test(`axe títulos ${site.key} ${mode}`, async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 900 });
      await page.goto(site.url, { waitUntil: 'domcontentloaded' });
      await page.evaluate((theme) => document.documentElement.setAttribute('data-theme', theme), mode);
      await page.locator('h1.aculta-heading').waitFor();

      const results = await new AxeBuilder({ page })
        .include('h1.aculta-heading')
        .withTags(WCAG_TAGS)
        .analyze();

      const blocking = results.violations.filter((violation) => BLOCKING.has(violation.impact));
      expect(
        blocking.map((violation) => `${violation.id} [${violation.impact}] ${violation.help}`),
        'violações serious/critical no H1',
      ).toEqual([]);
    });
  }
}
