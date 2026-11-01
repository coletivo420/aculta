import { devtoolsUrl, siteOrigin } from './lib/browser-env.mjs';
// Medição do modo escuro em Chrome DevTools (F1, pré-requisito). Sem dependências.
// Força data-bs-theme="dark" na página e mede contraste de textos principais por WCAG.
// Artefatos em tmp/ (ignorado pelo Git).
import { writeFile, mkdir } from 'node:fs/promises';

const origin = siteOrigin();
const output = new URL('../tmp/color-mode-review/', import.meta.url);
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

// Contraste WCAG: cor do texto e fundo efetivo (primeiro ancestral com fundo opaco).
const measure = `(() => {
  const parse = (c) => { const m = c.match(/rgba?\\(([^)]+)\\)/); if (!m) return null;
    const p = m[1].split(/[ ,\\/]+/).filter(Boolean).map(Number); return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 }; };
  const lum = ({ r, g, b }) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
  const background = (el) => { for (let n = el; n; n = n.parentElement) { const c = parse(getComputedStyle(n).backgroundColor); if (c && c.a === 1) return c; } return { r: 255, g: 255, b: 255, a: 1 }; };
  const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
  const samples = [
    ['texto de corpo', 'main p'], ['título h1', 'main h1, .aculta-hero h1'], ['título h2', 'main h2'],
    ['link editorial', 'main a'], ['rodapé', 'footer p, footer a'], ['menu', '#aculta-primary-menu a'],
    ['barra de conta', '.aculta-utility a, .aculta-utility button']
  ];
  const rows = samples.map(([label, selector]) => {
    const el = document.querySelector(selector);
    if (!el) return { label, missing: true };
    const text = parse(getComputedStyle(el).color);
    const large = /^H[12]$/.test(el.tagName) || parseFloat(getComputedStyle(el).fontSize) >= 24;
    const value = ratio(text, background(el));
    return { label, ratio: Math.round(value * 100) / 100, required: large ? 3 : 4.5, pass: value >= (large ? 3 : 4.5) };
  });
  return { theme: document.documentElement.getAttribute('data-bs-theme'), rows,
    overflow: document.documentElement.scrollWidth > innerWidth };
})()`;

const pages = ['/', '/institucional', '/projetos', '/contato'];
const viewports = [['desktop', 1440, 900], ['mobile', 390, 844]];
const report = [];
for (const [label, width, height] of viewports) {
  await call('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
  for (const path of pages) {
    await call('Page.navigate', { url: origin + path });
    for (let attempt = 0; attempt < 100; attempt++) {
      if (await evaluate('document.readyState === "complete"')) break;
      await new Promise((resolve) => setTimeout(resolve, 200));
    }
    // Sem transições durante a medição: senão a cor lida ainda é a do modo claro (ver DT de medição).
    await evaluate('document.documentElement.setAttribute("data-bs-theme", "dark")');
    await evaluate('(() => { const s = document.createElement("style"); s.id = "color-mode-probe"; s.textContent = "*,*::before,*::after{transition:none!important}"; document.head.appendChild(s); })()');
    await new Promise((resolve) => setTimeout(resolve, 300));
    const result = await evaluate(measure);
    await evaluate('document.getElementById("color-mode-probe")?.remove()');
    if (path === '/') {
      const shot = await call('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
      await writeFile(new URL(`dark-${label}.png`, output), Buffer.from(shot.data, 'base64'));
    }
    report.push({ viewport: label, path, ...result });
  }
}
await writeFile(new URL('color-mode-results.json', output), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
socket.close();
const failing = report.some((page) => page.overflow || page.rows.some((row) => row.pass === false));
console.log(`medidas ausentes (seletor não encontrado, não reprovam): ${report.reduce((n, page) => n + page.rows.filter((row) => row.missing).length, 0)}`);
if (failing) process.exitCode = 1;
