import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { expect, test } from '@playwright/test';

/**
 * Títulos ACULTA420 V2 pelo SDC heading.
 *
 * Confere por texto (fonte, tamanho, altura de linha, contraste, H1 único,
 * estouro de largura) nas páginas públicas das áreas do Portal, em claro e
 * escuro, mobile 390 e desktop 1280/1920. As imagens ficam em
 * capturas/titulos/<data>/ com manifesto versionado.
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

const VIEWPORTS = [
  { width: 390, height: 844, h1Min: 30, h1Max: 36 },
  { width: 1280, height: 900, h1Min: 55, h1Max: 65 },
  { width: 1920, height: 1080, h1Min: 63, h1Max: 65 },
];

const MODES = ['light', 'dark'];

const date = process.env.ACULTA_CAPTURA_DATA || new Date().toISOString().slice(0, 10);
const OUT = process.env.ACULTA_CAPTURAS
  || path.join(process.cwd(), 'capturas', 'titulos', date);
const commit = (() => {
  try {
    return execSync('git rev-parse HEAD', { cwd: process.cwd(), encoding: 'utf8' }).trim();
  }
  catch {
    return null;
  }
})();
const results = [];

fs.mkdirSync(OUT, { recursive: true });

for (const site of SITES) {
  for (const viewport of VIEWPORTS) {
    for (const mode of MODES) {
      test(`${site.key} ${viewport.width}px ${mode}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await page.goto(site.url, { waitUntil: 'domcontentloaded' });
        await page.evaluate((theme) => document.documentElement.setAttribute('data-theme', theme), mode);
        await page.locator('h1.aculta-heading').waitFor();

        const metrics = await page.evaluate(() => {
          const parse = (value) => {
            const match = value.match(/rgba?\(([^)]+)\)/);
            if (!match) {
              return null;
            }
            const parts = match[1].split(/[ ,/]+/).filter(Boolean).map(Number);
            return { r: parts[0], g: parts[1], b: parts[2], a: parts[3] ?? 1 };
          };
          const relativeLuminance = ({ r, g, b }) => {
            const channel = (v) => {
              const s = v / 255;
              return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
            };
            return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
          };
          const composite = (top, bottom) => ({
            r: top.r * top.a + bottom.r * (1 - top.a),
            g: top.g * top.a + bottom.g * (1 - top.a),
            b: top.b * top.a + bottom.b * (1 - top.a),
            a: 1,
          });
          const backgroundOf = (element) => {
            const layers = [];
            let node = element;
            while (node) {
              const color = parse(getComputedStyle(node).backgroundColor);
              if (color && color.a > 0) {
                layers.push(color);
                if (color.a === 1) {
                  break;
                }
              }
              node = node.parentElement;
            }
            let base = { r: 255, g: 255, b: 255, a: 1 };
            for (let i = layers.length - 1; i >= 0; i -= 1) {
              base = composite(layers[i], base);
            }
            return base;
          };

          const headings = [...document.querySelectorAll('h1')];
          const h1 = headings[0];
          const style = getComputedStyle(h1);
          const fontSize = parseFloat(style.fontSize);
          const lineHeight = parseFloat(style.lineHeight);
          const foreground = parse(style.color);
          const background = backgroundOf(h1);
          const fgLum = relativeLuminance(foreground);
          const bgLum = relativeLuminance(background);
          const contrast = (Math.max(fgLum, bgLum) + 0.05) / (Math.min(fgLum, bgLum) + 0.05);
          const rect = h1.getBoundingClientRect();

          return {
            h1Count: headings.length,
            headingComponents: document.querySelectorAll('h1.aculta-heading').length,
            text: h1.textContent.trim().slice(0, 80),
            fontFamily: style.fontFamily,
            fontSize,
            lineHeight: Number.isFinite(lineHeight) ? lineHeight : fontSize * 1.1,
            lineHeightRatio: Number((lineHeight / fontSize).toFixed(3)),
            fontWeight: Number(style.fontWeight),
            overflowWrap: style.overflowWrap,
            wordBreak: style.wordBreak,
            contrast: Number(contrast.toFixed(2)),
            headingWidth: Math.ceil(rect.width),
            headingOverflow: h1.scrollWidth - h1.clientWidth,
            pageOverflow: document.documentElement.scrollWidth - window.innerWidth,
          };
        });

        const checks = {
          single_h1: metrics.h1Count === 1,
          sdc_heading: metrics.headingComponents === 1,
          oswald: /Oswald/i.test(metrics.fontFamily),
          font_size: metrics.fontSize >= viewport.h1Min && metrics.fontSize <= viewport.h1Max,
          line_height: metrics.lineHeightRatio >= 1.04 && metrics.lineHeightRatio <= 1.16,
          contrast_aa: metrics.contrast >= 4.5,
          word_break: metrics.wordBreak === 'normal' && metrics.overflowWrap !== 'anywhere',
          no_heading_overflow: metrics.headingOverflow <= 1,
          no_page_overflow: metrics.pageOverflow <= 1,
        };

        const shot = path.join(OUT, `${site.key}-${mode}-${viewport.width}.png`);
        await page.screenshot({ path: shot, fullPage: false });

        results.push({
          site: site.key,
          url: site.url,
          viewport_css_px: viewport.width,
          mode,
          screenshot: path.relative(path.join(process.cwd(), '..', '..'), shot),
          checks,
          metrics,
        });

        expect(checks.single_h1, 'H1 único por página').toBe(true);
        expect(checks.sdc_heading, 'H1 renderizado pelo SDC heading').toBe(true);
        expect(checks.oswald, 'fonte Oswald').toBe(true);
        expect(checks.font_size, `tamanho do H1 entre ${viewport.h1Min} e ${viewport.h1Max} px`).toBe(true);
        expect(checks.line_height, 'altura de linha ~1.1').toBe(true);
        expect(checks.contrast_aa, 'contraste WCAG AA 4,5:1').toBe(true);
        expect(checks.word_break, 'palavras não quebradas no meio').toBe(true);
        expect(checks.no_heading_overflow, 'título dentro da largura').toBe(true);
        expect(checks.no_page_overflow, 'página sem rolagem horizontal').toBe(true);
      });
    }
  }
}

test.afterAll(() => {
  const manifest = {
    frente: 'titulos',
    data: date,
    metodo: 'Playwright com Firefox (headless)',
    motor: 'firefox',
    ambiente: 'desenvolvimento',
    commit,
    viewports: VIEWPORTS.map(({ width }) => width),
    modos: MODES,
    resultados: results.map(({ screenshot, ...rest }) => ({ ...rest, screenshot })),
    status: results.every(({ checks }) => Object.values(checks).every(Boolean)) ? 'PASS' : 'FAIL',
  };
  fs.writeFileSync(path.join(OUT, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);
});
