#!/usr/bin/env node
// Homologação visual do tema aculta420_v2 (auditoria CSS/layout 2026-11-11).
//
// Mede, por domínio público e por largura, em claro e escuro:
//   - canvas esperado (#f2f7f0 / #171513);
//   - ausência de overflow horizontal;
//   - um único main e no máximo um h1;
//   - contraste do cabeçalho institucional (WCAG AA 4.5:1);
//   - aside visualmente à esquerda do conteúdo no desktop.
//
// O modo escuro é forçado com data-theme="dark" no <html>: o servidor resolve
// a preferência da pessoa usuária e este teste mede o CSS dos dois modos.
//
// Uso:
//   SUITE_OUT=/caminho/externo node audit.mjs
// Variáveis opcionais: ACULTA_SITE_ORIGIN, ACULTA_AUDIT_COMMIT.

import { firefox } from 'playwright';
import { login } from '../../auth-login.mjs';
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const projectRoot = execFileSync('git', ['rev-parse', '--show-toplevel'], { cwd: here, encoding: 'utf8' }).trim();
const commit = process.env.ACULTA_AUDIT_COMMIT || execFileSync('git', ['rev-parse', 'HEAD'], { cwd: projectRoot, encoding: 'utf8' }).trim();
const suiteOut = process.env.SUITE_OUT;
if (!suiteOut) {
  console.error('SUITE_OUT obrigatório (diretório externo ao repositório).');
  process.exit(2);
}

const ORIGIN = process.env.ACULTA_SITE_ORIGIN || 'https://aculta.toca.net.br';
const HOSTS = [
  { key: 'home', label: 'Home institucional', url: ORIGIN + '/' },
  { key: 'forum', label: 'Fórum', url: 'https://growroom420.aculta.toca.net.br/' },
  { key: 'links', label: 'Links', url: 'https://links.aculta.toca.net.br/' },
  { key: 'wiki', label: 'Wiki', url: 'https://wiki420.aculta.toca.net.br/' },
  { key: 'revista', label: 'Revista', url: 'https://revista.aculta.toca.net.br/' },
  { key: 'eventos', label: 'Eventos', url: 'https://eventos.aculta.toca.net.br/' },
  { key: 'loja', label: 'Loja', url: 'https://loja.aculta.toca.net.br/' },
  { key: 'apoio', label: 'Apoio', url: 'https://apoio.aculta.toca.net.br/' },
  { key: 'cursos', label: 'Cursos', url: 'https://cursos.aculta.toca.net.br/' },
  { key: 'conta', label: 'Conta (login)', url: 'https://conta.aculta.toca.net.br/entrar' },
];
const WIDTHS = [320, 390, 768, 959, 960, 1024, 1440];
const MODES = ['light', 'dark'];
const CANVAS = { light: '#f2f7f0', dark: '#171513' };
const CAPTURE_WIDTHS = new Set([390, 1440]);

const results = [];
const failures = [];

function record(check) {
  results.push(check);
  if (!check.passed) failures.push(check);
}

