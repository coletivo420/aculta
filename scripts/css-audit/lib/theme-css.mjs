/**
 * Utilidades compartilhadas de CSS para auditoria e verificação.
 */

import { createHash } from 'node:crypto';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

export const THEME_PREFIX = 'web/themes/custom/aculta420_v2/';

/** Lista os arquivos CSS-fonte do tema, sem node_modules. */
export function listCssFiles(themeRoot, dir = themeRoot, acc = []) {
  for (const entry of readdirSync(dir)) {
    if (entry === 'node_modules' || entry === '.git') {
      continue;
    }
    const full = join(dir, entry);
    const stat = statSync(full);
    if (stat.isDirectory()) {
      listCssFiles(themeRoot, full, acc);
    } else if (entry.endsWith('.css')) {
      acc.push(full);
    }
  }
  return acc;
}

/** Folhas de origem com caminho relativo à raiz do repositório. */
export function readThemeCss(root) {
  const themeRoot = join(root, 'web/themes/custom/aculta420_v2');
  return listCssFiles(themeRoot)
    .sort()
    .map((file) => ({ name: relative(root, file), raw: readFileSync(file, 'utf8') }));
}

/** Hash estável das folhas de origem (nome + conteúdo). */
export function sourceHash(css) {
  const hash = createHash('sha256');
  for (const item of [...css].sort((a, b) => a.name.localeCompare(b.name))) {
    hash.update(item.name);
    hash.update('\0');
    hash.update(item.raw);
    hash.update('\0');
  }
  return hash.digest('hex');
}
