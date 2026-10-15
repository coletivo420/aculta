// Navegação e apresentação no Firefox; POST, privacidade e acesso em http-smoke.py.
import { Browser, check, drush, oneTimeLogin, results, OUT, GD } from '../aculta-messenger/suite-lib.mjs';
import { readFileSync, writeFileSync } from 'node:fs';

const accountBase = process.env.GAME_ACCOUNT_BASE;
const mainBase = process.env.SUITE_BASE;
const deploy = JSON.parse(readFileSync(new URL('../../../web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json', import.meta.url)));
const hosts = new Set(deploy.domain_transition.entries.map(entry => entry.test_host));
for (const address of [accountBase, mainBase, process.env.SUITE_LOGIN_URI]) {
  if (!address || !hosts.has(new URL(address).hostname)) throw new Error('Informe somente hosts de teste do Deployer.');
}
const adminName = drush(`if (\\Drupal::database()->getConnectionOptions()['database'] !== 'aculta_runtime') { throw new \\RuntimeException('Somente Runtime de testes.'); } print \\Drupal::entityTypeManager()->getStorage('user')->load(1)->getAccountName();`);
const browser = new Browser();
try {
  await browser.open('teste.comum');
  await browser.go(accountBase + '/');
  check('Visão geral mostra Conquistas e missões', await browser.exec(`return document.body.innerText.includes('Conquistas e missões');`));
  check('Entrada da visão geral é navegável', await browser.exec(`const link = [...document.querySelectorAll('a')].find(a => a.textContent.includes('Ver missões e escolher')); if (!link) return false; link.click(); return true;`));
  check('Formulário aparece após navegação AJAX', await browser.waitFor(`document.querySelector('input[name="hide_all"]')`, 'Game'));
  check('Três missões aparecem', await browser.exec(`return ['Primeira contribuição no Growroom420', 'Contribuição ao conhecimento', 'Primeira formação concluída'].every(t => document.body.innerText.includes(t));`));
  await browser.exec(`document.querySelector('[data-portal-account-data-content]')?.scrollIntoView(); return true;`);
  await browser.shot('game-conta-desktop');
  const resized = await fetch(GD + browser.p('/window/rect'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ width: 390, height: 844 }) });
  if (!resized.ok) throw new Error('Falha ao redimensionar janela de teste.');
  check('Mobile mostra os controles', await browser.exec(`return !!document.querySelector('input[name="hide_all"]');`));
  check('Mobile sem rolagem horizontal', await browser.exec(`return document.documentElement.scrollWidth <= window.innerWidth + 1;`));
  await browser.shot('game-conta-mobile');
  // Nova sessão HTTP de admin no mesmo Firefox, sem encerrar processos de outra execução.
  await fetch(GD + browser.p('/cookie'), { method: 'DELETE' });
  await browser.go(oneTimeLogin(adminName));
  await browser.go(mainBase + '/painel-administrativo/game');
  check('Administração Game mostra três controles', await browser.exec(`return document.querySelectorAll('input[type="checkbox"][name^="missions["]').length === 3;`));
  await browser.exec(`document.querySelector('input[name="missions[comunidade]"]')?.closest('form')?.scrollIntoView(); return true;`);
  await browser.shot('game-administracao');
  const menu = JSON.parse(drush(`$manager = \\Drupal::service('plugin.manager.menu.link'); $notice = $manager->getDefinition('aculta_messenger.admin_notices'); $game = $manager->getDefinition('aculta_game.admin'); print json_encode(['parent' => $game['parent'], 'after' => $game['weight'] > $notice['weight']]);`));
  check('Game está abaixo de Avisos no menu', menu.parent === 'system.admin' && menu.after);
}
finally {
  await browser.close();
  writeFileSync(OUT + '/game-results.json', JSON.stringify(results, null, 2));
}
console.log(`${results.filter(r => r.ok).length}/${results.length} verificações de navegador passaram.`);
if (results.some(r => !r.ok)) process.exitCode = 1;
