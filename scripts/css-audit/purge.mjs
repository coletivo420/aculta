#!/usr/bin/env node
/**
 * PurgeCSS para o tema aculta420_v2 — modos audit e apply.
 *
 * Uso:
 *   node scripts/css-audit/purge.mjs --mode=audit
 *   node scripts/css-audit/purge.mjs --mode=apply --write
 *
 * - audit: gera relatório e CSS rejeitado sem tocar nos arquivos-fonte.
 * - apply: calcula o CSS podado. Sem `--write`, grava em staging; com
 *   `--write`, substitui os arquivos-fonte (somente depois de revisão).
 *
 * O CSS é capturado de componentes SDC e de src/styles. O HTML renderizado no
 * Runtime complementa a auditoria (ver report.mjs e os testes de navegador);
 * classes dinâmicas vêm de collect-dynamic-classes.mjs + safelist.mjs.
 */

import { readFileSync, writeFileSync, mkdirSync, rmSync, readdirSync, statSync } from 'node:fs';
import { join, relative, dirname } from 'node:path';
import { PurgeCSS } from 'purgecss';
import { collectDynamicClasses } from './collect-dynamic-classes.mjs';
import { buildSafelist, describeSafelist } from './safelist.mjs';
import { EXTRACTORS } from './extractors/index.mjs';

const ROOT = process.cwd();
const THEME_ROOT = join(ROOT, 'web/themes/custom/aculta420_v2');
const THEME_PREFIX = 'web/themes/custom/aculta420_v2/';

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
  const args = { mode: 'audit', write: false, out: 'reports/css-audit' };
  for (const arg of argv.slice(2)) {
    if (arg.startsWith('--mode=')) {
      args.mode = arg.slice('--mode='.length);
    } else if (arg === '--write') {
      args.write = true;
    } else if (arg.startsWith('--out=')) {
      args.out = arg.slice('--out='.length);
    } else if (arg === '--help') {
      console.log('Uso: node scripts/css-audit/purge.mjs [--mode=audit|apply] [--write] [--out=DIR]');
      process.exit(0);
    }
  }
  return args;
}

/** Lista os arquivos CSS-fonte do tema, sem node_modules. */
function listCssFiles(dir, acc = []) {
  for (const entry of readdirSync(dir)) {
    if (entry === 'node_modules' || entry === '.git') {
      continue;
    }
    const full = join(dir, entry);
    const stat = statSync(full);
    if (stat.isDirectory()) {
      listCssFiles(full, acc);
    } else if (entry.endsWith('.css')) {
      acc.push(full);
    }
  }
  return acc;
}

async function main() {
  const args = parseArgs(process.argv);
  if (!['audit', 'apply'].includes(args.mode)) {
    console.error(`Modo inválido: ${args.mode}. Use audit ou apply.`);
    process.exit(2);
  }

  const dynamic = collectDynamicClasses({ root: ROOT });
  const safelist = buildSafelist(dynamic.classes);
  const cssFiles = listCssFiles(THEME_ROOT).sort();

  const css = cssFiles.map((file) => ({
    name: relative(ROOT, file),
    raw: readFileSync(file, 'utf8'),
  }));

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
    contentGlobs: CONTENT_GLOBS,
    dynamicClasses: dynamic.classes,
    safelist: describeSafelist(dynamic.classes),
    totals: { files: results.length, originalBytes: 0, purgedBytes: 0, rejectedBytes: 0, rejectedSelectors: 0 },
    files: [],
  };

  const rejectedChunks = [];
  for (const result of results) {
    const source = css.find((item) => item.name === result.file);
    const original = source ? Buffer.byteLength(source.raw) : 0;
    const purgedBytes = Buffer.byteLength(result.css);
    const rejectedBytes = Buffer.byteLength(result.rejectedCss || '');
    const rejected = [...new Set(result.rejected || [])].sort();
    report.totals.originalBytes += original;
    report.totals.purgedBytes += purgedBytes;
    report.totals.rejectedBytes += rejectedBytes;
    report.totals.rejectedSelectors += rejected.length;
    report.files.push({
      file: result.file,
      originalBytes: original,
      purgedBytes,
      rejectedBytes,
      rejectedSelectors: rejected.length,
      rejected,
    });
    if (result.rejectedCss && result.rejectedCss.trim() !== '') {
      rejectedChunks.push(`/* ${result.file} */\n${result.rejectedCss.trim()}\n`);
    }

    if (args.mode === 'apply' && args.write) {
      writeFileSync(join(ROOT, result.file), result.css);
    } else {
      const staged = join(outDir, 'staged', result.file);
      mkdirSync(dirname(staged), { recursive: true });
      writeFileSync(staged, result.css);
    }
  }

  writeFileSync(join(outDir, 'report.json'), `${JSON.stringify(report, null, 2)}\n`);
  writeFileSync(join(outDir, 'rejected.css'), rejectedChunks.join('\n'));

  const { totals } = report;
  console.log(`PurgeCSS ${args.mode}: ${totals.files} arquivos`);
  console.log(`  original: ${totals.originalBytes} bytes`);
  console.log(`  podado:   ${totals.purgedBytes} bytes`);
  console.log(`  rejeitado: ${totals.rejectedSelectors} seletores / ${totals.rejectedBytes} bytes`);
  console.log(`  classes dinâmicas preservadas: ${dynamic.classes.length} (${dynamic.components} componentes)`);
  console.log(`  relatório: ${relative(ROOT, join(outDir, 'report.json'))}`);
  if (args.mode === 'apply' && args.write) {
    console.log('  ATENÇÃO: arquivos-fonte foram reescritos (--write).');
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
