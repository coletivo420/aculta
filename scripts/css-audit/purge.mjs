#!/usr/bin/env node
/**
 * PurgeCSS para o tema aculta420_v2 — modos audit e apply.
 *
 * Uso:
 *   node scripts/css-audit/purge.mjs --mode=audit
 *   node scripts/css-audit/purge.mjs --mode=apply
 *   node scripts/css-audit/purge.mjs --mode=apply --write --confirm-write --review-hash=<sha256>
 *
 * - audit: gera relatório, CSS podado e CSS rejeitado sem tocar na fonte.
 * - apply: calcula o CSS podado e grava em staging.
 * - `--write`: só após revisar o staging e seu hash. Exige `--confirm-write`
 *   e `--review-hash`, cria backup e executa gates antes/depois da gravação.
 *
 * O relatório inclui `sourceHash` de todas as folhas de origem. O gate
 * `css:verify` compara o hash e falha se o relatório estiver ausente,
 * incompleto ou desatualizado.
 */

import { readFileSync, writeFileSync, mkdirSync, rmSync, copyFileSync } from 'node:fs';
import { join, relative, dirname } from 'node:path';
import { spawnSync } from 'node:child_process';
import { PurgeCSS } from 'purgecss';
import postcss from 'postcss';
import { collectDynamicClasses } from './collect-dynamic-classes.mjs';
import { buildSafelist, describeSafelist } from './safelist.mjs';
import { EXTRACTORS } from './extractors/index.mjs';
import { readThemeCss, sourceHash } from './lib/theme-css.mjs';
import { createWriteReviewHash, validatePurgeRemovalClassification } from './lib/write-review.mjs';
import { CONTENT_GLOBS } from './lib/content-globs.mjs';

const ROOT = process.cwd();

