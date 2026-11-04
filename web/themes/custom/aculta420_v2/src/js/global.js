/**
 * @file
 * Global JavaScript for ACULTA420 V2 theme.
 */

/**
 * Drupal behavior for global functionality.
 */
Drupal.behaviors.acultaGlobal = {
  attach: function (context, settings) {
    // Initialize theme-specific functionality.
    this.initTheme(context, settings);
  },

  /**
   * Initialize theme-specific functionality.
   *
   * @param {object} context
   *   The context for the behavior.
   * @param {object} settings
   *   The settings for the behavior.
   */
  initTheme: function (context, settings) {
    // Set dark theme if needed.
    this.setupDarkTheme();
    
    // Add any other global functionality here.
  },

  /**
   * Setup dark theme based on user preference or cookie.
   */
  setupDarkTheme: function () {
    const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
    const storedTheme = localStorage.getItem('theme');
    
    if (storedTheme === 'dark' || (!storedTheme && prefersDarkScheme.matches)) {
      document.documentElement.setAttribute('data-theme', 'dark');
    } else {
      document.documentElement.setAttribute('data-theme', 'light');
    }
  }
};