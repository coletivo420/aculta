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

/** Lê aliases `{% set local = prop|default(...) %}` e ternários literais. */
function parseAliases(twigSource) {
  const aliases = {};
  const literals = {};
  const setRegex = /\{%\s*set\s+([a-zA-Z_][\w]*)\s*=\s*([a-zA-Z_][\w]*)(?:\s*\|[^%]*)?\s*%\}/g;
  let match;
  while ((match = setRegex.exec(twigSource)) !== null) {
    aliases[match[1]] = match[2];
  }
  // {% set layout = variant == 'x' ? 'stacked' : 'inline' %}
  const ternaryRegex = /\{%\s*set\s+([a-zA-Z_][\w]*)\s*=\s*[^%]*\?\s*'([^']+)'\s*:\s*'([^']+)'[^%]*%\}/g;
  while ((match = ternaryRegex.exec(twigSource)) !== null) {
    literals[match[1]] = [...new Set([match[2], match[3]])];
  }
  return { aliases, literals };
}

/** Resolve os valores de uma variável, seguindo aliases e literais de `set`. */
function valuesFor(variable, enums, aliases, literals, seen = new Set()) {
  if (seen.has(variable)) {
    return [];
  }
  seen.add(variable);
  const values = new Set(enums[variable] || []);
  for (const literal of literals[variable] || []) {
    values.add(literal);
  }
  if (aliases[variable]) {
    for (const value of valuesFor(aliases[variable], enums, aliases, literals, seen)) {
      values.add(value);
    }
  }
  return [...values];
}

/** Expande concatenações e interpolações de classe em templates Twig. */
function expandTwigClasses(twigSource, enums, add) {
  const { aliases, literals } = parseAliases(twigSource);
  // 'prefixo-' ~ variavel  (com ou sem espaços; aceita filtro/default)
  const twigConcat = /(['"])([a-zA-Z][\w-]*[-_]{1,2})\1\s*~\s*\(?\s*([a-zA-Z_][\w]*)/g;
  let match;
  while ((match = twigConcat.exec(twigSource)) !== null) {
    const [, , prefix, variable] = match;
    for (const value of valuesFor(variable, enums, aliases, literals)) {
      add(`${prefix}${value}`);
    }
  }
  // 'prefixo--{{ variavel }}' (interpolação em qualquer posição do atributo)
  const twigInterpolation = /([a-zA-Z][\w-]*[-_]{1,2})\{\{\s*([a-zA-Z_][\w]*)/g;
  while ((match = twigInterpolation.exec(twigSource)) !== null) {
    const [, prefix, variable] = match;
    for (const value of valuesFor(variable, enums, aliases, literals)) {
      add(`${prefix}${value}`);
    }
  }
}

/** Expande concatenações em PHP/serviços do tema. */
export function expandPhpClasses(phpSource, enumsByComponent = {}, add = () => {}) {
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

  // Prefixos concatenados aos allowlists de safeValue() em PHP.
  // Ex.: 'aculta-radius--button-' . $this->safeValue($value, ['base', 'lg'], 'lg')
  const safeValuePrefix = /(['"])([a-zA-Z][\w-]*[-_]{1,2})\1\s*\.\s*\$this->safeValue\s*\(/g;
  while ((match = safeValuePrefix.exec(phpSource)) !== null) {
    const [, , prefix] = match;
    const argsStart = safeValuePrefix.lastIndex;
    let depth = 1;
    let quote = null;
    let escaped = false;
    let argsEnd = argsStart;
    for (; argsEnd < phpSource.length && depth > 0; argsEnd += 1) {
      const char = phpSource[argsEnd];
      if (quote) {
        if (escaped) escaped = false;
        else if (char === '\\') escaped = true;
        else if (char === quote) quote = null;
        continue;
      }
      if (char === "'" || char === '"') quote = char;
      else if (char === '(') depth += 1;
      else if (char === ')') depth -= 1;
    }
    if (depth !== 0) continue;
    const args = phpSource.slice(argsStart, argsEnd - 1);
    const parts = [];
    let partStart = 0;
    let parens = 0;
    let brackets = 0;
    quote = null;
    escaped = false;
    for (let index = 0; index < args.length; index += 1) {
      const char = args[index];
      if (quote) {
        if (escaped) escaped = false;
        else if (char === '\\') escaped = true;
        else if (char === quote) quote = null;
        continue;
      }
      if (char === "'" || char === '"') quote = char;
      else if (char === '(') parens += 1;
      else if (char === ')') parens -= 1;
      else if (char === '[') brackets += 1;
      else if (char === ']') brackets -= 1;
      else if (char === ',' && parens === 0 && brackets === 0) {
        parts.push(args.slice(partStart, index));
        partStart = index + 1;
      }
    }
    parts.push(args.slice(partStart));
    const allowed = parts[1]?.match(/^\s*\[([\s\S]*)\]\s*$/);
    if (!allowed) continue;
    for (const value of allowed[1].matchAll(/(['"])([a-zA-Z][\w-]*)\1/g)) {
      add(`${prefix}${value[2]}`);
    }
    safeValuePrefix.lastIndex = argsEnd;
  }
}

/** Extrai classes das chaves de opção do YAML de UI Styles. */
export function parseUiStylesClasses(source) {
  let metadata;
  try {
    metadata = yaml.load(source);
  } catch (error) {
    throw new Error(`Configuração UI Styles inválida: ${error.message}`);
  }
  const classes = new Set();
  for (const definition of Object.values(metadata ?? {})) {
    for (const option of Object.keys(definition?.options ?? {})) {
      for (const token of option.trim().split(/\s+/)) {
        if (/^[a-zA-Z][\w-]*$/.test(token)) {
          classes.add(token);
        }
      }
    }
  }
  return [...classes].sort();
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
    for (const cls of parseUiStylesClasses(readFileSync(UI_STYLES_FILE, 'utf8'))) {
      classes.add(cls);
    }
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