function parseArgs(argv) {
  const args = { mode: 'audit', write: false, confirmWrite: false, reviewHash: '', out: 'reports/css-audit' };
  for (const arg of argv.slice(2)) {
    if (arg.startsWith('--mode=')) {
      args.mode = arg.slice('--mode='.length);
    } else if (arg === '--write') {
      args.write = true;
    } else if (arg === '--confirm-write') {
      args.confirmWrite = true;
    } else if (arg.startsWith('--review-hash=')) {
      args.reviewHash = arg.slice('--review-hash='.length);
    } else if (arg.startsWith('--out=')) {
      args.out = arg.slice('--out='.length);
    } else if (arg === '--help') {
      console.log('Uso: node scripts/css-audit/purge.mjs [--mode=audit|apply] [--write --confirm-write --review-hash=<sha256>] [--out=DIR]');
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
  if (args.write && (!args.confirmWrite || args.mode !== 'apply' || !/^[a-f0-9]{64}$/.test(args.reviewHash))) {
    console.error('--write exige --mode=apply, --confirm-write e --review-hash=<sha256>. Nada foi alterado.');
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

  // O PurgeCSS pode deixar a folha vazia ou preservar espaçamento interno de
  // pseudo-classes após podar argumentos. Mantemos folhas sem regras intactas
  // e normalizamos o staging com o mesmo Stylelint usado no gate do tema.
  const outDir = join(ROOT, args.out, args.mode);
  rmSync(outDir, { recursive: true, force: true });
  mkdirSync(outDir, { recursive: true });
  for (const result of results) {
    const source = css.find((item) => item.name === result.file);
    let candidate = result.css;
    if (postcss.parse(candidate).nodes.length === 0) {
      candidate = source?.raw ?? candidate;
    }
    result.css = candidate;
    const staged = join(outDir, 'staged', result.file);
    mkdirSync(dirname(staged), { recursive: true });
    writeFileSync(staged, candidate);
  }
  const stagedFiles = results.map((result) => join(outDir, 'staged', result.file));
  const normalizeStaging = spawnSync(join(ROOT, 'node_modules/.bin/stylelint'), ['--fix', ...stagedFiles], { cwd: ROOT, encoding: 'utf8' });
  process.stdout.write(normalizeStaging.stdout ?? '');
  process.stderr.write(normalizeStaging.stderr ?? '');
  if (normalizeStaging.status !== 0) {
    throw new Error('Stylelint não conseguiu normalizar o CSS podado; fontes preservadas.');
  }
  for (const result of results) {
    result.css = readFileSync(join(outDir, 'staged', result.file), 'utf8');
  }

  const toolVersions = {
    purgecss: packageVersion('purgecss'),
    postcss: packageVersion('postcss'),
    stylelint: packageVersion('stylelint'),
  };
  const safelistDescription = describeSafelist(dynamic.classes);
  const classificationPath = join(ROOT, 'reports/css-audit/audit/classification.json');
  let classificationForReview = null;
  try {
    classificationForReview = JSON.parse(readFileSync(classificationPath, 'utf8'));
  } catch {
    // A ausência é relatada no gate de gravação; o staging continua disponível.
  }
  const writeReviewHash = createWriteReviewHash({
    sourceHash: hash,
    toolVersions,
    contentGlobs: CONTENT_GLOBS,
    dynamicClasses: dynamic.classes,
    safelist: safelistDescription,
    classification: classificationForReview,
    files: results.map((result) => ({ file: result.file, css: result.css })),
  });
  if (args.write && args.reviewHash !== writeReviewHash) {
    console.error(`Hash de revisão divergente. Nada foi escrito. Reveja o staging e use: --review-hash=${writeReviewHash}`);
    process.exit(2);
  }

  const report = {
    generatedAt: new Date().toISOString(),
    mode: args.mode,
    sourceHash: hash,
    toolVersions,
    contentGlobs: CONTENT_GLOBS,
    dynamicClasses: dynamic.classes,
    safelist: safelistDescription,
    writeReviewHash,
    totals: {
      files: results.length,
      originalBytes: 0,
      retainedBytes: 0,
      removedBytes: 0,
      originalSelectors: 0,
      retainedSelectors: 0,
      removedSelectors: 0,
      addedSelectors: 0,
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
    let addedSelectors = [];
    let originalSelectors = 0;
    let retainedSelectors = 0;
    try {
      const before = selectorSet(source.raw);
      const after = selectorSet(result.css);
      removedSelectors = [...before].filter((selector) => !after.has(selector)).sort();
      addedSelectors = [...after].filter((selector) => !before.has(selector)).sort();
      originalSelectors = before.size;
      retainedSelectors = after.size;
      report.totals.originalSelectors += originalSelectors;
      report.totals.retainedSelectors += retainedSelectors;
      report.totals.removedSelectors += removedSelectors.length;
      report.totals.addedSelectors += addedSelectors.length;
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
      originalSelectors,
      retainedSelectors,
      removedSelectors: removedSelectors.length,
      addedSelectors: addedSelectors.length,
      selectorsAdded: addedSelectors,
      purgecssRejectedSelectors: rejected.length,
      selectorsRemoved: removedSelectors,
      purgecssRejected: rejected,
    });

    if (result.rejectedCss && result.rejectedCss.trim() !== '') {
      rejectedChunks.push(`/* ${result.file} */\n${result.rejectedCss.trim()}\n`);
    }

    if (args.write) {
      postcss.parse(result.css, { from: result.file });
    }
  }

  if (invalid.length > 0) {
    console.error('CSS podado inválido; nada foi escrito na fonte:');
    invalid.forEach((message) => console.error(`  - ${message}`));
    process.exit(1);
  }

  if (args.mode === 'apply') {
    console.log(`hash de revisão do staging: ${writeReviewHash}`);
    console.log(`revise os arquivos em ${relative(ROOT, join(outDir, 'staged'))} e as listas em report.json antes de qualquer --write.`);
  }

  if (args.write) {
    const dynamicLost = [];
    for (const result of results) {
      const source = css.find((item) => item.name === result.file)?.raw ?? '';
      for (const className of dynamic.classes) {
        const selector = new RegExp(`\\.${className}(?![\\w-])`);
        if (selector.test(source) && !selector.test(result.css)) {
          dynamicLost.push(`${result.file}: ${className}`);
        }
      }
    }
    if (dynamicLost.length > 0) {
      throw new Error(`Gate de classes dinâmicas falhou; nada foi escrito: ${dynamicLost.slice(0, 10).join('; ')}`);
    }

    let currentClassification;
    try {
      currentClassification = JSON.parse(readFileSync(classificationPath, 'utf8'));
    } catch {
      throw new Error('Gate de classificação falhou: rode `npm run css:classify`; nada foi escrito.');
    }
    if (JSON.stringify(currentClassification) !== JSON.stringify(classificationForReview)) {
      throw new Error('Classificação mudou desde a preparação do hash de revisão; gere e revise um staging novo. Nada foi escrito.');
    }
    const classificationProblems = validatePurgeRemovalClassification({ classification: currentClassification, report, sourceHash: hash });
    if (classificationProblems.length > 0) {
      throw new Error(`Gate de classificação falhou: ${classificationProblems.join(' ')} Nada foi escrito.`);
    }

    const verifyBefore = spawnSync(process.execPath, [join(ROOT, 'scripts/css-audit/verify.mjs')], { cwd: ROOT, encoding: 'utf8' });
    process.stdout.write(verifyBefore.stdout ?? '');
    process.stderr.write(verifyBefore.stderr ?? '');
    if (verifyBefore.status !== 0) {
      throw new Error('Gate css:verify do estado-fonte falhou; nada foi escrito.');
    }
    const stagedFiles = results.map((result) => join(outDir, 'staged', result.file));
    const lintStaged = spawnSync(join(ROOT, 'node_modules/.bin/stylelint'), stagedFiles, { cwd: ROOT, encoding: 'utf8' });
    process.stdout.write(lintStaged.stdout ?? '');
    process.stderr.write(lintStaged.stderr ?? '');
    if (lintStaged.status !== 0) {
      throw new Error('Gate Stylelint do staging falhou; nada foi escrito.');
    }

    for (const result of results) {
      const backupFile = join(backupDir, result.file);
      mkdirSync(dirname(backupFile), { recursive: true });
      copyFileSync(join(ROOT, result.file), backupFile);
    }

    try {
      for (const result of results) {
        writeFileSync(join(ROOT, result.file), result.css);
      }
      let gateFailed = false;
      const postWriteCommands = [
        ['npm', ['run', 'css:audit']],
        [process.execPath, [join(ROOT, 'scripts/css-audit/verify.mjs')]],
        [join(ROOT, 'node_modules/.bin/stylelint'), results.map((result) => join(ROOT, result.file))],
      ];
      for (const [command, commandArgs] of postWriteCommands) {
        const gate = spawnSync(command, commandArgs, { cwd: ROOT, encoding: 'utf8' });
        process.stdout.write(gate.stdout ?? '');
        process.stderr.write(gate.stderr ?? '');
        if (gate.status !== 0) gateFailed = true;
      }
      if (gateFailed) {
        throw new Error('Um ou mais gates pós-gravação falharam.');
      }
    } catch (error) {
      for (const result of results) {
        copyFileSync(join(backupDir, result.file), join(ROOT, result.file));
      }
      const restoreAudit = spawnSync('npm', ['run', 'css:audit'], { cwd: ROOT, encoding: 'utf8' });
      process.stdout.write(restoreAudit.stdout ?? '');
      process.stderr.write(restoreAudit.stderr ?? '');
      throw new Error(`${error.message}; fontes restauradas do backup ${relative(ROOT, backupDir)}.`);
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
  console.log(`  reescrito: ${totals.addedSelectors} seletores retidos com forma alterada`);
  console.log(`  rejeitado pelo PurgeCSS (informativo): ${totals.purgecssRejectedSelectors}`);
  console.log(`  classes dinâmicas preservadas: ${dynamic.classes.length} (${dynamic.components} componentes)`);
  console.log(`  sourceHash: ${hash.slice(0, 16)}…`);
  if (args.mode === 'apply') console.log(`  reviewHash: ${writeReviewHash}`);
  console.log(`  relatório: ${relative(ROOT, join(outDir, 'report.json'))}`);
  if (args.write) {
    console.log(`  ATENÇÃO: arquivos-fonte reescritos. Backup em ${relative(ROOT, backupDir)}`);
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
