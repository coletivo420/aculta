#!/usr/bin/env node
/**
 * Inventário reprodutível de CSS do tema aculta420_v2.
 *
 * Gera `reports/css-audit/inventory/inventory.json` e `.md` com:
 * arquivo, biblioteca responsável, regras, seletores, declarações, tamanho,
 * duplicações, seletores repetidos entre arquivos, `!important`, `:has()`,
 * `:is()`, `:where()`, variáveis definidas/usadas, media queries, classes do
 * CSS sem referência textual em Twig/PHP/JS/YAML e variantes SDC.
 *
 * A ausência de referência textual não prova obsolescência: classes podem ser
 * montadas em tempo de execução. O relatório marca essas classes como
 * "sem referência textual", não como removíveis. O PurgeCSS (purge.mjs) é a
 * segunda opinião, com safelist de classes dinâmicas.
 */

import { readFileSync, writeFileSync, mkdirSync, readdirSync, statSync } from 'node:fs';
import { join, relative, extname } from 'node:path';
import postcss from 'postcss';
import { defaultExtractor, yamlExtractor } from './extractors/index.mjs';
import { parseComponentEnums } from './collect-dynamic-classes.mjs';

const ROOT = process.cwd();
const THEME_PREFIX = 'web/themes/custom/aculta420_v2/';
const THEME_ROOT = join(ROOT, THEME_PREFIX);
const OUT_DIR = join(ROOT, 'reports/css-audit/inventory');

const CONTENT_DIRS = [
  join(ROOT, THEME_PREFIX, 'components'),
  join(ROOT, THEME_PREFIX, 'templates'),
  join(ROOT, THEME_PREFIX, 'src'),
  join(ROOT, THEME_PREFIX, 'js'),
  join(ROOT, THEME_PREFIX, 'config'),
  join(ROOT, 'web/modules/custom'),
  join(ROOT, 'config/sync'),
];
const CONTENT_EXTS = new Set(['.twig', '.php', '.module', '.theme', '.install', '.js', '.yml']);
const SKIP_DIRS = new Set(['node_modules', '.git', 'vendor']);

function walk(dir, acc = []) {
  for (const entry of readdirSync(dir)) {
    if (SKIP_DIRS.has(entry)) {
      continue;
    }
    const full = join(dir, entry);
    const stat = statSync(full);
    if (stat.isDirectory()) {
      walk(full, acc);
    } else {
      acc.push(full);
    }
  }
  return acc;
}

