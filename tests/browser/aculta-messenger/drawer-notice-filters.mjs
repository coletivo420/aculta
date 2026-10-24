// Focused interaction check for source/read filters in the global notice drawer.
import { Browser, OUT, check, results } from './suite-lib.mjs';
import { writeFileSync } from 'node:fs';

const browser = new Browser();
const drawer = `document.querySelector('[data-aculta-drawer-count="alerts"] [data-aculta-drawer-content]')`;
const viewportWidth = Number.parseInt(process.env.SUITE_WIDTH || '1280', 10);
try {
  await browser.open('teste.b', { width: viewportWidth });
  await browser.exec(`document.querySelector('.aculta-alerts-trigger')?.click(); return 1;`);
  const ready = await browser.waitFor(`${drawer}?.querySelector('[data-aculta-notice-filters]')`, 'notice drawer filters');
  const initialState = ready ? null : JSON.parse(await browser.exec(`return JSON.stringify({ trigger: !!document.querySelector('.aculta-alerts-trigger'), drawerOpen: document.querySelector('[data-aculta-drawer-count="alerts"]')?.classList.contains('show') || false, state: ${drawer}?.querySelector('[data-aculta-drawer-state]')?.dataset.acultaDrawerState || null, errors: window.__errs || [], fetches: window.__f || [] });`));
  check('Opening the header notice drawer shows origin and read filters', ready, initialState ? JSON.stringify(initialState) : '');
  if (ready) {
    const groups = await browser.exec(`return ${drawer}.querySelectorAll('.aculta-alerts-filters__group').length;`);
    check('Filters are presented as two compact disclosure groups', groups === 2, `${groups} groups`);

    await browser.exec(`${drawer}.querySelector('.aculta-alerts-filters__group').open = true; return 1;`);
    await browser.exec(`[...${drawer}.querySelectorAll('[data-aculta-drawer-filter]')].find((link) => link.textContent.trim() === 'Wiki')?.click(); return 1;`);
    const wikiSelected = await browser.waitFor(
      `[...${drawer}.querySelectorAll('[data-aculta-notice-filters] [aria-current="page"]')].some((link) => link.textContent.trim() === 'Wiki')`,
      'Wiki filter selection',
    );
    check('Selecting an origin reloads the notice fragment in the same drawer', wikiSelected);

    await browser.exec(`${drawer}.querySelectorAll('.aculta-alerts-filters__group')[1].open = true; return 1;`);
    await browser.exec(`[...${drawer}.querySelectorAll('[data-aculta-drawer-filter]')].find((link) => link.textContent.trim() === 'Não lidos')?.click(); return 1;`);
    const bothSelected = await browser.waitFor(
      `${drawer}.querySelectorAll('[data-aculta-notice-filters] [aria-current="page"]').length === 2`,
      'combined origin and read filters',
    );
    const selected = JSON.parse(await browser.exec(`return JSON.stringify([...${drawer}.querySelectorAll('[data-aculta-notice-filters] [aria-current="page"]')].map((link) => ({ text: link.textContent.trim(), href: link.href, fragment: link.dataset.acultaDrawerFilterUrl })));`));
    check('Selecting read state preserves the selected origin', bothSelected && selected.some((item) => item.text === 'Wiki') && selected.some((item) => item.text === 'Não lidos'), JSON.stringify(selected.map((item) => item.text)));
    const fallbacks = selected.every((item) => new URL(item.href).hostname === 'conta.aculta.toca.net.br');
    const fragments = selected.every((item) => {
      const query = new URL(item.fragment, item.href).searchParams;
      return query.get('origem') === 'wiki' && query.get('leitura') === 'unread';
    });
    check('Filter links keep an account-page fallback and combined fragment URL', fallbacks && fragments);
    const dimensions = JSON.parse(await browser.exec(`return JSON.stringify({ viewport: window.innerWidth, page: document.documentElement.scrollWidth, drawer: ${drawer}.scrollWidth, drawerClient: ${drawer}.clientWidth });`));
    check('Drawer filters reflow without horizontal overflow', dimensions.page <= dimensions.viewport && dimensions.drawer <= dimensions.drawerClient + 1, JSON.stringify(dimensions));
    await browser.shot('drawer-notice-filters');
  }
}
catch (error) {
  console.error(error);
  results.push({ name: 'Browser execution', ok: false, detail: error.message });
}
finally {
  await browser.close();
}

const failed = results.filter((result) => !result.ok);
writeFileSync(`${OUT}/drawer-notice-filters-results.json`, JSON.stringify(results, null, 2));
console.log(`\n${results.length - failed.length}/${results.length} drawer notice filter checks passed`);
process.exit(failed.length ? 1 : 0);
