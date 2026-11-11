// ESLint do ACULTA (flat config). Escopo: JavaScript do próprio projeto.
// Não varre bibliotecas de terceiros (web/libraries, contrib, core, vendor, node_modules).
// Convenções: Drupal.behaviors, core/once, HTMX, ESM, sem jQuery global, sem Bootstrap.
// Regra: não modificar comportamento de componente só para silenciar um aviso.
import js from '@eslint/js';
import globals from 'globals';

const drupalGlobals = {
  Drupal: 'readonly',
  drupalSettings: 'readonly',
  once: 'readonly',
  htmx: 'readonly',
};

export default [
  {
    ignores: [
      'vendor/**',
      'web/core/**',
      'web/modules/contrib/**',
      'web/libraries/**',
      'node_modules/**',
      'tests/browser/node_modules/**',
      '**/*.min.js',
      // Bibliotecas de terceiros embutidas em módulos. Não são código do projeto.
      'web/modules/custom/aculta_portal/modules/aculta_calendar/js/dhtmlx/**',
      'web/modules/custom/aculta_portal/modules/aculta_lms_h5p/js/h5peditor*.js',
    ],
  },
  {
    files: [
      'web/themes/custom/aculta420_v2/js/**/*.js',
      'web/themes/custom/aculta420_v2/components/**/*.js',
      'web/modules/custom/aculta_portal/**/js/**/*.js',
    ],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: { ...globals.browser, ...drupalGlobals },
    },
    rules: {
      ...js.configs.recommended.rules,
      eqeqeq: ['error', 'always'],
      'no-var': 'error',
      'prefer-const': 'error',
      // jQuery global não é usado no ACULTA.
      'no-restricted-globals': ['error', { name: '$', message: 'Não use jQuery global. Use APIs nativas ou Drupal.behaviors.' }, { name: 'jQuery', message: 'Não use jQuery global.' }],
    },
  },
  {
    files: ['eslint.config.mjs'],
    languageOptions: { ecmaVersion: 2022, sourceType: 'module', globals: globals.node },
  },
];
