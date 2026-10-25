// S-R5: comparação somente leitura Conta Studio x legado e Perfil x MAIN.
// Firefox ESR visível, geckodriver WebDriver + BiDi; NUNCA headless/produção.
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const ROOT = fileURLToPath(new URL('../../../', import.meta.url));
const ACCOUNT = (process.env.SR5_ACCOUNT_ORIGIN || 'https://conta.aculta.toca.net.br').replace(/\/$/, '');
const MAIN = (process.env.SR5_MAIN_ORIGIN || 'https://aculta.toca.net.br').replace(/\/$/, '');
const DRIVER = process.env.SR5_WEBDRIVER || 'http://127.0.0.1:4444';
const OUT = process.env.SUITE_OUT || '/tmp/aculta-studio-s-r5';
const USERS = [process.env.SR5_USER_A, process.env.SR5_USER_B];
const SLUGS = [process.env.SR5_APELIDO_A, process.env.SR5_APELIDO_B];
const SENTINELS = [process.env.SR5_BIO_SENTINELA_A, process.env.SR5_BIO_SENTINELA_B];
const PUBLIC = [process.env.SR5_PERFIL_PUBLICO_A, process.env.SR5_PERFIL_PUBLICO_B];
const DOCUMENTS = process.env.SR5_DOCUMENTOS || '';
const results = [];

if (process.env.DISPLAY !== ':0') throw Error('Exige DISPLAY=:0 e Firefox ESR visível.');
if (new URL(ACCOUNT).origin !== 'https://conta.aculta.toca.net.br'
  || new URL(MAIN).origin !== 'https://aculta.toca.net.br') {
  throw Error('Esta suíte aceita somente ACCOUNT e MAIN do Homelab; produção proibida.');
}
if (USERS.some(value => !value) || USERS[0] === USERS[1]) throw Error('Defina duas contas sintéticas distintas SR5_USER_A/B.');
if (SLUGS.some(value => !value || !/^[a-z0-9-]+$/.test(value)) || SLUGS[0] === SLUGS[1]) {
  throw Error('Defina dois apelidos de fixture distintos em minúsculas [a-z0-9-].');
}
if (!['', 'enabled', 'disabled'].includes(DOCUMENTS)) throw Error('SR5_DOCUMENTOS: enabled ou disabled.');
if (PUBLIC.some(value => value && !['yes', 'no'].includes(value))) throw Error('SR5_PERFIL_PUBLICO_A/B: yes ou no.');
mkdirSync(OUT, { recursive: true, mode: 0o700 });

