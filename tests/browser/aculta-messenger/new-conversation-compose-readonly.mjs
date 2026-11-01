// Read-only authenticated smoke of recipient search and local group chips.
// Never submits the first-message form.
import { Browser, OUT, check, results, sleep } from './suite-lib.mjs';
import { writeFileSync } from 'node:fs';

const browser = new Browser();
const drawer = `document.querySelector('[data-aculta-drawer-url][data-aculta-drawer-count="messages"] [data-aculta-drawer-content]')`;
const form = `${drawer}?.querySelector('form[data-aculta-new-conversation]')`;
const search = `[data-aculta-drawer-url][data-aculta-drawer-count="messages"] input[name="q"]`;

try {
  await browser.open('teste.comum', { width: Number.parseInt(process.env.SUITE_WIDTH || '1280', 10) });
  check('authenticated account has the Messenger trigger', await browser.waitFor(`document.querySelector('.aculta-messenger-trigger')`, 'Messenger trigger'));
  check('conversation drawer opens', await browser.openDrawer());

  const startReady = await browser.waitFor(`${drawer}?.querySelector('[data-aculta-conversation-url="/mensagens/nova"]')`, 'new conversation link');
  check('drawer offers the shared new-conversation flow', startReady);
  if (startReady) {
    await browser.exec(`${drawer}.querySelector('[data-aculta-conversation-url="/mensagens/nova"]').click(); return 1;`);
    const formReady = await browser.waitFor(`${form} && ${form}.querySelector('textarea[data-portal-ui="new-conversation-message"]')`, 'compose form');
    check('compose view has member search, first-message field, and configured recipient limit', formReady && await browser.exec(`return !!(${form}.querySelector('input[name="q"]') && Number(${form}.dataset.acultaGroupMax) >= 2);`));

    if (formReady) {
      await browser.typeIn(search, 'teste-b');
      await sleep(700);
      await browser.pressKey('\uE007');
      const firstChip = await browser.waitFor(`${form}.querySelector('[data-aculta-chip] input[name="members[]"]')`, 'first keyboard-selected recipient');
      check('Enter adds the matching member as a local recipient chip', firstChip && await browser.exec(`return ${form}.querySelector('[data-aculta-chip] .aculta-chip__name')?.textContent.trim() === 'teste-b';`));

      await browser.typeIn(search, 'teste-d');
      const resultReady = await browser.waitFor(`${form}.querySelector('[data-aculta-add-member][data-aculta-member-name="teste-d"]')`, 'second member search result');
      check('eligible second member appears in the GET search preview', resultReady);
      if (resultReady) {
        await browser.exec(`${form}.querySelector('[data-aculta-add-member][data-aculta-member-name="teste-d"]').click(); return 1;`);
        check('click adds a second distinct chip without losing the first', await browser.waitFor(`${form}.querySelectorAll('[data-aculta-chip] input[name="members[]"]').length === 2`, 'two recipient chips')
          && await browser.exec(`const ids = [...${form}.querySelectorAll('[data-aculta-chip] input[name="members[]"]')].map((input) => input.value); return new Set(ids).size === 2;`));
      }

      check('recipient can be removed locally with its accessible control', await browser.exec(`const button = ${form}.querySelector('[data-aculta-chip-remove]'); if (!button || !button.getAttribute('aria-label')) return 0; button.click(); return 1;`)
        && await browser.waitFor(`${form}.querySelectorAll('[data-aculta-chip] input[name="members[]"]').length === 1`, 'one remaining recipient'));

      const state = JSON.parse(await browser.exec(`return JSON.stringify({
        selected: [...${form}.querySelectorAll('[data-aculta-chip] input[name="members[]"]')].map((input) => input.value),
        requests: window.__f || [], errors: window.__errs || [],
        viewport: { width: innerWidth, scrollWidth: document.documentElement.scrollWidth },
      });`));
      check('the smoke issued only GET requests and never submitted the form', state.requests.length > 0 && state.requests.every((request) => request.startsWith('GET ')), JSON.stringify(state.requests));
      check('compose controls have no horizontal overflow or JavaScript errors', state.viewport.scrollWidth <= state.viewport.width && state.errors.length === 0, JSON.stringify({ viewport: state.viewport, errors: state.errors }));
      await browser.shot('new-conversation-compose-readonly');
    }
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
