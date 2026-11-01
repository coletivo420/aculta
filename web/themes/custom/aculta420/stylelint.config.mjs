/** @type {import('stylelint').Config} */
export default {
  extends: ['stylelint-config-recommended'],
  rules: {
    // Theme CSS layers intentionally repeat selectors across states and media queries.
    'no-descending-specificity': null,
    'no-duplicate-selectors': null,
  },
};