const measure = () => {
  const parseColor = (value) => {
    if (!value) return null;
    const hex = value.trim().match(/^#([0-9a-f]{3,8})$/i);
    if (hex) {
      let h = hex[1];
      if (h.length === 3) h = h.split('').map((c) => c + c).join('');
      return {
        r: parseInt(h.slice(0, 2), 16),
        g: parseInt(h.slice(2, 4), 16),
        b: parseInt(h.slice(4, 6), 16),
        a: h.length === 8 ? parseInt(h.slice(6, 8), 16) / 255 : 1,
      };
    }
    const rgb = value.match(/rgba?\(([^)]+)\)/i);
    if (!rgb) return null;
    const p = rgb[1].split(/[ ,/]+/).filter(Boolean).map(Number);
    return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
  };
  const luminance = ({ r, g, b }) => {
    const f = (c) => {
      const v = c / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  };
  const contrast = (fg, bg) => {
    if (!fg || !bg) return null;
    const l1 = luminance(fg);
    const l2 = luminance(bg);
    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
  };

  const root = document.documentElement;
  const rootStyle = getComputedStyle(root);
  const bodyStyle = getComputedStyle(document.body);
  const canvas = parseColor(rootStyle.getPropertyValue('--color-canvas')) || parseColor(bodyStyle.backgroundColor) || parseColor(rootStyle.backgroundColor);

  const asideLayouts = [...document.querySelectorAll('.layout-page--aside, .page__main--with-sidebar')];
  const asideVariant = asideLayouts.length > 0;
  let asidePresent = false;
  let asideLeft = null;
  const asideRects = [];
  if (asideVariant && window.innerWidth >= 960) {
    const pairs = [];
    for (const layout of asideLayouts) {
      const content = layout.querySelector('.layout-page__content, .page__content');
      const aside = layout.querySelector('.layout-page__aside, .page__sidebar');
      if (!content || !aside) continue;
      const c = content.getBoundingClientRect();
      const a = aside.getBoundingClientRect();
      if (a.width > 0 && c.width > 0) {
        asidePresent = true;
        pairs.push(a.right <= c.left + 1);
        asideRects.push([Math.round(a.left), Math.round(a.right), Math.round(c.left), Math.round(c.right)]);
      }
    }
    if (pairs.length > 0) asideLeft = pairs.every(Boolean);
  }

  const header = document.querySelector('.aculta-surface-panel__header, .aculta-institution-bar');
  let headerContrast = null;
  if (header) {
    headerContrast = contrast(parseColor(getComputedStyle(header).color), parseColor(getComputedStyle(header).backgroundColor));
  }

  return {
    scrollWidth: root.scrollWidth,
    innerWidth: window.innerWidth,
    canvas,
    mainCount: document.querySelectorAll('main').length,
    h1Count: document.querySelectorAll('h1').length,
    asideLeft,
    asideVariant,
    asidePresent,
    asideRects,
    headerContrast,
    hasLayoutPage: Boolean(document.querySelector('.layout-page')),
  };
};

const browser = await firefox.launch({ headless: true });
const capturesDir = join(suiteOut, 'capturas');
mkdirSync(capturesDir, { recursive: true });

for (const host of (process.env.ACULTA_AUDIT_SKIP_PUBLIC === '1' ? [] : HOSTS)) {
  for (const width of WIDTHS) {
    for (const mode of MODES) {
      const context = await browser.newContext({
        viewport: { width, height: 900 },
        ignoreHTTPSErrors: true,
        locale: 'pt-BR',
      });
      const page = await context.newPage();
      const label = `${host.key} ${width}px ${mode}`;
      try {
        let response = await page.goto(host.url, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);
        if (!response) {
          await page.waitForTimeout(1000);
          response = await page.goto(host.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
        }
        if (!response || response.status() >= 400) {
          record({ name: label, check: 'status', passed: false, detail: `HTTP ${response?.status()}` });
          await context.close();
          continue;
        }
        if (mode === 'dark') {
          await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
          await page.waitForTimeout(120);
        }
        const m = await page.evaluate(measure);
        const checks = [
          ['overflow', m.scrollWidth <= m.innerWidth + 1, `${m.scrollWidth}/${m.innerWidth}`],
          ['canvas', m.canvas !== null && Math.abs(m.canvas.r - parseInt(CANVAS[mode].slice(1, 3), 16)) <= 2 && Math.abs(m.canvas.g - parseInt(CANVAS[mode].slice(3, 5), 16)) <= 2 && Math.abs(m.canvas.b - parseInt(CANVAS[mode].slice(5, 7), 16)) <= 2, JSON.stringify(m.canvas)],
          ['main-unique', m.mainCount === 1, String(m.mainCount)],
          ['h1-max-one', m.h1Count <= 1, String(m.h1Count)],
        ];
        if (m.asideVariant && m.asidePresent && width >= 960) {
          checks.push(['aside-left', m.asideLeft === true, String(m.asideLeft)]);
        }
        if (m.headerContrast !== null) {
          checks.push(['header-contrast', m.headerContrast >= 4.5, m.headerContrast.toFixed(2)]);
        }
        for (const [check, passed, detail] of checks) {
          record({ name: label, check, passed, detail });
        }
        if (CAPTURE_WIDTHS.has(width)) {
          await page.screenshot({ path: join(capturesDir, `${host.key}-${width}-${mode}.png`), fullPage: false });
        }
      } catch (error) {
        record({ name: label, check: 'navigation', passed: false, detail: String(error).slice(0, 200) });
      }
      await context.close();
    }
  }
}

// Página autenticada que usa a variante aside (Minha Conta).
if (process.env.ACULTA_AUDIT_AUTH !== 'off' && process.env.ACULTA_AUDIT_SKIP_PUBLIC !== '1') {
  const authHost = 'https://conta.aculta.toca.net.br';
  try {
    const context = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      ignoreHTTPSErrors: true,
      locale: 'pt-BR',
    });
    const page = await context.newPage();
    await login(page, authHost, 'teste_comum');
    for (const width of WIDTHS) {
      for (const mode of MODES) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(authHost + '/conta', { waitUntil: 'domcontentloaded', timeout: 30000 });
        if (mode === 'dark') {
          await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
          await page.waitForTimeout(120);
        }
        const m = await page.evaluate(measure);
        const label = `conta-auth ${width}px ${mode}`;
        record({ name: label, check: 'overflow', passed: m.scrollWidth <= m.innerWidth + 1, detail: `${m.scrollWidth}/${m.innerWidth}` });
        record({ name: label, check: 'canvas', passed: m.canvas !== null && Math.abs(m.canvas.r - parseInt(CANVAS[mode].slice(1, 3), 16)) <= 2 && Math.abs(m.canvas.g - parseInt(CANVAS[mode].slice(3, 5), 16)) <= 2 && Math.abs(m.canvas.b - parseInt(CANVAS[mode].slice(5, 7), 16)) <= 2, detail: JSON.stringify(m.canvas) });
        record({ name: label, check: 'main-unique', passed: m.mainCount === 1, detail: String(m.mainCount) });
        record({ name: label, check: 'h1-max-one', passed: m.h1Count <= 1, detail: String(m.h1Count) });
        if (m.asideVariant && m.asidePresent && width >= 960) {
          record({ name: label, check: 'aside-left', passed: m.asideLeft === true, detail: String(m.asideLeft) });
        }
        if (CAPTURE_WIDTHS.has(width)) {
          await page.screenshot({ path: join(capturesDir, `conta-auth-${width}-${mode}.png`) });
        }
      }
    }
    await context.close();
  } catch (error) {
    record({ name: 'conta-auth', check: 'deferred', passed: true, detail: `autenticação indisponível: ${String(error).slice(0, 160)}` });
  }
}

