#!/usr/bin/env node
/**
 * Classifica os seletores que o PurgeCSS removeria, usando evidência do
 * Runtime (HTML renderizado) além do conteúdo do repositório.
 *
 * Uso:
 *   node scripts/css-audit/classify.mjs --html-dir=reports/css-audit/runtime/html
 *
 * Saída: `reports/css-audit/audit/classification.json` e resumo no terminal.
 * Um seletor só é "unreferenced" quando todas as suas classes:
 *  - não são dinâmicas (enum SDC/UI Styles);
 *  - não casam com os padrões de runtime do Drupal/UI Suite/HTMX;
 *  - não aparecem no HTML renderizado dos hosts.
 * A classificação não altera CSS; é insumo para revisão humana.
 */

import { readFileSync, writeFileSync, mkdirSync, readdirSync, existsSync } from 'node:fs';
import { join, relative } from 'node:path';
import { collectDynamicClasses } from './collect-dynamic-classes.mjs';
import { SAFELIST_PATTERNS } from './safelist.mjs';
import { defaultExtractor } from './extractors/index.mjs';

const ROOT = process.cwd();
const args = Object.fromEntries(
  process.argv.slice(2).map((arg) => {
    const [key, ...rest] = arg.replace(/^--/, '').split('=');
    return [key, rest.join('=') || true];
  }),
);
const htmlDir = join(ROOT, args['html-dir'] || 'reports/css-audit/runtime/html');
const reportPath = join(ROOT, 'reports/css-audit/audit/report.json');

if (!existsSync(reportPath)) {
  console.error('Relatório do PurgeCSS ausente. Rode `npm run css:audit` antes.');
  process.exit(2);
}
if (!existsSync(htmlDir)) {
  console.error(`Diretório de HTML do Runtime ausente: ${htmlDir}`);
  process.exit(2);
}

const report = JSON.parse(readFileSync(reportPath, 'utf8'));
const dynamic = new Set(collectDynamicClasses({ root: ROOT }).classes);

// Referências textuais no repositório (inventário), quando disponível.
let referencedClasses = new Set();
const inventoryPath = join(ROOT, 'reports/css-audit/inventory/inventory.json');
if (existsSync(inventoryPath)) {
  const inventory = JSON.parse(readFileSync(inventoryPath, 'utf8'));
  referencedClasses = new Set(Object.keys(inventory.classesWithReferences ?? {}));
}

// Classes presentes no HTML renderizado de todos os hosts capturados.
const runtimeClasses = new Set();
const runtimeFiles = [];
for (const file of readdirSync(htmlDir).filter((name) => name.endsWith('.html'))) {
  const source = readFileSync(join(htmlDir, file), 'utf8');
  runtimeFiles.push(file);
  for (const attr of source.matchAll(/class="([^"]*)"/g)) {
    for (const token of attr[1].split(/\s+/)) {
      if (token) {
        runtimeClasses.add(token);
      }
    }
  }
  for (const token of defaultExtractor(source)) {
    runtimeClasses.add(token);
  }
}

const isRuntimePattern = (className) =>
  SAFELIST_PATTERNS.some((entry) => entry.pattern.test(`.${className}`));

const classification = {
  generatedAt: new Date().toISOString(),
  runtimeFiles,
  runtimeClassCount: runtimeClasses.size,
  dynamicClassCount: dynamic.size,
  totals: { selectors: 0, dynamic: 0, runtime: 0, runtimePattern: 0, sourceReference: 0, unreferenced: 0 },
  files: [],
};

for (const file of report.files) {
  const items = [];
  for (const selector of file.selectorsRemoved) {
    const classes = [...new Set([...selector.matchAll(/\.(-?[_a-zA-Z][\w-]*)/g)].map((m) => m[1]))];
    if (classes.length === 0) {
      continue;
    }
    let category = 'unreferenced';
    const evidence = [];
    if (classes.some((c) => dynamic.has(c))) {
      category = 'dynamic';
      evidence.push(...classes.filter((c) => dynamic.has(c)).map((c) => `dynamic:${c}`));
    } else if (classes.some((c) => runtimeClasses.has(c))) {
      category = 'runtime';
      evidence.push(...classes.filter((c) => runtimeClasses.has(c)).map((c) => `runtime:${c}`));
    } else if (classes.some(isRuntimePattern)) {
      category = 'runtimePattern';
      evidence.push(...classes.filter(isRuntimePattern).map((c) => `pattern:${c}`));
    } else if (classes.some((c) => referencedClasses.has(c))) {
      category = 'sourceReference';
      evidence.push(...classes.filter((c) => referencedClasses.has(c)).map((c) => `source:${c}`));
    }
    classification.totals.selectors += 1;
    classification.totals[category] += 1;
    items.push({ selector, category, evidence });
  }
  if (items.length > 0) {
    classification.files.push({ file: file.file, items });
  }
}

mkdirSync(join(ROOT, 'reports/css-audit/audit'), { recursive: true });
writeFileSync(
  join(ROOT, 'reports/css-audit/audit/classification.json'),
  `${JSON.stringify(classification, null, 2)}\n`,
);

const t = classification.totals;
console.log(`Classificação: ${t.selectors} seletores removíveis`);
console.log(`  dinâmicos: ${t.dynamic}`);
console.log(`  presentes no Runtime: ${t.runtime}`);
console.log(`  padrão de runtime: ${t.runtimePattern}`);
console.log(`  referência no repositório: ${t.sourceReference}`);
console.log(`  sem evidência: ${t.unreferenced}`);
console.log(`  HTML analisado: ${runtimeFiles.length} hosts (${runtimeClasses.size} tokens)`);
const unreferenced = classification.files.flatMap((file) =>
  file.items.filter((item) => item.category === 'unreferenced').map((item) => `${relative(ROOT, file.file)} :: ${item.selector}`),
);
if (unreferenced.length > 0) {
  console.log('\nSem evidência (revisar antes de qualquer corte):');
  for (const line of unreferenced.slice(0, 40)) {
    console.log(`  - ${line}`);
  }
}
