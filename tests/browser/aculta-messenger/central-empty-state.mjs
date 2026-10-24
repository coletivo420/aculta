// Focused visual check for the filtered empty state in the account notice center.
import { Browser, OUT, check, results } from './suite-lib.mjs';
import { writeFileSync } from 'node:fs';

const accountBase = process.env.SUITE_LOGIN_URI || 'https://conta.aculta.toca.net.br';
if (new URL(accountBase).hostname !== 'conta.aculta.toca.net.br') {
  throw new Error('The notice center test must use the ACULTA test account host.');
}

const browser = new Browser();
try {
  await browser.open('teste.b');
  await browser.go(`${accountBase}/avisos-mensagens/avisos?origem=doacao&leitura=read`);

  const accountFiltersVisible = await browser.waitFor(
    `document.querySelector('[data-aculta-notice-filters]')`,
    'account notice filters',
  );
  check('Account host renders the filtered notice center', accountFiltersVisible);

  const rendered = JSON.parse(await browser.exec(`
    const root = document.querySelector('[data-aculta-account-list]');
    const state = root?.querySelector('.aculta-messenger-state--empty');
    const filters = document.querySelector('[data-aculta-notice-filters]');
    return JSON.stringify({
      stateRole: state?.getAttribute('role') || null,
      stateText: state?.textContent.trim() || '',
      statePadding: state ? Number.parseFloat(getComputedStyle(state).paddingTop) : 0,
      selectedFilters: [...(filters?.querySelectorAll('[aria-current="page"]') || [])].map((link) => link.textContent.trim()),
    });
  `));

  check(
    'The filtered empty result is announced as a status in the account list',
    rendered.stateRole === 'status' && rendered.stateText.includes('Nenhum aviso corresponde aos filtros selecionados'),
    JSON.stringify({ role: rendered.stateRole, text: rendered.stateText }),
  );
  check(
    'Both origin and read-state filters remain active',
    rendered.selectedFilters.length === 2,
    JSON.stringify(rendered.selectedFilters),
  );
  check(
    'The empty state receives the Messenger theme spacing',
    rendered.statePadding >= 16,
    `${rendered.statePadding}px`,
  );
  await browser.shot('central-empty-state');
}
catch (error) {
  console.error(error);
  results.push({ name: 'Browser execution', ok: false, detail: error.message });
}
finally {
  await browser.close();
}

const failed = results.filter((result) => !result.ok);
writeFileSync(`${OUT}/central-empty-state-results.json`, JSON.stringify(results, null, 2));
console.log(`\n${results.length - failed.length}/${results.length} focused central checks passed`);
process.exit(failed.length ? 1 : 0);
