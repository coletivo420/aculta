/**
 * Extratores de classe por tipo de fonte.
 *
 * O extrator padrão do PurgeCSS serve para Twig/PHP/JS, mas YAML e metadados
 * SDC precisam de tratamento próprio: chaves e valores de `enum` são classes
 * consumidas por templates, mesmo sem aparecer literalmente na marcação.
 */

/** Extrai tokens de classe de marcação, código e configuração. */
export function defaultExtractor(content) {
  return content.match(/[\w-/:]+(?<!:)/g) || [];
}

/** Twig/PHP/JS: comportamento padrão, com remoção de comentários de bloco. */
export function codeExtractor(content) {
  const withoutBlockComments = content.replace(/\/\*[\s\S]*?\*\//g, ' ');
  return defaultExtractor(withoutBlockComments);
}

/**
 * YAML: considera chaves, valores e listas. Classes aparecem como chaves de
 * opções de UI Styles, valores de `enum` e valores de `class` em config.
 */
export function yamlExtractor(content) {
  const tokens = new Set();
  const lines = content.split(/\r?\n/);
  for (const line of lines) {
    // Ignora comentário de linha inteira.
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) {
      continue;
    }
    // Remove comentário de fim de linha (fora de aspas, aproximação segura
    // para YAML de configuração do Drupal).
    const withoutComment = trimmed.replace(/\s+#.*$/, '');
    for (const token of defaultExtractor(withoutComment)) {
      tokens.add(token);
    }
    // Classes separadas por espaço dentro de valores entre aspas:
    // "aculta-surface aculta-surface--spacing-compact".
    const quoted = withoutComment.match(/"([^"]*)"|'([^']*)'/g) || [];
    for (const value of quoted) {
      const inner = value.slice(1, -1);
      for (const token of inner.split(/\s+/)) {
        if (token) {
          tokens.add(token);
        }
      }
    }
  }
  return [...tokens];
}

/** Mapa extensão → extrator para o PurgeCSS. */
export const EXTRACTORS = {
  twig: codeExtractor,
  php: codeExtractor,
  module: codeExtractor,
  theme: codeExtractor,
  inc: codeExtractor,
  js: codeExtractor,
  mjs: codeExtractor,
  html: codeExtractor,
  yml: yamlExtractor,
  yaml: yamlExtractor,
  json: codeExtractor,
};
