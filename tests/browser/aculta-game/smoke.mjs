// Navegação e apresentação no Firefox; POST, privacidade e acesso em http-smoke.py.
import { Browser, check, drush, oneTimeLogin, results, OUT, GD } from '../aculta-messenger/suite-lib.mjs';
import { readFileSync, writeFileSync } from 'node:fs';

const accountBase = process.env.GAME_ACCOUNT_BASE;
const mainBase = process.env.SUITE_BASE;
const skipResponsive = process.env.GAME_SKIP_RESPONSIVE === '1';
const adminName = process.env.GAME_ADMIN_ACCOUNT;
const deploy = JSON.parse(readFileSync(new URL('../../../web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json', import.meta.url)));
const hosts = new Set(deploy.domain_transition.entries.map(entry => entry.test_host));
for (const address of [accountBase, mainBase, process.env.SUITE_LOGIN_URI]) {
  if (!address || !hosts.has(new URL(address).hostname)) throw new Error('Informe somente hosts de teste do Deployer.');
}
if (adminName && !/^[A-Za-z0-9_.-]+$/.test(adminName)) throw new Error('GAME_ADMIN_ACCOUNT inválido.');
const browser = new Browser();
try {
  await browser.open('teste.comum');
  await browser.go(accountBase + '/atividade/conquistas');
  check('Área de atividade mostra conquistas e missões', await browser.exec(`return document.body.innerText.includes('Conquistas') && document.body.innerText.includes('Missões');`));
  check('Formulário aparece após navegação AJAX', await browser.waitFor(`document.querySelector('input[name="hide_all"]')`, 'Game'));
  check('Cada conquista informa estado e condição', await browser.exec(`return document.body.innerText.includes('Ainda não conquistada') && document.body.innerText.includes('Como conquistar');`));
  check('Conquistas de participação exibem as quatro artes temáticas', await browser.exec(`const srcs = [...document.querySelectorAll('img')].map(image => image.src); return ['primeiro-comentario-forum.png', 'primeira-edicao-wiki.png', 'primeiro-curso.png', 'perfil-basico.png'].every(file => srcs.some(src => src.includes(file)));`));
  check('Conta apresenta uma única área de Conquistas', await browser.exec(`return !document.body.innerText.includes('Troféus da comunidade') && !document.body.innerText.includes('Troféu');`));
  check('Três missões aparecem', await browser.exec(`return ['Primeira contribuição no Growroom420', 'Contribuição ao conhecimento', 'Primeira formação concluída'].every(t => document.body.innerText.includes(t));`));
  check('Controles de escolha têm rótulos associados', await browser.exec(`const inputs = [...document.querySelectorAll('input[name="hide_all"], input[name^="items["]')]; return inputs.length > 0 && inputs.every(input => input.labels && input.labels.length > 0);`));
  await browser.exec(`document.querySelector('[data-portal-account-data-content]')?.scrollIntoView(); return true;`);
  await browser.shot('game-conta-desktop');
  if (!skipResponsive) {
    const resized = await fetch(GD + browser.p('/window/rect'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ width: 390, height: 844 }) });
    if (!resized.ok) throw new Error('Falha ao redimensionar janela de teste.');
    check('Mobile mostra os controles', await browser.exec(`const input = document.querySelector('input[name="hide_all"]'); return !!input && input.getBoundingClientRect().width > 0;`));
    check('Mobile sem rolagem horizontal', await browser.exec(`return document.documentElement.scrollWidth <= window.innerWidth + 1;`));
    await browser.exec(`document.querySelector('input[name="hide_all"]')?.closest('form')?.scrollIntoView(); return true;`);
    await browser.shot('game-conta-mobile');
  }
  // Public profile: only earned achievements are shown, with no achievement filters/search.
  await fetch(GD + browser.p('/cookie'), { method: 'DELETE' });
  await browser.go(mainBase + '/u/pessoa-5');
  check('Perfil público mostra apenas conquistas obtidas', await browser.waitFor(`document.querySelector('.portal-profile__achievements') && document.body.innerText.includes('Perfil básico completo')`, 'conquistas do perfil'));
  check('Avatar público apresenta uma imagem', await browser.exec(`return !!document.querySelector('.portal-profile__avatar img');`));
  check('Conquista de perfil usa arte canábica própria', await browser.exec(`return !!document.querySelector('.portal-profile__achievement-image img[src*="perfil-basico.png"]');`));
  check('Imagens de conquistas têm alternativa textual', await browser.exec(`const images = [...document.querySelectorAll('.portal-profile__achievement-image img')]; return images.length > 0 && images.every(image => image.alt.trim() !== '');`));
  check('Conquistas usam cartões e grade responsiva', await browser.exec(`const list = document.querySelector('.portal-profile__achievements-list'); const item = document.querySelector('.portal-profile__achievement'); return !!list && !!item && getComputedStyle(list).display === 'grid' && !document.body.innerText.includes('Pesquisar conquistas');`));
  if (!skipResponsive) {
    const profileResized = await fetch(GD + browser.p('/window/rect'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ width: 390, height: 844 }) });
    if (!profileResized.ok) throw new Error('Falha ao redimensionar perfil para celular.');
    check('Perfil público de conquistas cabe no celular', await browser.exec(`return document.documentElement.scrollWidth <= window.innerWidth + 1 && !!document.querySelector('.portal-profile__achievement');`));
    await browser.shot('game-perfil-publico-mobile');
  }
  if (adminName) {
    const permitted = drush(`if (\\Drupal::database()->getConnectionOptions()['database'] !== 'aculta_runtime') { throw new \\RuntimeException('Somente Runtime de testes.'); } $users = \\Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => ${JSON.stringify(adminName)}]); $user = reset($users); if (!$user || !$user->isActive() || !$user->hasPermission('administer aculta game')) { throw new \\RuntimeException('A conta de smoke não possui a permissão Game necessária.'); } print 'authorized';`);
    check('Conta administrativa temporária tem permissão Game', permitted === 'authorized');
    // Nova sessão HTTP de admin no mesmo Firefox, sem encerrar processos de outra execução.
    await fetch(GD + browser.p('/cookie'), { method: 'DELETE' });
    await browser.go(oneTimeLogin(adminName));
    await browser.go(mainBase + '/painel-administrativo/game');
    check('Administração Game mostra três controles', await browser.waitFor(`document.querySelectorAll('input[type="checkbox"][name^="missions["]').length === 3`, 'controles administrativos Game'));
    await browser.exec(`document.querySelector('input[name="missions[comunidade]"]')?.closest('form')?.scrollIntoView(); return true;`);
    await browser.shot('game-administracao');
    await browser.go(mainBase + '/painel-administrativo/game/conquistas');
    check('Lista administrativa usa apenas Conquistas', await browser.exec(`return document.body.innerText.includes('Conquistas') && !document.body.innerText.includes('Troféus');`));
    await browser.go(mainBase + '/painel-administrativo/game/conquistas/criar');
    check('Editor informa 512 × 512 px e organiza nome, imagem e condições', await browser.exec(`return !!document.querySelector('input[name="identity[label]"]') && !!document.querySelector('input[type="file"]') && document.body.innerText.includes('512 × 512 px') && document.body.innerText.includes('Participação na comunidade') && document.body.innerText.includes('Apoio à associação') && document.body.innerText.includes('Basta cumprir uma das formas');`));
    await browser.go(mainBase + '/painel-administrativo/game/conquistas/fundadore/editar');
    check('Imagem atual tem ação de remoção separada', await browser.exec(`return [...document.querySelectorAll('a')].some(a => a.textContent.includes('Remover imagem atual'));`));
    await browser.go(mainBase + '/painel-administrativo/game/conquistas/fundadore/imagem/remover');
    check('Remoção abre confirmação com cancelar e confirmar', await browser.waitFor(`document.body.innerText.includes('não pode ser desfeita') && [...document.querySelectorAll('form input[type="submit"], form button[type="submit"]')].some(button => (button.value || button.innerText).includes('Confirmar remoção da imagem')) && document.body.innerText.includes('Voltar sem remover')`, 'Game'));
    const menu = JSON.parse(drush(`$manager = \\Drupal::service('plugin.manager.menu.link'); $notice = $manager->getDefinition('aculta_messenger.admin_notices'); $game = $manager->getDefinition('aculta_game.admin'); print json_encode(['parent' => $game['parent'], 'after' => $game['weight'] > $notice['weight']]);`));
    check('Game está abaixo de Avisos no menu', menu.parent === 'system.admin' && menu.after);
  }
  else {
    console.log('SKIP  trecho administrativo: GAME_ADMIN_ACCOUNT não foi informado.');
  }
}
finally {
  await browser.close();
  writeFileSync(OUT + '/game-results.json', JSON.stringify(results, null, 2));
}
console.log(`${results.filter(r => r.ok).length}/${results.length} verificações de navegador passaram.`);
if (results.some(r => !r.ok)) process.exitCode = 1;
