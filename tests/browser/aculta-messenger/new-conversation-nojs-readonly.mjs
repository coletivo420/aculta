import { Browser, OUT, check, results, sleep } from './suite-lib.mjs';
import { writeFileSync } from 'node:fs';

const browser = new Browser();
const accountBase = new URL(process.env.SUITE_LOGIN_URI || 'https://conta.aculta.toca.net.br').origin;
const searchForm = 'form[data-aculta-nojs-search]';
const selected = '[data-aculta-chip] input[name="members[]"]';

try {
  await browser.open('teste.comum', { javascript: false });
  await browser.go(`${accountBase}/avisos-mensagens/mensagens`);
  const startLink = 'a[data-aculta-conversation-url="/mensagens/nova"]';
  const startReady = await browser.waitFor(startLink, 'native new-conversation link');
  check('the inbox exposes a normal link to start a conversation', startReady);

  if (startReady) {
    const href = await browser.exec(`return document.querySelector(${JSON.stringify(startLink)}).getAttribute('href');`);
    check('the entry has a usable href with JavaScript disabled', href === '/mensagens/nova', String(href));
    await browser.go(new URL(href, `${accountBase}/`).href);

    const searchReady = await browser.waitFor(`${searchForm} input[name="q"]`, 'native GET member search');
    check('the native GET search is visible without JavaScript', searchReady && await browser.exec(`return document.querySelector(${JSON.stringify(searchForm)}).method.toLowerCase() === 'get' && !document.querySelector(${JSON.stringify(searchForm)}).hidden;`));
    if (searchReady) {
      await browser.typeIn(`${searchForm} input[name="q"]`, 'teste-b');
      await browser.pressKey('\uE007');
      const firstResult = 'a[data-aculta-add-member][data-aculta-member-name="teste-b"]';
      const firstFound = await browser.waitFor(firstResult, 'first member result');
      check('search returns a selectable first member', firstFound);
      if (firstFound) {
        const firstHref = await browser.exec(`return document.querySelector(${JSON.stringify(firstResult)}).getAttribute('href');`);
        check('the result carries its recipient ID in the group URL', /members%5B0%5D=\d+/.test(firstHref || ''), String(firstHref));
        await browser.exec(`document.querySelector(${JSON.stringify(firstResult)}).click(); return 1;`);
        const oneRecipient = await browser.waitFor(`location.pathname === '/mensagens/nova/grupo/painel' && document.querySelectorAll(${JSON.stringify(selected)}).length === 1`, 'group form with one recipient');
        check('the group form restores the selected recipient', oneRecipient);

        if (oneRecipient) {
          await browser.typeIn(`${searchForm} input[name="q"]`, 'teste-d');
          await browser.pressKey('\uE007');
          const secondResult = 'a[data-aculta-add-member][data-aculta-member-name="teste-d"]';
          const secondFound = await browser.waitFor(secondResult, 'second member result');
          check('search keeps working after one recipient is selected', secondFound);
          if (secondFound) {
            await browser.exec(`document.querySelector(${JSON.stringify(secondResult)}).click(); return 1;`);
            const twoRecipients = await browser.waitFor(`document.querySelectorAll(${JSON.stringify(selected)}).length === 2`, 'two selected recipients');
            check('the next result adds a distinct recipient without losing the first', twoRecipients && await browser.exec(`const ids = [...document.querySelectorAll(${JSON.stringify(selected)})].map((input) => input.value); return new Set(ids).size === 2;`));
            if (twoRecipients) {
              await browser.exec(`document.querySelector('[data-aculta-chip-remove]').click(); return 1;`);
              check('a chip can be removed by following its native link', await browser.waitFor(`document.querySelectorAll(${JSON.stringify(selected)}).length === 1`, 'one remaining recipient'));
            }
          }
        }
      }
    }
  }

  check('the first-message form remains POST and was not submitted', await browser.exec(`const form = document.querySelector('form[data-aculta-new-conversation-form]'); return !!form && form.method.toLowerCase() === 'post' && !!form.querySelector('textarea[data-portal-ui="new-conversation-message"]');`));
  const state = await browser.exec(`return JSON.stringify({ errors: window.__errs || [], url: location.pathname + location.search, width: innerWidth, scrollWidth: document.documentElement.scrollWidth });`);
  const details = JSON.parse(state);
  check('the no-JavaScript flow has no horizontal overflow', details.scrollWidth <= details.width, JSON.stringify(details));
  await browser.shot('new-conversation-nojs-readonly');
}
catch (error) {
  check('no-JavaScript browser smoke completed without an exception', false, String(error));
}
finally {
  await browser.close();
  writeFileSync(`${OUT}/new-conversation-nojs-results.json`, JSON.stringify(results, null, 2));
}

const failed = results.filter((result) => !result.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
process.exit(failed.length ? 1 : 0);
