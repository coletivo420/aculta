import { spawnSync } from 'node:child_process';

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
process.exit(result.status === 124 ? 124 : result.status === 0 ? 0 : 1);
