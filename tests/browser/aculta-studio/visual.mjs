// Revisão visual e de navegação do ACULTA Studio Marley (/marley no purpose account), no Firefox/geckodriver.
// Confere: seção "Estúdio" no shell da conta, abas e painel, navegação AJAX (sem recarregar a página),
// largura de celular emulada pelo BiDi (390 px) e ausência de erros de JavaScript.
// Ver README.md desta pasta. Somente no servidor de teste, com sessão de conta de teste.
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const PROJECT = fileURLToPath(new URL('../../../', import.meta.url));
const PAGE = process.env.STUDIO_PAGE || 'https://conta.aculta.toca.net.br/marley';
const ACCOUNT_CONTEXT_PAGE = process.env.STUDIO_ACCOUNT_CONTEXT_PAGE || '';
const LOGIN_URI = process.env.STUDIO_LOGIN_URI || 'https://conta.aculta.toca.net.br';
const DRUSH_ROOT = process.env.STUDIO_DRUSH_ROOT || PROJECT;
const USER = process.env.STUDIO_USER || 'teste.comum';
const EXPECT_MODERATION = process.env.STUDIO_EXPECT_MODERATION || '';
const OUT = process.env.SUITE_OUT;
const GD = process.env.GECKODRIVER || 'http://127.0.0.1:4444';
const CSS_OVERRIDE = process.env.STUDIO_CSS_OVERRIDE === '1';
const CSS_ROOT = process.env.STUDIO_CSS_ROOT || PROJECT;
const USER_ID = Number(process.env.STUDIO_USER_ID || '5');
let firefoxPid = 0;
if (!OUT) throw new Error('Defina SUITE_OUT em armazenamento fora do repositório.');
const pressFirefoxKey = (key) => {
  if (!firefoxPid) throw new Error('geckodriver não informou o PID do Firefox; zoom real abortado para não atingir outra janela.');
  const windows = execFileSync('xdotool', ['search', '--onlyvisible', '--pid', String(firefoxPid)], { encoding: 'utf8' }).trim().split('\n').filter(Boolean);
  if (!windows.length) throw new Error(`Janela visível do Firefox ESR do PID ${firefoxPid} não encontrada.`);
  execFileSync('xdotool', ['windowactivate', '--sync', windows.at(-1)]);
  execFileSync('xdotool', ['key', '--clearmodifiers', key]);
};
const candidateThemePath = 'web/themes/custom/aculta420';
const candidateFont = (name) => `data:font/woff2;base64,${readFileSync(join(CSS_ROOT, candidateThemePath, 'assets/fonts/montserrat', name)).toString('base64')}`;
const extractCssRule = (css, selector) => {
  const start = css.indexOf(selector);
  if (start < 0) throw new Error(`Regra CSS candidata ausente: ${selector}`);
  const open = css.indexOf('{', start);
  let depth = 0;
  for (let i = open; i < css.length; i += 1) {
    if (css[i] === '{') depth += 1;
    if (css[i] === '}' && --depth === 0) return css.slice(start, i + 1);
  }
  throw new Error(`Regra CSS candidata sem fechamento: ${selector}`);
};
const fidelityTokenCss = (css) => {
  const start = css.indexOf('/* OpenCulturas Next 3.1.5 tokens are scoped to Studio-owned visual roots. */');
  const end = css.indexOf('\n[data-bs-theme="dark"] {', start);
  if (start < 0 || end < 0) throw new Error('Bloco de tokens Studio não encontrado em tokens.css.');
  const lightAndResponsive = css.slice(start, end);
  const dark = extractCssRule(css, '[data-bs-theme="dark"] .studio,');
  const automatic = extractCssRule(css, ':root:not([data-bs-theme="light"]) .studio,');
  return `${lightAndResponsive}\n${dark}\n@media (prefers-color-scheme: dark) {\n${automatic}\n}`;
};
const CANDIDATE_CSS = CSS_OVERRIDE
  ? `${fidelityTokenCss(readFileSync(join(CSS_ROOT, candidateThemePath, 'css/tokens.css'), 'utf8'))}\n${readFileSync(join(CSS_ROOT, candidateThemePath, 'css/components/studio.css'), 'utf8')}`
    .replaceAll('../../assets/fonts/montserrat/Montserrat-latin.woff2', candidateFont('Montserrat-latin.woff2'))
    .replaceAll('../../assets/fonts/montserrat/Montserrat-latin-ext.woff2', candidateFont('Montserrat-latin-ext.woff2'))
  : '';
