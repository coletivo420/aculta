#!/usr/bin/env node
// Baseline de performance de laboratório do ACULTA420 V2 em hosts públicos.
// Requer origens explícitas para não fixar hosts de um ambiente no script.
import { chromium, firefox, webkit } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { isAbsolute, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = resolve(fileURLToPath(new URL('../../../../..', import.meta.url)));
const suiteOut = process.env.SUITE_OUT;
const hostMap = process.env.ACULTA_PERF_HOSTS || '';
const engine = process.env.ACULTA_BROWSER_ENGINE || 'firefox';
const modes = (process.env.ACULTA_PERF_MODES || 'light,dark').split(',').map((v) => v.trim()).filter(Boolean);
const widths = (process.env.ACULTA_PERF_WIDTHS || '390,1440').split(',').map((v) => Number(v.trim()));
const engines = { firefox, chromium, webkit };

if (!suiteOut || !isAbsolute(suiteOut) || relative(projectRoot, resolve(suiteOut)) !== '..' && !relative(projectRoot, resolve(suiteOut)).startsWith(`..${process.platform === 'win32' ? '\\' : '/'}`)) {
  console.error('SUITE_OUT precisa ser um diretório absoluto fora do repositório.');
  process.exit(2);
}
if (!hostMap) {
  console.error('ACULTA_PERF_HOSTS obrigatório: site_key=https://host;site_key=https://host.');
  process.exit(2);
}
if (!engines[engine]) {
  console.error('ACULTA_BROWSER_ENGINE deve ser firefox, chromium ou webkit.');
  process.exit(2);
}
if (!modes.length || modes.some((mode) => !['light', 'dark'].includes(mode))) {
  console.error('ACULTA_PERF_MODES aceita light,dark.');
  process.exit(2);
}
if (!widths.length || widths.some((width) => !Number.isInteger(width) || width < 320 || width > 2560)) {
  console.error('ACULTA_PERF_WIDTHS deve conter larguras CSS entre 320 e 2560.');
  process.exit(2);
}

const hosts = hostMap.split(';').filter(Boolean).map((item) => {
  const separator = item.indexOf('=');
  if (separator < 1) throw new Error(`Entrada inválida em ACULTA_PERF_HOSTS: ${item.split('=')[0] || '(vazia)'}`);
  const siteKey = item.slice(0, separator).trim();
  const origin = item.slice(separator + 1).trim();
  const url = new URL(origin);
  const developmentHost = url.hostname === 'aculta.toca.net.br' || url.hostname.endsWith('.aculta.toca.net.br');
  if (!/^[a-z0-9_-]+$/.test(siteKey) || url.protocol !== 'https:' || !developmentHost || url.username || url.password || url.pathname !== '/' || url.search || url.hash) {
    throw new Error(`Origem inválida para ${siteKey}: use apenas host HTTPS de desenvolvimento *.aculta.toca.net.br, sem credenciais nem caminho.`);
  }
  return { siteKey, origin: url.origin };
});
if (!hosts.length || new Set(hosts.map(({ siteKey }) => siteKey)).size !== hosts.length) {
  console.error('ACULTA_PERF_HOSTS precisa de site_keys únicos.');
  process.exit(2);
}

const observerInit = (mode) => {
  window.__adr057Performance = {
    lcp: null,
    cls: 0,
    layoutShifts: [],
    longTasks: [],
    eventTimings: [],
    supported: {},
  };
  const applyMode = () => {
    if (!document.documentElement) return false;
    document.documentElement.dataset.theme = mode;
    return true;
  };
  if (!applyMode()) {
    const rootObserver = new MutationObserver(() => {
      if (applyMode()) rootObserver.disconnect();
    });
    rootObserver.observe(document, { childList: true, subtree: true });
  }
  const observe = (type, callback, options = {}) => {
    try {
      const observer = new PerformanceObserver((list) => callback(list.getEntries()));
      observer.observe({ type, buffered: true, ...options });
      window.__adr057Performance.supported[type] = true;
    }
    catch {
      window.__adr057Performance.supported[type] = false;
    }
  };
  observe('largest-contentful-paint', (entries) => {
    const entry = entries.at(-1);
    if (entry) window.__adr057Performance.lcp = {
      startTime: entry.startTime,
      size: entry.size,
      url: entry.url ? new URL(entry.url).pathname : null,
      element: entry.element?.tagName?.toLowerCase() || null,
    };
  });
  observe('layout-shift', (entries) => {
    for (const entry of entries) {
      if (!entry.hadRecentInput) {
        window.__adr057Performance.cls += entry.value;
        window.__adr057Performance.layoutShifts.push({
          value: entry.value,
          startTime: entry.startTime,
          sources: (entry.sources || []).map((source) => ({
            node: source.node?.tagName?.toLowerCase() || null,
            id: source.node?.id || null,
            className: typeof source.node?.className === 'string' ? source.node.className.slice(0, 120) : null,
            previousRect: source.previousRect,
            currentRect: source.currentRect,
          })),
        });
      }
    }
  });
  observe('longtask', (entries) => window.__adr057Performance.longTasks.push(...entries.map((entry) => entry.duration)));
  observe('event', (entries) => window.__adr057Performance.eventTimings.push(...entries
    .filter((entry) => entry.interactionId > 0)
    .map((entry) => ({ name: entry.name, duration: entry.duration, interactionId: entry.interactionId }))), { durationThreshold: 16 });
};

const capture = () => {
  const navigation = performance.getEntriesByType('navigation')[0];
  const resources = performance.getEntriesByType('resource');
  const buckets = {};
  for (const resource of resources) {
    const path = new URL(resource.name, location.href).pathname;
    const type = /\.css$/i.test(path) ? 'css'
      : /\.m?js$/i.test(path) ? 'js'
        : /\.(woff2?|ttf|otf)$/i.test(path) ? 'font'
          : /\.(avif|gif|jpe?g|png|svg|webp)$/i.test(path) ? 'image'
            : resource.initiatorType || 'other';
    buckets[type] ??= { requests: 0, transferBytes: 0, encodedBytes: 0, decodedBytes: 0, unavailableTransferSizes: 0 };
    const bucket = buckets[type];
    bucket.requests++;
    bucket.transferBytes += resource.transferSize || 0;
    bucket.encodedBytes += resource.encodedBodySize || 0;
    bucket.decodedBytes += resource.decodedBodySize || 0;
    if (resource.transferSize === 0 && resource.encodedBodySize === 0) bucket.unavailableTransferSizes++;
  }
  const durations = window.__adr057Performance.longTasks;
  return {
    url: `${location.origin}${location.pathname}`,
    mode: document.documentElement.dataset.theme || null,
    status: navigation?.responseStatus ?? null,
    timingMs: navigation ? {
      ttfb: navigation.responseStart,
      domContentLoaded: navigation.domContentLoadedEventEnd,
      load: navigation.loadEventEnd,
      duration: navigation.duration,
    } : null,
    paintMs: Object.fromEntries(performance.getEntriesByType('paint').map((entry) => [entry.name, entry.startTime])),
    lcp: window.__adr057Performance.supported['largest-contentful-paint'] ? window.__adr057Performance.lcp : null,
    cls: window.__adr057Performance.supported['layout-shift'] ? window.__adr057Performance.cls : null,
    layoutShifts: window.__adr057Performance.supported['layout-shift'] ? window.__adr057Performance.layoutShifts : null,
    longTasks: {
      supported: Boolean(window.__adr057Performance.supported.longtask),
      count: window.__adr057Performance.supported.longtask ? durations.length : null,
      totalDurationMs: window.__adr057Performance.supported.longtask ? durations.reduce((sum, duration) => sum + duration, 0) : null,
      maxDurationMs: window.__adr057Performance.supported.longtask ? (durations.length ? Math.max(...durations) : 0) : null,
    },
    criticalResourceTiming: resources.filter((resource) => /\.(css|m?js|woff2?|ttf|otf|avif|gif|jpe?g|png|svg|webp)(?:\?|$)/i.test(resource.name)).map((resource) => ({
      path: new URL(resource.name, location.href).pathname,
      initiatorType: resource.initiatorType,
      startTime: resource.startTime,
      responseEnd: resource.responseEnd,
      duration: resource.duration,
      transferBytes: resource.transferSize,
      encodedBytes: resource.encodedBodySize,
      decodedBytes: resource.decodedBodySize,
    })),
    resources: {
      requestCount: resources.length,
      buckets,
      fieldInp: null,
      inpNote: 'INP exige dados de campo e não é inferido deste laboratório.',
    },
    eventTimingSupported: Boolean(window.__adr057Performance.supported.event),
    interactionLatencyLabMs: window.__adr057Performance.eventTimings.length
      ? Math.max(...window.__adr057Performance.eventTimings.map((entry) => entry.duration))
      : null,
    interactionTimings: window.__adr057Performance.eventTimings,
  };
};

const summarizeResponses = async (pendingResponses) => {
  const rows = await Promise.all(pendingResponses);
  const byEncoding = {};
  for (const row of rows) {
    const key = `${row.type}:${row.contentEncoding}`;
    byEncoding[key] ??= { requests: 0, contentLengthBytes: 0, knownContentLengths: 0 };
    byEncoding[key].requests++;
    if (row.contentLengthBytes !== null) {
      byEncoding[key].contentLengthBytes += row.contentLengthBytes;
      byEncoding[key].knownContentLengths++;
    }
  }
  return {
    count: rows.length,
    errors: rows.filter((row) => row.status >= 400).map(({ type, status }) => ({ type, status })),
    byEncoding,
  };
};

const browser = await engines[engine].launch({ headless: true });
const results = [];
mkdirSync(suiteOut, { recursive: true });
try {
  for (const host of hosts) {
    for (const width of widths) {
      for (const mode of modes) {
        const context = await browser.newContext({
          viewport: { width, height: width < 600 ? 844 : 900 },
          ignoreHTTPSErrors: process.env.ACULTA_BROWSER_ENV === 'local',
          serviceWorkers: 'block',
          locale: 'pt-BR',
        });
        const page = await context.newPage();
        const pageErrors = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));
        await page.addInitScript(observerInit, mode);
        for (const cacheState of ['cold', 'warm']) {
          const requestLog = [];
          const responseLog = [];
          const onRequest = (request) => requestLog.push({ type: request.resourceType(), url: request.url() });
          const onResponse = (response) => {
            const type = response.request().resourceType();
            responseLog.push(response.allHeaders().then((headers) => ({
              type,
              status: response.status(),
              contentEncoding: headers['content-encoding'] || 'identity',
              contentLengthBytes: Number(headers['content-length']) || null,
            })).catch(() => ({ type, status: response.status(), contentEncoding: 'unavailable', contentLengthBytes: null })));
          };
          page.on('request', onRequest);
          page.on('response', onResponse);
          let response;
          try {
            response = cacheState === 'cold'
              ? await page.goto(`${host.origin}/`, { waitUntil: 'load', timeout: 30_000 })
              : await page.reload({ waitUntil: 'load', timeout: 30_000 });
            if (!await page.evaluate((expectedMode) => document.documentElement.dataset.theme === expectedMode && Boolean(window.__adr057Performance), mode)) {
              // Fallback para manter a coleta quando o init script não está disponível.
              await page.evaluate(observerInit, mode);
            }
            await page.evaluate(() => document.fonts?.ready ?? Promise.resolve());
            const beforeInteraction = await page.evaluate(capture);
            const menu = page.locator('[data-aculta-navigation-toggle]').first();
            let interaction = false;
            if (await menu.count() && await menu.isVisible()) {
              await menu.click();
              interaction = true;
              await page.waitForFunction(() => performance.getEntriesByType('event').some((entry) => entry.interactionId > 0), null, { timeout: 2_000 }).catch(() => {});
            }
            const metrics = interaction ? await page.evaluate(capture) : beforeInteraction;
            results.push({
              siteKey: host.siteKey,
              viewport: { width, height: width < 600 ? 844 : 900 },
              mode,
              cacheState,
              status: response?.status() ?? null,
              requestTypes: requestLog.reduce((counts, item) => ({ ...counts, [item.type]: (counts[item.type] || 0) + 1 }), {}),
              httpResponses: await summarizeResponses(responseLog),
              navigation: beforeInteraction,
              afterInteraction: interaction ? metrics : null,
              interactionAttempted: interaction,
            });
          }
          catch (caught) {
            results.push({ siteKey: host.siteKey, viewport: { width }, mode, cacheState, status: response?.status() ?? null, error: String(caught).slice(0, 240), pageErrors });
          }
          finally {
            page.off('request', onRequest);
            page.off('response', onResponse);
          }
        }
        await context.close();
      }
    }
  }
}
finally {
  await browser.close();
}

