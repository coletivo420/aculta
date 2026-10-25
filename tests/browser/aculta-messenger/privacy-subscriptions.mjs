// Read-only visual and keyboard check for followed-topic controls in Account privacy settings.
import { Browser, OUT, check, results } from './suite-lib.mjs';
import { writeFileSync } from 'node:fs';

const accountBase = process.env.SUITE_LOGIN_URI || 'https://conta.aculta.toca.net.br';
if (new URL(accountBase).hostname !== 'conta.aculta.toca.net.br') {
  throw new Error('The subscriptions test must use the ACULTA test account host.');
}

const viewportWidth = Number.parseInt(process.env.SUITE_WIDTH || '1280', 10);
const browser = new Browser();
try {
  await browser.open('teste.comum', { width: viewportWidth });
  await browser.go(`${accountBase}/configuracoes/privacidade`);

  const ready = await browser.waitFor(
    `[...document.querySelectorAll('details')].some((details) => details.querySelector('summary')?.textContent.trim() === 'Tópicos que você segue')`,
    'followed topics disclosure',
  );
  check('Account privacy page renders followed-topic management', ready);
  if (ready) {
    const initial = JSON.parse(await browser.exec(`
      const details = [...document.querySelectorAll('details')].find((item) => item.querySelector('summary')?.textContent.trim() === 'Tópicos que você segue');
      const summary = details.querySelector('summary');
      summary.id = 'aculta-test-followed-topics';
      return JSON.stringify({
        keyboardFocusable: summary.tabIndex === 0,
        description: details.textContent.includes('Seguir um tópico controla os avisos; não altera o acesso ao conteúdo.'),
        rows: details.querySelectorAll('table tbody tr').length,
        empty: details.textContent.includes('Nenhum tópico seguido.'),
        viewport: window.innerWidth,
        page: document.documentElement.scrollWidth,
      });
    `));
    check('Disclosure is keyboard-focusable and explains follow semantics', initial.keyboardFocusable && initial.description, JSON.stringify(initial));
    const overlayCount = await browser.exec(`return document.querySelectorAll('.ui-widget-overlay').length;`);
    check('Privacy form is not blocked by a modal overlay', overlayCount === 0, `${overlayCount} overlay(s)`);
    if (overlayCount !== 0) throw new Error('A modal blocks the privacy form; no subscription controls were activated.');
    await browser.clickSelector('#aculta-test-followed-topics');
    const openedByClick = await browser.waitFor(
      `[...document.querySelectorAll('details')].some((details) => details.querySelector('summary')?.id === 'aculta-test-followed-topics' && details.open)`,
      'followed topics disclosure opened by click',
    );
    check('Pointer opens the followed-topic list', openedByClick);
    await browser.pressKey('\uE007');
    const closedByEnter = await browser.waitFor(
      `[...document.querySelectorAll('details')].some((details) => details.querySelector('summary')?.id === 'aculta-test-followed-topics' && !details.open)`,
      'followed topics disclosure closed by Enter',
    );
    check('Enter toggles the disclosure closed', closedByEnter);
    await browser.pressKey('\uE007');
    const expanded = await browser.waitFor(
      `[...document.querySelectorAll('details')].some((details) => details.querySelector('summary')?.id === 'aculta-test-followed-topics' && details.open)`,
      'followed topics disclosure reopened by Enter',
    );
    check('Enter toggles the disclosure open', expanded);

    const content = JSON.parse(await browser.exec(`
      const details = [...document.querySelectorAll('details')].find((item) => item.querySelector('summary')?.textContent.trim() === 'Tópicos que você segue');
      return JSON.stringify({
        rows: details.querySelectorAll('table tbody tr').length,
        empty: details.textContent.includes('Nenhum tópico seguido.'),
        removeControl: !!details.querySelector('input[type="submit"][value="Parar de seguir selecionados"]'),
        description: details.textContent.includes('Seguir um tópico controla os avisos; não altera o acesso ao conteúdo.'),
      });
    `));
    check('List or empty state is readable without changing subscriptions', content.rows > 0 || content.empty, JSON.stringify(content));
    check('Removing subscriptions is an explicit form action', content.rows === 0 ? !content.removeControl : content.removeControl, JSON.stringify(content));

    const dimensions = JSON.parse(await browser.exec(`return JSON.stringify({ viewport: window.innerWidth, page: document.documentElement.scrollWidth });`));
    check('Privacy settings reflow without horizontal page overflow', dimensions.page <= dimensions.viewport, JSON.stringify(dimensions));
    await browser.shot('privacy-subscriptions');
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
writeFileSync(`${OUT}/privacy-subscriptions-results.json`, JSON.stringify(results, null, 2));
console.log(`\n${results.length - failed.length}/${results.length} privacy subscription checks passed`);
process.exit(failed.length ? 1 : 0);