mkdirSync(OUT, { recursive: true });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const results = [];
const check = (name, ok, detail = '') => { const safeDetail = String(detail).replace(/([?&](?:token|hash|destination|form_build_id|form_token)=)[^&\s"'<>]+/gi, '$1[redacted]'); results.push({ name, ok: ok === null ? null : !!ok, detail: safeDetail }); console.log(`${ok === null ? 'SKIP' : ok ? 'PASS' : 'FAIL'}  ${name}${safeDetail ? '  -- ' + safeDetail : ''}`); return ok === null ? null : !!ok; };

async function wd(method, path, body) {
  const res = await fetch(GD + path, { method, headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  const json = await res.json();
  if (json.value && json.value.error) throw new Error(`${json.value.error}: ${String(json.value.message).split('\n')[0]}`);
  return json.value;
}
const oneTimeLogin = () => execFileSync('php', ['vendor/drush/drush/drush.php', 'uli', '--no-browser', `--name=${USER}`, `--uri=${LOGIN_URI}`], { cwd: DRUSH_ROOT }).toString().trim().split('\n').pop();

const moz = { prefs: { 'network.dns.localDomains': [new URL(PAGE).hostname].join(',') } };
const { value: session } = await wd('POST', '/session', { capabilities: { alwaysMatch: { browserName: 'firefox', acceptInsecureCerts: true, webSocketUrl: true, 'moz:firefoxOptions': moz } } }).then((v) => ({ value: { sessionId: v.sessionId, webSocketUrl: v.capabilities.webSocketUrl, processId: v.capabilities['moz:processID'] } }));
firefoxPid = Number(session.processId) || 0;
const sid = session.sessionId;
const p = (path) => `/session/${sid}${path}`;
const exec = (script, args = []) => wd('POST', p('/execute/sync'), { script, args });
const installAjaxFailure = (status, statusText) => exec(`window.__studioOriginalAjax=Drupal.Ajax;const Original=window.__studioOriginalAjax;Drupal.Ajax=function(...args){const request=new Original(...args);request.execute=function(){try{request.error({status:${status},statusText:${JSON.stringify(statusText)},responseText:''},request.url,'Simulated HTTP ${status}')}catch(error){}return Promise.resolve()};return request};Drupal.Ajax.prototype=Original.prototype;return 1;`);
const restoreAjax = () => exec(`if(window.__studioOriginalAjax){Drupal.Ajax=window.__studioOriginalAjax;delete window.__studioOriginalAjax}return 1;`);
const trackPageErrors = () => exec(`window.__errs = []; window.addEventListener('error', (e) => window.__errs.push(String(e.message))); return 1;`);
const shot = async (name) => writeFileSync(join(OUT, `${name}.png`), Buffer.from(await wd('GET', p('/screenshot')), 'base64'));
const injectCandidateCss = async () => exec(`if(document.getElementById('aculta-studio-css-candidate'))return true;const style=document.createElement('style');style.id='aculta-studio-css-candidate';style.textContent=arguments[0];document.head.append(style);return !!style.sheet&&style.sheet.cssRules.length>0;`, [CANDIDATE_CSS]);
const navigateTo = async (url) => {
  await wd('POST', p('/url'), { url });
  await sleep(1000);
  if (CSS_OVERRIDE) check('CSS candidata reinjetada após navegação', await injectCandidateCss());
  await trackPageErrors();
};

const bidi = new WebSocket(session.webSocketUrl);
let bid = 0; const bpend = new Map();
bidi.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && bpend.has(d.id)) { bpend.get(d.id)(d); bpend.delete(d.id); } };
await new Promise((r) => (bidi.onopen = r));
const bsend = (method, params = {}) => new Promise((res) => { const i = ++bid; bpend.set(i, res); bidi.send(JSON.stringify({ id: i, method, params })); });
const tree = await bsend('browsingContext.getTree', {});
const context = tree.result.contexts[0].context;
const setViewport = async (width, height) => {
  const response = await bsend('browsingContext.setViewport', { context, viewport: { width, height } });
  if (response?.error) throw new Error(`Firefox recusou o viewport solicitado (${width} × ${height}): ${response.error}`);
  await sleep(100);
  const actualWidth = await exec('return window.innerWidth;');
  if (Number(actualWidth) !== width) throw new Error(`Firefox aplicou innerWidth=${actualWidth}; esperado ${width}.`);
  return response;
};
async function waitFor(expr, label, ms = 20000) {
  const end = Date.now() + ms;
  while (Date.now() < end) { if (await exec(`return !!(${expr});`)) return true; await sleep(300); }
  console.log(`  (timeout: ${label})`); return false;
}

try {
  await setViewport(1280, 900);
  await wd('POST', p('/url'), { url: oneTimeLogin() });
  await sleep(2500);
  await navigateTo(PAGE);
  const pageReady = await waitFor(`document.querySelector('[data-aculta-studio="tabs"]')`, 'abas');
  check('página do Estúdio carrega logada', pageReady);
  if (!pageReady) {
    await shot('00-page-error');
    const pageState = await exec(`return JSON.stringify({ url: location.href, title: document.title, message: document.body.innerText.slice(0, 240) });`);
    throw new Error(`Página sem navegação do Studio: ${pageState}`);
  }
  if (CSS_OVERRIDE) {
    const globalGutterBefore = await exec(`return getComputedStyle(document.documentElement).getPropertyValue('--aculta-page-gutter').trim();`);
    check('CSS candidata do Studio injetada', await injectCandidateCss());
    const globalGutterAfter = await exec(`return getComputedStyle(document.documentElement).getPropertyValue('--aculta-page-gutter').trim();`);
    check('tokens de fidelidade não alteram o gutter global do shell', globalGutterBefore === globalGutterAfter, `${globalGutterBefore} -> ${globalGutterAfter}`);
    await waitFor(`document.fonts.check('400 16px Montserrat')`, 'fonte Montserrat candidata');
    const candidateTypography = JSON.parse(await exec(`const root=document.querySelector('.studio, .aculta-profile-editor');const heading=root?.querySelector('h1,h2,h3,h4,h5,h6');return JSON.stringify({family:getComputedStyle(root).fontFamily,loaded:document.fonts.check('400 16px Montserrat'),headingSize:heading?getComputedStyle(heading).fontSize:null,headingFamily:heading?getComputedStyle(heading).fontFamily:null});`));
    check('tokens e tipografia candidatos aplicados apenas ao Studio', candidateTypography.family.includes('Montserrat') && candidateTypography.loaded && candidateTypography.headingFamily.includes('Montserrat') && candidateTypography.headingSize !== null, JSON.stringify(candidateTypography));
    const navStyles = JSON.parse(await exec(`return JSON.stringify({
      primary: [...document.querySelectorAll('[data-aculta-studio="tab"]')].map((link) => { const s=getComputedStyle(link),r=link.getBoundingClientRect(); return { tag:link.tagName, className:link.className, parent:link.parentElement?.tagName, parentClass:link.parentElement?.className, top:s.borderTopWidth, left:s.borderLeftWidth, right:s.borderRightWidth, bottom:s.borderBottomWidth, radius:s.borderRadius, width:r.width, height:r.height, current:link.getAttribute('aria-current')==='page' }; }),
      secondary: [...document.querySelectorAll('[data-aculta-studio="subtab"]')].map((link) => { const s=getComputedStyle(link),r=link.getBoundingClientRect(); return { tag:link.tagName, className:link.className, parent:link.parentElement?.tagName, parentClass:link.parentElement?.className, top:s.borderTopWidth, left:s.borderLeftWidth, right:s.borderRightWidth, bottom:s.borderBottomWidth, radius:s.borderRadius, width:r.width, height:r.height, current:link.getAttribute('aria-current')==='page' }; }),
      lists: [...document.querySelectorAll('[data-aculta-studio="tabs"],[data-aculta-studio="subtabs"]')].map((list) => { const s=getComputedStyle(list); const r=list.getBoundingClientRect(); return { className:list.className, width:r.width, height:r.height, top:s.borderTopWidth, left:s.borderLeftWidth, right:s.borderRightWidth, bottom:s.borderBottomWidth }; }),
      primaryItems: [...document.querySelectorAll('[data-aculta-studio="tabs"] > *')].map((item) => { const s=getComputedStyle(item); return { tag:item.tagName, className:item.className, top:s.borderTopWidth, left:s.borderLeftWidth, right:s.borderRightWidth, bottom:s.borderBottomWidth, radius:s.borderRadius, padding:s.padding, background:s.backgroundColor }; }),
      secondaryItems: [...document.querySelectorAll('[data-aculta-studio="subtabs"] > *')].map((item) => { const s=getComputedStyle(item); return { tag:item.tagName, className:item.className, top:s.borderTopWidth, left:s.borderLeftWidth, right:s.borderRightWidth, bottom:s.borderBottomWidth, radius:s.borderRadius, padding:s.padding, background:s.backgroundColor }; }),
      separatorAtViewportBottom: (() => { const el=document.elementFromPoint(1000,848); if(!el)return null; const s=getComputedStyle(el); return {tag:el.tagName,className:el.className,bottom:s.borderBottomWidth,borderColor:s.borderBottomColor}; })()
    })`));
    const cleanPrimary = navStyles.primary.length > 0 && navStyles.primary.every((s) => s.top === '0px' && s.left === '0px' && s.right === '0px' && s.radius === '0px');
    const cleanSecondary = navStyles.secondary.length > 0 && navStyles.secondary.every((s) => s.top === '0px' && s.left === '0px' && s.right === '0px' && s.radius === '0px');
    const cleanItems = (items) => items.length > 0 && items.every((s) => s.top === '0px' && s.left === '0px' && s.right === '0px' && s.bottom === '0px' && s.radius === '0px' && s.padding === '0px' && s.background === 'rgba(0, 0, 0, 0)');
    const cleanLists = navStyles.lists.length > 0 && navStyles.lists.every((s) => s.top === '0px' && s.left === '0px' && s.right === '0px' && s.bottom === '0px');
    check('abas primárias sem moldura Bootstrap (somente indicador inferior)', cleanPrimary, JSON.stringify(navStyles.primary));
    check('abas secundárias sem moldura Bootstrap', cleanSecondary, JSON.stringify(navStyles.secondary));
    const targetsAtLeast44 = [...navStyles.primary, ...navStyles.secondary].length > 0
      && [...navStyles.primary, ...navStyles.secondary].every((target) => target.width >= 44 && target.height >= 44);
    check('abas primárias e secundárias com alvos mínimos de 44×44px', targetsAtLeast44, JSON.stringify({ primary: navStyles.primary, secondary: navStyles.secondary }));
    check('itens das listas de abas sem moldura list-group', cleanItems(navStyles.primaryItems) && cleanItems(navStyles.secondaryItems), JSON.stringify({ primary: navStyles.primaryItems, secondary: navStyles.secondaryItems }));
    check('listas de navegação sem borda contínua Bootstrap', cleanLists, JSON.stringify({ lists: navStyles.lists, separator: navStyles.separatorAtViewportBottom }));
  }
  await trackPageErrors();
  await sleep(1000);

  const d = JSON.parse(await exec(`return JSON.stringify({
    root: !!document.querySelector('[data-aculta-studio="root"]'),
    accountShell: !!document.getElementById('portal-account-body') || !!document.querySelector('[data-portal-account-menu]'),
    areas: document.querySelectorAll('[data-aculta-studio="tab"]').length,
    sidebar: document.querySelectorAll('[data-aculta-studio="section"]').length,
    subtabs: document.querySelectorAll('[data-aculta-studio="subtab"]').length,
    actions: [...document.querySelectorAll('[data-aculta-studio="action"]')].map((a) => a.textContent.trim()),
    viewportWidth: window.innerWidth,
    pageWidth: document.documentElement.scrollWidth,
    bodyWidth: document.body.scrollWidth,
    bodyOverflowX: getComputedStyle(document.body).overflowX,
    rootFontSize: getComputedStyle(document.documentElement).fontSize,
    media48rem: matchMedia('(min-width: 48rem)').matches,
    pageGutterToken: getComputedStyle(document.documentElement).getPropertyValue('--aculta-page-gutter').trim(),
    overflow: document.documentElement.scrollWidth > window.innerWidth,
    studioWidth: document.querySelector('.studio')?.scrollWidth,
    studioClientWidth: document.querySelector('.studio')?.clientWidth,
    overflowNodes: [...document.querySelectorAll('body *')].filter((e) => !e.closest('.offcanvas:not(.show)') && (e.getBoundingClientRect().right > window.innerWidth + 1 || e.getBoundingClientRect().left < -1)).slice(0, 8).map((e) => ({ tag:e.tagName, className:String(e.className).slice(0, 70), left:Math.round(e.getBoundingClientRect().left), right:Math.round(e.getBoundingClientRect().right), width:Math.round(e.getBoundingClientRect().width) })),
    overflowChain: (() => { const e=[...document.querySelectorAll('body *')].find((n)=>!n.closest('.offcanvas:not(.show)')&&(n.getBoundingClientRect().right>window.innerWidth+1||n.getBoundingClientRect().left< -1));const a=[];for(let n=e;n&&a.length<8;n=n.parentElement){const r=n.getBoundingClientRect(),s=getComputedStyle(n);a.push({tag:n.tagName,className:String(n.className).slice(0,70),left:Math.round(r.left),right:Math.round(r.right),width:Math.round(r.width),scrollWidth:n.scrollWidth,clientWidth:n.clientWidth,padding:[s.paddingLeft,s.paddingRight],margin:[s.marginLeft,s.marginRight],boxSizing:s.boxSizing,display:s.display,position:s.position,minWidth:s.minWidth,maxWidth:s.maxWidth})}return a})()
  })`));
  check('página própria do Studio (sem shell nem menu da conta)', d.root && !d.accountShell);
  check('áreas em abas superiores', d.areas >= 1, `${d.areas} área(s)`);
  // 0e1eb5dbc removeu o submenu lateral: as seções vivem nas abas superiores.
  check('navegação de seções em abas, sem submenu lateral', d.sidebar === 0, `${d.sidebar} link(s) de seção`);
  check('abas próprias da seção', d.subtabs >= 1, `${d.subtabs} aba(s)`);
  // A raiz /marley mostra a primeira aba disponível; a Revista tem cinco
  // estados somente na seção Publicações. Verificamos essa matriz no destino
  // real abaixo, sem impor sua estrutura a outras áreas do Studio.
  check('abas da seção atual renderizadas', d.subtabs >= 1, `${d.subtabs} aba(s)`);
  check('sem rolagem horizontal em 1280 px', !d.overflow, `page=${d.pageWidth}px body=${d.bodyWidth}px viewport=${d.viewportWidth}px root-font=${d.rootFontSize} media48rem=${d.media48rem} gutter=${d.pageGutterToken} body-overflow-x=${d.bodyOverflowX} Studio=${d.studioWidth}/${d.studioClientWidth}px offenders=${JSON.stringify(d.overflowNodes)} chain=${JSON.stringify(d.overflowChain)}`);
  await shot('01-desktop');

  const magazineTabAvailable = await exec(`return [...document.querySelectorAll('[data-aculta-studio="tab"]')].some((a) => (a.getAttribute('href') || '').includes('/marley/revista'));`);
  if (magazineTabAvailable) {
    await navigateTo(PAGE.replace(/\/marley.*$/, '') + '/marley/revista/publicacoes/todos');
    const magazineListTabsReady = await waitFor(`document.querySelectorAll('[data-aculta-studio="subtab"]').length >= 5`, 'estados de Publicações da Revista');
    const magazineListTabs = await exec(`return [...document.querySelectorAll('[data-aculta-studio="subtab"]')].map((a) => a.textContent.trim());`);
    const requiredMagazineStates = ['Publicações acessíveis','Rascunhos','Em revisão','Publicados','Arquivados'];
    check('Revista apresenta os cinco estados editoriais na seção Publicações', magazineListTabsReady && requiredMagazineStates.every((label) => magazineListTabs.includes(label)), magazineListTabs.join(' | '));
    await navigateTo(PAGE);
  }
  else {
    check('Revista apresenta os cinco estados editoriais na seção Publicações', null, 'aba Revista não disponível para esta conta no Runtime; revisar configuração/papel antes da homologação');
  }

  // Navegação AJAX: clicar na aba mantém a página (sem recarga) e atualiza a URL.
  const startPath = await exec(`return window.location.pathname;`);
  const sectionTarget = JSON.parse(await exec(`return JSON.stringify((() => { const a=[...document.querySelectorAll('[data-aculta-studio="section"]')].find((link) => link.getAttribute('aria-current') !== 'page' && new URL(link.href).pathname !== location.pathname); return a ? {href:a.href, text:a.textContent.trim(), ajax:typeof Drupal?.Ajax} : null; })());`));
  check('há um destino de seção diferente para exercitar navegação', sectionTarget ? true : null, sectionTarget ? JSON.stringify(sectionTarget) : 'sem link de seção; a navegação é feita pelas abas');
  if (sectionTarget) {
    await exec(`window.__marker='sem-recarga';const a=document.querySelector('[data-aculta-studio="section"][href="${new URL(sectionTarget.href).pathname}"]');const event=new MouseEvent('click',{bubbles:true,cancelable:true,view:window,button:0});a?.dispatchEvent(event);window.__acultaClickPrevented=event.defaultPrevented;return 1;`);
  }
  await sleep(2500);
  const navigationState = JSON.parse(await exec(`return JSON.stringify({marker:window.__marker||null,prevented:window.__acultaClickPrevented??null,path:location.pathname,live:!!document.querySelector('[data-aculta-studio="live"][aria-live="polite"]'),current:!!document.querySelector('[data-aculta-studio="section"][aria-current="page"]'),behavior:!!Drupal?.behaviors?.acultaStudioNavigation,drupalAjax:typeof Drupal?.Ajax});`));
  check('clique no submenu troca o conteúdo sem recarregar a página', sectionTarget ? (navigationState.marker === 'sem-recarga') : null, JSON.stringify({target:sectionTarget,state:navigationState}));
  check('seção ativa marcada por aria-current', sectionTarget ? navigationState.current : null, JSON.stringify(navigationState));
  check('URL própria do nível (caminho /marley/área/seção)', sectionTarget ? navigationState.path.startsWith('/marley/') : null, navigationState.path);
  check('região aria-live presente', navigationState.live, JSON.stringify(navigationState));
  await exec(`history.back(); return 1;`);
  await sleep(2500);
  const backPath = await exec(`return window.location.pathname;`);
  check('voltar do navegador devolve o caminho anterior', sectionTarget ? backPath === startPath : null, `${startPath} -> ${backPath}`);

  await setViewport(390, 844);
  await exec(`location.reload(); return 1;`);
  await waitFor(`document.querySelector('[data-aculta-studio="tabs"]')`, 'abas no celular');
  if (CSS_OVERRIDE) check('CSS candidata reinjetada após recarga mobile', await injectCandidateCss());
  await sleep(1000);
  const m = JSON.parse(await exec(`return JSON.stringify({ w: window.innerWidth, overflow: document.documentElement.scrollWidth > window.innerWidth })`));
  check('celular em 390 px (viewport emulado)', m.w === 390, `innerWidth=${m.w}`);
  // Em caso de estouro, o relatório traz os elementos que passam da largura da tela (causa, não só o sintoma).
  const offenders = m.overflow ? await exec(`return [...document.querySelectorAll('body *')].filter((e) => e.getBoundingClientRect().right > window.innerWidth + 1 && !e.closest('.offcanvas, [data-aculta-studio="listing-wrap"]')).slice(0, 6).map((e) => e.tagName + '.' + String(e.className).slice(0, 50) + ' ' + Math.round(e.getBoundingClientRect().right));`) : [];
  check('sem rolagem horizontal em 390 px', !m.overflow, offenders.join(' | '));
  await shot('02-celular');
  for (const width of [320, 360, 390, 599, 600, 767, 768, 1024, 1280, 1440]) {
    await setViewport(width, width < 500 ? 844 : 900);
    await sleep(300);
    const responsive = JSON.parse(await exec(`const layout=document.querySelector('.studio__layout');const main=layout?.querySelector('.studio__main');const lr=layout?.getBoundingClientRect();const mr=main?.getBoundingClientRect();return JSON.stringify({w:window.innerWidth,overflow:document.documentElement.scrollWidth>window.innerWidth,direction:layout?getComputedStyle(layout).flexDirection:null,layoutWidth:lr?.width,mainWidth:mr?.width,childrenFit:!!lr&&!!mr&&mr.right<=lr.right+1&&mr.left>=lr.left-1});`));
    check(`sem rolagem horizontal em ${width}px`, !responsive.overflow, `innerWidth=${responsive.w}`);
    check(`composição do Studio correta em ${width}px`, responsive.direction === (width < 768 ? 'column' : 'row') && responsive.mainWidth > 0 && responsive.childrenFit, JSON.stringify(responsive));
  }
  await setViewport(1280, 900);

  // Teclado: valida foco visível e Enter no destino real de criação da Revista.
  if (magazineTabAvailable) {
    await navigateTo(PAGE.replace(/\/marley.*$/, '') + '/marley/revista/criar');
    const createTabsReady = await waitFor(`document.querySelector('[data-aculta-studio="subtab"]')`, 'aba Nova publicação');
    const subTabs = JSON.parse(await exec(`return JSON.stringify([...document.querySelectorAll('[data-aculta-studio="subtab"]')].map((a)=>({text:a.textContent.trim(),href:a.getAttribute('href')})));`));
    const createTab = subTabs.find((tab) => /nova publicação/i.test(tab.text) && (tab.href || '').includes('nova-publicacao'));
    check('aba de criação aponta para destino real', createTabsReady && !!createTab, JSON.stringify(subTabs));
    if (createTabsReady && createTab) {
      const subLink = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-studio="subtab"][href*="nova-publicacao"]' });
      const subId = Object.values(subLink)[0];
      await wd('POST', p('/execute/sync'), { script: "document.querySelector('[data-aculta-studio=\"subtab\"]').focus(); return 1;", args: [] });
      await wd('POST', p(`/element/${subId}/value`), { text: '\uE004' });
      const focusVisual = JSON.parse(await exec(`const e=document.activeElement;const s=getComputedStyle(e);return JSON.stringify({subtab:e.matches('[data-aculta-studio="subtab"]'),visible:e.matches(':focus-visible'),outlineWidth:s.outlineWidth,outlineStyle:s.outlineStyle});`));
      check('foco visível após navegação por Tab', focusVisual.visible && Number.parseFloat(focusVisual.outlineWidth) >= 3 && focusVisual.outlineStyle !== 'none', JSON.stringify(focusVisual));
      await wd('POST', p('/execute/sync'), { script: "document.querySelector('[data-aculta-studio=\"subtab\"][href*=\"nova-publicacao\"]').focus(); return 1;", args: [] });
      await wd('POST', p(`/element/${subId}/value`), { text: '\uE007' });
      await sleep(2500);
      const kbPath = await exec(`return window.location.pathname;`);
      check('Enter no teclado troca a aba pela URL dela', kbPath === '/marley/revista/criar/nova-publicacao' || kbPath.startsWith('/marley/revista/criar/'), kbPath);
    }
    else {
      check('foco visível após navegação por Tab', null, 'aba de criação não disponível');
      check('Enter no teclado troca a aba pela URL dela', null, 'aba de criação não disponível');
    }
  }
  else {
    check('aba de criação aponta para destino real', null, 'aba Revista não disponível para esta conta no Runtime');
    check('foco visível após navegação por Tab', null, 'aba Revista não disponível para esta conta no Runtime');
    check('Enter no teclado troca a aba pela URL dela', null, 'aba Revista não disponível para esta conta no Runtime');
  }

  // Contraste WCAG: texto do Studio contra o fundo efetivo, com as cores computadas pelo navegador (claro e escuro).
  const contrastScript = `
    const parse = (c) => (c.match(/[\\d.]+/g) || []).slice(0, 3).map(Number);
    const lum = ([r, g, b]) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
    const bgOf = (el) => { for (let e = el; e; e = e.parentElement) { const c = getComputedStyle(e).backgroundColor; const m = c.match(/rgba?\\(([^)]+)\\)/); if (m && !/rgba\\([^)]*,\\s*0\\)$/.test(c)) return parse(c); } return [255, 255, 255]; };
    const items = [...document.querySelectorAll('[data-aculta-studio="tab"],[data-aculta-studio="section"],[data-aculta-studio="subtab"],[data-aculta-studio="action"],[data-aculta-studio="heading"]')];
    return items.map((el) => { const fg = parse(getComputedStyle(el).color); const bg = bgOf(el); const L1 = lum(fg), L2 = lum(bg); const ratio = (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05); return Math.round(ratio * 100) / 100; });
  `;
  await navigateTo(magazineTabAvailable ? PAGE.replace(/\/marley.*$/, '') + '/marley/revista/criar' : PAGE);
  await waitFor(`document.querySelector('[data-aculta-studio="subtab"]')`, 'contraste (claro)');
  const contrastLight = JSON.parse(JSON.stringify(await exec(contrastScript)));
  const minLight = Math.min(...contrastLight);
  check('contraste mínimo do texto do Studio no modo claro (WCAG AA, 4,5:1)', minLight >= 4.5, `mínimo ${minLight}:1 em ${contrastLight.length} elemento(s)`);

  // Paridade claro/escuro: mesma geometria nos dois modos. A preferência da conta de teste é trocada e restaurada.
  const boxesFor = () => exec(`return JSON.stringify([...document.querySelectorAll('[data-aculta-studio="tab"],[data-aculta-studio="section"],[data-aculta-studio="subtab"],[data-aculta-studio="action"],[data-aculta-studio="panel"]')].map((e) => { const r = e.getBoundingClientRect(); return [Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height)]; }));`);
  await navigateTo(PAGE.replace(/\/marley.*$/, '') + '/marley/revista/criar');
  await waitFor(`document.querySelector('[data-aculta-studio="subtab"]')`, 'abas (claro)');
  const lightBoxes = JSON.parse(await boxesFor());
  const lightMode = await exec(`return document.documentElement.getAttribute('data-bs-theme') || 'none';`);
  const drushRun = (code) => execFileSync('php', ['vendor/drush/drush/drush.php', 'php:eval', code, `--uri=${LOGIN_URI}`], { cwd: DRUSH_ROOT, env: { ...process.env, STUDIO_USER_ID: String(USER_ID) } }).toString().trim();
  const original = drushRun(`echo json_encode(\\Drupal::service('aculta_portal.color_mode_preference')->forUser((int) getenv('STUDIO_USER_ID')));`);
  drushRun(`\\Drupal::service('aculta_portal.color_mode_preference')->save((int) getenv('STUDIO_USER_ID'), 'dark'); echo 'ok';`);
  await wd('POST', p('/refresh'), {});
  await waitFor(`document.querySelector('[data-aculta-studio="subtab"]')`, 'abas (escuro)');
  if (CSS_OVERRIDE) check('CSS candidata reinjetada no modo escuro', await injectCandidateCss());
  await sleep(800);
  const darkBoxes = JSON.parse(await boxesFor());
  const contrastDark = JSON.parse(JSON.stringify(await exec(contrastScript)));
  check('contraste mínimo do texto do Studio no modo escuro (WCAG AA, 4,5:1)', Math.min(...contrastDark) >= 4.5, `mínimo ${Math.min(...contrastDark)}:1 em ${contrastDark.length} elemento(s)`);
  const darkMode = await exec(`return document.documentElement.getAttribute('data-bs-theme') || 'none';`);
  if (original === 'null' || original === '') { drushRun(`\\Drupal::service('user.data')->delete('aculta_portal', (int) getenv('STUDIO_USER_ID'), 'color_mode'); echo 'ok';`); } else { drushRun(`\\Drupal::service('aculta_portal.color_mode_preference')->save((int) getenv('STUDIO_USER_ID'), json_decode('${original}')); echo 'ok';`); }
  check('modo escuro aplicado à página', darkMode !== lightMode, `claro=${lightMode} escuro=${darkMode}`);
  check('mesma geometria nos modos claro e escuro', JSON.stringify(lightBoxes) === JSON.stringify(darkBoxes), `${lightBoxes.length} elemento(s) comparado(s)`);

  // Galerias: navegação visual da área (acesso concedido ao papel de editor no Runtime de teste).
  await navigateTo(PAGE.replace(/\/marley.*$/, '') + '/marley/galerias/criar');
  await waitFor(`document.querySelector('[data-aculta-studio="action"]')`, 'Galerias');
  const gal = await exec(`return [...document.querySelectorAll('[data-aculta-studio="action"]')].map((a) => a.textContent.trim());`);
  check('área Galerias com a ação Nova galeria', gal.includes('Nova galeria'), gal.join(' | '));
  await shot('04-galerias');

  // Comments moderation is an authorized tab under Revista and is absent for
  // roles without the Magazine comments capability and native comment grant.
  await navigateTo(PAGE);
  const moderationTab = JSON.parse(await exec(`const a=[...document.querySelectorAll('[data-aculta-studio="subtab"]')].find((link)=>/Comentários e moderação/i.test(link.textContent));return JSON.stringify(a?{href:a.href,label:a.textContent.trim()}:null);`));
  check('aba Comentários e moderação segue a autorização do owner', EXPECT_MODERATION === 'yes' ? !!moderationTab : EXPECT_MODERATION === 'no' ? !moderationTab : null, JSON.stringify({expected:EXPECT_MODERATION||'not specified',tab:moderationTab}));
  if (EXPECT_MODERATION === 'yes' && moderationTab) {
    await navigateTo(moderationTab.href);
    const moderationState = JSON.parse(await exec(`return JSON.stringify({path:location.pathname,listing:!!document.querySelector('[data-aculta-studio="listing"]'),rows:[...document.querySelectorAll('[data-aculta-studio="listing"] tbody tr')].filter((r)=>r.querySelector('td')&&!r.querySelector('td[colspan]')).length,empty:!!document.querySelector('[data-aculta-studio="listing"] td[colspan]')});`));
    check('owner de moderação abre sua fila e o estado vazio/listagem', moderationState.listing && (moderationState.rows > 0 || moderationState.empty), JSON.stringify(moderationState));
  }
  else if (EXPECT_MODERATION === 'yes') check('owner de moderação abre sua fila e o estado vazio/listagem', false, 'aba autorizada não foi renderizada');

  // Listagem da Revista na aba Publicados: tabela com as linhas que a conta pode ver.
  await navigateTo(PAGE.replace(/\/marley.*$/, '') + '/marley/revista/publicacoes/publicados');
  await waitFor(`document.querySelector('[data-aculta-studio="listing"]')`, 'listagem');
  const listingState = JSON.parse(await exec(`return JSON.stringify({ rows: [...document.querySelectorAll('[data-aculta-studio="listing"] tbody tr')].filter((row) => row.querySelector('td') && !row.querySelector('td[colspan]')).length, empty: !!document.querySelector('[data-aculta-studio="listing"] td[colspan]') });`));
  check('listagem da Revista apresenta conteúdo ou estado vazio', listingState.rows > 0 || listingState.empty, `${listingState.rows} conteúdo(s); estado vazio=${listingState.empty}`);
  let editorialEditHref = null;
  if (listingState.rows > 0) {
    const editLinks = await exec(`return [...document.querySelectorAll('[data-aculta-studio="listing"] a')].filter((a) => /^editar$/i.test(a.textContent.trim())).map((a) => ({href:a.href,path:new URL(a.href).pathname}));`);
    const canonicalOwnerRoutes = editLinks.filter((link) => /^\/marley\/revista\/\d+\/editar$/.test(link.path));
    editorialEditHref = canonicalOwnerRoutes[0]?.href || null;
    check('edição aponta para a rota editorial canônica do owner no purpose Conta', canonicalOwnerRoutes.length >= 1, JSON.stringify({links:editLinks,canonical:canonicalOwnerRoutes.length}));
  }
  else {
    check('edição aponta para a rota editorial canônica do owner no purpose Conta', null, 'sem publicação própria publicada para testar a ação de edição');
  }
  const unnamed = await exec(`return [...document.querySelectorAll('[data-aculta-studio="tab"],[data-aculta-studio="section"],[data-aculta-studio="subtab"],[data-aculta-studio="action"]')].filter((a) => !(a.textContent || '').trim()).length;`);
  check('todo link do Studio tem nome acessível (texto)', unnamed === 0, `${unnamed} sem nome`);
  const groupsOk = await exec(`return !!document.querySelector('[data-aculta-studio="tabs"][aria-label]') && !!document.querySelector('[data-aculta-studio="live"][role="status"]');`);
  check('áreas com nome (aria-label) e região de status (role=status, aria-live)', groupsOk);
  await shot('03-listagem-revista');

  if (editorialEditHref) {
    await navigateTo(editorialEditHref);
    const editFormReady = await waitFor(`document.querySelector('form.node-form, form[id*="node-revista-edit"]')`, 'formulário Form API de edição da Revista');
    const editForm = JSON.parse(await exec(`const form=document.querySelector('form.node-form, form[id*="node-revista-edit"]');const main=document.querySelector('.aculta-main');const heading=main?.querySelector('h1');const institution=document.querySelector('.aculta-institution-bar');const domainHeader=document.querySelector('.aculta-header');const s=form?getComputedStyle(form):null;return JSON.stringify({ready:!!form,identity:document.documentElement.classList.contains('studio-identity'),id:form?.id,className:form?.className,bodyClass:document.body.className,titleField:!!form?.querySelector('[name="title[0][value]"]'),secondary:!!form?.querySelector('[data-aculta-revista="secondary"]'),submit:!!form?.querySelector('[type="submit"]'),fontFamily:s?.fontFamily,color:s?.color,mainFamily:main?getComputedStyle(main).fontFamily:null,headingFamily:heading?getComputedStyle(heading).fontFamily:null,headingSize:heading?getComputedStyle(heading).fontSize:null,institutionCount:document.querySelectorAll('.aculta-institution-bar').length,domainHeaderCount:document.querySelectorAll('.aculta-header').length,shellFidelityTokens:[institution,domainHeader].map((node)=>node?getComputedStyle(node).getPropertyValue('--aculta-studio-fidelity-font-family').trim():null),width:form?.scrollWidth,clientWidth:form?.clientWidth,page:document.documentElement.scrollWidth});`));
    check('owner GET renderiza o Form API nativo de edição sem submit', editFormReady && editForm.ready && editForm.titleField && editForm.submit, JSON.stringify(editForm));
    const expectedBodyFont = editForm.identity ? 'Inter' : 'Montserrat';
    const expectedHeadingFont = editForm.identity ? 'Oswald' : 'Montserrat';
    check('formulário editorial recebe tipografia ACULTA420 escopada ao Marley', editForm.fontFamily?.includes(expectedBodyFont), JSON.stringify({expectedBodyFont,...editForm}));
    check('título e composição do editor recebem tipografia ACULTA420', editForm.mainFamily?.includes(expectedBodyFont) && editForm.headingFamily?.includes(expectedHeadingFont) && Number.parseFloat(editForm.headingSize) >= 32, JSON.stringify({expectedBodyFont,expectedHeadingFont,...editForm}));
    check('Institution Bar e Domain Header permanecem únicos e fora dos tokens Studio', editForm.institutionCount === 1 && editForm.domainHeaderCount === 1 && editForm.shellFidelityTokens.every((value) => value === ''), JSON.stringify(editForm));
    await shot('04-editor-revista-desktop');
    for (const width of [320, 390, 768, 1280]) {
      await setViewport(width, width < 500 ? 844 : 900);
      const formDimensions = JSON.parse(await exec(`const form=document.querySelector('form.node-form, form[id*="node-revista-edit"]');const r=form?.getBoundingClientRect();return JSON.stringify({width:innerWidth,page:document.documentElement.scrollWidth,formWidth:r?.width,formRight:r?.right});`));
      check(`formulário editorial sem overflow em ${width}px`, formDimensions.page <= width && formDimensions.formRight <= width + 1, JSON.stringify(formDimensions));
      if (width === 320) await shot('05-editor-revista-mobile');
    }
    await setViewport(1280, 900);

    await navigateTo(PAGE.replace(/\/marley.*$/, '') + '/marley/revista/criar/nova-publicacao');
    const createActionHref = await exec(`const action=[...document.querySelectorAll('[data-aculta-studio="action"]')].find((link)=>/nova publicação/i.test(link.textContent));return action?.href||null;`);
    const createActionPath = createActionHref ? new URL(createActionHref).pathname : null;
    check('ação Nova publicação aponta para a rota Form API proprietária', createActionPath === '/marley/revista/nova', JSON.stringify({href:createActionHref,path:createActionPath}));
    if (createActionHref) {
      await navigateTo(createActionHref);
      const createFormReady = await waitFor(`document.querySelector('form.node-revista-form')`, 'formulário Form API de criação da Revista');
      const createForm = JSON.parse(await exec(`const form=document.querySelector('form.node-revista-form');const main=document.querySelector('.aculta-main');const heading=main?.querySelector('h1');return JSON.stringify({ready:!!form,identity:document.documentElement.classList.contains('studio-identity'),id:form?.id,fontFamily:form?getComputedStyle(form).fontFamily:null,mainFamily:main?getComputedStyle(main).fontFamily:null,headingFamily:heading?getComputedStyle(heading).fontFamily:null,titleField:!!form?.querySelector('[name="title[0][value]"]'),page:document.documentElement.scrollWidth});`));
      check('owner GET renderiza formulário Form API de criação sem submit', createFormReady && createForm.ready && createForm.titleField, JSON.stringify(createForm));
      const createBodyFont = createForm.identity ? 'Inter' : 'Montserrat';
      const createHeadingFont = createForm.identity ? 'Oswald' : 'Montserrat';
      check('formulário e título de criação recebem tipografia ACULTA420', createForm.fontFamily?.includes(createBodyFont) && createForm.mainFamily?.includes(createBodyFont) && createForm.headingFamily?.includes(createHeadingFont), JSON.stringify({createBodyFont,createHeadingFont,...createForm}));
      await shot('06-criar-revista-desktop');
      for (const width of [320, 390, 768, 1280]) {
        await setViewport(width, width < 500 ? 844 : 900);
        const createDimensions = JSON.parse(await exec(`const form=document.querySelector('form.node-revista-form');const r=form?.getBoundingClientRect();return JSON.stringify({width:innerWidth,page:document.documentElement.scrollWidth,formWidth:r?.width,formRight:r?.right});`));
        check(`formulário de criação sem overflow em ${width}px`, createDimensions.page <= width && createDimensions.formRight <= width + 1, JSON.stringify(createDimensions));
        if (width === 320) await shot('07-criar-revista-mobile');
      }
      await setViewport(1280, 900);
    }
    else {
      check('owner GET renderiza formulário Form API de criação sem submit', null, 'ação de criação ausente para esta conta');
      check('formulário e título de criação recebem tipografia Fidelity', null, 'ação de criação ausente para esta conta');
      for (const width of [320, 390, 768, 1280]) check(`formulário de criação sem overflow em ${width}px`, null, 'ação de criação ausente para esta conta');
    }
  }
  else {
    check('owner GET renderiza o Form API nativo de edição sem submit', null, 'não há link de edição próprio nesta conta');
    check('formulário editorial recebe tipografia Fidelity escopada ao Marley', null, 'não há link de edição próprio nesta conta');
    check('título e composição do editor recebem tipografia Fidelity', null, 'não há link de edição próprio nesta conta');
    check('Institution Bar e Domain Header permanecem únicos e fora dos tokens Studio', null, 'não há link de edição próprio nesta conta');
    for (const width of [320, 390, 768, 1280]) check(`formulário editorial sem overflow em ${width}px`, null, 'não há link de edição próprio nesta conta');
    check('ação Nova publicação aponta para a rota Form API proprietária', null, 'não há publicação própria para iniciar inspeção de formulário');
    check('owner GET renderiza formulário Form API de criação sem submit', null, 'não há publicação própria para iniciar inspeção de formulário');
    check('formulário e título de criação recebem tipografia Fidelity', null, 'não há publicação própria para iniciar inspeção de formulário');
    for (const width of [320, 390, 768, 1280]) check(`formulário de criação sem overflow em ${width}px`, null, 'não há publicação própria para iniciar inspeção de formulário');
  }

  const errs = await exec(`return (window.__errs || []).slice(0, 10);`);
  check('sem erros de JavaScript na página', errs.length === 0, errs.join(' | '));

  // Account dashboard and the owner-backed public-profile editor are separate
  // entry points from Marley and from private account forms.
  await navigateTo(new URL('/', LOGIN_URI).href);
  const dashboardReady = await waitFor(`document.querySelector('.aculta-account-entry h1')?.textContent.trim()==='Studio Dashboard'`, 'Studio Dashboard');
  check('Dashboard da Conta apresenta o título canônico', dashboardReady);
  if (dashboardReady) {
    const dashboard = JSON.parse(await exec(`const entry=document.querySelector('.aculta-account-entry');const links=[...entry.querySelectorAll('a[href]')];const profile=links.find((a)=>new URL(a.href).pathname==='/perfil');const publicProfile=entry.querySelector('[data-portal-ui="studio-profile-public-link"]');const privateAccount=links.find((a)=>new URL(a.href).pathname==='/conta/dados');const experiences=links.filter((a)=>a!==profile&&a!==publicProfile&&a!==privateAccount).map((a)=>({label:a.textContent.trim(),href:a.href}));return JSON.stringify({identity:!!entry.querySelector('.studio-account-identity'),profile:profile?.getAttribute('href')||null,publicProfile:publicProfile?.getAttribute('href')||null,privateAccount:privateAccount?.getAttribute('href')||null,experiences,cards:entry.querySelectorAll('.aculta-card').length});`));
    check('Dashboard separa Meu Perfil, perfil público, Minha Conta e destinos autorizados', dashboard.profile === '/perfil' && dashboard.privateAccount === '/conta/dados' && dashboard.experiences.every((item) => !!item.href && !!item.label), JSON.stringify({identity: dashboard.identity, profile: dashboard.profile, publicProfileAvailable: !!dashboard.publicProfile, privateAccount: dashboard.privateAccount, experienceCount: dashboard.experiences.length, cards: dashboard.cards}));
    await shot('00-conta-dashboard');
    for (const width of [320, 390, 768, 1280]) {
      await setViewport(width, width < 500 ? 844 : 900);
      const dashboardLayout = JSON.parse(await exec(`const actions=document.querySelector('.aculta-account-entry .studio__actions');const columns=actions?getComputedStyle(actions).gridTemplateColumns.split(' ').length:0;return JSON.stringify({width:innerWidth,page:document.documentElement.scrollWidth,columns});`));
      check(`Dashboard sem overflow e com grade responsiva em ${width}px`, dashboardLayout.page <= width && dashboardLayout.columns === (width < 768 ? 1 : 2), JSON.stringify(dashboardLayout));
    }
    await setViewport(1280, 900);
    if (dashboard.profile) {
      await navigateTo(new URL(dashboard.profile, LOGIN_URI).href);
      const profileReady = await waitFor(`document.querySelector('.aculta-profile-editor')`, 'editor do perfil próprio');
      const profileState = JSON.parse(await exec(`const editor=document.querySelector('.aculta-profile-editor');return JSON.stringify({editor:!!editor,privateRoot:!!document.querySelector('[data-aculta-account-root]'),privateLabels:[...document.querySelectorAll('label')].map((l)=>l.innerText.trim()).filter((t)=>/CPF|CNPJ|CEP|endereço|complemento/i.test(t)),publicLink:!!document.querySelector('[data-portal-ui="studio-profile-public-link"]')});`));
      check('Meu Perfil é independente dos formulários privados da Conta', profileReady && !profileState.privateRoot && profileState.privateLabels.length === 0, JSON.stringify(profileState));
      if (profileReady) {
        await shot('07-meu-perfil');
        const identityActions = JSON.parse(await exec(`const card=document.querySelector('.aculta-profile-editor__identity .aculta-profile-identity');const actions=card?.querySelector('.aculta-profile-identity__actions');const cardStyle=card?getComputedStyle(card):null;const links=[...(actions?.querySelectorAll('a,button')||[])].map((el)=>{const r=el.getBoundingClientRect();return {width:r.width,height:r.height}});return JSON.stringify({direction:cardStyle?.flexDirection,actionsWidth:actions?.getBoundingClientRect().width||0,links});`));
        check('ações do Perfil permanecem legíveis sob a identidade na coluna lateral', identityActions.direction === 'column' && identityActions.actionsWidth >= 320 && identityActions.links.length > 0 && identityActions.links.every((link) => link.width >= 120 && link.height <= 64), JSON.stringify(identityActions));
        for (const width of [320, 390, 768, 1280]) {
          await setViewport(width, width < 500 ? 844 : 900);
          const profileLayout = JSON.parse(await exec(`const grid=document.querySelector('.aculta-profile-editor__grid');const columns=grid?getComputedStyle(grid).gridTemplateColumns.split(' ').length:0;return JSON.stringify({width:innerWidth,page:document.documentElement.scrollWidth,columns});`));
          check(`Meu Perfil reorganiza em ${width}px sem overflow`, profileLayout.page <= width && profileLayout.columns === (width < 768 ? 1 : 2), JSON.stringify(profileLayout));
          if (width === 320) await shot('08-meu-perfil-mobile');
        }
        await setViewport(1280, 900);
        const zoomResults = [];
        for (const target of [2, 4]) {
          pressFirefoxKey('ctrl+0');
          await sleep(250);
          let zoom = JSON.parse(await exec(`return JSON.stringify({width:innerWidth,dpr:devicePixelRatio,page:document.documentElement.scrollWidth});`));
          for (let attempt = 0; attempt < 24 && 1280 / Math.max(1, zoom.width) < target - 0.08; attempt += 1) {
            pressFirefoxKey('ctrl+plus');
            await sleep(120);
            zoom = JSON.parse(await exec(`return JSON.stringify({width:innerWidth,dpr:devicePixelRatio,page:document.documentElement.scrollWidth});`));
          }
          const effective = 1280 / Math.max(1, zoom.width);
          const noOverflow = zoom.page <= zoom.width;
          zoomResults.push({ target, effective: Number(effective.toFixed(2)), ...zoom, noOverflow });
          check(`Meu Perfil reflow em zoom real de ${target * 100}% sem overflow`, effective >= target - 0.08 && noOverflow, JSON.stringify(zoomResults.at(-1)));
          await shot(`09-meu-perfil-zoom-${target * 100}`);
        }
        pressFirefoxKey('ctrl+0');
        await sleep(250);
        check('zoom do Firefox restaurado ao terminar a medição', await exec(`return Math.abs(innerWidth-1280)<=2;`), await exec(`return JSON.stringify({width:innerWidth,dpr:devicePixelRatio});`));
      }
    } else check('Meu Perfil é independente dos formulários privados da Conta', false, 'link de perfil não foi renderizado');
  } else check('Dashboard separa entrada do Perfil e lista apenas destinos autorizados', false, 'Dashboard ausente');

  if (ACCOUNT_CONTEXT_PAGE) {
    await navigateTo(ACCOUNT_CONTEXT_PAGE);
    const navReady = await waitFor(`document.querySelector('.studio__subtabs')`, 'navegação contextual da Conta');
    check('Conta renderiza navegação contextual do Studio', navReady);
    if (navReady) {
      await shot('05-conta-dados');
      const directAccountRoutes = JSON.parse(await exec(`return (async()=>{const paths=['/conta/dados','/conta/endereco','/conta/documentos','/conta/seguranca','/conta/seguranca/senha','/conta/conexoes','/conta/pedidos','/conta/apoio','/conta/preferencias'];const rows=[];for(const path of paths){try{const response=await fetch(path,{credentials:'same-origin',cache:'no-store'});const html=await response.text();const parsed=new DOMParser().parseFromString(html,'text/html');rows.push({path,status:response.status,finalPath:new URL(response.url).pathname,title:parsed.title,form:!!parsed.querySelector('form')});}catch(error){rows.push({path,error:String(error)});}}return JSON.stringify(rows);})()`));
      const unexpectedAccountRoutes = directAccountRoutes.filter((route) => route.error || (route.path === '/conta/documentos' ? ![200, 404].includes(route.status) : route.status !== 200));
      check('rotas Conta respondem direto com status coerente à disponibilidade (documentos opcional)', directAccountRoutes.length === 9 && unexpectedAccountRoutes.length === 0, JSON.stringify(directAccountRoutes));
      const navAudit = JSON.parse(await exec(`const nav=document.querySelector('.studio__subtabs');const links=[...nav.querySelectorAll('a')];return JSON.stringify({links:links.length,current:links.filter((a)=>a.getAttribute('aria-current')==='page').length,direction:getComputedStyle(nav).flexDirection,wrap:getComputedStyle(nav).flexWrap,items:[...nav.children].map((item)=>{const s=getComputedStyle(item);return [s.borderTopWidth,s.borderRightWidth,s.borderBottomWidth,s.borderLeftWidth,s.borderRadius,s.padding,s.backgroundColor]})});`));
      check('Conta preserva links e estado atual sem moldura de list-group', navAudit.links > 0 && navAudit.current === 1 && navAudit.direction === 'row' && navAudit.wrap === 'wrap' && navAudit.items.every((style) => style.join('|') === '0px|0px|0px|0px|0px|0px|rgba(0, 0, 0, 0)'), JSON.stringify(navAudit));
      const preservedClicks = JSON.parse(await exec(`const cases=[{name:'Ctrl',href:'/conta/endereco',ctrlKey:true},{name:'Shift',href:'/conta/endereco',shiftKey:true},{name:'Alt',href:'/conta/endereco',altKey:true},{name:'target',href:'/conta/endereco',target:'_blank'},{name:'download',href:'/conta/endereco',download:true},{name:'externo',href:'https://example.invalid/'}];const rows=[];for(const item of cases){const link=document.createElement('a');link.dataset.acultaAccountLink='';link.href=item.href;link.textContent=item.name;if(item.target)link.target=item.target;if(item.download)link.download='arquivo';document.querySelector('[data-aculta-account-root]').append(link);let rootPrevented=false;const guard=(event)=>{if(event.target===link){rootPrevented=event.defaultPrevented;event.preventDefault()}};document.addEventListener('click',guard,{once:true});const event=new MouseEvent('click',{bubbles:true,cancelable:true,view:window,button:0,ctrlKey:!!item.ctrlKey,shiftKey:!!item.shiftKey,altKey:!!item.altKey});link.dispatchEvent(event);rows.push({name:item.name,rootPrevented,stillCurrent:location.pathname==='/conta/dados'});link.remove()}const logout=document.createElement('a');logout.href='/sair';logout.textContent='Sair';document.querySelector('[data-aculta-account-root]').append(logout);let logoutPrevented=false;document.addEventListener('click',(event)=>{if(event.target===logout){logoutPrevented=event.defaultPrevented;event.preventDefault()}},{once:true});logout.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true,view:window,button:0}));logout.remove();return JSON.stringify({cases:rows,logoutPrevented});`));
      check('Conta preserva Ctrl/Shift/Alt, target, download, externo e logout', preservedClicks.cases.every((item) => !item.rootPrevented && item.stillCurrent) && !preservedClicks.logoutPrevented, JSON.stringify(preservedClicks));
      for (const width of [320, 390, 768, 1280]) {
        await setViewport(width, width < 500 ? 844 : 900);
        const dimensions = JSON.parse(await exec(`const nav=document.querySelector('.studio__subtabs');const targets=[...nav.querySelectorAll('a')].map((link)=>{const r=link.getBoundingClientRect();return {text:link.textContent.trim(),width:r.width,height:r.height}});return JSON.stringify({width:window.innerWidth,page:document.documentElement.scrollWidth,nav:nav?.scrollWidth,client:nav?.clientWidth,targets});`));
        check(`Conta sem overflow com abas em ${width}px`, dimensions.page <= width && dimensions.nav <= dimensions.client + 1, JSON.stringify(dimensions));
        check(`Conta com alvos de aba mínimos em ${width}px`, dimensions.targets.length > 0 && dimensions.targets.every((target) => target.width >= 44 && target.height >= 44), JSON.stringify(dimensions.targets));
      }

      await setViewport(1280, 900);
      const accountKeyboard = [];
      for (let index = 0; index < 34; index += 1) {
        await wd('POST', p('/actions'), { actions: [{ type: 'key', id: 'studio-account-keyboard', actions: [{ type: 'keyDown', value: '\uE004' }, { type: 'keyUp', value: '\uE004' }] }] });
        const focus = JSON.parse(await exec(`const e=document.activeElement;let href='';try{const u=new URL(e?.href||'',location.href);href=u.origin===location.origin?u.pathname:u.hostname+u.pathname}catch{}return JSON.stringify({tag:e?.tagName||'',text:(e?.innerText||e?.getAttribute('aria-label')||'').trim().slice(0,60),href,visible:!!e&&e.getClientRects().length>0,focusVisible:!!e?.matches(':focus-visible')});`));
        accountKeyboard.push(focus);
      }
      check('teclado Tab percorre controles visíveis da Conta com foco perceptível', accountKeyboard.filter((item) => item.tag === 'A' && item.href.startsWith('/conta/')).length > 0 && accountKeyboard.every((item) => item.visible && (item.tag !== 'A' || item.focusVisible)), JSON.stringify(accountKeyboard));

      // The account navigation has a separate Ajax contract from Marley. Exercise its
      // real Drupal.Ajax response, root replacement, metadata, and browser history.
      await setViewport(1280, 900);
      const beforeAccountNavigation = JSON.parse(await exec(`sessionStorage.setItem('aculta-account-navigation-time-origin',String(performance.timeOrigin));return JSON.stringify({path:location.pathname,title:document.title,root:!!document.querySelector('[data-aculta-account-root]'),behavior:!!Drupal?.behaviors?.acultaStudioAccountNavigation,timeOrigin:performance.timeOrigin});`));
      const addressLink = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-account-link][href="/conta/endereco"]' }).catch(() => null);
      if (addressLink) {
        const addressId = Object.values(addressLink)[0];
        await wd('POST', p(`/element/${addressId}/click`), {});
        const addressLoaded = await waitFor(`location.pathname==='/conta/endereco'&&document.querySelector('[data-aculta-account-root] [data-aculta-account-link][aria-current="page"]')?.getAttribute('href')==='/conta/endereco'&&document.querySelector('[data-aculta-account-root] form')`, 'seção Endereço via AJAX');
        const addressState = JSON.parse(await exec(`const sameDocument=Number(sessionStorage.getItem('aculta-account-navigation-time-origin'))===performance.timeOrigin;sessionStorage.removeItem('aculta-account-navigation-time-origin');return JSON.stringify({path:location.pathname,title:document.title,root:!!document.querySelector('[data-aculta-account-root]'),behavior:!!Drupal?.behaviors?.acultaStudioAccountNavigation,live:!!document.querySelector('[data-aculta-account-live]'),busy:document.querySelector('[data-aculta-account-root]')?.hasAttribute('aria-busy'),current:document.querySelector('[data-aculta-account-root] [data-aculta-account-link][aria-current="page"]')?.getAttribute('href'),form:!!document.querySelector('[data-aculta-account-root] form'),sameDocument,breadcrumb:document.querySelector('[data-portal-breadcrumb]')?.innerText||''});`));
        check('Conta troca para Endereço via Drupal.Ajax sem reload', addressLoaded && addressState.sameDocument && addressState.root && addressState.form, JSON.stringify({before:beforeAccountNavigation,addressLoaded,state:addressState}));
        check('Conta consome título, breadcrumb e limpa estado busy', addressState.title !== beforeAccountNavigation.title && addressState.breadcrumb.length > 0 && !addressState.busy, JSON.stringify({before:beforeAccountNavigation.title,after:addressState.title,breadcrumb:addressState.breadcrumb,busy:addressState.busy}));
        check('Drupal behavior da Conta permanece ligado após replaceWith', addressState.behavior && addressState.live, JSON.stringify(addressState));
        if (addressLoaded) await shot('06-conta-endereco-ajax');

        await exec(`history.back();return 1;`);
        const accountBack = await waitFor(`location.pathname==='/conta/dados'&&document.querySelector('[data-aculta-account-root] [data-aculta-account-link][aria-current="page"]')?.getAttribute('href')==='/conta/dados'`, 'voltar para Dados via popstate');
        check('voltar do navegador restaura a seção Conta anterior via AJAX', accountBack, await exec(`return JSON.stringify({path:location.pathname,current:document.querySelector('[data-aculta-account-root] [data-aculta-account-link][aria-current="page"]')?.getAttribute('href'),entries:performance.getEntriesByType('navigation').length});`));
        await exec(`history.forward();return 1;`);
        const accountForward = await waitFor(`location.pathname==='/conta/endereco'&&document.querySelector('[data-aculta-account-root] [data-aculta-account-link][aria-current="page"]')?.getAttribute('href')==='/conta/endereco'`, 'avançar para Endereço via popstate');
        check('avançar do navegador restaura a seção Conta seguinte via AJAX', accountForward, await exec(`return JSON.stringify({path:location.pathname,current:document.querySelector('[data-aculta-account-root] [data-aculta-account-link][aria-current="page"]')?.getAttribute('href')});`));
        await exec(`history.back();return 1;`);
        await waitFor(`location.pathname==='/conta/dados'&&document.querySelector('[data-aculta-account-root] [data-aculta-account-link][aria-current="page"]')?.getAttribute('href')==='/conta/dados'`, 'retorno a Dados antes do menu mobile');

        await setViewport(390, 844);
        const detailsReady = await waitFor(`document.querySelector('[data-aculta-account-root] details')`, 'menu responsivo de Conta');
        if (detailsReady) {
          await exec(`const d=document.querySelector('[data-aculta-account-root] details');d.open=false;d.querySelector('summary').click();return 1;`);
          check('menu Conta pode fechar e reabrir em viewport móvel', await exec(`return document.querySelector('[data-aculta-account-root] details')?.open===true;`));
          const securityLink = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-account-link][href="/conta/seguranca"]' }).catch(() => null);
          if (securityLink) {
            await wd('POST', p(`/element/${Object.values(securityLink)[0]}/click`), {});
            const securityLoaded = await waitFor(`location.pathname==='/conta/seguranca'&&document.querySelector('[data-aculta-account-root] form')`, 'Segurança via AJAX no mobile');
            check('navegação mobile abre outra seção sem perder a raiz', securityLoaded && await exec(`return !!document.querySelector('[data-aculta-account-root] details')&&document.documentElement.scrollWidth<=innerWidth;`));
          } else check('navegação mobile abre outra seção sem perder a raiz', false, 'link de Segurança ausente');

          // Exercise the navigation error callback without causing a server write.
          // The next retry restores Drupal.Ajax.execute and reaches a real GET route.
          await installAjaxFailure(503, 'Service Unavailable');
          const dataLink = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-account-link][href="/conta/dados"]' }).catch(() => null);
          if (dataLink) {
            await wd('POST', p(`/element/${Object.values(dataLink)[0]}/click`), {});
            const retryReady = await waitFor(`!!document.querySelector('[data-aculta-account-retry]')`, 'botão de retry após falha');
            const failedState = JSON.parse(await exec(`return JSON.stringify({retry:!!document.querySelector('[data-aculta-account-retry]'),message:document.querySelector('[data-aculta-account-live]')?.textContent||'',busy:document.querySelector('[data-aculta-account-root]')?.hasAttribute('aria-busy'),path:location.pathname});`));
            check('falha de navegação anuncia erro e oferece retry', retryReady && /não foi possível carregar/i.test(failedState.message) && !failedState.busy && failedState.path === '/conta/seguranca', JSON.stringify(failedState));
            await restoreAjax();
            if (retryReady) {
              const retry = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-account-retry]' });
              await wd('POST', p(`/element/${Object.values(retry)[0]}/click`), {});
              const retryRecovered = await waitFor(`location.pathname==='/conta/dados'&&document.querySelector('[data-aculta-account-root] form')&&!document.querySelector('[data-aculta-account-retry]')`, 'retry da navegação Conta');
              check('retry recupera a seção com resposta Drupal real', retryRecovered, await exec(`return JSON.stringify({path:location.pathname,form:!!document.querySelector('[data-aculta-account-root] form'),retry:!!document.querySelector('[data-aculta-account-retry]')});`));
            } else check('retry recupera a seção com resposta Drupal real', false, 'controle de retry ausente');

            // Authorization/not-found responses leave the current private section
            // intact and offer retry, as required for server-side access failures.
            for (const status of [403, 404]) {
              await installAjaxFailure(status, status === 403 ? 'Forbidden' : 'Not Found');
              const failureClick = JSON.parse(await exec(`const link=document.querySelector('[data-aculta-account-link][href="/conta/seguranca"]');if(!link)return JSON.stringify({found:false});const root=document.querySelector('[data-aculta-account-root]');root.removeAttribute('data-studio-test-guard');const guard=(event)=>{if(event.target.closest('[data-aculta-account-link][href="/conta/seguranca"]')){root.dataset.studioTestGuard=event.defaultPrevented?'prevented':'missed';event.preventDefault()}};document.addEventListener('click',guard);const event=new MouseEvent('click',{bubbles:true,cancelable:true,view:window,button:0});link.dispatchEvent(event);document.removeEventListener('click',guard);return JSON.stringify({found:true,guard:root.dataset.studioTestGuard||'',path:location.pathname,retry:!!document.querySelector('[data-aculta-account-retry]')});`));
              if (failureClick.found) {
                const errorReady = await waitFor(`!!document.querySelector('[data-aculta-account-retry]')`, `erro HTTP ${status} na Conta`);
                const errorState = JSON.parse(await exec(`return JSON.stringify({path:location.pathname,retry:!!document.querySelector('[data-aculta-account-retry]'),busy:document.querySelector('[data-aculta-account-root]')?.hasAttribute('aria-busy'),message:document.querySelector('[data-aculta-account-live]')?.textContent||''});`));
                check(`HTTP ${status} na navegação encerra busy e oferece recuperação`, errorReady && failureClick.guard === 'prevented' && errorState.path === '/conta/dados' && errorState.retry && !errorState.busy, JSON.stringify({click:failureClick,state:errorState}));
                await restoreAjax();
                if (errorReady) {
                  await exec(`document.querySelector('[data-aculta-account-retry]').click();return 1;`);
                  const recovered = await waitFor(`location.pathname==='/conta/seguranca'&&document.querySelector('[data-aculta-account-root] form')`, `retry após HTTP ${status}`);
                  check(`retry após HTTP ${status} restaura o destino com resposta real`, recovered, await exec(`return JSON.stringify({path:location.pathname,form:!!document.querySelector('[data-aculta-account-root] form'),retry:!!document.querySelector('[data-aculta-account-retry]')});`));
                } else check(`retry após HTTP ${status} restaura o destino com resposta real`, false, 'controle de retry ausente');
                const returnToData = await exec(`const link=document.querySelector('[data-aculta-account-link][href="/conta/dados"]');if(!link)return false;link.click();return true;`);
                if (returnToData) await waitFor(`location.pathname==='/conta/dados'&&document.querySelector('[data-aculta-account-root] form')`, 'retorno ao formulário Dados');
              } else {
                await restoreAjax();
                check(`HTTP ${status} na navegação encerra busy e oferece recuperação`, false, 'link Segurança ausente');
                check(`retry após HTTP ${status} restaura o destino com resposta real`, false, 'link Segurança ausente');
              }
            }

            // Two eligible clicks in the same task must leave only the latest request visible.
            await exec(`const a=document.querySelector('[data-aculta-account-link][href="/conta/seguranca"]');const b=document.querySelector('[data-aculta-account-link][href="/conta/endereco"]');a.click();b.click();return 1;`);
            const latestClickWins = await waitFor(`location.pathname==='/conta/endereco'&&document.querySelector('[data-aculta-account-root] [data-aculta-account-link][aria-current="page"]')?.getAttribute('href')==='/conta/endereco'`, 'cliques concorrentes Conta');
            check('cliques rápidos preservam a resposta da última intenção', latestClickWins && await exec(`return document.title.startsWith('Endereço')&&!!document.querySelector('[data-aculta-account-root] form');`), await exec(`return JSON.stringify({path:location.pathname,title:document.title,current:document.querySelector('[data-aculta-account-root] [data-aculta-account-link][aria-current="page"]')?.getAttribute('href')});`));

            // Force only the navigation timeout timer to run immediately and delay the
            // actual GET; this proves a late response cannot replace the fallback state.
            await setViewport(1280, 900);
            await exec(`window.__accountNativeTimeout=window.setTimeout.bind(window);window.setTimeout=(callback,delay,...args)=>delay===15000?window.__accountNativeTimeout(callback,0,...args):window.__accountNativeTimeout(callback,delay,...args);window.__studioOriginalAjax=Drupal.Ajax;const Original=window.__studioOriginalAjax;Drupal.Ajax=function(...args){const request=new Original(...args);request.execute=()=>{window.__accountNativeTimeout(()=>{Drupal.Ajax=Original;delete window.__studioOriginalAjax;request.error({status:200,statusText:'OK',responseText:''},request.url,'Simulated late response')},100);return Promise.resolve()};return request};Drupal.Ajax.prototype=Original.prototype;return 1;`);
            await exec(`document.querySelector('[data-aculta-account-link][href="/conta/dados"]').click();return 1;`);
            const timeoutReady = await waitFor(`!!document.querySelector('[data-aculta-account-fallback]')`, 'fallback após timeout');
            check('timeout anuncia demora e oferece navegação normal', timeoutReady && await exec(`return /demorou demais/i.test(document.querySelector('[data-aculta-account-live]')?.textContent||'')&&!document.querySelector('[data-aculta-account-root]')?.hasAttribute('aria-busy');`), await exec(`return JSON.stringify({path:location.pathname,fallback:!!document.querySelector('[data-aculta-account-fallback]'),busy:document.querySelector('[data-aculta-account-root]')?.hasAttribute('aria-busy')});`));
            await sleep(300);
            check('resposta tardia após timeout não substitui o conteúdo atual', await exec(`return location.pathname==='/conta/endereco'&&!!document.querySelector('[data-aculta-account-fallback]');`), await exec(`return JSON.stringify({path:location.pathname,fallback:!!document.querySelector('[data-aculta-account-fallback]'),title:document.title});`));
            const fallback = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-account-fallback]' }).catch(() => null);
            if (fallback) {
              await wd('POST', p(`/element/${Object.values(fallback)[0]}/click`), {});
              const fallbackRecovered = await waitFor(`location.pathname==='/conta/dados'&&document.querySelector('[data-aculta-account-root] form')`, 'abertura direta após timeout');
              check('fallback abre a rota direta sem JavaScript AJAX', fallbackRecovered, await exec(`return JSON.stringify({path:location.pathname,root:!!document.querySelector('[data-aculta-account-root]'),form:!!document.querySelector('[data-aculta-account-root] form')});`));
              const addressAgain = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-account-link][href="/conta/endereco"]' }).catch(() => null);
              if (addressAgain) {
                await wd('POST', p(`/element/${Object.values(addressAgain)[0]}/click`), {});
                const addressFormReady = await waitFor(`location.pathname==='/conta/endereco'&&document.querySelector('[data-aculta-account-root] form')`, 'Form API Endereço após várias trocas');
                const addressFormState = JSON.parse(await exec(`const f=document.querySelector('[data-aculta-account-root] form');return JSON.stringify({path:location.pathname,form:!!f,id:f?.id||'',selector:f?.getAttribute('data-drupal-selector')||'',ajax:typeof Drupal?.Ajax});`));
                check('Form API owner permanece disponível após AJAX, popstate e timeout', addressFormReady, JSON.stringify(addressFormState));
                if (addressFormReady) {
                  const validationOrigin = await exec(`window.__accountFormTimeOrigin=performance.timeOrigin;const f=document.querySelector('[data-aculta-account-root] form');for(const e of f.querySelectorAll('[required]')){e.removeAttribute('required');if(e.tagName==='SELECT')e.value='';else e.value=''}return true;`);
                  const submit = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-account-root] form [type="submit"]' }).catch(() => null);
                  if (submit && validationOrigin) {
                    await wd('POST', p(`/element/${Object.values(submit)[0]}/click`), {});
                    const invalidRebuilt = await waitFor(`document.querySelector('[data-aculta-account-root] form [aria-invalid="true"],[data-aculta-account-root] form .form-item--error-message,.messages--error')`, 'validação inválida do endereço');
                    const validationState = JSON.parse(await exec(`const f=document.querySelector('[data-aculta-account-root] form');return JSON.stringify({sameDocument:performance.timeOrigin===window.__accountFormTimeOrigin,path:location.pathname,form:!!f,id:f?.id||'',invalid:!!document.querySelector('[data-aculta-account-root] form [aria-invalid="true"],[data-aculta-account-root] form .form-item--error-message,.messages--error'),buildId:!!f?.querySelector('input[name="form_build_id"]'),token:!!f?.querySelector('input[name="form_token"]')});`));
                    check('Form API rejeita endereço inválido sem perder a seção nem o formulário', invalidRebuilt && validationState.sameDocument && validationState.form && validationState.invalid && validationState.buildId && validationState.token, JSON.stringify(validationState));
                    const preferencesLink = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-account-link][href="/conta/preferencias"]' }).catch(() => null);
                    if (preferencesLink) {
                      await exec(`document.querySelector('[data-aculta-account-link][href="/conta/preferencias"]').click();return 1;`);
                      const preferencesReady = await waitFor(`location.pathname==='/conta/preferencias'&&document.querySelector('[data-aculta-account-root] form input[type="radio"]:checked')`, 'Preferências com opção atual selecionada');
                      if (preferencesReady) {
                        await exec(`window.__accountPreferenceTimeOrigin=performance.timeOrigin;return 1;`);
                        const preferenceSubmit = await wd('POST', p('/element'), { using: 'css selector', value: '[data-aculta-account-root] form [type="submit"]' }).catch(() => null);
                        if (preferenceSubmit) {
                          await exec(`document.querySelector('[data-aculta-account-root] form [type="submit"]').click();return 1;`);
                          const preferenceSaved = await waitFor(`document.querySelector('[data-aculta-account-root] form input[type="radio"]:checked')&&document.querySelector('[data-aculta-account-root] form [type="submit"]')`, 'Preferências salvas via Form API');
                          const preferenceState = JSON.parse(await exec(`return JSON.stringify({sameDocument:performance.timeOrigin===window.__accountPreferenceTimeOrigin,path:location.pathname,form:!!document.querySelector('[data-aculta-account-root] form'),checked:!!document.querySelector('[data-aculta-account-root] form input[type="radio"]:checked'),error:!!document.querySelector('.messages--error'),status:!!document.querySelector('.messages--status,[role="status"]'),busy:document.querySelector('[data-aculta-account-root]')?.hasAttribute('aria-busy')});`));
                          check('Form API válida de Preferências salva via AJAX sem recarga', preferenceSaved && preferenceState.sameDocument && preferenceState.form && preferenceState.checked && !preferenceState.error && !preferenceState.busy, JSON.stringify(preferenceState));
                          await exec(`window.__afterPreferenceTimeOrigin=performance.timeOrigin;document.querySelector('[data-aculta-account-link][href="/conta/dados"]').click();return 1;`);
                          const postFormNavigation = await waitFor(`location.pathname==='/conta/dados'&&document.querySelector('[data-aculta-account-root] form')`, 'navegação Conta após rebuild Form API');
                          const postFormState = JSON.parse(await exec(`return JSON.stringify({sameDocument:performance.timeOrigin===window.__afterPreferenceTimeOrigin,path:location.pathname,root:!!document.querySelector('[data-aculta-account-root]'),behavior:!!Drupal?.behaviors?.acultaStudioAccountNavigation,form:!!document.querySelector('[data-aculta-account-root] form')});`));
                          check('navegação Conta continua AJAX após rebuild de formulário válido', postFormNavigation && postFormState.sameDocument && postFormState.root && postFormState.behavior && postFormState.form, JSON.stringify(postFormState));
                          await exec(`document.querySelector('[data-aculta-account-link][href="/conta/preferencias"]').click();return 1;`);
                          await waitFor(`location.pathname==='/conta/preferencias'&&document.querySelector('[data-aculta-account-root] form input[type="radio"]:checked')`, 'retorno a Preferências antes do teste de expiração');
                          // Revoke only this temporary browser's Drupal session and
                          // verify the real anonymous/expired-session Ajax failure.
                          await wd('DELETE', p('/cookie'), {});
                          await exec(`document.querySelector('[data-aculta-account-link][href="/conta/dados"]').click();return 1;`);
                          const expiredSessionFailure = await waitFor(`!!document.querySelector('[data-aculta-account-retry]')`, 'resposta Ajax após expiração de sessão');
                          const expiredState = JSON.parse(await exec(`return JSON.stringify({path:location.pathname,retry:!!document.querySelector('[data-aculta-account-retry]'),message:document.querySelector('[data-aculta-account-live]')?.textContent||'',busy:document.querySelector('[data-aculta-account-root]')?.hasAttribute('aria-busy'),privateForm:!!document.querySelector('[data-aculta-account-root] form')});`));
                          check('sessão expirada encerra o estado busy e oferece recuperação', expiredSessionFailure && expiredState.path === '/conta/preferencias' && expiredState.retry && !expiredState.busy, JSON.stringify(expiredState));
                        } else check('Form API válida de Preferências salva via AJAX sem recarga', false, 'botão de Preferências ausente');
                      } else check('Form API válida de Preferências salva via AJAX sem recarga', false, 'formulário de Preferências ausente');
                    } else check('Form API válida de Preferências salva via AJAX sem recarga', false, 'link de Preferências ausente');
                  } else check('Form API rejeita endereço inválido sem perder a seção nem o formulário', false, 'botão de envio ausente');
                } else check('Form API rejeita endereço inválido sem perder a seção nem o formulário', false, 'Form API Endereço ausente');
              } else {
                check('Form API owner permanece disponível após AJAX, popstate e timeout', false, 'link Endereço ausente');
                check('Form API rejeita endereço inválido sem perder a seção nem o formulário', false, 'link Endereço ausente');
                check('Form API válida de Preferências salva via AJAX sem recarga', false, 'link Endereço ausente');
                check('navegação Conta continua AJAX após rebuild de formulário válido', false, 'link Endereço ausente');
                check('sessão expirada encerra o estado busy e oferece recuperação', false, 'link Endereço ausente');
              }
            } else check('fallback abre a rota direta sem JavaScript AJAX', false, 'link fallback ausente');
          } else {
            await restoreAjax();
            check('falha de navegação anuncia erro e oferece retry', false, 'link Dados ausente');
            check('retry recupera a seção com resposta Drupal real', false, 'link Dados ausente');
            check('cliques rápidos preservam a resposta da última intenção', false, 'link Dados ausente');
            check('timeout anuncia demora e oferece navegação normal', false, 'link Dados ausente');
            check('resposta tardia após timeout não substitui o conteúdo atual', false, 'link Dados ausente');
            check('fallback abre a rota direta sem JavaScript AJAX', false, 'link Dados ausente');
            check('Form API owner permanece disponível após AJAX, popstate e timeout', false, 'link Dados ausente');
            check('Form API rejeita endereço inválido sem perder a seção nem o formulário', false, 'link Dados ausente');
          }
        } else {
          check('menu Conta pode fechar e reabrir em viewport móvel', false, 'details ausente');
          check('navegação mobile abre outra seção sem perder a raiz', false, 'details ausente');
          for (const label of ['falha de navegação anuncia erro e oferece retry','retry recupera a seção com resposta Drupal real','cliques rápidos preservam a resposta da última intenção','timeout anuncia demora e oferece navegação normal','resposta tardia após timeout não substitui o conteúdo atual','fallback abre a rota direta sem JavaScript AJAX','Form API owner permanece disponível após AJAX, popstate e timeout','Form API rejeita endereço inválido sem perder a seção nem o formulário','Form API válida de Preferências salva via AJAX sem recarga','navegação Conta continua AJAX após rebuild de formulário válido','sessão expirada encerra o estado busy e oferece recuperação']) check(label, false, 'details ausente');
        }
      } else {
        check('Conta troca para Endereço via Drupal.Ajax sem reload', false, 'link Endereço ausente');
        check('Conta consome título, breadcrumb e limpa estado busy', false, 'link Endereço ausente');
        check('Drupal behavior da Conta permanece ligado após replaceWith', false, 'link Endereço ausente');
        check('voltar do navegador restaura a seção Conta anterior via AJAX', false, 'link Endereço ausente');
        check('menu Conta pode fechar e reabrir em viewport móvel', false, 'link Endereço ausente');
        check('navegação mobile abre outra seção sem perder a raiz', false, 'link Endereço ausente');
        for (const label of ['falha de navegação anuncia erro e oferece retry','retry recupera a seção com resposta Drupal real','cliques rápidos preservam a resposta da última intenção','timeout anuncia demora e oferece navegação normal','resposta tardia após timeout não substitui o conteúdo atual','fallback abre a rota direta sem JavaScript AJAX']) check(label, false, 'link Endereço ausente');
        check('Form API owner permanece disponível após AJAX, popstate e timeout', false, 'link Endereço ausente');
        check('Form API rejeita endereço inválido sem perder a seção nem o formulário', false, 'link Endereço ausente');
        check('Form API válida de Preferências salva via AJAX sem recarga', false, 'link Endereço ausente');
        check('navegação Conta continua AJAX após rebuild de formulário válido', false, 'link Endereço ausente');
        check('sessão expirada encerra o estado busy e oferece recuperação', false, 'link Endereço ausente');
      }
    }
  }
} catch (e) {
  check('execução da revisão', false, String(e.message || e));
} finally {
  writeFileSync(join(OUT, 'results.json'), JSON.stringify(results, null, 2));
  bidi.close();
  await wd('DELETE', p('')).catch(() => {});
  const failed = results.filter((r) => r.ok === false).length;
  const skipped = results.filter((r) => r.ok === null).length;
  const passed = results.length - failed - skipped;
  console.log(`\n${passed}/${results.length - skipped} verificações OK; ${skipped} ignorada(s), ${failed} falha(s). Capturas e relatório em ${OUT}`);
  process.exit(failed ? 1 : 0);
}
