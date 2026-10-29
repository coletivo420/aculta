// Read-only authenticated smoke of the conversation drawer and group rows.
import { Browser, OUT, PROJECT, check, results } from './suite-lib.mjs';
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const browser = new Browser();
const drawer = `document.querySelector('[data-aculta-drawer-url][data-aculta-drawer-count="messages"] [data-aculta-drawer-content]')`;

try {
  await browser.open('teste.comum', { width: Number.parseInt(process.env.SUITE_WIDTH || '1280', 10) });
  check('authenticated account renders the Messenger trigger', await browser.waitFor(`document.querySelector('.aculta-messenger-trigger')`, 'Messenger trigger'));

  if (process.env.SUITE_CSS_OVERRIDE === '1') {
    const cssRoot = process.env.SUITE_CSS_ROOT ? resolve(process.env.SUITE_CSS_ROOT) : PROJECT;
    const candidateCss = readFileSync(resolve(cssRoot, 'web/themes/custom/aculta420/css/components/portal-cart.css'), 'utf8');
    const encodedCss = Buffer.from(candidateCss, 'utf8').toString('base64');
    const injected = await browser.exec(`const style = document.createElement('style');
      style.id = 'aculta-utility-drawer-candidate';
      style.textContent = atob(${JSON.stringify(encodedCss)});
      document.head.append(style); return Boolean(document.getElementById(style.id));`);
    check('candidate shared utility drawer CSS injected', !!injected);
  }

  const opened = await browser.openDrawer();
  check('conversation drawer opens', opened);
  if (opened) {
    const rowsReady = await browser.waitFor(
      `${drawer}?.querySelectorAll('div[data-aculta-thread-id]').length === 3`,
      'three conversation rows',
    );
    check('conversation rows finish loading', rowsReady);
    const state = JSON.parse(await browser.exec(`return JSON.stringify({
      path: new URL(location.href).pathname,
      rows: [...${drawer}.querySelectorAll('div[data-aculta-thread-id]')].map((row) => ({
        id: row.dataset.acultaThreadId || '',
        name: row.querySelector('a[data-aculta-conversation-url]')?.textContent.trim() || '',
        label: row.querySelector('a[data-aculta-conversation-url]')?.getAttribute('aria-label') || '',
        unread: row.dataset.acultaThreadUnread || '',
      })),
      fetches: window.__f || [],
      errors: window.__errs || [],
      viewport: { width: window.innerWidth, scrollWidth: document.documentElement.scrollWidth },
      utilityDrawer: (() => {
        const panel = document.querySelector('.aculta-messenger-offcanvas');
        if (!panel) return null;
        return {
          width: panel.getBoundingClientRect().width,
          configuredWidth: getComputedStyle(panel).getPropertyValue('--bs-offcanvas-width').trim(),
        };
      })(),
    });`));
    const request = state.fetches.find((item) => item.includes('/mensagens/resumo'));
    check('conversation list is fetched by GET with HTTP 200', !!request && request.startsWith('GET 200 '), JSON.stringify(request || state.fetches));
    check('the list presents three distinct synthetic conversations', state.rows.length === 3, `${state.rows.length} rows`);
    check('each row has a unique thread ID and an accessible open label',
      state.rows.length === 3
      && new Set(state.rows.map((row) => row.id)).size === 3
      && state.rows.every((row) => row.id !== '' && row.label !== ''),
      JSON.stringify(state.rows.map(({ id, label }) => ({ id, label }))),
    );
    const groupRows = state.rows.filter((row) => row.name.split(',').length > 1).length;
    check('group conversations remain separate list rows', groupRows === 2, `${groupRows} group rows`);
    check('read status is explicit for every conversation', state.rows.every((row) => ['true', 'false'].includes(row.unread)));
    check('conversation drawer has no horizontal overflow', state.viewport.scrollWidth <= state.viewport.width, JSON.stringify(state.viewport));
    const compactDrawer = state.viewport.width <= 560;
    const expectedDrawerWidth = Math.min(420, state.viewport.width);
    check('shared utility drawer width follows the 560px mobile-first boundary',
      !!state.utilityDrawer
      && state.utilityDrawer.configuredWidth === (compactDrawer ? '100vw' : 'min(26.25rem, 100vw)')
      && Math.abs(state.utilityDrawer.width - expectedDrawerWidth) <= 1,
      JSON.stringify({ viewport: state.viewport.width, expectedDrawerWidth, utilityDrawer: state.utilityDrawer }),
    );
    check('the read-only smoke issued no write request and reported no JavaScript error',
      !!request && state.fetches.every((item) => item.startsWith('GET ')) && state.errors.length === 0,
      JSON.stringify({ fetches: state.fetches, errors: state.errors }),
    );
    await browser.shot('conversation-list-readonly');
  }
}
catch (error) {
  check('browser smoke completed without an exception', false, String(error));
}
finally {
  await browser.close();
  writeFileSync(`${OUT}/results.json`, JSON.stringify(results, null, 2));
}

const failed = results.filter((result) => !result.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
process.exit(failed.length ? 1 : 0);