function record(name, status, details = '') {
  if (!['PASS', 'FAIL', 'SKIP'].includes(status)) throw Error('Status inválido');
  results.push({ name, status, details });
  console.log(`${status}: ${name}${details ? ` — ${details}` : ''}`);
}
function check(name, verdict, details = '') {
  record(name, verdict === null ? 'SKIP' : verdict ? 'PASS' : 'FAIL', details);
}
const sleep = ms => new Promise(done => setTimeout(done, ms));
async function wd(method, path, body) {
  const response = await fetch(DRIVER + path, {
    method, headers: { 'content-type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const value = (await response.json()).value;
  if (!response.ok || value?.error) throw Error(`WebDriver ${method} ${path}: ${value?.error || response.status}`);
  return value;
}
function oneTimeLogin(user) {
  // URL de login usada apenas em memória; nunca gravada no relatório.
  const stdout = execFileSync('php', [
    'vendor/drush/drush/drush.php', 'uli', '--no-browser', `--name=${user}`, `--uri=${ACCOUNT}`,
  ], { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  const url = stdout.trim().split('\n').at(-1);
  if (!url?.startsWith(ACCOUNT + '/')) throw Error('Drush uli retornou origem de login inesperada.');
  return url;
}
async function firefox(javascriptEnabled = true) {
  const result = await wd('POST', '/session', { capabilities: { alwaysMatch: {
    browserName: 'firefox', acceptInsecureCerts: true, webSocketUrl: true,
    'moz:firefoxOptions': {
      binary: '/usr/bin/firefox-esr',
      prefs: { 'javascript.enabled': javascriptEnabled, 'network.dns.localDomains': 'conta.aculta.toca.net.br,aculta.toca.net.br' },
    },
  } } });
  if (!result.capabilities?.webSocketUrl) throw Error('Firefox sem WebDriver BiDi');
  const base = endpoint => `/session/${result.sessionId}${endpoint}`;
  return {
    url: full => wd('POST', base('/url'), { url: full }),
    currentUrl: () => wd('GET', base('/url')),
    source: () => wd('GET', base('/source')),
    eval: source => wd('POST', base('/execute/sync'), { script: source, args: [] }),
    screenshot: async name => {
      const bytes = Buffer.from(await wd('GET', base('/screenshot')), 'base64');
      writeFileSync(join(OUT, `${name}.png`), bytes, { mode: 0o600 });
    },
    close: () => wd('DELETE', base('')).catch(() => {}),
    ws: result.capabilities.webSocketUrl,
  };
}
async function resize(browser, width) {
  const socket = new WebSocket(browser.ws);
  await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
  let id = 0;
  try {
    const call = (method, params) => new Promise((resolve, reject) => {
      const n = ++id;
      const timeout = setTimeout(() => reject(Error('Timeout BiDi: ' + method)), 12000);
      socket.onmessage = event => {
        const message = JSON.parse(event.data);
        if (message.id !== n) return;
        clearTimeout(timeout);
        message.error ? reject(Error(message.error)) : resolve(message.result);
      };
      socket.send(JSON.stringify({ id: n, method, params }));
    });
    const context = (await call('browsingContext.getTree', {})).contexts[0].context;
    await call('browsingContext.setViewport', {
      context, viewport: { width, height: width <= 390 ? 844 : 900 },
    });
  } finally { socket.close(); }
}

// Não recolher valores privados de formulários. Exportar apenas nomes dos campos,
// número de registros, marcadores booleanos e métricas geométricas.
const inspectJs = `return JSON.stringify((() => {
  const forms = [...document.querySelectorAll('form')].filter(f => !f.closest('header,nav,[role="banner"]'));
  const fields = [...new Set(forms.flatMap(form => [...form.querySelectorAll('input,select,textarea')]
    .filter(el => !['hidden','submit','button','search','file'].includes(el.type))
    .map(el => el.name || '').filter(name => name && name !== 'op')))].sort();
  const ownName = document.querySelector('.aculta-profile-identity__name');
  const text = document.body?.innerText || '';
  const nav = performance.getEntriesByType('navigation').at(-1);
  const tables = [...document.querySelectorAll('table')];
  return {
    pathname: location.pathname, origin: location.origin,
    status: nav?.responseStatus || 0,
    fields, formCount: forms.length,
    tableCount: tables.length,
    rowCount: tables.reduce((n,t) => n + t.querySelectorAll('tbody tr').length,0),
    accountHome: !!document.querySelector('[data-portal-ui="studio-account-home"],.aculta-studio-account-home'),
    profileEditor: !!document.querySelector('.aculta-profile-editor'),
    legacyShell: !!document.querySelector('#portal-account-body,[data-portal-account-menu]'),
    publicProfile: !!document.querySelector('[data-portal-ui="public-profile"],.aculta-public-profile'),
    privateName: ownName?.textContent.trim() || '',
    publicBio: !!document.querySelector('[data-portal-ui="public-profile-bio"],.aculta-public-profile-bio'),
    publicLinks: !!document.querySelector('[data-portal-ui="public-profile-links"],.aculta-public-profile-links'),
    publicLinkFromEditor: document.querySelector('.aculta-profile-identity__public-link a')?.href || '',
    anchorHrefs: [...document.querySelectorAll('main a[href]')].map(a=>a.getAttribute('href')).filter(Boolean),
    screenWidth: innerWidth,
    pageOverflow: document.documentElement.scrollWidth > innerWidth + 1,
    markerFlags: (window.__sr5Markers || []).map(marker => !!marker && text.includes(marker)),
    formNamed: forms.every(f => !!(f.getAttribute('id') || f.getAttribute('aria-label'))),
  };
})());`;

const cases = [
  ['cadastro', '/studio/conta/dados', '/dados', 'different'],
  ['endereco', '/studio/conta/endereco', '/dados/endereco', 'forms'],
  ['documentos', '/studio/conta/documentos', '/dados/outros-dados', 'optional'],
  ['seguranca-email', '/studio/conta/seguranca', '/seguranca', 'forms'],
  ['seguranca-senha', '/studio/conta/seguranca/senha', '/seguranca/senha', 'forms'],
  ['conexoes', '/studio/conta/conexoes', '/conexoes', 'visual'],
  // O Portal não registra uma rota legada /pedidos: usar dashboard como
  // referência visual, sem alegar equivalência funcional de tabelas.
  ['pedidos', '/studio/conta/pedidos', '/conta-legada', 'reference'],
  ['apoio', '/studio/conta/apoio', '/meu-apoio', 'visual'],
  ['preferencias', '/studio/conta/preferencias', '/configuracoes', 'forms'],
];
async function inspect(browser, url) {
  await browser.url(url);
  await sleep(450);
  // Exclui deliberadamente o valor das sentinelas e todo conteúdo privado do retorno.
  await browser.eval(`window.__sr5Markers = ${JSON.stringify(SENTINELS.filter(Boolean))}; return true;`);
  return JSON.parse(await browser.eval(inspectJs));
}
const sorted = list => [...new Set(list)].sort();
function compareFields(label, studio, legacy, mode) {
  if (!studio.formCount || !legacy.formCount) {
    check(`${label}: formulários reais comparáveis`, null, 'sem formulário em uma das páginas');
    return;
  }
  if (mode === 'different') {
    const hasNickname = fields => fields.some(f => f.includes('field_nickname'));
    check(`${label}: apelido fora do cadastro Studio`, !hasNickname(studio.fields));
    check(`${label}: apelido preservado na Conta legada`, hasNickname(legacy.fields));
    const original = legacy.fields.filter(field => !field.includes('field_nickname'));
    check(`${label}: demais campos do mesmo formulário`, JSON.stringify(sorted(studio.fields)) === JSON.stringify(sorted(original)),
      `Studio=${studio.fields.length}; legado sem apelido=${original.length}`);
  } else {
    check(`${label}: campos Form API equivalentes`, JSON.stringify(studio.fields) === JSON.stringify(legacy.fields),
      `Studio=${studio.fields.length}; legado=${legacy.fields.length}`);
  }
}

try {
  // Avaliação anônima independente: never accidentally use a logged-in session.
  const anonymous = await firefox();
  try {
    for (const path of ['/studio/conta','/studio/perfil','/conta-legada']) {
      const page = await inspect(anonymous, ACCOUNT + path);
      check(`anônimo sem acesso privado ${path}`,
        page.pathname !== path || [401,403].includes(page.status));
    }
    const internal = await inspect(anonymous, ACCOUNT + '/studio');
    check('acesso técnico direto /studio não habilitado', internal.status === 404 ? true : internal.status ? false : null,
      'não aceitar redirect temporário');
  } finally { await anonymous.close(); }

  for (let i = 0; i < USERS.length; i++) {
    const owner = i === 0 ? 'A' : 'B';
    const browser = await firefox();
    try {
      await browser.url(oneTimeLogin(USERS[i]));
      const hub = await inspect(browser, ACCOUNT + '/');
      const entry = await inspect(browser, ACCOUNT + '/studio/conta');
      const legacyHome = await inspect(browser, ACCOUNT + '/conta-legada');
      check(`${owner}: hub ACCOUNT na raiz`, hub.pathname === '/' && hub.origin === ACCOUNT);
      check(`${owner}: Conta Studio`, entry.pathname === '/studio/conta' && entry.origin === ACCOUNT);
      check(`${owner}: legado permanente`, legacyHome.pathname === '/conta-legada' && legacyHome.origin === ACCOUNT);
      check(`${owner}: acesso ao legado pelo Studio`, entry.anchorHrefs.some(url => url.includes('conta-legada')));

      // Uma seção equivalente usa a mesma Form API do Portal, exceto o apelido
      // explicitamente separado em cadastro Studio.
      for (const [name, studioPath, legacyPath, mode] of cases) {
        if (mode === 'optional' && DOCUMENTS === 'disabled') {
          const page = await inspect(browser, ACCOUNT + studioPath);
          check(`${owner}/${name}: rota opcional indisponível`, page.status === 404 ? true : page.status ? false : null);
          continue;
        }
        if (mode === 'optional' && !DOCUMENTS) {
          check(`${owner}/${name}: owner opcional configurado`, null, 'SR5_DOCUMENTOS não definido');
          continue;
        }
        let studio, old;
        try {
          studio = await inspect(browser, ACCOUNT + studioPath);
          old = await inspect(browser, ACCOUNT + legacyPath);
        } catch (error) {
          check(`${owner}/${name}: carregamento`, false, error.name || 'Erro de navegador');
          continue;
        }
        check(`${owner}/${name}: rotas próprias`, studio.pathname === studioPath && old.pathname === legacyPath);
        check(`${owner}/${name}: mesmo domínio ACCOUNT`, studio.origin === ACCOUNT && old.origin === ACCOUNT);
        if (mode === 'forms' || mode === 'different' || mode === 'optional') {
          compareFields(`${owner}/${name}`, studio, old, mode === 'different' ? 'different' : 'forms');
        } else if (mode === 'reference') {
          check(`${owner}/${name}: paridade de listagem com legado`, null,
            'dashboard legado é referência visual; sem rota legada de pedidos');
        } else {
          if (studio.tableCount && old.tableCount) {
            check(`${owner}/${name}: quantidade de linhas`, studio.rowCount === old.rowCount,
              `Studio=${studio.rowCount}; legado=${old.rowCount}`);
          } else {
            check(`${owner}/${name}: tabelas comparáveis`, null, 'comparar lista/estado vazio manualmente');
          }
        }
        for (const width of [320,375,390,768,1024,1440]) {
          await resize(browser, width);
          const metric = await inspect(browser, ACCOUNT + studioPath);
          check(`${owner}/${name}: reflow Studio ${width}px`, metric.screenWidth === width && !metric.pageOverflow);
          const compare = await inspect(browser, ACCOUNT + legacyPath);
          check(`${owner}/${name}: reflow legado ${width}px`, compare.screenWidth === width && !compare.pageOverflow);
          if ([320,390,1440].includes(width) && ['endereco','documentos','pedidos','preferencias'].includes(name)) {
            await browser.screenshot(`${owner}-${name}-legado-${width}`);
            await inspect(browser, ACCOUNT + studioPath);
            await browser.screenshot(`${owner}-${name}-studio-${width}`);
          }
        }
      }

      const own = await inspect(browser, ACCOUNT + '/studio/perfil');
      check(`${owner}: perfil privado no ACCOUNT`, own.profileEditor && own.pathname === '/studio/perfil');
      check(`${owner}: não inferir apelido do login`, own.privateName !== USERS[i] || own.privateName === SLUGS[i],
        'verificar também fixture sem apelido em rodada S-R4');
      if (PUBLIC[i] === 'yes' || PUBLIC[i] === 'no') {
        check(`${owner}: link público somente quando permitido`, !!own.publicLinkFromEditor === (PUBLIC[i] === 'yes'));
      } else check(`${owner}: link público condicionado`, null, 'SR5_PERFIL_PUBLICO_A/B sem fixture');
      if (SENTINELS.every(Boolean) && SENTINELS[0] !== SENTINELS[1]) {
        check(`${owner}: identidade própria no Studio`, own.markerFlags[i] === true);
        check(`${owner}: nunca incluir bio do outro titular`, own.markerFlags[1-i] === false);
      } else check(`${owner}: isolamento com duas sentinelas`, null, 'SR5_BIO_SENTINELA_A/B ausentes');
      const publicPage = await inspect(browser, MAIN + '/u/' + SLUGS[i]);
      check(`${owner}: perfil público em MAIN`, publicPage.origin === MAIN && publicPage.pathname === '/u/' + SLUGS[i]);
      if (PUBLIC[i] === 'no') {
        check(`${owner}: perfil privado não mostra bio`, !publicPage.publicBio);
        check(`${owner}: perfil privado não mostra links de contato`, !publicPage.publicLinks);
        if (SENTINELS.every(Boolean) && SENTINELS[0] !== SENTINELS[1]) {
          check(`${owner}: bio privada não vaza no MAIN`, !publicPage.markerFlags[i]);
        } else check(`${owner}: bio privada não vaza no MAIN`, null, 'sentinelas ausentes');
      }
      if (SENTINELS.every(Boolean) && SENTINELS[0] !== SENTINELS[1]) {
        check(`${owner}: bio do outro titular nunca aparece no MAIN`, !publicPage.markerFlags[1-i]);
      } else check(`${owner}: isolamento MAIN entre contas`, null, 'sentinelas ausentes');
      // E-mail no perfil público é opt-in do ACULTA User: NÃO proibir
      // indiscriminadamente; validar configuração do owner e fixture.
      for (const width of [320,375,390,768,1024,1440]) {
        await resize(browser, width);
        const p = await inspect(browser, MAIN + '/u/' + SLUGS[i]);
        check(`${owner}: reflow perfil público MAIN ${width}px`, p.screenWidth === width && !p.pageOverflow);
        if ([320,390,1440].includes(width)) await browser.screenshot(`${owner}-perfil-main-${width}`);
        const s = await inspect(browser, ACCOUNT + '/studio/perfil');
        check(`${owner}: reflow Meu Perfil Studio ${width}px`, s.screenWidth === width && !s.pageOverflow);
        if ([320,390,1440].includes(width)) await browser.screenshot(`${owner}-perfil-studio-${width}`);
      }
      // Alternância local para inspecionar geometria, SEM alterar preferência persistida.
      for (const mode of ['light','dark']) {
        await inspect(browser, ACCOUNT + '/studio/conta');
        await browser.eval(`document.documentElement.setAttribute('data-bs-theme','${mode}');return true;`);
        const view = JSON.parse(await browser.eval(inspectJs));
        check(`${owner}: modo ${mode} sem overflow`, !view.pageOverflow);
        await browser.screenshot(`${owner}-conta-${mode}`);
      }
    } finally { await browser.close(); }
  }

  // Sessão sem JS separada: formulário nativo deve permanecer navegável.
  const noJs = await firefox(false);
  try {
    await noJs.url(oneTimeLogin(USERS[0]));
    for (const path of ['/studio/conta/dados','/studio/conta/endereco','/studio/conta/seguranca','/studio/perfil/apelido','/studio/perfil/bio']) {
      await noJs.url(ACCOUNT + path);
      const actual = new URL(await noJs.currentUrl());
      const html = await noJs.source();
      check(`sem JS: URL e acesso ${path}`, actual.origin === ACCOUNT && actual.pathname === path);
      check(`sem JS: formulário próprio ${path}`, /<form\b/i.test(html) ? true : null,
        'GET somente; nenhum submit, verificar operações em teste manual');
    }
  } finally { await noJs.close(); }
} catch (error) {
  check('suíte S-R5 não interrompida', false, `${error.name || 'Error'}: ${(error.message || '').split('\n')[0]}`);
} finally {
  const totals = Object.fromEntries(['PASS','FAIL','SKIP'].map(status => [status, results.filter(r => r.status === status).length]));
  writeFileSync(join(OUT, 'results.json'), JSON.stringify({
    test: 'S-R5 comparação pré-homologação; sem escrita, sem dados privados em JSON',
    limitations: ['S-R4 ainda depende de gates', 'capturas requerem auditoria humana', 'não há submit/CSRF/AJAX nem zoom/teclado automatizados', 'paridade visual não é igualdade de pixels'],
    totals, results,
  }, null, 2), { mode: 0o600 });
  console.log(`S-R5: PASS=${totals.PASS}, FAIL=${totals.FAIL}, SKIP=${totals.SKIP}; evidências privadas em ${OUT}`);
  process.exitCode = totals.FAIL ? 1 : totals.SKIP ? 3 : 0;
}