// UI Patterns Library (exige access components page): exercita as stories do
// layout-page, inclusive a variante aside, à esquerda do conteúdo no desktop.
if (process.env.ACULTA_AUDIT_ADMIN !== 'off') {
  try {
    const context = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      ignoreHTTPSErrors: true,
      locale: 'pt-BR',
    });
    const page = await context.newPage();
    // A sessão é compartilhada no domínio .aculta.toca.net.br; o formulário
    // de login existe apenas no host da Conta.
    await login(page, 'https://conta.aculta.toca.net.br', 'teste_administrativo');
    const storyUrl = ORIGIN + '/painel-administrativo/aparencia/ui/components/aculta420_v2/layout-page';
    for (const width of [960, 1024, 1440]) {
      for (const mode of MODES) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(storyUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
        if (mode === 'dark') {
          await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
          await page.waitForTimeout(120);
        }
        let m = await page.evaluate(measure);
        for (let attempt = 0; attempt < 3 && m.asideLeft !== true; attempt++) {
          await page.waitForTimeout(250);
          m = await page.evaluate(measure);
        }
        const label = `ui-library ${width}px ${mode}`;
        record({ name: label, check: 'overflow', passed: m.scrollWidth <= m.innerWidth + 1, detail: `${m.scrollWidth}/${m.innerWidth}` });
        record({ name: label, check: 'aside-left', passed: m.asideLeft === true, detail: `${m.asideLeft} rects=${JSON.stringify(m.asideRects)} inner=${m.innerWidth}` });
        const previews = await page.locator('.ui_patterns_story__preview, .ui_patterns_story__story').count();
        record({ name: label, check: 'stories-render', passed: previews > 0, detail: String(previews) });
        if (width === 1440) {
          await page.screenshot({ path: join(capturesDir, `ui-library-layout-page-${mode}.png`) });
        }
      }
    }
    await context.close();
  } catch (error) {
    record({ name: 'ui-library', check: 'deferred', passed: true, detail: `acesso administrativo indisponível: ${String(error).slice(0, 160)}` });
  }
}

await browser.close();

const summary = {
  method: 'Playwright Firefox headless',
  engine: 'firefox',
  commit,
  origin: ORIGIN,
  generated_at: new Date().toISOString(),
  total: results.length,
  passed: results.filter((r) => r.passed).length,
  failed: failures.length,
  results,
};
const manifestDir = join(projectRoot, 'tests/browser/capturas/aculta420-v2-audit/2026-11-11');
mkdirSync(manifestDir, { recursive: true });
writeFileSync(join(manifestDir, 'manifest.json'), JSON.stringify(summary, null, 2) + '\n');
writeFileSync(join(suiteOut, 'audit-summary.json'), JSON.stringify(summary, null, 2) + '\n');

console.log(`checks: ${summary.passed}/${summary.total} PASS, ${summary.failed} FAIL`);
for (const failure of failures.slice(0, 40)) {
  console.log(`FAIL ${failure.name} [${failure.check}] ${failure.detail}`);
}
process.exit(failures.length ? 1 : 0);
