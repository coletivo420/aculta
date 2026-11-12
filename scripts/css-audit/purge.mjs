#!/usr/bin/env node
/**
 * PurgeCSS para o tema aculta420_v2 — modos audit e apply.
 *
 * Uso:
 *   node scripts/css-audit/purge.mjs --mode=audit
 *   node scripts/css-audit/purge.mjs --mode=apply
 *   node scripts/css-audit/purge.mjs --mode=apply --write --confirm-write
 *
 * - audit: gera relatório, CSS podado e CSS rejeitado sem tocar na fonte.
 * - apply: calcula o CSS podado e grava em staging.
 * - `--write`: substitui os arquivos-fonte. Exige `--confirm-write`, cria
 *   backup em `reports/css-audit/backup/` e recusa qualquer arquivo podado
 *   que não faça parse como CSS.
 *
 * O relatório inclui `sourceHash` de todas as folhas de origem. O gate
 * `css:verify` compara o hash e falha se o relatório estiver ausente,
 * incompleto ou desatualizado.
 */

import { readFileSync, writeFileSync, mkdirSync, rmSync, copyFileSync } from 'node:fs';
import { join, relative, dirname } from 'node:path';
import { PurgeCSS } from 'purgecss';
import postcss from 'postcss';
import { collectDynamicClasses } from './collect-dynamic-classes.mjs';
import { buildSafelist, describeSafelist } from './safelist.mjs';
import { EXTRACTORS } from './extractors/index.mjs';
import { THEME_PREFIX, readThemeCss, sourceHash } from './lib/theme-css.mjs';

const ROOT = process.cwd();

/** Fontes que emitem classes consumidas pelo tema. */
const CONTENT_GLOBS = [
  `${THEME_PREFIX}components/**/*.{twig,php,yml,js,html}`,
  `${THEME_PREFIX}templates/**/*.{twig,php,yml,js,html}`,
  `${THEME_PREFIX}src/**/*.{php,yml,html}`,
  `${THEME_PREFIX}js/**/*.js`,
  `${THEME_PREFIX}config/**/*.{yml,php}`,
  `${THEME_PREFIX}*.yml`,
  'web/modules/custom/**/*.{twig,php,module,theme,install,yml,js}',
  'config/sync/*.yml',
];

function parseArgs(argv) {
  const args = { mode: 'audit', write: false, confirmWrite: false, out: 'reports/css-audit' };
  for (const arg of argv.slice(2)) {
    if (arg.startsWith('--mode=')) {
      args.mode = arg.slice('--mode='.length);
    } else if (arg === '--write') {
      args.write = true;
    } else if (arg === '--confirm-write') {
      args.confirmWrite = true;
    } else if (arg.startsWith('--out=')) {
      args.out = arg.slice('--out='.length);
    } else if (arg === '--help') {
      console.log('Uso: node scripts/css-audit/purge.mjs [--mode=audit|apply] [--write --confirm-write] [--out=DIR]');
      process.exit(0);
    }
  }
  return args;
}

