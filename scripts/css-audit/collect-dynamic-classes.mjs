/**
 * Coleta classes montadas dinamicamente a partir dos metadados SDC e do
 * UI Styles, para alimentar a safelist específica do PurgeCSS.
 *
 * Exemplos resolvidos:
 *   'aculta-surface--tone-' ~ tone          (surface.component.yml: enum tone)
 *   'aculta-surface-panel--elevation-' ~ elevation
 *   opções de aculta420_v2.ui_styles.yml    ("aculta-surface aculta-surface--spacing-compact")
 *
 * O coletor não usa expressão regular genérica sobre todo o repositório: ele
 * parte dos metadados estritos de cada componente e expande somente variáveis
 * declaradas em `enum`.
 */

import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import yaml from 'js-yaml';

const THEME_ROOT = 'web/themes/custom/aculta420_v2';
const COMPONENTS_DIR = join(THEME_ROOT, 'components');
const UI_STYLES_FILE = join(THEME_ROOT, 'aculta420_v2.ui_styles.yml');

/** Percorre diretórios sem entrar em node_modules. */
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

/**
 * Lê pares prop → valores de `enum` de um metadata SDC usando YAML
 * estruturado. Valores inteiros são convertidos para string porque viram
 * sufixo de classe.
 */
export function parseComponentEnums(ymlSource) {
  let metadata;
  try {
    metadata = yaml.load(ymlSource);
  } catch (error) {
    throw new Error(`Metadata SDC inválido: ${error.message}`);
  }
  const properties = metadata?.props?.properties ?? {};
  const enums = {};
  for (const [prop, definition] of Object.entries(properties)) {
    if (Array.isArray(definition?.enum)) {
      enums[prop] = definition.enum.map((value) => String(value));
    }
  }
  return enums;
}

/** Lê aliases `{% set local = prop|default(...) %}` de um template Twig. */
function parseAliases(twigSource) {
  const aliases = {};
  const setRegex = /\{%\s*set\s+([a-zA-Z_][\w]*)\s*=\s*([a-zA-Z_][\w]*)(?:\s*\|[^%]*)?\s*%\}/g;
  let match;
  while ((match = setRegex.exec(twigSource)) !== null) {
    aliases[match[1]] = match[2];
  }
  return aliases;
}

/** Resolve os valores de uma variável, seguindo aliases de `set`. */
function valuesFor(variable, enums, aliases, seen = new Set()) {
  if (seen.has(variable)) {
    return [];
  }
  seen.add(variable);
  if (enums[variable]) {
    return enums[variable];
  }
  const alias = aliases[variable];
  return alias ? valuesFor(alias, enums, aliases, seen) : [];
}

/** Expande concatenações e interpolações de classe em templates Twig. */
function expandTwigClasses(twigSource, enums, add) {
  const aliases = parseAliases(twigSource);
  // 'prefixo-' ~ variavel  (com ou sem espaços; aceita filtro/default)
  const twigConcat = /(['"])([a-zA-Z][\w-]*[-_]{1,2})\1\s*~\s*\(?\s*([a-zA-Z_][\w]*)/g;
  let match;
  while ((match = twigConcat.exec(twigSource)) !== null) {
    const [, , prefix, variable] = match;
    for (const value of valuesFor(variable, enums, aliases)) {
      add(`${prefix}${value}`);
    }
  }
  // 'prefixo--{{ variavel }}' (interpolação em qualquer posição do atributo)
  const twigInterpolation = /([a-zA-Z][\w-]*[-_]{1,2})\{\{\s*([a-zA-Z_][\w]*)/g;
  while ((match = twigInterpolation.exec(twigSource)) !== null) {
    const [, prefix, variable] = match;
    for (const value of valuesFor(variable, enums, aliases)) {
      add(`${prefix}${value}`);
    }
  }
}

/** Expande concatenações em PHP/serviços do tema. */
function expandPhpClasses(phpSource, enumsByComponent, add) {
  // 'prefixo-' . $variavel  ou  "prefixo-$variavel"
  const phpConcat = /(['"])([a-zA-Z][\w-]*[-_]{1,2})\1\s*\.\s*\$([a-zA-Z_][\w]*)/g;
  let match;
  while ((match = phpConcat.exec(phpSource)) !== null) {
    const [, , prefix, variable] = match;
    const values = new Set();
    for (const enums of Object.values(enumsByComponent)) {
      for (const value of enums[variable] || []) {
        values.add(value);
      }
    }
    for (const value of values) {
      add(`${prefix}${value}`);
    }
  }
}

/** Extrai classes literais declaradas nas opções do UI Styles. */
function collectUiStyles(source, add) {
  for (const line of source.split(/\r?\n/)) {
    const option = line.match(/^\s{4}([^:#]+):\s*"/);
    if (!option) {
      continue;
    }
    for (const token of option[1].trim().split(/\s+/)) {
      if (/^[a-zA-Z][\w-]*$/.test(token)) {
        add(token);
      }
    }
  }
}

/** Coleta todas as classes dinâmicas resolvíveis. */
export function collectDynamicClasses({ root = '.' } = {}) {
  const base = join(root, THEME_ROOT);
  const enumsByComponent = {};
  const classes = new Set();

  for (const file of walk(join(root, COMPONENTS_DIR))) {
    if (!file.endsWith('.component.yml')) {
      continue;
    }
    const name = relative(join(root, COMPONENTS_DIR), file).split('/')[0];
    enumsByComponent[name] = parseComponentEnums(readFileSync(file, 'utf8'));
  }

  // União de enums por nome de variável para resolver concatenações fora do
  // componente (PHP do tema).
  for (const file of walk(join(base, 'components'))) {
    if (!file.endsWith('.twig')) {
      continue;
    }
    const component = relative(join(base, 'components'), file).split('/')[0];
    expandTwigClasses(readFileSync(file, 'utf8'), enumsByComponent[component] || {}, (cls) => classes.add(cls));
  }

  for (const file of walk(join(base, 'src'))) {
    if (!file.endsWith('.php')) {
      continue;
    }
    expandPhpClasses(readFileSync(file, 'utf8'), enumsByComponent, (cls) => classes.add(cls));
  }

  if (statSync(UI_STYLES_FILE).isFile()) {
    collectUiStyles(readFileSync(UI_STYLES_FILE, 'utf8'), (cls) => classes.add(cls));
  }

  return {
    classes: [...classes].sort(),
    enumsByComponent,
    components: Object.keys(enumsByComponent).length,
  };
}

// Execução direta: imprime JSON para inspeção manual.
if (import.meta.url === `file://${process.argv[1]}`) {
  const result = collectDynamicClasses();
  console.log(JSON.stringify(result, null, 2));
}
