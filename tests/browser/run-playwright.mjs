import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

const origin = process.env.ACULTA_SITE_ORIGIN;
const urls = process.env.ACULTA_BROWSER_URLS;
if (!origin && !urls) {
  console.error('PRÉ-REQUISITO AUSENTE: defina ACULTA_SITE_ORIGIN ou ACULTA_BROWSER_URLS.');
  process.exit(2);
}

const result = spawnSync(process.execPath, ['./node_modules/@playwright/test/cli.js', 'test', '--config=playwright.config.mjs', ...process.argv.slice(2)], {
  cwd: new URL('.', import.meta.url).pathname,
  stdio: 'inherit',
  env: process.env,
});

if (result.error) process.exit(1);
if (result.status === 0) process.exit(0);

const reportPath = process.env.SUITE_OUT ? `${process.env.SUITE_OUT}/playwright.json` : '';
let report = '';
try { report = reportPath ? readFileSync(reportPath, 'utf8') : ''; } catch {}
process.exit(result.status === 124 || /TimeoutError|timeout/i.test(report) ? 124 : 1);