/** Separa seletores de topo respeitando parênteses de :is()/:has()/:where(). */
function splitSelectors(selector) {
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

/** Conjunto de seletores de topo de uma folha CSS. */
function selectorSet(cssSource) {
  const set = new Set();
  const root = postcss.parse(cssSource);
  root.walkRules((rule) => {
    rule.selectors.forEach((selector) => set.add(selector.trim()));
  });
  // At-rules sem regras (keyframes, font-face) não entram na contagem de
  // seletores, mas permanecem no CSS final.
  return set;
}

function packageVersion(name) {
  try {
    return JSON.parse(readFileSync(join(ROOT, 'node_modules', name, 'package.json'), 'utf8')).version;
  } catch {
    return null;
  }
}

async function main() {
  const args = parseArgs(process.argv);
  if (!['audit', 'apply'].includes(args.mode)) {
    console.error(`Modo inválido: ${args.mode}. Use audit ou apply.`);
    process.exit(2);
  }
  if (args.write && (!args.confirmWrite || args.mode !== 'apply')) {
    console.error('--write exige --mode=apply e --confirm-write. Nada foi alterado.');
    process.exit(2);
  }

  const dynamic = collectDynamicClasses({ root: ROOT });
  const safelist = buildSafelist(dynamic.classes);
  const css = readThemeCss(ROOT);
  const hash = sourceHash(css);

  const purgecss = new PurgeCSS();
  const results = await purgecss.purge({
    content: CONTENT_GLOBS,
    css,
    safelist,
    extractors: Object.entries(EXTRACTORS).map(([ext, extractor]) => ({ extractor, extensions: [ext] })),
    rejected: true,
    rejectedCss: true,
    variables: false,
    keyframes: false,
    fontFace: false,
  });

  const outDir = join(ROOT, args.out, args.mode);
  rmSync(outDir, { recursive: true, force: true });
  mkdirSync(outDir, { recursive: true });

  const report = {
    generatedAt: new Date().toISOString(),
    mode: args.mode,
    sourceHash: hash,
    toolVersions: { purgecss: packageVersion('purgecss'), postcss: packageVersion('postcss') },
    contentGlobs: CONTENT_GLOBS,
    dynamicClasses: dynamic.classes,
    safelist: describeSafelist(dynamic.classes),
    totals: {
      files: results.length,
      originalBytes: 0,
      retainedBytes: 0,
      removedBytes: 0,
      originalSelectors: 0,
      retainedSelectors: 0,
      removedSelectors: 0,
      purgecssRejectedSelectors: 0,
    },
    files: [],
  };

  const rejectedChunks = [];
  const backupDir = join(ROOT, args.out, 'backup', new Date().toISOString().replace(/[:.]/g, '-'));
  const invalid = [];

  for (const result of results) {
    const source = css.find((item) => item.name === result.file);
    const original = source ? Buffer.byteLength(source.raw) : 0;
    const retained = Buffer.byteLength(result.css);
    const removed = original - retained;
    const rejected = [...new Set(result.rejected || [])].sort();

    // Seletores realmente removidos: diferença entre o CSS de origem e o podado.
    let removedSelectors = [];
    try {
      const before = selectorSet(source.raw);
      const after = selectorSet(result.css);
      removedSelectors = [...before].filter((selector) => !after.has(selector)).sort();
      report.totals.originalSelectors += before.size;
      report.totals.retainedSelectors += after.size;
      report.totals.removedSelectors += removedSelectors.length;
    } catch (error) {
      invalid.push(`${result.file}: ${error.message}`);
    }

    report.totals.originalBytes += original;
    report.totals.retainedBytes += retained;
    report.totals.removedBytes += removed;
    report.totals.purgecssRejectedSelectors += rejected.length;
    report.files.push({
      file: result.file,
      originalBytes: original,
      retainedBytes: retained,
      removedBytes: removed,
      removedSelectors: removedSelectors.length,
      purgecssRejectedSelectors: rejected.length,
      selectorsRemoved: removedSelectors,
      purgecssRejected: rejected,
    });

    if (result.rejectedCss && result.rejectedCss.trim() !== '') {
      rejectedChunks.push(`/* ${result.file} */\n${result.rejectedCss.trim()}\n`);
    }

    const staged = join(outDir, 'staged', result.file);
    mkdirSync(dirname(staged), { recursive: true });
    writeFileSync(staged, result.css);

    if (args.write) {
      postcss.parse(result.css, { from: result.file });
      const backupFile = join(backupDir, result.file);
      mkdirSync(dirname(backupFile), { recursive: true });
      copyFileSync(join(ROOT, result.file), backupFile);
    }
  }

  if (invalid.length > 0) {
    console.error('CSS podado inválido; nada foi escrito na fonte:');
    invalid.forEach((message) => console.error(`  - ${message}`));
    process.exit(1);
  }

  if (args.write) {
    for (const result of results) {
      writeFileSync(join(ROOT, result.file), result.css);
    }
  }

  const manifest = {
    generatedAt: report.generatedAt,
    sourceHash: report.sourceHash,
    toolVersions: report.toolVersions,
    totals: report.totals,
    files: results.map((result) => result.file),
  };

  writeFileSync(join(outDir, 'report.json'), `${JSON.stringify(report, null, 2)}\n`);
  writeFileSync(join(outDir, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);
  writeFileSync(join(outDir, 'rejected.css'), rejectedChunks.join('\n'));

  const { totals } = report;
  console.log(`PurgeCSS ${args.mode}: ${totals.files} arquivos`);
  console.log(`  original:  ${totals.originalBytes} bytes / ${totals.originalSelectors} seletores`);
  console.log(`  retido:    ${totals.retainedBytes} bytes / ${totals.retainedSelectors} seletores`);
  console.log(`  removido:  ${totals.removedBytes} bytes / ${totals.removedSelectors} seletores`);
  console.log(`  rejeitado pelo PurgeCSS (informativo): ${totals.purgecssRejectedSelectors}`);
  console.log(`  classes dinâmicas preservadas: ${dynamic.classes.length} (${dynamic.components} componentes)`);
  console.log(`  sourceHash: ${hash.slice(0, 16)}…`);
  console.log(`  relatório: ${relative(ROOT, join(outDir, 'report.json'))}`);
  if (args.write) {
    console.log(`  ATENÇÃO: arquivos-fonte reescritos. Backup em ${relative(ROOT, backupDir)}`);
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
