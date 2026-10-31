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
const OUT = process.env.SUITE_OUT;
const GD = process.env.GECKODRIVER || 'http://127.0.0.1:4444';
const CSS_OVERRIDE = process.env.STUDIO_CSS_OVERRIDE === '1';
const CSS_ROOT = process.env.STUDIO_CSS_ROOT || PROJECT;
const USER_ID = Number(process.env.STUDIO_USER_ID || '5');
if (!OUT) throw new Error('Defina SUITE_OUT em armazenamento fora do repositório.');
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
const check = (name, ok, detail = '') => { results.push({ name, ok: ok === null ? null : !!ok, detail }); console.log(`${ok === null ? 'SKIP' : ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  -- ' + detail : ''}`); return ok === null ? null : !!ok; };

async function wd(method, path, body) {
  const res = await fetch(GD + path, { method, headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  const json = await res.json();
  if (json.value && json.value.error) throw new Error(`${json.value.error}: ${String(json.value.message).split('\n')[0]}`);
  return json.value;
}
const oneTimeLogin = () => execFileSync('php', ['vendor/drush/drush/drush.php', 'uli', '--no-browser', `--name=${USER}`, `--uri=${LOGIN_URI}`], { cwd: DRUSH_ROOT }).toString().trim().split('\n').pop();

const moz = { prefs: { 'network.dns.localDomains': [new URL(PAGE).hostname].join(',') } };
const { value: session } = await wd('POST', '/session', { capabilities: { alwaysMatch: { browserName: 'firefox', acceptInsecureCerts: true, webSocketUrl: true, 'moz:firefoxOptions': moz } } }).then((v) => ({ value: { sessionId: v.sessionId, webSocketUrl: v.capabilities.webSocketUrl } }));
const sid = session.sessionId;
const p = (path) => `/session/${sid}${path}`;
const exec = (script, args = []) => wd('POST', p('/execute/sync'), { script, args });
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
const setViewport = (width, height) => bsend('browsingContext.setViewport', { context, viewport: { width, height } });
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
  check('submenu à esquerda com as seções da área', d.sidebar >= 1, `${d.sidebar} seção(ões)`);
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
    const magazineListTabsReady = await waitFor(`document.querySelectorAll('[data-aculta-studio="subtab"]').length >= 5`, 'cinco estados de Publicações da Revista');
    const magazineListTabs = await exec(`return [...document.querySelectorAll('[data-aculta-studio="subtab"]')].map((a) => a.textContent.trim());`);
    check('Revista apresenta os cinco estados editoriais na seção Publicações', magazineListTabsReady && magazineListTabs.length === 5, magazineListTabs.join(' | '));
    await navigateTo(PAGE);
  }
  else {
    check('Revista apresenta os cinco estados editoriais na seção Publicações', null, 'aba Revista não disponível para esta conta no Runtime; revisar configuração/papel antes da homologação');
  }

  // Navegação AJAX: clicar na aba mantém a página (sem recarga) e atualiza a URL.
  const startPath = await exec(`return window.location.pathname;`);
  const sectionTarget = JSON.parse(await exec(`return JSON.stringify((() => { const a=[...document.querySelectorAll('[data-aculta-studio="section"]')].find((link) => link.getAttribute('aria-current') !== 'page' && new URL(link.href).pathname !== location.pathname); return a ? {href:a.href, text:a.textContent.trim(), ajax:typeof Drupal?.Ajax} : null; })());`));
  check('há um destino de seção diferente para exercitar navegação', !!sectionTarget, JSON.stringify(sectionTarget));
  if (sectionTarget) {
    await exec(`window.__marker='sem-recarga';const a=document.querySelector('[data-aculta-studio="section"][href="${new URL(sectionTarget.href).pathname}"]');const event=new MouseEvent('click',{bubbles:true,cancelable:true,view:window,button:0});a?.dispatchEvent(event);window.__acultaClickPrevented=event.defaultPrevented;return 1;`);
  }
  await sleep(2500);
  const navigationState = JSON.parse(await exec(`return JSON.stringify({marker:window.__marker||null,prevented:window.__acultaClickPrevented??null,path:location.pathname,live:!!document.querySelector('[data-aculta-studio="live"][aria-live="polite"]'),current:!!document.querySelector('[data-aculta-studio="section"][aria-current="page"]'),behavior:!!Drupal?.behaviors?.acultaStudioNavigation,drupalAjax:typeof Drupal?.Ajax});`));
  check('clique no submenu troca o conteúdo sem recarregar a página', !!sectionTarget && navigationState.marker === 'sem-recarga', JSON.stringify({target:sectionTarget,state:navigationState}));
  check('seção ativa marcada por aria-current', navigationState.current, JSON.stringify(navigationState));
  check('URL própria do nível (caminho /marley/área/seção)', navigationState.path.startsWith('/marley/'), navigationState.path);
  check('região aria-live presente', navigationState.live, JSON.stringify(navigationState));
  await exec(`history.back(); return 1;`);
  await sleep(2500);
  check('voltar do navegador devolve o caminho anterior', (await exec(`return window.location.pathname;`)) === startPath, `${startPath} -> ${await exec(`return window.location.pathname;`)}`);

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
    const responsive = JSON.parse(await exec(`const layout=document.querySelector('.studio__layout');const sidebar=layout?.querySelector('.studio__sidebar');const main=layout?.querySelector('.studio__main');const lr=layout?.getBoundingClientRect();const sr=sidebar?.getBoundingClientRect();const mr=main?.getBoundingClientRect();return JSON.stringify({w:window.innerWidth,overflow:document.documentElement.scrollWidth>window.innerWidth,direction:layout?getComputedStyle(layout).flexDirection:null,layoutWidth:lr?.width,sidebarWidth:sr?.width,mainWidth:mr?.width,childrenFit:!!lr&&!!sr&&!!mr&&sr.right<=lr.right+1&&mr.right<=lr.right+1});`));
    check(`sem rolagem horizontal em ${width}px`, !responsive.overflow, `innerWidth=${responsive.w}`);
    check(`composição do Studio correta em ${width}px`, responsive.direction === (width < 768 ? 'column' : 'row') && responsive.sidebarWidth > 0 && responsive.mainWidth > 0 && responsive.childrenFit, JSON.stringify(responsive));
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

  // Fila de moderação do Fórum: comentários de teste aguardando moderação aparecem para a conta.
  await navigateTo(PAGE.replace(/\/marley.*$/, '') + '/marley/forum/moderacao/fila');
  await waitFor(`document.querySelector('[data-aculta-studio="listing"]')`, 'fila do Fórum');
  const queueRows = await exec(`return [...document.querySelectorAll('[data-aculta-studio="listing"] tr')].filter((r) => r.querySelector('td')).length;`);
  check('fila de moderação do Fórum lista comentários aguardando moderação', queueRows >= 1, `${queueRows} linha(s)`);

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
    const editForm = JSON.parse(await exec(`const form=document.querySelector('form.node-form, form[id*="node-revista-edit"]');const main=document.querySelector('.aculta-main');const heading=main?.querySelector('h1');const institution=document.querySelector('.aculta-institution-bar');const domainHeader=document.querySelector('.aculta-header');const s=form?getComputedStyle(form):null;return JSON.stringify({ready:!!form,id:form?.id,className:form?.className,bodyClass:document.body.className,titleField:!!form?.querySelector('[name="title[0][value]"]'),secondary:!!form?.querySelector('[data-aculta-revista="secondary"]'),submit:!!form?.querySelector('[type="submit"]'),fontFamily:s?.fontFamily,color:s?.color,mainFamily:main?getComputedStyle(main).fontFamily:null,headingFamily:heading?getComputedStyle(heading).fontFamily:null,headingSize:heading?getComputedStyle(heading).fontSize:null,institutionCount:document.querySelectorAll('.aculta-institution-bar').length,domainHeaderCount:document.querySelectorAll('.aculta-header').length,shellFidelityTokens:[institution,domainHeader].map((node)=>node?getComputedStyle(node).getPropertyValue('--aculta-studio-fidelity-font-family').trim():null),width:form?.scrollWidth,clientWidth:form?.clientWidth,page:document.documentElement.scrollWidth});`));
    check('owner GET renderiza o Form API nativo de edição sem submit', editFormReady && editForm.ready && editForm.titleField && editForm.submit, JSON.stringify(editForm));
    check('formulário editorial recebe tipografia Fidelity escopada ao Marley', editForm.fontFamily?.includes('Montserrat'), JSON.stringify(editForm));
    check('título e composição do editor recebem tipografia Fidelity', editForm.mainFamily?.includes('Montserrat') && editForm.headingFamily?.includes('Montserrat') && Number.parseFloat(editForm.headingSize) >= 32, JSON.stringify(editForm));
    check('Institution Bar e Domain Header permanecem únicos e fora dos tokens Studio', editForm.institutionCount === 1 && editForm.domainHeaderCount === 1 && editForm.shellFidelityTokens.every((value) => value === ''), JSON.stringify(editForm));
    await shot('04-editor-revista-desktop');
    for (const width of [320, 390, 768, 1280]) {
      await setViewport(width, width < 500 ? 844 : 900);
      const formDimensions = JSON.parse(await exec(`const form=document.querySelector('form.node-form, form[id*="node-revista-edit"]');const r=form?.getBoundingClientRect();return JSON.stringify({width:innerWidth,page:document.documentElement.scrollWidth,formWidth:r?.width,formRight:r?.right});`));
      check(`formulário editorial sem overflow em ${width}px`, formDimensions.page <= width && formDimensions.formRight <= width + 1, JSON.stringify(formDimensions));
      if (width === 320) await shot('05-editor-revista-mobile');
    }
    await setViewport(1280, 900);
  }
  else {
    check('owner GET renderiza o Form API nativo de edição sem submit', null, 'não há link de edição próprio nesta conta');
    check('formulário editorial recebe tipografia Fidelity escopada ao Marley', null, 'não há link de edição próprio nesta conta');
    check('título e composição do editor recebem tipografia Fidelity', null, 'não há link de edição próprio nesta conta');
    check('Institution Bar e Domain Header permanecem únicos e fora dos tokens Studio', null, 'não há link de edição próprio nesta conta');
    for (const width of [320, 390, 768, 1280]) check(`formulário editorial sem overflow em ${width}px`, null, 'não há link de edição próprio nesta conta');
  }

  const errs = await exec(`return (window.__errs || []).slice(0, 10);`);
  check('sem erros de JavaScript na página', errs.length === 0, errs.join(' | '));

  if (ACCOUNT_CONTEXT_PAGE) {
    await navigateTo(ACCOUNT_CONTEXT_PAGE);
    const navReady = await waitFor(`document.querySelector('.studio__subtabs')`, 'navegação contextual da Conta');
    check('Conta renderiza navegação contextual do Studio', navReady);
    if (navReady) {
      const navAudit = JSON.parse(await exec(`const nav=document.querySelector('.studio__subtabs');const links=[...nav.querySelectorAll('a')];return JSON.stringify({links:links.length,current:links.filter((a)=>a.getAttribute('aria-current')==='page').length,direction:getComputedStyle(nav).flexDirection,wrap:getComputedStyle(nav).flexWrap,items:[...nav.children].map((item)=>{const s=getComputedStyle(item);return [s.borderTopWidth,s.borderRightWidth,s.borderBottomWidth,s.borderLeftWidth,s.borderRadius,s.padding,s.backgroundColor]})});`));
      check('Conta preserva links e estado atual sem moldura de list-group', navAudit.links > 0 && navAudit.current === 1 && navAudit.direction === 'row' && navAudit.wrap === 'wrap' && navAudit.items.every((style) => style.join('|') === '0px|0px|0px|0px|0px|0px|rgba(0, 0, 0, 0)'), JSON.stringify(navAudit));
      for (const width of [320, 390, 768, 1280]) {
        await setViewport(width, width < 500 ? 844 : 900);
        const dimensions = JSON.parse(await exec(`const nav=document.querySelector('.studio__subtabs');const targets=[...nav.querySelectorAll('a')].map((link)=>{const r=link.getBoundingClientRect();return {text:link.textContent.trim(),width:r.width,height:r.height}});return JSON.stringify({width:window.innerWidth,page:document.documentElement.scrollWidth,nav:nav?.scrollWidth,client:nav?.clientWidth,targets});`));
        check(`Conta sem overflow com abas em ${width}px`, dimensions.page <= width && dimensions.nav <= dimensions.client + 1, JSON.stringify(dimensions));
        check(`Conta com alvos de aba mínimos em ${width}px`, dimensions.targets.length > 0 && dimensions.targets.every((target) => target.width >= 44 && target.height >= 44), JSON.stringify(dimensions.targets));
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
