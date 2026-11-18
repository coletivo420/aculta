#!/usr/bin/env node

import { chromium } from 'playwright';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, isAbsolute, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(here, '../../../..');
const routes = JSON.parse(readFileSync(join(here, 'routes.json'), 'utf8')).routes;
const suiteOut = process.env.SUITE_OUT;
const missing = routes.filter(({ origin_env }) => !process.env[origin_env]);

if (!suiteOut || !isAbsolute(suiteOut) || suiteOut === projectRoot || suiteOut.startsWith(`${projectRoot}/`)) {
  console.error('SUITE_OUT deve ser absoluto e ficar fora do repositório.');
  process.exit(2);
}
if (missing.length) {
  console.error(`Origens ausentes: ${missing.map(({ origin_env }) => origin_env).join(', ')}`);
  process.exit(2);
}

const repetitions = Number.parseInt(process.env.ACULTA_PERF_REPETITIONS ?? '3', 10);
if (!Number.isInteger(repetitions) || repetitions < 1 || repetitions > 10) {
  console.error('ACULTA_PERF_REPETITIONS deve ser inteiro entre 1 e 10.');
  process.exit(2);
}

const matrix = [
  { mode: 'light', width: 390 },
  { mode: 'light', width: 1440 },
  { mode: 'dark', width: 390 },
  { mode: 'dark', width: 1440 },
];
const samples = [];
const failures = [];
const browser = await chromium.launch({ headless: true });

try {
  for (const route of routes) {
    const url = new URL(route.path, process.env[route.origin_env]);
    for (const variant of matrix) {
      for (let repetition = 1; repetition <= repetitions; repetition++) {
        const context = await browser.newContext({
          viewport: { width: variant.width, height: 900 },
          ignoreHTTPSErrors: true,
        });
        await context.addInitScript((mode) => {
          if (mode === 'dark') {
            const applyTheme = () => {
              if (!document.documentElement) return false;
              document.documentElement.setAttribute('data-theme', 'dark');
              return true;
            };
            if (!applyTheme()) {
              const observer = new MutationObserver(() => {
                if (applyTheme()) observer.disconnect();
              });
              observer.observe(document, { childList: true });
            }
          }
          window.__adr057Vitals = { lcp: null, cls: 0, clsWindowStart: null, clsWindowEnd: null, clsWindowValue: 0, inp: null };
          try {
            new PerformanceObserver((list) => {
              const entries = list.getEntries();
              if (entries.length) window.__adr057Vitals.lcp = entries.at(-1).startTime;
            }).observe({ type: 'largest-contentful-paint', buffered: true });
          } catch {}
          try {
            new PerformanceObserver((list) => {
              for (const entry of list.getEntries()) {
                if (entry.hadRecentInput) continue;
                const state = window.__adr057Vitals;
                if (state.clsWindowStart === null || entry.startTime - state.clsWindowEnd > 1000 || entry.startTime - state.clsWindowStart > 5000) {
                  state.clsWindowStart = entry.startTime;
                  state.clsWindowValue = 0;
                }
                state.clsWindowEnd = entry.startTime;
                state.clsWindowValue += entry.value;
                state.cls = Math.max(state.cls, state.clsWindowValue);
              }
            }).observe({ type: 'layout-shift', buffered: true });
          } catch {}
          try {
            new PerformanceObserver((list) => {
              for (const entry of list.getEntries()) {
                if (entry.interactionId) window.__adr057Vitals.inp = Math.max(window.__adr057Vitals.inp ?? 0, entry.duration);
              }
            }).observe({ type: 'event', buffered: true, durationThreshold: 16 });
          } catch {}
        }, variant.mode);

        const page = await context.newPage();
        for (const cache of ['cold', 'warm']) {
          let response;
          try {
            response = cache === 'cold'
              ? await page.goto(url.href, { waitUntil: 'load', timeout: 60000 })
              : await page.reload({ waitUntil: 'load', timeout: 60000 });
            await page.evaluate(() => document.fonts.ready);
            const metrics = await page.evaluate(() => {
              const navigation = performance.getEntriesByType('navigation')[0];
              const resources = performance.getEntriesByType('resource');
              const group = (test) => {
                const entries = resources.filter(test);
                return {
                  count: entries.length,
                  transferBytes: entries.reduce((sum, entry) => sum + (entry.transferSize || 0), 0),
                  encodedBytes: entries.reduce((sum, entry) => sum + (entry.encodedBodySize || 0), 0),
                };
              };
              return {
                lcpMs: window.__adr057Vitals.lcp,
                cls: window.__adr057Vitals.cls,
                inpMs: window.__adr057Vitals.inp,
                navigation: {
                  responseStartMs: navigation?.responseStart ?? null,
                  domContentLoadedMs: navigation?.domContentLoadedEventEnd ?? null,
                  loadEventEndMs: navigation?.loadEventEnd ?? null,
                  transferBytes: navigation?.transferSize ?? null,
                  encodedBytes: navigation?.encodedBodySize ?? null,
                },
                resources: {
                  total: group(() => true),
                  css: group((entry) => /\.css(?:[?#]|$)/i.test(entry.name)),
                  js: group((entry) => /\.m?js(?:[?#]|$)/i.test(entry.name)),
                  fonts: group((entry) => /\.(?:woff2?|ttf|otf)(?:[?#]|$)/i.test(entry.name)),
                  images: group((entry) => /\.(?:png|jpe?g|webp|avif|svg)(?:[?#]|$)/i.test(entry.name)),
                },
                resourceEntryCount: resources.length,
              };
            });
            const sample = {
              route: route.key,
              mode: variant.mode,
              width: variant.width,
              repetition,
              cache,
              status: response?.status() ?? null,
              metrics,
            };
            samples.push(sample);
            if (sample.status !== 200) failures.push(`${route.key}/${variant.mode}/${variant.width}/${cache}: HTTP ${sample.status}`);
          } catch (error) {
            failures.push(`${route.key}/${variant.mode}/${variant.width}/${cache}: ${error.message}`);
          }
        }
        await context.close();
      }
    }
  }
} finally {
  await browser.close();
}

mkdirSync(suiteOut, { recursive: true });
writeFileSync(join(suiteOut, 'baseline.json'), JSON.stringify({
  capturedAt: new Date().toISOString(),
  browser: 'Chromium headless / Playwright',
  throttle: 'none',
  target: 'Runtime de desenvolvimento; rotas públicas; sem sessão autenticada',
  repetitions,
  matrix,
  samples,
  failures,
}, null, 2));
console.log(`Amostras: ${samples.length}; falhas: ${failures.length}; artefato: ${join(suiteOut, 'baseline.json')}`);
if (failures.length) {
  for (const failure of failures) console.error(failure);
  process.exitCode = 1;
}
