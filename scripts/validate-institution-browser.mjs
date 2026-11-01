import { devtoolsUrl, siteOrigin, supportOrigin } from './lib/browser-env.mjs';
// Dependency-free Chrome DevTools review. Artifacts stay in ignored tmp/.
import { writeFile, mkdir } from 'node:fs/promises';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);

const origin = siteOrigin();
const output = new URL('../tmp/institution-review/', import.meta.url);
await mkdir(output, { recursive: true });
const targets = await (await fetch(devtoolsUrl('/json'))).json();
const target = targets.find((item) => item.type === 'page');
const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
let sequence = 0;
const pending = new Map();
socket.onmessage = (event) => {
  const message = JSON.parse(event.data);
  if (pending.has(message.id)) {
    const { resolve, reject } = pending.get(message.id);
    pending.delete(message.id);
    message.error ? reject(new Error(JSON.stringify(message.error))) : resolve(message.result);
  }
};
const call = (method, params = {}) => new Promise((resolve, reject) => {
  const id = ++sequence;
  pending.set(id, { resolve, reject });
  socket.send(JSON.stringify({ id, method, params }));
});
async function evaluate(expression) {
  const result = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
  if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
  return result.result.value;
}
await call('Page.enable');
const report = [];
for (const [label, width, height] of [['desktop', 1440, 1000], ['wide-laptop', 1200, 900], ['menu-limit', 1100, 900], ['menu-collapsed', 1099, 900], ['notebook', 1024, 900], ['tablet', 768, 1024], ['mobile', 480, 844], ['small-mobile', 360, 844]]) {
  await call('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
  await call('Page.navigate', { url: origin + '/' });
  for (let attempt = 0; attempt < 100; attempt++) {
    if (await evaluate('document.readyState === "complete" && !!document.querySelector(".aculta-hero")')) break;
    await new Promise((resolve) => setTimeout(resolve, 300));
  }
  const result = await evaluate(`(() => {
    const toggle = document.querySelector('.aculta-menu-toggle');
    const style = getComputedStyle(document.body);
    return { title: document.title, h1: [...document.querySelectorAll('h1')].map(n => n.innerText),
      overflow: document.documentElement.scrollWidth > innerWidth, bodyFont: style.fontFamily,
      bodySize: style.fontSize, projects: document.querySelectorAll('.aculta-project').length,
      bodyLineHeight: style.lineHeight,
      h1Size: getComputedStyle(document.querySelector('.aculta-hero h1')).fontSize,
      slogan: document.querySelector('.aculta-hero-slogan')?.innerText,
      projectParagraphCounts: [...document.querySelectorAll('.aculta-project .aculta-prose')].map(n => n.querySelectorAll('p').length),
      utility: !!document.querySelector('.aculta-utility'),
      publicAcronym: /\\bACULTA\\b/.test(document.body.innerText),
      fullNameVisible: document.body.innerText.includes('Associação Cultural Antiproibicionista'),
      defaultContent: /Welcome!|Lorem ipsum|AGUARDANDO|Em construção|No front page content/.test(document.body.innerText),
      toggleVisible: getComputedStyle(toggle).display !== 'none',
      menuLabels: [...document.querySelectorAll('#aculta-primary-menu a')].map(n => n.innerText),
      menuContactPath: new URL([...document.querySelectorAll('#aculta-primary-menu a')].find(n => n.innerText === 'Contato').href).pathname,
      cnpj: document.querySelector('main').innerText.includes('68.238.467/0001-08'),
      footerCnpj: document.querySelector('footer.aculta-footer').innerText.includes('68.238.467/0001-08'),
      headings: [...document.querySelectorAll('main h2, main h3')].map(n => n.innerText) };
  })()`);
  if (result.toggleVisible) {
    await evaluate('document.querySelector(".aculta-menu-toggle").click()');
    await new Promise((resolve) => setTimeout(resolve, 500));
    result.menuOpened = await evaluate('document.querySelector(".aculta-menu-toggle").getAttribute("aria-expanded") === "true"');
    await evaluate('document.querySelector("#aculta-primary-menu").dispatchEvent(new KeyboardEvent("keydown", {key:"Escape",bubbles:true}))');
    await new Promise((resolve) => setTimeout(resolve, 500));
    result.menuClosedWithEscape = await evaluate('document.querySelector(".aculta-menu-toggle").getAttribute("aria-expanded") === "false" && document.activeElement.classList.contains("aculta-menu-toggle")');
  }
  const screenshot = await call('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
  await writeFile(new URL(label + '.png', output), Buffer.from(screenshot.data, 'base64'));
  report.push({ viewport: label, width, ...result });
}
const pages = await evaluate(`(async () => {
  const urls = [...new Set([...document.querySelectorAll('a[href]')].map(a => a.href)
    .filter(url => url.startsWith(location.origin) && !url.includes('#') && !url.includes('/user/')))];
  const results = [];
  for (const url of urls) {
    const response = await fetch(url);
    const html = await response.text();
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const schemaElement = doc.querySelector('script[type="application/ld+json"]');
    const parsedSchema = schemaElement ? JSON.parse(schemaElement.textContent) : null;
    const schema = (parsedSchema?.['@graph'] || [parsedSchema]).find(item => item?.['@type'] === 'Organization') || parsedSchema;
    const text = doc.body.textContent;
    results.push({ path: new URL(url).pathname, status: response.status, title: doc.title,
      h1Count: doc.querySelectorAll('h1').length, brokenImages: [...doc.images].filter(i => !i.getAttribute('src')).length,
      schema, cnpj: text.includes('68.238.467/0001-08'),
      address: text.includes('Avenida Cristóvão Colombo, 736') && text.includes('Quadra 205, Lote 27, Sala 3') && text.includes('74705-130'),
      contactFields: [...doc.querySelectorAll('.webform-submission-form input:not([type="hidden"]), .webform-submission-form textarea')].map(n => ({name:n.name, type:n.type, required:n.required})),
      description: doc.querySelector('meta[name="description"]')?.content || '',
      defaultContent: /Welcome!|Lorem ipsum|AGUARDANDO|Em construção/.test(doc.body.innerText || doc.body.textContent) });
  }
  return results;
})()`);
await call('Page.navigate', { url: supportOrigin() + '/' });
for (let attempt = 0; attempt < 100; attempt++) {
  if (await evaluate('document.readyState === "complete" && location.origin === ' + JSON.stringify(supportOrigin()))) break;
  await new Promise(resolve => setTimeout(resolve, 200));
}
const supportLayout = await evaluate(`(async () => {
  const doc = document;
  const status = doc.querySelector('.aculta-support') ? 200 : 404;
  const form = doc.querySelector('.aculta-support');
  const choice = form?.querySelector('fieldset');
  const actions = form?.querySelector('.form-actions');
  const privacy = form?.querySelector('.aculta-support > .form-item-privacy, .aculta-support > [data-drupal-selector="edit-privacy"]');
  const button = [...(actions?.querySelectorAll('button,input[type="submit"]') || [])].find(item => /Contribuir com Mercado Pago/.test(item.textContent || item.value));
  const pix = form?.querySelector('[data-aculta-pix], .aculta-pix');
  const graph = [...doc.querySelectorAll('script[type="application/ld+json"]')].flatMap(script => { const schema=JSON.parse(script.textContent); return schema['@graph'] || [schema]; });
  const canonical = doc.querySelector('link[rel="canonical"]')?.href || '';
  return {status, choice: !!choice, actionImmediatelyAfterChoice: !!choice && choice.nextElementSibling === actions,
    buttonAfterChoice: !!choice && !!button && choice.compareDocumentPosition(button) & Node.DOCUMENT_POSITION_FOLLOWING,
    pixVisible: !!pix, primaryDisabled: button?.disabled ?? null,
    duplicateMessageAbsent: !form?.textContent.includes('Para contribuir, fale com a Associação'),
    unavailable: /temporariamente indisponível/.test(form?.textContent || ''), buttonPresent: !!button,
    canonical, openGraph: doc.querySelector('meta[property="og:title"]')?.content || '',
    schemaTypes: graph.map(item => item['@type']),
    order: [...(form?.children || [])].map(element => element.className || element.tagName)};
})()`);
const internalViewports = [];
for (const path of ['/institucional', '/transparencia', '/contato']) {
  for (const width of [390, 1440]) {
    await call('Emulation.setDeviceMetricsOverride', {width, height: 900, deviceScaleFactor: 1, mobile: false});
    await call('Page.navigate', {url: origin + path});
    for (let attempt = 0; attempt < 100; attempt++) {
      if (await evaluate('document.readyState === "complete" && location.pathname === ' + JSON.stringify(path))) break;
      await new Promise(resolve => setTimeout(resolve, 200));
    }
    internalViewports.push({path, width, overflow: await evaluate('document.documentElement.scrollWidth > innerWidth')});
    if (width === 390) {
      const shot = await call('Page.captureScreenshot', {format:'png', captureBeyondViewport:true});
      await writeFile(new URL(path.slice(1) + '-mobile.png', output), Buffer.from(shot.data, 'base64'));
    }
  }
}
const official = pages.every(p => p.schema?.name === 'Associação Cultural Antiproibicionista' && p.schema?.taxID === '68.238.467/0001-08' && p.schema?.email === '4e20coletivo@gmail.com' && p.schema?.telephone === '+55 62 9282-0666' && p.schema?.alternateName === 'Coletivo 420' && p.schema?.address?.postalCode === '74705-130' && !p.schema?.sameAs && !p.schema?.logo);
const contact = pages.find(p => p.path === '/contato');
const contactValid = !!contact?.address && ['name','email','subject','message'].every(name => contact.contactFields.some(f => f.name === name && f.required));
// Descoberta e sitemaps por ambiente pertencem ao aculta_deployer (fase 7): este validador
// só registra o resultado. O ambiente vem do host do site.
const deployerEnv = new URL(origin).hostname.endsWith('aculta.org') ? 'production' : 'test';
const deployerCli = new URL('../web/modules/custom/aculta_portal/modules/aculta_deployer/bin/aculta-deployer', import.meta.url);
const sitemapRun = await execFileAsync('php', [deployerCli.pathname, 'sitemap', `--env=${deployerEnv}`])
  .then(({ stdout }) => ({ code: 0, stdout }), (error) => ({ code: error.code, stdout: error.stdout || '' }));
await writeFile(new URL('sitemap-deployer.txt', output), sitemapRun.stdout);
const faviconUrl = await evaluate('document.querySelector("link[rel=icon]")?.getAttribute("href") || ""');
const faviconResponse = await fetch(origin + faviconUrl);
const sitemapValid = sitemapRun.code === 0;
await writeFile(new URL('browser-results.json', output), JSON.stringify({viewports:report,pages,internalViewports,official,contactValid,sitemapValid,sitemapDeployer:sitemapRun.stdout.trim().split('\n'),faviconUrl,faviconStatus:faviconResponse.status,supportLayout},null,2));
console.log(JSON.stringify({viewports:report.map(({headings,...r})=>r), pages:pages.map(({schema,...p})=>({...p,schemaValid:!!schema})),internalViewports,official,contactValid,sitemapValid,sitemapDeployer:sitemapRun.stdout.trim().split('\n'),faviconUrl,faviconStatus:faviconResponse.status,supportLayout},null,2));
socket.close();
if (!official || !contactValid || !sitemapValid || faviconResponse.status !== 200 || !faviconUrl.includes('aculta420-favicon.ico') || supportLayout.status !== 200 || !supportLayout.actionImmediatelyAfterChoice || (supportLayout.unavailable ? supportLayout.buttonPresent : !supportLayout.buttonAfterChoice) || !supportLayout.duplicateMessageAbsent || supportLayout.canonical !== supportOrigin() + '/' || !supportLayout.openGraph || !supportLayout.schemaTypes.includes('WebPage') || supportLayout.pixVisible || (!supportLayout.unavailable && supportLayout.primaryDisabled !== true) || report.some(r => r.overflow || (r.publicAcronym && !r.fullNameVisible) || r.defaultContent || r.h1.length !== 1 || r.projects !== 4 || r.menuLabels.length !== 8 || r.menuContactPath !== '/contato' || !r.cnpj || !r.footerCnpj || parseFloat(r.h1Size) > 54 || r.bodySize !== '16px' || r.slogan !== 'Lutando por um futuro livre da proibição.' || r.projectParagraphCounts.some(n=>n!==1) || (r.toggleVisible && (!r.menuOpened || !r.menuClosedWithEscape))) || pages.some(p => p.status !== 200 || p.h1Count !== 1 || p.defaultContent || !p.description) || internalViewports.some(r=>r.overflow)) process.exitCode = 1;