const report = {
  schema: 'aculta.adr057.performance-lab.v1',
  generatedAt: new Date().toISOString(),
  gitHead: process.env.ACULTA_PERF_COMMIT || null,
  engine,
  browserVersion: browser.version(),
  environment: process.env.ACULTA_BROWSER_ENV || 'desenvolvimento',
  hostCount: hosts.length,
  modes,
  widths,
  cacheStates: ['cold', 'warm'],
  limits: [
    'Laboratory measurements do not represent CrUX/RUM field p75.',
    'A warm navigation is a reload in the same browser context; it estimates browser-cache reuse, not CDN cache state.',
    'Cross-origin Resource Timing sizes can be zero without Timing-Allow-Origin.',
    'INP is not measured; interactionLatencyLabMs is a single menu interaction proxy.',
    'Resource priority is not exposed by this runner.',
  ],
  results,
};
writeFileSync(resolve(suiteOut, 'performance-summary.json'), `${JSON.stringify(report, null, 2)}\n`);
console.log(`performance: ${results.length} registros; ${results.filter((item) => item.error || (item.status && item.status >= 400)).length} falhas; JSON em ${resolve(suiteOut, 'performance-summary.json')}`);
process.exit(results.some((item) => item.error || (item.status && item.status >= 400)) ? 1 : 0);
