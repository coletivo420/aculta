/**
 * ADR-057 - inventário estático do tema V2. Sem acesso ao Runtime e sem mutações.
 * Uso: node scripts/audit-aculta420-adr057.mjs [--json] [--check] [--root DIR]
 */
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { resolve, join, relative, dirname, basename } from 'node:path';
import { fileURLToPath } from 'node:url';

const REGIONS = ['institution', 'header', 'content', 'sidebar_first', 'footer'];
const SUITE = ['ui_patterns', 'ui_patterns_library', 'ui_skins', 'ui_styles', 'ui_icons'];

function walk(dir) {
  if (!existsSync(dir)) return [];
  const result = [], pending = [dir];
  while (pending.length) {
    const current = pending.pop();
    for (const entry of readdirSync(current, { withFileTypes: true })) {
      const path = join(current, entry.name);
      if (entry.isDirectory()) pending.push(path);
      else if (entry.isFile()) result.push(path);
    }
  }
  return result.sort();
}
function read(path) { return existsSync(path) ? readFileSync(path, 'utf8') : ''; }
function sectionKeys(source, section) {
  const match = source.match(new RegExp('(?:^|\\n)' + section + ':\\s*\\n((?:  [^\\n]*\\n?)*)'));
  return match ? [...match[1].matchAll(/^  ([a-zA-Z0-9_-]+):/gm)].map(v => v[1]) : [];
}

export function auditAdr057(root) {
  const base = resolve(root);
  const theme = join(base, 'web/themes/custom/aculta420_v2');
  const config = join(base, 'config/sync');
  const info = read(join(theme, 'aculta420_v2.info.yml'));
  const extensions = read(join(config, 'core.extension.yml'));
  const libraries = read(join(theme, 'aculta420_v2.libraries.yml'));
  const files = walk(theme), sync = walk(config);
  const manifests = files.filter(p => p.endsWith('.component.yml'));
  const stories = files.filter(p => p.endsWith('.story.yml'));
  const storyDirs = new Set(stories.map(dirname));
  const withoutStory = manifests.map(dirname).filter(p => !storyDirs.has(p))
    .map(p => relative(theme, p)).sort();
  const css = files.filter(p => p.endsWith('.css'));
  const regions = sectionKeys(info, 'regions');
  const modules = sectionKeys(extensions, 'module');
  const missingSuite = SUITE.filter(m => !modules.includes(m));
  const violations = [];
  if (!info) violations.push('Falta aculta420_v2.info.yml');
  if (!extensions) violations.push('Falta core.extension.yml');
  if (JSON.stringify(regions) !== JSON.stringify(REGIONS)) violations.push('Cinco regiões do shell divergem');
  if (missingSuite.length) violations.push('Integrações UI Suite ausentes no Sync: ' + missingSuite.join(', '));
  if (modules.includes('ui_patterns_ckeditor5')) violations.push('UI Patterns CKEditor 5 não aprovado');
  if (withoutStory.length) violations.push('SDCs sem story: ' + withoutStory.join(', '));
  const observations = [];
  if (libraries.includes('fonts.googleapis.com')) observations.push('Fontes externas Google Fonts: medir CWV');
  if (!modules.includes('ui_patterns_field_group')) observations.push('Field Group no Sync ausente: BLOCKED_UPSTREAM para novos consumidores');
  return {
    adr: 'ADR-057',
    scope: 'Git worktree, não Runtime',
    version: info.match(/^version:\s*['"]?([^'"\n]+)/m)?.[1] ?? null,
    regions,
    ui_suite: { missing: missingSuite, field_group_sync: modules.includes('ui_patterns_field_group') },
    inventory: {
      manifests: manifests.length,
      stories: stories.length,
      without_story: withoutStory,
      twig_templates: files.filter(p => p.startsWith(join(theme, 'templates') + '/') && p.endsWith('.twig')).length,
      css_files: css.length,
      css_bytes: css.reduce((n, p) => n + statSync(p).size, 0),
      javascript_files: files.filter(p => p.endsWith('.js')).length,
      views_sync: sync.filter(p => /^views\.view\..+\.yml$/.test(basename(p))).length,
      displays_sync: sync.filter(p => /^core\.entity_view_display\..+\.yml$/.test(basename(p))).length,
      browser_routes: walk(join(base, 'tests/browser')).filter(p => basename(p) === 'routes.json').length,
    },
    observations,
    violations,
    static_status: violations.length ? 'FAIL' : 'PASS_STATIC',
    runtime_status: 'DEFERRED',
  };
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const argv = process.argv.slice(2), idx = argv.indexOf('--root');
  if (idx !== -1 && !argv[idx + 1]) {
    process.stderr.write('Erro: --root requer caminho\n'); process.exitCode = 2;
  } else if (argv.some(x => !['--root', '--json', '--check', ...(idx === -1 ? [] : [argv[idx + 1]])].includes(x))) {
    process.stderr.write('Uso: node scripts/audit-aculta420-adr057.mjs [--json] [--check] [--root DIR]\n');
    process.exitCode = 2;
  } else {
    const report = auditAdr057(idx === -1 ? resolve(dirname(fileURLToPath(import.meta.url)), '..') : argv[idx + 1]);
    if (argv.includes('--json')) process.stdout.write(JSON.stringify(report, null, 2) + '\n');
    else process.stdout.write(JSON.stringify(report, null, 2) + '\n');
    if (argv.includes('--check') && report.violations.length) process.exitCode = 1;
  }
}
