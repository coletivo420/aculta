// Evidência de árvore de acessibilidade e zoom nativo no painel do Fórum.
// Usa a conta administrativa dos secrets via auth-login.mjs; não imprime valores.
import { firefox } from '@playwright/test';
import { execFileSync, spawn } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { login } from '../auth-login.mjs';

const origin = process.env.ACULTA_SITE_ORIGIN || 'https://aculta.toca.net.br';
const contaHost = process.env.ACULTA_CONTA_ORIGIN || 'https://conta.aculta.toca.net.br';
const out = process.env.SUITE_OUT;
if (!out) throw new Error('SUITE_OUT é obrigatório');

const orca = spawn('orca', ['--replace', '--no-setup'], {
  env: { ...process.env, DISPLAY: process.env.DISPLAY || ':0' },
  stdio: 'ignore',
});
await new Promise((resolve) => setTimeout(resolve, 2500));
const browser = await firefox.launch({ headless: process.env.ACULTA_BROWSER_HEADLESS === '1' });
const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await context.newPage();
const result = { origin, urls: {}, a11y: {}, zoom: {}, errors: [] };
try {
  await login(page, contaHost, 'teste_administrativo');
  const panel = `${origin}/painel-administrativo/aculta/estatisticas/area/forum`;
  await page.goto(panel, { waitUntil: 'domcontentloaded' });
  result.urls.panel = page.url();
  result.status = (await page.request.get(panel)).status();
  result.a11y.snapshot = await page.locator('main').ariaSnapshot();
  result.a11y.tables = await page.locator('main table').count();
  result.a11y.captions = await page.locator('main table caption').count();
  result.a11y.scopedHeaders = await page.locator('main th[scope="col"]').count();
  try {
    execFileSync('python3', [new URL('./atspi_dump.py', import.meta.url).pathname], {
      env: { ...process.env, DISPLAY: process.env.DISPLAY || ':0', SUITE_OUT: out },
      timeout: 60000,
    });
    result.a11y.atspi = 'coletado';
  } catch (error) {
    result.a11y.atspi = `indisponível: ${String(error?.message || error).slice(0, 200)}`;
  }

  for (let i = 0; i < 4; i++) await page.keyboard.press('Control+=');
  await page.waitForTimeout(300);
  result.zoom = await page.evaluate(() => ({
    devicePixelRatio: window.devicePixelRatio,
    innerWidth: window.innerWidth,
    scrollWidth: document.documentElement.scrollWidth,
    overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
  }));

  // Zoom nativo em nível de navegador: devPixelsPerPx=2 equivale a 200%.
  const zoomBrowser = await firefox.launch({
    headless: process.env.ACULTA_BROWSER_HEADLESS === '1',
    firefoxUserPrefs: { 'layout.css.devPixelsPerPx': '2' },
  });
  const zoomContext = await zoomBrowser.newContext({ viewport: { width: 1280, height: 900 } });
  const zoomPage = await zoomContext.newPage();
  await login(zoomPage, contaHost, 'teste_administrativo');
  await zoomPage.goto(panel, { waitUntil: 'domcontentloaded' });
  result.zoom.native200 = await zoomPage.evaluate(() => ({
    devicePixelRatio: window.devicePixelRatio,
    innerWidth: window.innerWidth,
    scrollWidth: document.documentElement.scrollWidth,
    overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    h1: document.querySelector('main h1')?.innerText || '',
    url: location.href,
    main: !!document.querySelector('main'),
  }));
  result.zoom.nativeStatus = (await zoomPage.request.get(panel)).status();
  await zoomBrowser.close();
} catch (error) {
  result.errors.push(String(error?.message || error));
} finally {
  try { orca.kill('SIGTERM'); } catch { /* ignore */ }
  writeFileSync(join(out, 'statistics-accessibility.json'), JSON.stringify(result, null, 2) + '\n');
  await browser.close();
}
if (result.errors.length) process.exitCode = 1;
