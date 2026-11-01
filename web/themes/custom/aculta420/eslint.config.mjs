import js from '@eslint/js';
import globals from 'globals';

export default [
  js.configs.recommended,
  {
    files: ['js/**/*.js'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'script',
      globals: {
        ...globals.browser,
        Drupal: 'readonly',
        bootstrap: 'readonly',
        once: 'readonly',
        drupalSettings: 'readonly',
      },
    },
  },
];
