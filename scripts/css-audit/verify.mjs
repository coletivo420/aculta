#!/usr/bin/env node
/**
 * Gate de integridade do CSS do aculta420_v2.
 *
 * Verifica:
 *  1. Toda folha declarada em aculta420_v2.libraries.yml existe.
 *  2. Nenhuma referência a CSS inexistente em templates/PHP do tema.
 *  3. Nenhum CSS gerado/temporário anexado como biblioteca.
 *  4. Nenhuma dependência visual da V1 (aculta420/) ou Bootstrap reintroduzida.
 *  5. Cada SDC que declara CSS tem o arquivo e o declara em assets.css.
 *  6. A safelist do PurgeCSS não usa padrão geral `^aculta-`.
 *  7. Toda classe dinâmica resolvível (SDC/UI Styles) continua coberta por CSS.
 *
 * Sai com código 1 quando encontra problema.
 */

import { readFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { join, relative, dirname } from 'node:path';
import yaml from 'js-yaml';
import { collectDynamicClasses } from './collect-dynamic-classes.mjs';
import { SAFELIST_PATTERNS, describeSafelist } from './safelist.mjs';
import { readThemeCss, sourceHash } from './lib/theme-css.mjs';
import { validateAuditArtifacts } from './lib/report-integrity.mjs';
import { CONTENT_GLOBS } from './lib/content-globs.mjs';

const ROOT = process.cwd();
const THEME_PREFIX = 'web/themes/custom/aculta420_v2/';
const THEME_ROOT = join(ROOT, THEME_PREFIX);
const LIBRARIES = join(THEME_ROOT, 'aculta420_v2.libraries.yml');

function packageVersion(name) {
  try {
    return JSON.parse(readFileSync(join(ROOT, 'node_modules', name, 'package.json'), 'utf8')).version;
  } catch {
    return null;
  }
}

const errors = [];
const checks = [];

function pass(name, detail = '') {
  checks.push({ name, status: 'PASS', detail });
}

function fail(name, detail) {
  errors.push({ name, detail });
  checks.push({ name, status: 'FAIL', detail });
}

function walk(dir, acc = []) {
  for (const entry of readdirSync(dir)) {
    if (entry === 'node_modules' || entry === '.git') {
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

// 0. Relatório do PurgeCSS presente, completo e atualizado.
const currentCss = readThemeCss(ROOT);
const currentHash = sourceHash(currentCss);
const dynamic = collectDynamicClasses({ root: ROOT });
const auditDir = join(ROOT, 'reports/css-audit/audit');
const manifestPath = join(auditDir, 'manifest.json');
const reportPath = join(auditDir, 'report.json');
const stagedRoot = join(auditDir, 'staged');
const stagedCssByName = new Map();
for (const item of currentCss) {
  const stagedPath = join(stagedRoot, item.name);
  if (existsSync(stagedPath)) {
    stagedCssByName.set(item.name, readFileSync(stagedPath, 'utf8'));
  }
}
if (!existsSync(manifestPath) || !existsSync(reportPath)) {
  fail('Relatório do PurgeCSS presente', 'Rode `npm run css:audit` antes de `css:verify`.');
} else {
  try {
    const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    const report = JSON.parse(readFileSync(reportPath, 'utf8'));
    const problems = validateAuditArtifacts({
      manifest,
      report,
      currentCss,
      currentHash,
      expectedDynamicClasses: dynamic.classes,
      expectedContentGlobs: CONTENT_GLOBS,
      expectedSafelist: describeSafelist(dynamic.classes),
      expectedToolVersions: {
        purgecss: packageVersion('purgecss'),
        postcss: packageVersion('postcss'),
        stylelint: packageVersion('stylelint'),
      },
      stagedCssByName,
    });
    if (problems.length > 0) {
      fail('Relatório do PurgeCSS atualizado e completo', problems.join(' '));
    } else {
      pass('Relatório do PurgeCSS atualizado e completo', `${manifest.files.length} arquivos, hash ${currentHash.slice(0, 12)}…`);
    }
  }
  catch (error) {
    fail('Relatório do PurgeCSS atualizado e completo', `JSON inválido ou estrutura incompleta: ${error.message}`);
  }
}

// 1. Bibliotecas → arquivos existentes.
const librarySource = readFileSync(LIBRARIES, 'utf8');
let libraries;
try {
  libraries = yaml.load(librarySource) ?? {};
} catch (error) {
  fail('aculta420_v2.libraries.yml válido', error.message);
  libraries = {};
}
const declared = [];
for (const definition of Object.values(libraries)) {
  for (const group of Object.values(definition?.css ?? {})) {
    for (const asset of Object.keys(group ?? {})) {
      if (asset.endsWith('.css')) {
        declared.push(asset);
      }
    }
  }
}
if (declared.length === 0) {
  fail('Bibliotecas declaram CSS', 'Nenhuma folha CSS encontrada em aculta420_v2.libraries.yml.');
} else {
  const missing = declared.filter((file) => !existsSync(join(THEME_ROOT, file)));
  if (missing.length > 0) {
    fail('Bibliotecas → arquivos existentes', `Arquivos ausentes: ${missing.join(', ')}`);
  } else {
    pass('Bibliotecas → arquivos existentes', `${declared.length} folhas verificadas.`);
  }
}

// 2. Referências a CSS no tema.
const refPattern = /(?:src\/styles|components\/[\w-]+)\/[\w.-]+\.css/g;
const referenceFiles = walk(THEME_ROOT).filter((file) => /\.(twig|php|yml|theme|module)$/.test(file));
const dangling = [];
for (const file of referenceFiles) {
  const source = readFileSync(file, 'utf8');
  for (const match of source.matchAll(refPattern)) {
    if (!existsSync(join(THEME_ROOT, match[0]))) {
      dangling.push(`${relative(ROOT, file)} → ${match[0]}`);
    }
  }
}
if (dangling.length > 0) {
  fail('Sem referências a CSS inexistente', dangling.slice(0, 10).join('; '));
} else {
  pass('Sem referências a CSS inexistente');
}

// 3. CSS gerado/temporário anexado.
const forbidden = declared.filter((file) => /(^|\/)(reports|staging|dist|build|tmp)\//.test(file) || /purged|\.min\./.test(file));
if (forbidden.length > 0) {
  fail('Sem CSS gerado/temporário anexado', forbidden.join(', '));
} else {
  pass('Sem CSS gerado/temporário anexado');
}

// 4. Dependências visuais da V1/Bootstrap.
// Somente declarações de biblioteca e anexos contam; comentários podem citar a
// V1 como referência histórica.
const v1Patterns = [
  { label: 'tema V1', regex: /web\/themes\/custom\/aculta420\// },
  { label: 'Bootstrap', regex: /\bbootstrap\b/i },
  { label: 'Vite/Webpack', regex: /\b(vite|webpack)\b/i },
];
const v1Hits = [];
for (const file of referenceFiles) {
  const lines = readFileSync(file, 'utf8').split(/\r?\n/);
  const isLibraryDeclaration = /\.(libraries|info)\.yml$/.test(file);
  lines.forEach((line, index) => {
    const isComment = /^\s*(#|\*|\/\/)/.test(line);
    const isAttachment = /#attached|libraries|library|css:/.test(line);
    if (isComment && !isLibraryDeclaration) {
      return;
    }
    if (!isLibraryDeclaration && !isAttachment) {
      return;
    }
    for (const pattern of v1Patterns) {
      if (pattern.regex.test(line)) {
        v1Hits.push(`${relative(ROOT, file)}:${index + 1} (${pattern.label})`);
      }
    }
  });
}
if (v1Hits.length > 0) {
  fail('Sem dependência visual V1/Bootstrap', v1Hits.slice(0, 10).join('; '));
} else {
  pass('Sem dependência visual V1/Bootstrap');
}

// 5. SDC: o core descobre automaticamente `<machine-name>.css`; arquivos com
// outro nome precisam estar declarados em assets.css.
const sdcProblems = [];
let sdcWithCss = 0;
for (const componentYml of walk(join(THEME_ROOT, 'components')).filter((file) => file.endsWith('.component.yml'))) {
  const dir = dirname(componentYml);
  const machineName = componentYml.split('/').slice(-2)[0];
  const cssFiles = readdirSync(dir).filter((file) => file.endsWith('.css'));
  if (cssFiles.length === 0) {
    continue;
  }
  sdcWithCss += 1;
  const source = readFileSync(componentYml, 'utf8');
  let metadata;
  try {
    metadata = yaml.load(source);
  } catch (error) {
    sdcProblems.push(`${relative(ROOT, componentYml)} inválido: ${error.message}`);
    continue;
  }
  const declaredAssets = new Set();
  for (const group of Object.values(metadata?.assets?.css ?? {})) {
    for (const asset of Object.keys(group ?? {})) {
      declaredAssets.add(asset);
    }
  }
  for (const css of cssFiles) {
    if (css === `${machineName}.css`) {
      continue;
    }
    if (!declaredAssets.has(css) && !source.includes(css)) {
      sdcProblems.push(`${relative(ROOT, componentYml)} não declara ${css}`);
    }
  }
}
if (sdcProblems.length > 0) {
  fail('SDC declara seu CSS', sdcProblems.slice(0, 10).join('; '));
} else {
  pass('SDC declara seu CSS', `${sdcWithCss} componentes com CSS.`);
}

// 6. Safelist sem padrão geral aculta-.
const forbiddenPattern = SAFELIST_PATTERNS.find((entry) => entry.pattern.source === '^aculta-');
if (forbiddenPattern) {
  fail('Safelist sem padrão geral aculta-', 'Remova o padrão ^aculta- da safelist.');
} else {
  pass('Safelist sem padrão geral aculta-');
}

// 7. PurgeCSS não pode remover regras de classes dinâmicas válidas.
// Compara o CSS-fonte com o CSS podado gravado pelo modo audit: uma classe
// dinâmica que tinha regra e sumiu do resultado é uma remoção indevida.
const dynamicSet = new Set(dynamic.classes);
const lost = [];
for (const original of currentCss) {
  const after = stagedCssByName.get(original.name);
  if (after === undefined) continue;
  for (const className of dynamicSet) {
    const selector = `.${className}`;
    if (original.raw.includes(selector) && !after.includes(selector) && new RegExp(`\\${selector}(?![\\w-])`).test(original.raw)) {
      lost.push(`${relative(ROOT, original.name)}: ${className}`);
    }
  }
}
if (lost.length > 0) {
  fail('PurgeCSS preserva classes dinâmicas', `${lost.length} removidas: ${lost.slice(0, 15).join('; ')}`);
} else {
  pass('PurgeCSS preserva classes dinâmicas', `${dynamic.classes.length} classes em ${dynamic.components} componentes.`);
}

for (const check of checks) {
  console.log(`${check.status === 'PASS' ? '✓' : '✗'} ${check.name}${check.detail ? ` — ${check.detail}` : ''}`);
}
if (errors.length > 0) {
  console.error(`\n${errors.length} verificação(ões) falharam.`);
  process.exit(1);
}
console.log('\nTodas as verificações passaram.');
