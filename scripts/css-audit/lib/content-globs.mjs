import { THEME_PREFIX } from './theme-css.mjs';

/** Fontes que emitem classes consumidas pelo tema. */
export const CONTENT_GLOBS = [
  `${THEME_PREFIX}components/**/*.{twig,php,yml,js,html}`,
  `${THEME_PREFIX}templates/**/*.{twig,php,yml,js,html}`,
  `${THEME_PREFIX}src/**/*.{php,yml,html}`,
  `${THEME_PREFIX}js/**/*.{js,mjs}`,
  `${THEME_PREFIX}config/**/*.{yml,php}`,
  `${THEME_PREFIX}*.yml`,
  'web/modules/custom/**/*.{twig,php,module,theme,install,yml,js,mjs}',
  'config/sync/*.yml',
];