/** Mapa caminho CSS → biblioteca declarada em aculta420_v2.libraries.yml. */
function parseLibraries() {
  const source = readFileSync(join(THEME_ROOT, 'aculta420_v2.libraries.yml'), 'utf8');
  const map = new Map();
  let library = null;
  for (const line of source.split(/\r?\n/)) {
    const top = line.match(/^([a-z0-9-]+):\s*$/);
    if (top) {
      library = top[1];
      continue;
    }
    const css = line.match(/^\s{6}(['"]?)([^'":]+\.css)\1:\s*\{/);
    if (css && library) {
      map.set(css[2], library);
    }
  }
  return map;
}

function splitSelectors(selector) {
  // Vírgulas dentro de :is()/:where()/:has() não separam seletores de topo.
  const parts = [];
  let depth = 0;
  let current = '';
  for (const char of selector) {
    if (char === '(') depth += 1;
    if (char === ')') depth = Math.max(0, depth - 1);
    if (char === ',' && depth === 0) {
      parts.push(current.trim());
      current = '';
    } else {
      current += char;
    }
  }
  if (current.trim() !== '') {
    parts.push(current.trim());
  }
  return parts;
}

function countMatches(source, regex) {
  return (source.match(regex) || []).length;
}

/** Coleta tokens de conteúdo por arquivo para medir referências. */
function buildContentIndex(files) {
  const index = new Map();
  for (const file of files) {
    const ext = extname(file);
    if (!CONTENT_EXTS.has(ext)) {
      continue;
    }
    const source = readFileSync(file, 'utf8');
    const extractor = ext === '.yml' ? yamlExtractor : defaultExtractor;
    index.set(relative(ROOT, file), new Set(extractor(source)));
  }
  return index;
}

function main() {
  mkdirSync(OUT_DIR, { recursive: true });
  const libraries = parseLibraries();
  const cssFiles = walk(THEME_ROOT)
    .filter((file) => file.endsWith('.css'))
    .sort();

  const contentFiles = CONTENT_DIRS.flatMap((dir) => walk(dir));
  const contentIndex = buildContentIndex(contentFiles);

  const inventory = {
    generatedAt: new Date().toISOString(),
    theme: 'aculta420_v2',
    totals: {
      files: 0,
      bytes: 0,
      rules: 0,
      selectors: 0,
      declarations: 0,
      important: 0,
      has: 0,
      is: 0,
      where: 0,
      mediaQueries: 0,
      duplicateDeclarations: 0,
      selectorsInMultipleFiles: 0,
      customPropertiesDefined: 0,
      customPropertiesUsed: 0,
    },
    libraries: {},
    files: [],
    selectorFiles: {},
    classesWithoutTextualReference: [],
    classesWithReferences: {},
    sdc: { components: 0, enumClasses: 0 },
  };

  const selectorToFiles = new Map();
  const definedProps = new Set();
  const usedProps = new Set();
  const allClasses = new Set();

  for (const file of cssFiles) {
    const rel = relative(ROOT, file);
    const source = readFileSync(file, 'utf8');
    const bytes = Buffer.byteLength(source);
    let root;
    try {
      root = postcss.parse(source, { from: file });
    } catch (error) {
      console.error(`CSS inválido: ${rel}: ${error.message}`);
      process.exitCode = 1;
      continue;
    }

    let rules = 0;
    let selectors = 0;
    let declarations = 0;
    let important = 0;
    let has = 0;
    let is = 0;
    let where = 0;
    let mediaQueries = 0;
    const seenDeclarations = new Map();
    let duplicateDeclarations = 0;

    root.walkAtRules((atRule) => {
      if (atRule.name === 'media') {
        mediaQueries += 1;
      }
    });

    root.walkRules((rule) => {
      rules += 1;
      const parts = splitSelectors(rule.selector);
      selectors += parts.length;
      for (const part of parts) {
        for (const match of part.matchAll(/\.(-?[_a-zA-Z][\w-]*)/g)) {
          allClasses.add(match[1]);
        }
      }
      has += countMatches(rule.selector, /:has\(/g);
      is += countMatches(rule.selector, /:is\(/g);
      where += countMatches(rule.selector, /:where\(/g);
      rule.walkDecls((decl) => {
        declarations += 1;
        if (decl.important) {
          important += 1;
        }
        if (decl.prop.startsWith('--')) {
          definedProps.add(decl.prop);
        }
        for (const match of decl.value.matchAll(/var\(\s*(--[\w-]+)/g)) {
          usedProps.add(match[1]);
        }
        const key = `${decl.prop}:${decl.value}${decl.important ? '!important' : ''}`;
        const count = (seenDeclarations.get(key) || 0) + 1;
        seenDeclarations.set(key, count);
        if (count === 2) {
          duplicateDeclarations += 1;
        }
      });
      for (const part of parts) {
        if (!selectorToFiles.has(part)) {
          selectorToFiles.set(part, new Set());
        }
        selectorToFiles.get(part).add(rel);
      }
    });

    const owner = libraries.get(rel.replace(THEME_PREFIX, '')) || null;
    inventory.files.push({
      file: rel,
      library: owner,
      bytes,
      rules,
      selectors,
      declarations,
      important,
      has,
      is,
      where,
      mediaQueries,
      duplicateDeclarations,
    });
    inventory.totals.files += 1;
    inventory.totals.bytes += bytes;
    inventory.totals.rules += rules;
    inventory.totals.selectors += selectors;
    inventory.totals.declarations += declarations;
    inventory.totals.important += important;
    inventory.totals.has += has;
    inventory.totals.is += is;
    inventory.totals.where += where;
    inventory.totals.mediaQueries += mediaQueries;
    inventory.totals.duplicateDeclarations += duplicateDeclarations;

    if (owner) {
      inventory.libraries[owner] = (inventory.libraries[owner] || 0) + 1;
    }
  }

  for (const [selector, files] of selectorToFiles.entries()) {
    if (files.size > 1) {
      inventory.totals.selectorsInMultipleFiles += 1;
      inventory.selectorFiles[selector] = [...files];
    }
  }
  inventory.totals.customPropertiesDefined = definedProps.size;
  inventory.totals.customPropertiesUsed = usedProps.size;

  // Referências textuais por classe.
  for (const className of [...allClasses].sort()) {
    const refs = [];
    for (const [file, tokens] of contentIndex.entries()) {
      if (tokens.has(className)) {
        refs.push(file);
      }
    }
    if (refs.length === 0) {
      inventory.classesWithoutTextualReference.push(className);
    } else {
      inventory.classesWithReferences[className] = refs.length;
    }
  }

  // Metadados SDC.
  for (const file of walk(join(THEME_ROOT, 'components'))) {
    if (!file.endsWith('.component.yml')) {
      continue;
    }
    inventory.sdc.components += 1;
    const enums = parseComponentEnums(readFileSync(file, 'utf8'));
    for (const values of Object.values(enums)) {
      inventory.sdc.enumClasses += values.length;
    }
  }

  inventory.variables = {
    defined: [...definedProps].sort(),
    used: [...usedProps].sort(),
    definedUnused: [...definedProps].filter((prop) => !usedProps.has(prop)).sort(),
    usedUndefined: [...usedProps].filter((prop) => !definedProps.has(prop)).sort(),
  };

  // Funde o relatório do PurgeCSS, quando existir.
  try {
    const purge = JSON.parse(readFileSync(join(ROOT, 'reports/css-audit/audit/report.json'), 'utf8'));
    inventory.purge = {
      generatedAt: purge.generatedAt,
      totals: purge.totals,
      dynamicClasses: purge.dynamicClasses.length,
      rejected: purge.files
        .filter((file) => file.rejectedSelectors > 0)
        .map((file) => ({ file: file.file, rejected: file.rejectedSelectors, selectors: file.rejected })),
    };
  } catch {
    inventory.purge = null;
  }

  writeFileSync(join(OUT_DIR, 'inventory.json'), `${JSON.stringify(inventory, null, 2)}\n`);
  writeFileSync(join(OUT_DIR, 'inventory.md'), renderMarkdown(inventory));

  const t = inventory.totals;
  console.log(`Inventário: ${t.files} arquivos, ${t.bytes} bytes, ${t.rules} regras, ${t.selectors} seletores`);
  console.log(`  !important: ${t.important}; :has ${t.has}; :is ${t.is}; :where ${t.where}; media ${t.mediaQueries}`);
  console.log(`  seletores repetidos entre arquivos: ${t.selectorsInMultipleFiles}`);
  console.log(`  classes sem referência textual: ${inventory.classesWithoutTextualReference.length}`);
  console.log(`  classes dinâmicas (SDC/UI Styles): ${inventory.purge?.dynamicClasses ?? 'n/d'}`);
  console.log(`  saída: ${relative(ROOT, OUT_DIR)}`);
}

function renderMarkdown(inventory) {
  const t = inventory.totals;
  const lines = [];
  lines.push('# Inventário CSS — aculta420_v2');
  lines.push('');
  lines.push(`Gerado em ${inventory.generatedAt}.`);
  lines.push('');
  lines.push('## Totais');
  lines.push('');
  lines.push('| Métrica | Valor |');
  lines.push('| --- | ---: |');
  lines.push(`| Arquivos CSS | ${t.files} |`);
  lines.push(`| Bytes-fonte | ${t.bytes} |`);
  lines.push(`| Regras | ${t.rules} |`);
  lines.push(`| Seletores | ${t.selectors} |`);
  lines.push(`| Declarações | ${t.declarations} |`);
  lines.push(`| Declarações \`!important\` | ${t.important} |`);
  lines.push(`| Seletores com \`:has()\` | ${t.has} |`);
  lines.push(`| Seletores com \`:is()\` | ${t.is} |`);
  lines.push(`| Seletores com \`:where()\` | ${t.where} |`);
  lines.push(`| Media queries | ${t.mediaQueries} |`);
  lines.push(`| Declarações duplicadas | ${t.duplicateDeclarations} |`);
  lines.push(`| Seletores em mais de um arquivo | ${t.selectorsInMultipleFiles} |`);
  lines.push(`| Custom properties definidas | ${t.customPropertiesDefined} |`);
  lines.push(`| Custom properties usadas | ${t.customPropertiesUsed} |`);
  lines.push('');
  lines.push('## Por biblioteca');
  lines.push('');
  lines.push('| Biblioteca | Arquivos CSS |');
  lines.push('| --- | ---: |');
  for (const [library, count] of Object.entries(inventory.libraries).sort()) {
    lines.push(`| ${library} | ${count} |`);
  }
  lines.push('| (fora de biblioteca) | ' + (t.files - Object.values(inventory.libraries).reduce((a, b) => a + b, 0)) + ' |');
  lines.push('');
  lines.push('## Arquivos');
  lines.push('');
  lines.push('| Arquivo | Biblioteca | Bytes | Regras | Seletores | !important | :has | :is | :where | Duplicadas |');
  lines.push('| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |');
  for (const file of inventory.files) {
    lines.push(
      `| ${file.file.replace(THEME_PREFIX, '')} | ${file.library || '—'} | ${file.bytes} | ${file.rules} | ${file.selectors} | ${file.important} | ${file.has} | ${file.is} | ${file.where} | ${file.duplicateDeclarations} |`,
    );
  }
  lines.push('');
  lines.push(`## Classes sem referência textual (${inventory.classesWithoutTextualReference.length})`);
  lines.push('');
  lines.push('Ausência de referência textual não prova obsolescência: classes podem ser montadas em tempo de execução. Confirmar com PurgeCSS e Runtime antes de remover.');
  lines.push('');
  lines.push(inventory.classesWithoutTextualReference.map((c) => `\`${c}\``).join(', '));
  lines.push('');
  lines.push('## Variáveis definidas sem uso textual');
  lines.push('');
  lines.push(inventory.variables.definedUnused.map((v) => `\`${v}\``).join(', ') || '—');
  lines.push('');
  lines.push('## Variáveis usadas e não definidas nos CSS do tema');
  lines.push('');
  lines.push('Podem vir de UI Skins em tempo de execução.');
  lines.push('');
  lines.push(inventory.variables.usedUndefined.map((v) => `\`${v}\``).join(', ') || '—');
  lines.push('');
  if (inventory.purge) {
    lines.push('## PurgeCSS');
    lines.push('');
    lines.push(`Rejeitados: ${inventory.purge.totals.rejectedSelectors} seletores em ${inventory.purge.totals.files} arquivos.`);
    lines.push('');
  }
  return `${lines.join('\n')}\n`;
}

main();
