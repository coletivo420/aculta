<?php

declare(strict_types=1);

/**
 * Small, dependency-free analyzers for the ACULTA420 design-foundation gate.
 *
 * Supported CSS is the flat token stylesheet used by ACULTA420: top-level
 * rules, nested selectors/at-rules for mode-selector discovery, custom
 * properties, CSS color functions/named colors, and var(--token) aliases without fallbacks.
 * This is intentionally not a complete CSS, Twig, PHP, or JavaScript parser.
 */
final class Aculta420DesignFoundationsAnalyzer {

  private const LIGHT_SELECTOR = ':root, [data-bs-theme="light"]';

  private const DARK_SELECTOR = '[data-bs-theme="dark"]';

  private const MODE_CLASSES = [
    'dark', 'light', 'dark-theme', 'light-theme', 'theme-dark', 'theme-light',
    'dark-mode', 'light-mode', 'is-dark', 'is-light', 'color-mode-dark', 'color-mode-light',
  ];

  private const CSS_NAMED_COLORS = [
    'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque',
    'black', 'blanchedalmond', 'blue', 'blueviolet', 'brown', 'burlywood', 'cadetblue',
    'chartreuse', 'chocolate', 'coral', 'cornflowerblue', 'cornsilk', 'crimson', 'cyan',
    'darkblue', 'darkcyan', 'darkgoldenrod', 'darkgray', 'darkgrey', 'darkgreen',
    'darkkhaki', 'darkmagenta', 'darkolivegreen', 'darkorange', 'darkorchid', 'darkred',
    'darksalmon', 'darkseagreen', 'darkslateblue', 'darkslategray', 'darkslategrey',
    'darkturquoise', 'darkviolet', 'deeppink', 'deepskyblue', 'dimgray', 'dimgrey',
    'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen', 'fuchsia', 'gainsboro',
    'ghostwhite', 'gold', 'goldenrod', 'gray', 'grey', 'green', 'greenyellow', 'honeydew',
    'hotpink', 'indianred', 'indigo', 'ivory', 'khaki', 'lavender', 'lavenderblush',
    'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral', 'lightcyan', 'lightgoldenrodyellow',
    'lightgray', 'lightgrey', 'lightgreen', 'lightpink', 'lightsalmon', 'lightseagreen',
    'lightskyblue', 'lightslategray', 'lightslategrey', 'lightsteelblue', 'lightyellow',
    'lime', 'limegreen', 'linen', 'magenta', 'maroon', 'mediumaquamarine', 'mediumblue',
    'mediumorchid', 'mediumpurple', 'mediumseagreen', 'mediumslateblue', 'mediumspringgreen',
    'mediumturquoise', 'mediumvioletred', 'midnightblue', 'mintcream', 'mistyrose', 'moccasin',
    'navajowhite', 'navy', 'oldlace', 'olive', 'olivedrab', 'orange', 'orangered', 'orchid',
    'palegoldenrod', 'palegreen', 'paleturquoise', 'palevioletred', 'papayawhip', 'peachpuff',
    'peru', 'pink', 'plum', 'powderblue', 'purple', 'rebeccapurple', 'red', 'rosybrown',
    'royalblue', 'saddlebrown', 'salmon', 'sandybrown', 'seagreen', 'seashell', 'sienna',
    'silver', 'skyblue', 'slateblue', 'slategray', 'slategrey', 'snow', 'springgreen',
    'steelblue', 'tan', 'teal', 'thistle', 'tomato', 'turquoise', 'violet', 'wheat', 'white',
    'whitesmoke', 'yellow', 'yellowgreen',
  ];

  private const REQUIRED_TOKENS = [
    '--aculta-surface-page', '--aculta-surface-raised', '--aculta-surface-muted',
    '--aculta-surface-header', '--aculta-surface-interactive',
    '--aculta-text-primary', '--aculta-text-secondary', '--aculta-text-muted',
    '--aculta-text-inverse', '--aculta-text-accent',
    '--aculta-border-subtle', '--aculta-border-default', '--aculta-border-strong',
    '--aculta-border-accent', '--aculta-interactive-bg', '--aculta-interactive-text',
    '--aculta-interactive-border', '--aculta-interactive-hover-bg',
    '--aculta-interactive-hover-text', '--aculta-interactive-active-bg',
    '--aculta-interactive-active-text', '--aculta-focus-ring',
    '--aculta-shell-institution-bg', '--aculta-shell-institution-text',
    '--aculta-shell-domain-bg', '--aculta-shell-domain-text', '--aculta-shell-border',
    '--aculta-shell-active-bg', '--aculta-shell-active-text',
  ];

  private const DARK_SURFACES = [
    '--aculta-surface-page' => '#171513',
    '--aculta-surface-raised' => '#211e1b',
    '--aculta-surface-muted' => '#2b2723',
    '--aculta-surface-interactive' => '#302b27',
    '--aculta-surface-header' => '#211e1b',
    '--aculta-shell-institution-bg' => '#2b2723',
    '--aculta-shell-domain-bg' => '#211e1b',
  ];

  private const COLOR_TOKENS = [
    '--aculta-green', '--aculta-green-dark', '--aculta-yellow', '--aculta-red',
    '--aculta-cream', '--aculta-white', '--aculta-button-primary-bg',
    '--aculta-button-primary-text', '--aculta-button-secondary-bg',
    '--aculta-button-secondary-text', '--aculta-button-secondary-border',
    '--aculta-button-hover-bg', '--aculta-button-hover-text', '--aculta-nav-current-bg',
    '--aculta-nav-current-text', '--aculta-nav-current-underline', '--aculta-nav-hover-bg',
    '--aculta-nav-hover-text', '--aculta-nav-hover-underline', '--aculta-link-editorial',
    '--aculta-link-editorial-hover', '--aculta-link', '--aculta-border',
    '--aculta-control-border', '--aculta-focus',
    '--aculta-surface-page', '--aculta-surface-raised', '--aculta-surface-muted',
    '--aculta-surface-header', '--aculta-surface-interactive', '--aculta-text-primary',
    '--aculta-text-secondary', '--aculta-text-muted', '--aculta-text-inverse',
    '--aculta-text-accent', '--aculta-border-subtle', '--aculta-border-default',
    '--aculta-border-strong', '--aculta-border-accent', '--aculta-interactive-bg',
    '--aculta-interactive-text', '--aculta-interactive-border',
    '--aculta-interactive-hover-bg', '--aculta-interactive-hover-text',
    '--aculta-interactive-active-bg', '--aculta-interactive-active-text',
    '--aculta-focus-ring', '--aculta-shell-institution-bg',
    '--aculta-shell-institution-text', '--aculta-shell-domain-bg',
    '--aculta-shell-domain-text', '--aculta-shell-border', '--aculta-shell-active-bg',
    '--aculta-shell-active-text',
    '--bs-primary', '--bs-secondary', '--bs-success', '--bs-info', '--bs-warning',
    '--bs-danger', '--bs-light', '--bs-dark', '--bs-black', '--bs-white', '--bs-gray',
    '--bs-gray-dark', '--bs-gray-100', '--bs-gray-200', '--bs-gray-300', '--bs-gray-400',
    '--bs-gray-500', '--bs-gray-600', '--bs-gray-700', '--bs-gray-800', '--bs-gray-900',
    '--bs-body-color', '--bs-body-bg', '--bs-code-color', '--bs-highlight-color',
    '--bs-highlight-bg', '--bs-heading-color',
    '--bs-emphasis-color', '--bs-secondary-color', '--bs-tertiary-color',
    '--bs-secondary-bg', '--bs-tertiary-bg', '--bs-link-color', '--bs-link-hover-color',
    '--bs-border-color', '--bs-border-color-translucent', '--bs-focus-ring-color',
    '--bs-form-valid-color', '--bs-form-valid-border-color', '--bs-form-invalid-color',
    '--bs-form-invalid-border-color', '--bs-primary-text-emphasis',
    '--bs-secondary-text-emphasis', '--bs-success-text-emphasis', '--bs-info-text-emphasis',
    '--bs-warning-text-emphasis', '--bs-danger-text-emphasis', '--bs-light-text-emphasis',
    '--bs-dark-text-emphasis', '--bs-primary-bg-subtle', '--bs-secondary-bg-subtle',
    '--bs-success-bg-subtle', '--bs-info-bg-subtle', '--bs-warning-bg-subtle',
    '--bs-danger-bg-subtle', '--bs-light-bg-subtle', '--bs-dark-bg-subtle',
    '--bs-primary-border-subtle', '--bs-secondary-border-subtle', '--bs-success-border-subtle',
    '--bs-info-border-subtle', '--bs-warning-border-subtle', '--bs-danger-border-subtle',
    '--bs-light-border-subtle', '--bs-dark-border-subtle',
  ];

  private const RGB_TOKENS = [
    '--aculta-surface-page-rgb', '--bs-body-color-rgb', '--bs-body-bg-rgb',
    '--bs-primary-rgb', '--bs-secondary-rgb', '--bs-success-rgb', '--bs-info-rgb',
    '--bs-warning-rgb', '--bs-danger-rgb', '--bs-light-rgb', '--bs-dark-rgb',
    '--bs-black-rgb', '--bs-white-rgb', '--bs-emphasis-color-rgb',
    '--bs-secondary-color-rgb', '--bs-tertiary-color-rgb', '--bs-secondary-bg-rgb',
    '--bs-tertiary-bg-rgb', '--bs-link-color-rgb', '--bs-link-hover-color-rgb',
  ];

  private const RGB_MAPPINGS = [
    '--bs-primary-rgb' => '--bs-primary',
    '--bs-secondary-rgb' => '--bs-secondary',
    '--bs-success-rgb' => '--bs-success',
    '--bs-info-rgb' => '--bs-info',
    '--bs-warning-rgb' => '--bs-warning',
    '--bs-danger-rgb' => '--bs-danger',
    '--bs-black-rgb' => '--bs-black',
    '--bs-white-rgb' => '--bs-white',
    '--bs-body-bg-rgb' => '--aculta-surface-page',
    '--bs-body-color-rgb' => '--aculta-text-primary',
    '--bs-secondary-bg-rgb' => '--aculta-surface-raised',
    '--bs-tertiary-bg-rgb' => '--aculta-surface-muted',
    '--bs-light-rgb' => '--aculta-surface-muted',
    '--bs-dark-rgb' => '--aculta-surface-page',
    '--bs-secondary-color-rgb' => '--aculta-text-secondary',
    '--bs-tertiary-color-rgb' => '--aculta-text-muted',
    '--bs-emphasis-color-rgb' => '--aculta-text-primary',
    '--bs-link-color-rgb' => '--bs-link-color',
    '--bs-link-hover-color-rgb' => '--bs-link-hover-color',
    '--aculta-surface-page-rgb' => '--aculta-surface-page',
  ];

  /** Check absolute paths using the selected platform's root syntax. */
  public static function isAbsolutePath(string $path, ?string $platform = NULL): bool {
    if ($path === '' || str_contains($path, "\0")) {
      return FALSE;
    }
    $platform ??= PHP_OS_FAMILY;
    $windows = strtolower($platform) === 'windows';
    $absolute = $windows
      ? (preg_match('/^[a-z]:[\\\\\/]/i', $path) === 1
        || preg_match('/^(?:\\\\\\\\|\/\/)[^\\\\\/]+[\\\\\/][^\\\\\/]+/', $path) === 1)
      : str_starts_with($path, '/');
    if (!$absolute) {
      return FALSE;
    }
    $segments = preg_split('~[\\\\/]+~', $path) ?: [];
    return !in_array('..', $segments, TRUE);
  }

  /** Compare paths after normalizing platform separators and case rules. */
  public static function pathsEquivalent(string $first, string $second, ?string $platform = NULL): bool {
    $normalize = static fn (string $path): string => str_replace('\\', '/', $path);
    $first = $normalize($first);
    $second = $normalize($second);
    $platform ??= PHP_OS_FAMILY;
    return strtolower($platform) === 'windows'
      ? strcasecmp($first, $second) === 0
      : $first === $second;
  }

  /** Analyze the two supported token blocks and their effective values. */
  public static function analyzeTokens(string $css): array {
    $assertions = [];
    $assert = static function (bool $condition, string $message) use (&$assertions): void {
      $assertions[] = [$condition, $message];
    };
    $clean = self::stripCssComments($css);
    [$rules, $parse_errors] = self::parseCssRules($clean);
    foreach ($parse_errors as $error) {
      $assert(FALSE, 'Token CSS parses within the supported flat-rule subset: ' . $error . '.');
    }

    $light_rules = [];
    $dark_rules = [];
    $unsupported_token_rules = [];
    $auto_rules = [];
    $walk = static function (array $nodes, bool $top_level = TRUE) use (&$walk, &$light_rules, &$dark_rules, &$auto_rules, &$unsupported_token_rules): void {
      foreach ($nodes as $node) {
        if ($top_level && self::isAutoModeBlock($node)) {
          $auto_rules[] = $node['children'][0];
          continue;
        }
        $selector = self::normalizeSelector($node['selector']);
        if ($top_level && $selector === self::normalizeSelector(self::LIGHT_SELECTOR)) {
          $light_rules[] = $node;
        }
        elseif ($top_level && $selector === self::normalizeSelector(self::DARK_SELECTOR)) {
          $dark_rules[] = $node;
        }
        else {
          $unsupported_token_rules[] = $node['selector'];
        }
        if (self::containsModeSelector($node['selector'])) {
          $is_supported_root = $top_level && ($selector === self::normalizeSelector(self::LIGHT_SELECTOR)
            || $selector === self::normalizeSelector(self::DARK_SELECTOR));
          if (!$is_supported_root) {
            $unsupported_token_rules[] = $node['selector'];
          }
          elseif ($node['children'] !== []) {
            $unsupported_token_rules[] = $node['selector'] . ' contains a nested selector';
          }
        }
        $walk($node['children'], FALSE);
      }
    };
    $walk($rules);
    $assert(count($light_rules) === 1, 'Exactly one supported root/light token block exists.');
    $assert(count($dark_rules) === 1, 'Exactly one supported dark token block exists.');
    $assert($unsupported_token_rules === [], 'Mode selectors and ACULTA/Bootstrap tokens occur only in the two supported top-level token blocks, with no nested rules.');

    $parse_mode = static function (array $mode_rules, string $mode) use ($assert): array {
      $declarations = [];
      foreach ($mode_rules as $rule) {
        foreach (self::parseDeclarations($rule['body']) as $declaration) {
          if (!str_starts_with($declaration['name'], '--')) {
            $assert(FALSE, ucfirst($mode) . ' token block contains custom properties only (found ' . $declaration['name'] . ').');
            continue;
          }
          $declarations[$declaration['name']][] = $declaration['value'];
        }
      }
      foreach ($declarations as $name => $values) {
        $assert(count($values) === 1, ucfirst($mode) . ' token block has one declaration for ' . $name . '.');
      }
      return array_map(static fn (array $values): string => $values[array_key_last($values)], $declarations);
    };
    $light = $parse_mode($light_rules, 'light');
    $dark = $parse_mode($dark_rules, 'dark');
    $assert(count($auto_rules) <= 1, 'At most one automatic (prefers-color-scheme) token block exists.');
    if ($auto_rules !== []) {
      $assert($parse_mode($auto_rules, 'auto') === $dark, 'Automatic token block mirrors the dark token block exactly.');
    }

    foreach (self::REQUIRED_TOKENS as $token) {
      $assert(array_key_exists($token, $light), $token . ' has a light value.');
      $assert(array_key_exists($token, $dark), $token . ' has a dark value.');
    }
    $light_effective = $light;
    $dark_effective = array_replace($light, $dark);

    $resolve = static fn (string $token, array $map): array => self::resolveToken($token, $map);
    $token_names = array_values(array_unique(array_merge(array_keys($light), array_keys($dark))));
    foreach (['light' => $light_effective, 'dark' => $dark_effective] as $mode => $effective) {
      foreach ($token_names as $token) {
        if (preg_match('/^--(?:aculta|bs)-/', $token) !== 1) {
          continue;
        }
        $result = $resolve($token, $effective);
        $assert($result['error'] === NULL, ucfirst($mode) . ' token ' . $token . ' resolves without missing references or cycles.');
      }
      foreach (self::COLOR_TOKENS as $token) {
        $result = $resolve($token, $effective);
        $assert($result['error'] === NULL && self::parseColor($result['value'] ?? '') !== NULL, ucfirst($mode) . ' color token ' . $token . ' resolves to a supported color value.');
      }
      foreach (self::RGB_TOKENS as $token) {
        $result = $resolve($token, $effective);
        $assert($result['error'] === NULL && self::parseRgbTriplet($result['value'] ?? '') !== NULL, ucfirst($mode) . ' RGB token ' . $token . ' resolves to a valid channel triplet.');
      }
    }

    foreach (self::DARK_SURFACES as $token => $expected) {
      $result = $resolve($token, $dark_effective);
      $assert($result['error'] === NULL && self::sameColor($result['value'] ?? '', $expected), $token . ' resolves to approved neutral dark surface ' . $expected . '.');
    }

    $dark_pairs = [
      ['--aculta-text-primary', '--aculta-surface-page'],
      ['--aculta-text-primary', '--aculta-surface-raised'],
      ['--aculta-text-secondary', '--aculta-surface-raised'],
      ['--aculta-text-muted', '--aculta-surface-raised'],
      ['--aculta-text-accent', '--aculta-surface-raised'],
      ['--aculta-interactive-text', '--aculta-interactive-bg'],
      ['--aculta-interactive-hover-text', '--aculta-interactive-hover-bg'],
      ['--aculta-interactive-active-text', '--aculta-interactive-active-bg'],
      ['--aculta-shell-institution-text', '--aculta-shell-institution-bg'],
      ['--aculta-shell-domain-text', '--aculta-shell-domain-bg'],
    ];
    $dark_contrast = [];
    foreach ($dark_pairs as [$foreground, $background]) {
      $fg = self::resolvedColor($foreground, $dark_effective);
      $bg = self::resolvedColor($background, $dark_effective);
      $assert($bg !== NULL && ($bg[3] ?? 1.0) === 1.0, $background . ' is opaque for contrast evaluation.');
      $ratio = $fg !== NULL && $bg !== NULL ? self::contrast(self::composite($fg, $bg), $bg) : 0.0;
      $dark_contrast[$foreground . ' / ' . $background] = $ratio;
      $assert($ratio >= 4.5, $foreground . ' / ' . $background . ' meets WCAG AA for normal text in dark mode.');
    }
    $focus_contrast = [];
    foreach (['--aculta-surface-page', '--aculta-surface-raised', '--aculta-surface-muted', '--aculta-surface-header', '--aculta-surface-interactive'] as $surface) {
      $fg = self::resolvedColor('--aculta-focus-ring', $dark_effective);
      $bg = self::resolvedColor($surface, $dark_effective);
      $assert($bg !== NULL && ($bg[3] ?? 1.0) === 1.0, $surface . ' is opaque for focus contrast evaluation.');
      $ratio = $fg !== NULL && $bg !== NULL ? self::contrast(self::composite($fg, $bg), $bg) : 0.0;
      $focus_contrast[$surface] = $ratio;
      $assert($ratio >= 3.0, '--aculta-focus-ring has at least 3:1 contrast against ' . $surface . '.');
    }

    $mode_contrast = [];
    foreach (['light' => $light_effective, 'dark' => $dark_effective] as $mode => $effective) {
      $mode_contrast[$mode] = [];
      foreach ([
        ['--aculta-text-primary', '--aculta-surface-page'],
        ['--aculta-text-primary', '--aculta-surface-raised'],
        ['--aculta-text-secondary', '--aculta-surface-raised'],
        ['--aculta-text-muted', '--aculta-surface-raised'],
        ['--aculta-text-accent', '--aculta-surface-raised'],
        ['--aculta-interactive-text', '--aculta-interactive-bg'],
        ['--aculta-interactive-hover-text', '--aculta-interactive-hover-bg'],
        ['--aculta-interactive-active-text', '--aculta-interactive-active-bg'],
        ['--aculta-shell-institution-text', '--aculta-shell-institution-bg'],
        ['--aculta-shell-domain-text', '--aculta-shell-domain-bg'],
      ] as [$foreground, $background]) {
        $fg = self::resolvedColor($foreground, $effective);
        $bg = self::resolvedColor($background, $effective);
        $assert($bg !== NULL && ($bg[3] ?? 1.0) === 1.0, $background . ' is opaque for ' . $mode . ' contrast evaluation.');
        $ratio = $fg !== NULL && $bg !== NULL ? self::contrast(self::composite($fg, $bg), $bg) : 0.0;
        $mode_contrast[$mode][$foreground . ' / ' . $background] = $ratio;
        $assert($ratio >= 4.5, $foreground . ' / ' . $background . ' meets WCAG AA for normal text in ' . $mode . ' mode.');
      }
    }

    $mapping_values = [
      '--bs-primary' => '--aculta-green',
      '--bs-secondary' => '--aculta-green-dark',
      '--bs-success' => '--aculta-green',
      '--bs-info' => '--aculta-green',
      '--bs-warning' => '--aculta-yellow',
      '--bs-danger' => '--aculta-red',
      '--bs-white' => '--aculta-white',
      '--bs-body-bg' => '--aculta-surface-page',
      '--bs-body-color' => '--aculta-text-primary',
      '--bs-secondary-bg' => '--aculta-surface-raised',
      '--bs-tertiary-bg' => '--aculta-surface-muted',
      '--bs-light' => '--aculta-surface-muted',
      '--bs-gray' => '--aculta-text-secondary',
      '--bs-gray-dark' => '--aculta-text-primary',
      '--bs-gray-100' => '--aculta-surface-muted',
      '--bs-gray-200' => '--aculta-surface-muted',
      '--bs-gray-300' => '--aculta-border-default',
      '--bs-gray-400' => '--aculta-border-strong',
      '--bs-gray-500' => '--aculta-text-muted',
      '--bs-gray-600' => '--aculta-text-secondary',
      '--bs-gray-700' => '--aculta-text-primary',
      '--bs-gray-800' => '--aculta-text-primary',
      '--bs-gray-900' => '--aculta-text-primary',
      '--bs-code-color' => '--aculta-text-primary',
      '--bs-highlight-color' => '--aculta-text-primary',
      '--bs-highlight-bg' => 'rgba(242, 202, 54, 0.2)',
      '--bs-heading-color' => '--aculta-text-primary',
      '--bs-secondary-color' => '--aculta-text-secondary',
      '--bs-tertiary-color' => '--aculta-text-muted',
      '--bs-emphasis-color' => '--aculta-text-primary',
      '--bs-link-color' => '--aculta-link',
      '--bs-link-hover-color' => '--aculta-text-primary',
      '--bs-focus-ring-color' => '--aculta-focus-ring',
      '--bs-form-valid-color' => '--aculta-text-primary',
      '--bs-form-valid-border-color' => '--aculta-border-accent',
      '--bs-form-invalid-color' => '--aculta-text-primary',
      '--bs-form-invalid-border-color' => '--aculta-red',
      '--bs-border-color-translucent' => 'rgba(12, 60, 41, 0.2)',
      '--bs-primary-text-emphasis' => '--aculta-text-primary',
      '--bs-secondary-text-emphasis' => '--aculta-text-primary',
      '--bs-success-text-emphasis' => '--aculta-text-primary',
      '--bs-info-text-emphasis' => '--aculta-text-primary',
      '--bs-warning-text-emphasis' => '--aculta-text-primary',
      '--bs-danger-text-emphasis' => '--aculta-text-primary',
      '--bs-light-text-emphasis' => '--aculta-text-primary',
      '--bs-dark-text-emphasis' => '--aculta-text-primary',
      '--bs-primary-bg-subtle' => '--aculta-surface-muted',
      '--bs-secondary-bg-subtle' => '--aculta-surface-muted',
      '--bs-success-bg-subtle' => '--aculta-surface-muted',
      '--bs-info-bg-subtle' => '--aculta-surface-muted',
      '--bs-warning-bg-subtle' => '--aculta-surface-muted',
      '--bs-danger-bg-subtle' => '--aculta-surface-muted',
      '--bs-light-bg-subtle' => '--aculta-surface-raised',
      '--bs-dark-bg-subtle' => '--aculta-surface-muted',
      '--bs-primary-border-subtle' => '--aculta-border-accent',
      '--bs-secondary-border-subtle' => '--aculta-border-default',
      '--bs-success-border-subtle' => '--aculta-border-accent',
      '--bs-info-border-subtle' => '--aculta-border-accent',
      '--bs-warning-border-subtle' => '--aculta-interactive-border',
      '--bs-danger-border-subtle' => '--aculta-text-accent',
      '--bs-light-border-subtle' => '--aculta-border-default',
      '--bs-dark-border-subtle' => '--aculta-border-strong',
    ];
    $bootstrap_results = [];
    foreach (['light' => $light_effective, 'dark' => $dark_effective] as $mode => $effective) {
      $mode_mappings = $mapping_values;
      $mode_mappings['--bs-dark'] = $mode === 'dark' ? '--aculta-surface-page' : '--aculta-green-dark';
      $mode_mappings['--bs-gray'] = $mode === 'dark' ? '--aculta-text-muted' : '--aculta-text-secondary';
      $mode_mappings['--bs-black'] = $mode === 'dark' ? '#000000' : '--aculta-green-dark';
      $mode_mappings['--bs-form-invalid-border-color'] = $mode === 'dark' ? '--aculta-text-accent' : '--aculta-red';
      $mode_mappings['--bs-border-color-translucent'] = $mode === 'dark'
        ? 'rgba(216, 208, 200, 0.22)'
        : 'rgba(12, 60, 41, 0.2)';
      foreach ($mode_mappings as $bootstrap_token => $source_token) {
        $actual = $resolve($bootstrap_token, $effective);
        $expected = str_starts_with($source_token, '--')
          ? $resolve($source_token, $effective)
          : ['value' => $source_token, 'error' => NULL];
        $matches = $actual['error'] === NULL && $expected['error'] === NULL
          && self::sameColor($actual['value'] ?? '', $expected['value'] ?? '');
        $bootstrap_results[$mode][$bootstrap_token] = $matches;
        $assert($matches, ucfirst($mode) . ' ' . $bootstrap_token . ' effectively maps to ' . $source_token . '.');
      }
      $mode_rgb_mappings = self::RGB_MAPPINGS;
      $mode_rgb_mappings['--bs-dark-rgb'] = $mode === 'dark' ? '--aculta-surface-page' : '--aculta-green-dark';
      foreach ($mode_rgb_mappings as $rgb_token => $source_token) {
        $actual = $resolve($rgb_token, $effective);
        $expected_color = self::resolvedColor($source_token, $effective);
        $actual_rgb = $actual['error'] === NULL ? self::parseRgbTriplet($actual['value'] ?? '') : NULL;
        $expected_rgb = $expected_color !== NULL ? array_slice($expected_color, 0, 3) : NULL;
        $matches = $actual_rgb !== NULL && $expected_rgb !== NULL && $actual_rgb == $expected_rgb;
        $bootstrap_results[$mode][$rgb_token] = $matches;
        $assert($matches, ucfirst($mode) . ' ' . $rgb_token . ' matches ' . $source_token . '.');
      }
      $border = $resolve('--bs-border-color', $effective);
      $border_source = $resolve('--aculta-border-default', $effective);
      $matches = $border['error'] === NULL && $border_source['error'] === NULL
        && self::normalizeColorValue($border['value'] ?? '') === self::normalizeColorValue($border_source['value'] ?? '');
      $bootstrap_results[$mode]['--bs-border-color'] = $matches;
      $assert($matches, ucfirst($mode) . ' --bs-border-color maps to --aculta-border-default.');
    }

    return [
      'assertions' => $assertions,
      'dark_contrast' => $dark_contrast,
      'focus_contrast' => $focus_contrast,
      'mode_contrast' => $mode_contrast,
      'bootstrap' => $bootstrap_results,
    ];
  }

  /** Count prohibited color-mode selectors, including inside tokens.css. */
  public static function countModeSelectors(string $css, bool $tokens_file = FALSE): int {
    [$rules] = self::parseCssRules(self::stripCssComments($css));
    $count = 0;
    $walk = static function (array $nodes, bool $top_level = TRUE) use (&$walk, &$count, $tokens_file): void {
      foreach ($nodes as $node) {
        if ($tokens_file && $top_level && self::isAutoModeBlock($node)) {
          continue;
        }
        if (self::containsModeSelector($node['selector'])) {
          $selector = self::normalizeSelector($node['selector']);
          $allowed = $tokens_file && $top_level
            && in_array($selector, [self::normalizeSelector(self::LIGHT_SELECTOR), self::normalizeSelector(self::DARK_SELECTOR)], TRUE)
            && $node['children'] === []
            && self::onlyCustomPropertyDeclarations($node['body']);
          if (!$allowed) {
            $count++;
          }
        }
        $walk($node['children'], FALSE);
      }
    };
    $walk($rules);
    return $count;
  }

  /** Detect CSS color literals outside the token stylesheet. */
  public static function hasRawColorLiteral(string $css): bool {
    $clean = self::stripCssComments($css);
    if (preg_match('/#[0-9a-f]{3,8}\\b|\\b(?:rgb|rgba|hsl|hsla|hwb|lab|lch|oklab|oklch|color|color-mix|device-cmyk)\\s*\\(/i', $clean) === 1) {
      return TRUE;
    }
    [$rules] = self::parseCssRules($clean);
    $color_properties = [
      'color', 'background', 'background-color', 'background-image', 'border', 'border-top', 'border-right',
      'border-bottom', 'border-left', 'border-color', 'border-top-color', 'border-right-color',
      'border-bottom-color', 'border-left-color', 'outline', 'outline-color',
      'text-decoration-color', 'text-emphasis-color', 'column-rule-color', 'caret-color',
      'accent-color', 'fill', 'stroke', 'box-shadow', 'text-shadow',
    ];
    $contains_named_color = static function (array $nodes) use (&$contains_named_color, $color_properties): bool {
      foreach ($nodes as $node) {
        foreach (self::parseDeclarations($node['body']) as $declaration) {
          if (in_array(strtolower($declaration['name']), $color_properties, TRUE)
            && preg_match('/(?<![-\\w])(?:' . implode('|', self::CSS_NAMED_COLORS) . ')(?![-\\w])/i', $declaration['value']) === 1) {
            return TRUE;
          }
        }
        if ($contains_named_color($node['children'])) {
          return TRUE;
        }
      }
      return FALSE;
    };
    return $contains_named_color($rules);
  }

  public static function countTwigModeBranches(string $source): int {
    $source = preg_replace('/\{#.*?#\}/s', '', $source) ?? $source;
    preg_match_all('/\{\{(.*?)\}\}|\{%([^%]*?)%\}/s', $source, $tags, PREG_SET_ORDER);
    $count = 0;
    foreach ($tags as $tag) {
      $expression = trim(($tag[1] ?? '') !== '' ? $tag[1] : ($tag[2] ?? ''));
      $expression = preg_replace('/^-\\s*(?=(?:if|elseif|set)\\b)/i', '', $expression) ?? $expression;
      $expression = preg_replace('/\\s+-$/', '', $expression) ?? $expression;
      $is_branch = preg_match('/^(?:if|elseif)\b/i', $expression) === 1;
      $is_ternary = str_contains($expression, '?') && str_contains($expression, ':');
      $decisions = $is_ternary ? self::collectTernaryPredicates($expression) : [$expression];
      if ($is_branch && $is_ternary) {
        // Keep any mode-dependent predicate surrounding a nested ternary.
        $decisions[] = self::stripTernaryResultArms($expression);
      }
      $has_mode_decision = FALSE;
      foreach ($decisions as $decision) {
        if (self::hasModeDecision($decision)) {
          $has_mode_decision = TRUE;
          break;
        }
      }
      if (($is_branch || $is_ternary) && $has_mode_decision) {
        $count++;
      }
    }
    return $count;
  }

  public static function countTwigEmbeddedJsModeBranches(string $source): int {
    preg_match_all('/<script\b[^>]*>(.*?)<\/script\s*>/is', $source, $scripts);
    $count = 0;
    foreach ($scripts[1] ?? [] as $script) {
      $count += self::countJsModeBranches($script);
    }
    return $count;
  }

  public static function countTwigInlineCssModeSelectors(string $source): int {
    preg_match_all('/<style\b[^>]*>(.*?)<\/style\s*>/is', $source, $styles);
    $count = 0;
    foreach ($styles[1] ?? [] as $style) {
      $count += self::countModeSelectors($style);
    }
    return $count;
  }

  public static function hasTwigInlineRawColorLiteral(string $source): bool {
    preg_match_all('/<style\\b[^>]*>(.*?)<\\/style\\s*>/is', $source, $styles);
    foreach ($styles[1] ?? [] as $style) {
      if (self::hasRawColorLiteral($style)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public static function hasTwigEmbeddedPrematureColorModeScript(string $source): bool {
    preg_match_all('/<script\b[^>]*>(.*?)<\/script\s*>/is', $source, $scripts);
    foreach ($scripts[1] ?? [] as $script) {
      if (self::hasPrematureColorModeScript($script)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public static function countPhpModeBranches(string $source): int {
    $tokens = token_get_all($source);
    $count = 0;
    foreach ($tokens as $index => $token) {
      if (!is_array($token) || !in_array($token[0], [T_IF, T_ELSEIF, T_SWITCH, T_MATCH], TRUE)) {
        continue;
      }
      [$condition, $after_condition] = self::readPhpParenthesized($tokens, $index + 1);
      $expression = $condition;
      if ($token[0] === T_SWITCH) {
        $expression .= ' ' . self::readPhpDecisionArms($tokens, $after_condition, TRUE);
      }
      elseif ($token[0] === T_MATCH) {
        $expression .= ' ' . self::readPhpMatchConditions($tokens, $after_condition);
      }
      if (self::hasModeDecision($expression)) {
        $count++;
      }
    }
    return $count;
  }

  public static function countJsModeBranches(string $source): int {
    $source = self::stripJsComments($source);
    $count = 0;
    preg_match_all('/\bif\s*\(/s', $source, $ifs, PREG_OFFSET_CAPTURE);
    foreach ($ifs[0] ?? [] as [$match_text, $match_offset]) {
      $open = $match_offset + strlen($match_text) - 1;
      [$condition] = self::readBalancedJsParentheses($source, $open);
      if (self::hasModeDecision($condition)) {
        $count++;
      }
    }
    preg_match_all('/\bswitch\s*\(/s', $source, $switches, PREG_OFFSET_CAPTURE);
    foreach ($switches[0] as [$switch_text, $switch_offset]) {
      $open = $switch_offset + strlen($switch_text) - 1;
      [$discriminant, $after_discriminant] = self::readBalancedJsParentheses($source, $open);
      $brace = strpos($source, '{', $after_discriminant);
      if ($brace === FALSE) {
        continue;
      }
      $body_start = $brace + 1;
      $body = self::readBalancedJsBlock($source, $body_start);
      if (self::hasModeDecision($discriminant . ' ' . self::readJsCaseExpressions($body))) {
        $count++;
      }
    }
    // Keep object-literal arms and semicolons inside strings/templates intact.
    $statements = self::splitJsTopLevelStatements($source);
    foreach ($statements as $statement) {
      foreach (self::collectTernaryPredicates($statement) as $predicate) {
        if (self::hasModeDecision($predicate)) {
          $count++;
        }
      }
    }
    if (preg_match('/\.dataset(?:\s*\.\s*(?:theme|bsTheme|colorMode|colorScheme)|\s*\[\s*["\'](?:theme|bsTheme|colorMode|colorScheme)["\']\s*\])\s*(?:\?\?=|\|\|=|&&=|[+*\/%&|^\-]?=(?!=|>))/i', $source) === 1
      || preg_match('/setAttribute\s*(?:\?\.)?\s*\(\s*["\']data-(?:(?:bs-)?theme|color-mode|color-scheme)["\']\s*,/i', $source) === 1
      || preg_match('/classList\s*\.\s*(?:add|toggle|remove|replace)\s*\([^)]*["\'](?:dark|light|dark-theme|light-theme|theme-dark|theme-light|dark-mode|light-mode|is-dark|is-light|color-mode-dark|color-mode-light)["\']/i', $source) === 1
      || preg_match('/classList\s*\.\s*(?:add|toggle|remove|replace)\s*\([^)]*(?:getTheme|getColorMode|getColorScheme|\btheme\b|\bcolor(?:Mode|Scheme|_mode|_scheme)\b)/i', $source) === 1) {
      $count++;
    }
    preg_match_all('/\.className\s*(?:\?\?=|\|\|=|&&=|[+*\/%&|^\-]?=(?!=|>))\s*(["\'])(.*?)\1/s', $source, $class_assignments);
    $class_values = $class_assignments[2] ?? [];
    preg_match_all('/\.className\s*(?:\?\?=|\|\|=|&&=|[+*\/%&|^\-]?=(?!=|>))\s*`([^`]*)`/s', $source, $template_class_assignments);
    $class_values = array_merge($class_values, $template_class_assignments[1] ?? []);
    foreach ($class_values as $class_value) {
      if (self::containsModeClass($class_value)) {
        $count++;
        break;
      }
    }
    if (preg_match('/\.className\s*(?:\?\?=|\|\|=|&&=|[+*\/%&|^\-]?=(?!=|>))\s*[^;]*(?:getTheme|getColorMode|getColorScheme|\btheme\b|\bcolor(?:Mode|Scheme|_mode|_scheme)\b)/i', $source) === 1) {
      $count++;
    }
    return $count;
  }

  /** Split JavaScript at statement-level semicolons only. */
  private static function splitJsTopLevelStatements(string $source): array {
    $statements = [];
    $start = 0;
    $quote = NULL;
    $paren = $bracket = $brace = 0;
    $length = strlen($source);
    for ($i = 0; $i < $length; $i++) {
      $char = $source[$i];
      if ($quote !== NULL) {
        if ($char === '\\') { $i++; }
        elseif ($char === $quote) { $quote = NULL; }
        continue;
      }
      if ($char === '"' || $char === "'" || $char === '`') { $quote = $char; continue; }
      if ($char === '/' && self::isJsRegexStart($source, $i)) { $i = self::skipJsRegexLiteral($source, $i); continue; }
      if ($char === '(') { $paren++; continue; }
      if ($char === ')') { $paren = max(0, $paren - 1); continue; }
      if ($char === '[') { $bracket++; continue; }
      if ($char === ']') { $bracket = max(0, $bracket - 1); continue; }
      if ($char === '{') { $brace++; continue; }
      if ($char === '}') { $brace = max(0, $brace - 1); continue; }
      if ($char === ';' && $paren === 0 && $bracket === 0 && $brace === 0) {
        $statement = trim(substr($source, $start, $i - $start));
        if ($statement !== '') { $statements[] = $statement; }
        $start = $i + 1;
      }
    }
    $tail = trim(substr($source, $start));
    if ($tail !== '') { $statements[] = $tail; }
    return $statements === [] ? [$source] : $statements;
  }

  public static function hasPrematureColorModeScript(string $source): bool {
    $source = self::stripJsComments($source);
    if (preg_match('/prefers-color-scheme|setAttribute\s*(?:\?\.)?\s*\(\s*[\'\"]data-(?:(?:bs-)?theme|color-mode|color-scheme)/i', $source) === 1) {
      return TRUE;
    }
    preg_match_all('/\b(?:localStorage|sessionStorage)\s*\.\s*(?:getItem|setItem|removeItem)\s*\(([^;]*)/i', $source, $storage_calls);
    foreach ($storage_calls[1] ?? [] as $arguments) {
      if (preg_match('/(?:theme|color[-_ ]?(?:mode|scheme)|getTheme|getColorMode|getColorScheme)/i', $arguments) === 1) {
        return TRUE;
      }
    }
    if (preg_match('/\b(?:localStorage|sessionStorage)(?:\s*\.\s*(?:theme|bsTheme|colorMode|colorScheme)|\s*\[\s*["\'](?:theme|bsTheme|colorMode|colorScheme|color-mode|color-scheme)["\']\s*\])/i', $source) === 1) {
      return TRUE;
    }
    if (preg_match('/\b(?:apply|set|init(?:ialize)?)(?:Theme|ColorMode|ColorScheme)\s*\(/i', $source) === 1) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Bloco automático permitido em tokens.css: @media (prefers-color-scheme: dark) com um único
   * filho :root:not([data-bs-theme="light"]), sem aninhamento. Tem de espelhar o bloco escuro.
   */
  public static function isAutoModeBlock(array $node): bool {
    if (preg_match('/^@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)$/i', trim($node['selector'])) !== 1) {
      return FALSE;
    }
    $children = $node['children'];
    return count($children) === 1
      && self::normalizeSelector($children[0]['selector']) === self::normalizeSelector(':root:not([data-bs-theme="light"])')
      && $children[0]['children'] === [];
  }

  private static function normalizeSelector(string $selector): string {
    return strtolower(preg_replace('/\s+/', '', trim($selector)) ?? trim($selector));
  }

  private static function stripCssComments(string $source): string {
    $output = '';
    $quote = NULL;
    $length = strlen($source);
    for ($i = 0; $i < $length; $i++) {
      $char = $source[$i];
      if ($quote !== NULL) {
        $output .= $char;
        if ($char === '\\') {
          if (isset($source[$i + 1])) {
            $output .= $source[++$i];
          }
        }
        elseif ($char === $quote) {
          $quote = NULL;
        }
        continue;
      }
      if ($char === '"' || $char === "'") {
        $quote = $char;
        $output .= $char;
        continue;
      }
      if ($char === '/' && ($source[$i + 1] ?? '') === '*') {
        $output .= '  ';
        $i += 2;
        while ($i < $length) {
          if ($source[$i] === '*' && ($source[$i + 1] ?? '') === '/') {
            $output .= '  ';
            $i++;
            break;
          }
          $output .= ($source[$i] === "\r" || $source[$i] === "\n") ? $source[$i] : ' ';
          $i++;
        }
        continue;
      }
      $output .= $char;
    }
    return $output;
  }

  /** Parse nested CSS blocks while respecting strings, comments and parens. */
  private static function parseCssRules(string $css): array {
    $rules = [];
    $errors = [];
    $length = strlen($css);
    $cursor = 0;
    while ($cursor < $length) {
      while ($cursor < $length && (ctype_space($css[$cursor]) || $css[$cursor] === ';')) {
        $cursor++;
      }
      if ($cursor >= $length) {
        break;
      }
      $start = $cursor;
      $quote = NULL;
      $paren_depth = 0;
      while ($cursor < $length) {
        $char = $css[$cursor];
        if ($quote !== NULL) {
          if ($char === '\\') {
            $cursor += 2;
            continue;
          }
          if ($char === $quote) {
            $quote = NULL;
          }
        }
        elseif ($char === '"' || $char === "'") {
          $quote = $char;
        }
        elseif ($char === '(') {
          $paren_depth++;
        }
        elseif ($char === ')') {
          $paren_depth = max(0, $paren_depth - 1);
        }
        elseif ($paren_depth === 0 && $char === ';') {
          $statement = trim(substr($css, $start, $cursor - $start));
          if ($statement !== '') {
            $errors[] = 'unsupported statement outside a rule block';
          }
          $cursor++;
          continue 2;
        }
        elseif ($paren_depth === 0 && $char === '{') {
          break;
        }
        $cursor++;
      }
      if ($cursor >= $length || $css[$cursor] !== '{') {
        break;
      }
      $selector = trim(substr($css, $start, $cursor - $start));
      $body_start = ++$cursor;
      $depth = 1;
      $quote = NULL;
      while ($cursor < $length && $depth > 0) {
        $char = $css[$cursor];
        if ($quote !== NULL) {
          if ($char === '\\') {
            $cursor += 2;
            continue;
          }
          if ($char === $quote) {
            $quote = NULL;
          }
        }
        elseif ($char === '"' || $char === "'") {
          $quote = $char;
        }
        elseif ($char === '{') {
          $depth++;
        }
        elseif ($char === '}') {
          $depth--;
        }
        $cursor++;
      }
      if ($depth !== 0) {
        $errors[] = 'unclosed block for ' . ($selector !== '' ? $selector : 'selector');
        break;
      }
      $body = substr($css, $body_start, $cursor - $body_start - 1);
      if (str_contains($body, '{')) {
        [$children, $child_errors] = self::parseCssRules($body);
      }
      else {
        $children = [];
        $child_errors = [];
      }
      $errors = array_merge($errors, $child_errors);
      $rules[] = ['selector' => $selector, 'body' => $body, 'children' => $children];
    }
    return [$rules, $errors];
  }

  private static function parseDeclarations(string $body): array {
    $declarations = [];
    foreach (self::splitCss($body, ';') as $part) {
      $part = trim($part);
      if ($part === '') {
        continue;
      }
      $colon = strpos($part, ':');
      if ($colon === FALSE) {
        $declarations[] = ['name' => '', 'value' => $part];
        continue;
      }
      $declarations[] = ['name' => trim(substr($part, 0, $colon)), 'value' => trim(substr($part, $colon + 1))];
    }
    return $declarations;
  }

  private static function splitCss(string $input, string $separator): array {
    $parts = [];
    $start = 0;
    $depth = 0;
    $quote = NULL;
    $length = strlen($input);
    for ($i = 0; $i < $length; $i++) {
      $char = $input[$i];
      if ($quote !== NULL) {
        if ($char === '\\') {
          $i++;
        }
        elseif ($char === $quote) {
          $quote = NULL;
        }
      }
      elseif ($char === '"' || $char === "'") {
        $quote = $char;
      }
      elseif ($char === '(') {
        $depth++;
      }
      elseif ($char === ')') {
        $depth = max(0, $depth - 1);
      }
      elseif ($char === $separator && $depth === 0) {
        $parts[] = substr($input, $start, $i - $start);
        $start = $i + 1;
      }
    }
    $parts[] = substr($input, $start);
    return $parts;
  }

  private static function onlyCustomPropertyDeclarations(string $body): bool {
    foreach (self::parseDeclarations($body) as $declaration) {
      if (preg_match('/^--[a-zA-Z0-9_-]+$/', $declaration['name']) !== 1 || $declaration['value'] === '') {
        return FALSE;
      }
    }
    return TRUE;
  }

  private static function containsModeSelector(string $selector): bool {
    if (preg_match('/prefers-color-scheme\s*:\s*(?:dark|light)/i', $selector) === 1
      || self::containsModeClass($selector)) {
      return TRUE;
    }
    preg_match_all('/\[\s*data-(?:(?:bs-)?theme|color-mode|color-scheme)\s*(~=|\|=|\^=|\$=|\*=|=)\s*(?:(["\'])(.*?)\2|([^\]\s]+))(?:\s+([is]))?\s*\]/i', $selector, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $operator = $match[1];
      $value = ($match[3] ?? '') !== '' ? $match[3] : ($match[4] ?? '');
      $insensitive = strtolower($match[5] ?? '') === 'i';
      foreach (['dark', 'light'] as $mode) {
        $candidate = $insensitive ? strtolower($value) : $value;
        $target = $insensitive ? $mode : $mode;
        $matches_mode = match ($operator) {
          '=' , '~=' => $candidate === $target,
          '|=' => $candidate === $target || str_starts_with($candidate, $target . '-'),
          '^=' => str_starts_with($target, $candidate),
          '$=' => str_ends_with($target, $candidate),
          '*=' => $candidate !== '' && str_contains($target, $candidate),
          default => FALSE,
        };
        if ($matches_mode) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  private static function resolveToken(string $name, array $effective, array $stack = []): array {
    if (in_array($name, $stack, TRUE)) {
      return ['value' => NULL, 'error' => 'circular token reference'];
    }
    if (!array_key_exists($name, $effective)) {
      return ['value' => NULL, 'error' => 'unresolved token reference'];
    }
    $stack[] = $name;
    $error = NULL;
    $resolved = preg_replace_callback('/var\(\s*(--[a-zA-Z0-9_-]+)\s*\)/i', static function (array $match) use (&$error, $effective, $stack): string {
      $result = self::resolveToken($match[1], $effective, $stack);
      if ($result['error'] !== NULL) {
        $error = $result['error'];
        return '';
      }
      return (string) $result['value'];
    }, trim($effective[$name]));
    if ($error !== NULL || preg_match('/var\s*\(/i', (string) $resolved) === 1) {
      return ['value' => NULL, 'error' => $error ?? 'unsupported or unresolved var() syntax'];
    }
    return ['value' => trim((string) $resolved), 'error' => NULL];
  }

  private static function parseColor(string $value): ?array {
    $value = trim(strtolower($value));
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $value, $match) === 1) {
      $hex = $match[1];
      if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
      }
      return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), 1.0];
    }
    if ($value === 'black') {
      return [0, 0, 0, 1.0];
    }
    if ($value === 'white') {
      return [255, 255, 255, 1.0];
    }
    if ($value === 'transparent') {
      return [0, 0, 0, 0.0];
    }
    if (preg_match('/^rgba?\(([^)]+)\)$/', $value, $match) === 1) {
      $body = trim($match[1]);
      $has_commas = str_contains($body, ',');
      $has_slash = str_contains($body, '/');
      if ($has_commas && $has_slash) {
        return NULL;
      }
      if ($has_commas) {
        $channels = preg_split('/\s*,\s*/', $body) ?: [];
      }
      else {
        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)(?:\s*\/\s*(\d+(?:\.\d+)?))?\s*$/', $body, $modern) !== 1) {
          return NULL;
        }
        $channels = array_values(array_filter(array_slice($modern, 1), static fn (string $channel): bool => $channel !== ''));
      }
      if (count($channels) < 3 || count($channels) > 4) {
        return NULL;
      }
      $rgb = [];
      foreach (array_slice($channels, 0, 3) as $channel) {
        if (preg_match('/^\d+(?:\.\d+)?$/', $channel) !== 1 || (float) $channel < 0 || (float) $channel > 255) {
          return NULL;
        }
        $rgb[] = (float) $channel;
      }
        if (isset($channels[3]) && !is_numeric($channels[3])) {
          return NULL;
        }
        $alpha = isset($channels[3]) ? (float) $channels[3] : 1.0;
      if ($alpha < 0 || $alpha > 1) {
        return NULL;
      }
      return [$rgb[0], $rgb[1], $rgb[2], $alpha];
    }
    return NULL;
  }

  private static function resolvedColor(string $token, array $effective): ?array {
    $resolved = self::resolveToken($token, $effective);
    return $resolved['error'] === NULL ? self::parseColor((string) $resolved['value']) : NULL;
  }

  private static function sameColor(string $first, string $second): bool {
    $a = self::parseColor($first);
    $b = self::parseColor($second);
    if ($a === NULL || $b === NULL) {
      return FALSE;
    }
    foreach ($a as $index => $channel) {
      if (abs((float) $channel - (float) $b[$index]) > 0.000001) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private static function normalizeColorValue(string $value): string {
    $color = self::parseColor($value);
    return $color === NULL ? strtolower(preg_replace('/\s+/', '', trim($value)) ?? trim($value)) : implode(',', $color);
  }

  private static function parseRgbTriplet(string $value): ?array {
    $parts = preg_split('/\s*,\s*/', trim($value)) ?: [];
    if (count($parts) !== 3) {
      return NULL;
    }
    $channels = [];
    foreach ($parts as $part) {
      if (preg_match('/^\d+(?:\.\d+)?$/', $part) !== 1 || (float) $part < 0 || (float) $part > 255) {
        return NULL;
      }
      $channels[] = (float) $part;
    }
    return $channels;
  }

  private static function contrast(array $foreground, array $background): float {
    $luminance = static function (array $color): float {
      $channels = array_map(static function (float $channel): float {
        $value = $channel / 255;
        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
      }, array_slice($color, 0, 3));
      return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    };
    $first = $luminance($foreground);
    $second = $luminance($background);
    return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
  }

  /** Composite a translucent foreground over its opaque contrast surface. */
  private static function composite(array $foreground, array $background): array {
    $alpha = $foreground[3] ?? 1.0;
    return [
      $foreground[0] * $alpha + $background[0] * (1 - $alpha),
      $foreground[1] * $alpha + $background[1] * (1 - $alpha),
      $foreground[2] * $alpha + $background[2] * (1 - $alpha),
      1.0,
    ];
  }

  /**
   * Extract nested ternary predicates without inspecting result expressions.
   *
   * This is a bounded expression scanner, not a full Twig/JavaScript parser.
   */
  private static function collectTernaryPredicates(string $expression): array {
    $length = strlen($expression);
    $quote = NULL;
    $stack = [];
    $question = NULL;
    $question_depth = NULL;
    $question_stack = [];
    $paren = $bracket = $brace = 0;
    for ($i = 0; $i < $length; $i++) {
      $char = $expression[$i];
      if ($quote !== NULL) {
        if ($char === '\\') { $i++; }
        elseif ($char === $quote) { $quote = NULL; }
        continue;
      }
      if ($char === '"' || $char === "'") {
        $quote = $char;
        continue;
      }
      if ($char === '(' || $char === '[' || $char === '{') {
        $stack[] = [$char, $i];
        if ($char === '(') { $paren++; }
        elseif ($char === '[') { $bracket++; }
        else { $brace++; }
        continue;
      }
      if ($char === ')' || $char === ']' || $char === '}') {
        array_pop($stack);
        if ($char === ')') { $paren--; }
        elseif ($char === ']') { $bracket--; }
        else { $brace--; }
        continue;
      }
      if ($char === '?' && ($expression[$i + 1] ?? '') !== '?'
        && ($expression[$i + 1] ?? '') !== '.'
        && ($expression[$i - 1] ?? '') !== '?') {
        $question = $i;
        $question_depth = [$paren, $bracket, $brace];
        $question_stack = $stack;
        break;
      }
    }
    if ($question === NULL || $question_depth === NULL) {
      return [];
    }

    [$target_paren, $target_bracket, $target_brace] = $question_depth;
    $paren = $target_paren;
    $bracket = $target_bracket;
    $brace = $target_brace;
    $nested_questions = 0;
    $colon = NULL;
    $quote = NULL;
    for ($i = $question + 1; $i < $length; $i++) {
      $char = $expression[$i];
      if ($quote !== NULL) {
        if ($char === '\\') { $i++; }
        elseif ($char === $quote) { $quote = NULL; }
        continue;
      }
      if ($char === '"' || $char === "'") { $quote = $char; continue; }
      if ($char === '(') { $paren++; continue; }
      if ($char === ')') { $paren--; continue; }
      if ($char === '[') { $bracket++; continue; }
      if ($char === ']') { $bracket--; continue; }
      if ($char === '{') { $brace++; continue; }
      if ($char === '}') { $brace--; continue; }
      if ([$paren, $bracket, $brace] !== $question_depth) {
        continue;
      }
      if ($char === '?' && ($expression[$i + 1] ?? '') !== '?'
        && ($expression[$i + 1] ?? '') !== '.') {
        $nested_questions++;
      }
      elseif ($char === ':') {
        if ($nested_questions > 0) {
          $nested_questions--;
        }
        else {
          $colon = $i;
          break;
        }
      }
    }
    if ($colon === NULL) {
      return [];
    }

    $start = $question_stack === [] ? 0 : $question_stack[array_key_last($question_stack)][1] + 1;
    $predicates = [trim(substr($expression, $start, $question - $start))];
    $predicates = array_merge(
      $predicates,
      self::collectTernaryPredicates(substr($expression, $question + 1, $colon - $question - 1)),
      self::collectTernaryPredicates(substr($expression, $colon + 1)),
    );
    return $predicates;
  }

  private static function hasModeDecision(string $expression): bool {
    [$code, $strings] = self::extractQuotedStrings($expression);
    $mode_reference = preg_match('/\$?\b(?:theme|mode|colorMode|color_mode|color-mode|colorScheme|color_scheme|color-scheme|getTheme|getColorMode|getColorScheme)\b/i', $code) === 1
      || preg_match('/\[\s*["\']aculta_(?:color_mode|color_scheme)["\']\s*\]/i', $expression) === 1
      || preg_match('/dataset(?:\s*\.\s*(?:theme|bsTheme|colorMode|colorScheme)|\s*\[\s*["\'](?:theme|bsTheme|colorMode|colorScheme)["\']\s*\])/i', $expression) === 1
      || preg_match('/getAttribute\s*(?:\?\.)?\s*\(\s*["\']data-(?:(?:bs-)?theme|color-mode|color-scheme)["\']/i', $expression) === 1;
    $mode_literal = preg_match('/\b(?:dark|light)\b/i', $code) === 1;
    foreach ($strings as $string) {
      if (preg_match('/^(?:dark|light)$/i', trim($string)) === 1) {
        $mode_literal = TRUE;
        break;
      }
    }
    $boolean_mode = preg_match('/\$?\b(?:is[_-]?(?:dark|light)(?:[_-]?mode)?|(?:dark|light)[_-]?mode)\b/i', $code) === 1;
    return ($mode_reference && $mode_literal) || $boolean_mode;
  }

  /** Return code with quoted strings blanked and the decoded text values. */
  private static function extractQuotedStrings(string $source): array {
    $code = '';
    $strings = [];
    $length = strlen($source);
    for ($i = 0; $i < $length; $i++) {
      $quote = $source[$i];
      if (!in_array($quote, ["'", '"', '`'], TRUE)) {
        $code .= $quote;
        continue;
      }
      $value = '';
      for ($i++; $i < $length; $i++) {
        if ($source[$i] === '\\') {
          $value .= $source[++$i] ?? '';
        }
        elseif ($source[$i] === $quote) {
          break;
        }
        else {
          $value .= $source[$i];
        }
      }
      $strings[] = $value;
      $code .= ' ';
    }
    return [$code, $strings];
  }

  private static function firstTernaryPrefix(string $expression): string {
    return self::stripTernaryResultArms($expression);
  }

  /** Remove ternary result arms while retaining surrounding predicates. */
  private static function stripTernaryResultArms(string $expression): string {
    for ($attempt = 0; $attempt < 32; $attempt++) {
      $question = self::findFirstTernaryQuestion($expression);
      if ($question === NULL) { break; }
      $quote = NULL;
      $paren = $bracket = $brace = 0;
      $length = strlen($expression);
      for ($i = 0; $i < $question; $i++) {
        $char = $expression[$i];
        if ($quote !== NULL) {
          if ($char === '\\') { $i++; }
          elseif ($char === $quote) { $quote = NULL; }
          continue;
        }
        if ($char === '"' || $char === "'") { $quote = $char; continue; }
        if ($char === '(') { $paren++; }
        elseif ($char === '[') { $bracket++; }
        elseif ($char === '{') { $brace++; }
        elseif ($char === ')') { $paren--; }
        elseif ($char === ']') { $bracket--; }
        elseif ($char === '}') { $brace--; }
      }
      $target = [$paren, $bracket, $brace];
      $nested_questions = 0;
      $colon = NULL;
      $quote = NULL;
      for ($i = $question + 1; $i < $length; $i++) {
        $char = $expression[$i];
        if ($quote !== NULL) {
          if ($char === '\\') { $i++; }
          elseif ($char === $quote) { $quote = NULL; }
          continue;
        }
        if ($char === '"' || $char === "'") { $quote = $char; continue; }
        if ($char === '(') { $paren++; continue; }
        if ($char === '[') { $bracket++; continue; }
        if ($char === '{') { $brace++; continue; }
        if ($char === ')' || $char === ']' || $char === '}') {
          if ([$paren, $bracket, $brace] === $target) { break; }
          if ($char === ')') { $paren--; }
          elseif ($char === ']') { $bracket--; }
          else { $brace--; }
          continue;
        }
        if ([$paren, $bracket, $brace] !== $target) { continue; }
        if ($char === '?' && ($expression[$i + 1] ?? '') !== '?' && ($expression[$i + 1] ?? '') !== '.' && ($expression[$i - 1] ?? '') !== '?') {
          $nested_questions++;
        }
        elseif ($char === ':') {
          if ($nested_questions > 0) { $nested_questions--; }
          else { $colon = $i; break; }
        }
      }
      if ($colon === NULL) { break; }
      $false_end = $length;
      $paren = $target[0];
      $bracket = $target[1];
      $brace = $target[2];
      $quote = NULL;
      for ($i = $colon + 1; $i < $length; $i++) {
        $char = $expression[$i];
        if ($quote !== NULL) {
          if ($char === '\\') { $i++; }
          elseif ($char === $quote) { $quote = NULL; }
          continue;
        }
        if ($char === '"' || $char === "'") { $quote = $char; continue; }
        if ($char === '(') { $paren++; continue; }
        if ($char === '[') { $bracket++; continue; }
        if ($char === '{') { $brace++; continue; }
        if ($char === ')' || $char === ']' || $char === '}') {
          if ([$paren, $bracket, $brace] === $target) { $false_end = $i; break; }
          if ($char === ')') { $paren--; }
          elseif ($char === ']') { $bracket--; }
          else { $brace--; }
          continue;
        }
        if ([$paren, $bracket, $brace] === $target && ($char === ',' || $char === ';')) {
          $false_end = $i;
          break;
        }
      }
      $expression = substr($expression, 0, $question) . substr($expression, $false_end);
    }
    return $expression;
  }

  private static function findFirstTernaryQuestion(string $expression): ?int {
    $quote = NULL;
    $length = strlen($expression);
    for ($i = 0; $i < $length; $i++) {
      $char = $expression[$i];
      if ($quote !== NULL) {
        if ($char === '\\') { $i++; }
        elseif ($char === $quote) { $quote = NULL; }
        continue;
      }
      if ($char === '"' || $char === "'") { $quote = $char; continue; }
      if ($char === '?' && ($expression[$i + 1] ?? '') !== '?' && ($expression[$i + 1] ?? '') !== '.' && ($expression[$i - 1] ?? '') !== '?') {
        return $i;
      }
    }
    return NULL;
  }

  private static function containsModeClass(string $value): bool {
    $classes = implode('|', array_map(static fn (string $class): string => preg_quote($class, '/'), self::MODE_CLASSES));
    return preg_match('/(?:^|[\s.])(?:' . $classes . ')(?=$|[\s.#:\[\]),])/i', $value) === 1;
  }

  private static function readPhpParenthesized(array $tokens, int $index): array {
    $condition = '';
    $depth = 0;
    $started = FALSE;
    for ($i = $index; isset($tokens[$i]); $i++) {
      if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_COMMENT, T_DOC_COMMENT], TRUE)) {
        continue;
      }
      $part = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
      if (!$started && $part !== '(') {
        continue;
      }
      if ($part === '(') {
        $depth++;
        $started = TRUE;
        if ($depth === 1) {
          continue;
        }
      }
      elseif ($part === ')') {
        $depth--;
        if ($depth === 0) {
          return [$condition, $i + 1];
        }
      }
      $condition .= $part;
    }
    return [$condition, count($tokens)];
  }

  private static function readPhpDecisionArms(array $tokens, int $index, bool $switch = FALSE): string {
    if ($switch) {
      return self::readPhpSwitchCaseLabels($tokens, $index);
    }
    $depth = 0;
    $started = FALSE;
    $body = '';
    for ($i = $index; isset($tokens[$i]); $i++) {
      if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_COMMENT, T_DOC_COMMENT], TRUE)) {
        continue;
      }
      $part = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
      if (!$started && $part !== '{' && !($switch && $part === ':')) {
        continue;
      }
      if ($part === '{' || ($switch && !$started && $part === ':')) {
        $depth++;
        $started = TRUE;
      }
      elseif ($part === '}') {
        $depth--;
        if ($started && $depth === 0) {
          return $body;
        }
      }
      elseif ($switch && is_array($tokens[$i]) && $tokens[$i][0] === T_ENDSWITCH) {
        return $body;
      }
      $body .= $part;
    }
    return $body;
  }

  /** Read only PHP match conditions before =>, excluding result expressions. */
  private static function readPhpMatchConditions(array $tokens, int $index): string {
    $started = FALSE;
    $brace = 0;
    $paren = $bracket = 0;
    $in_result = FALSE;
    $candidate = $conditions = '';
    for ($i = $index; isset($tokens[$i]); $i++) {
      if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_COMMENT, T_DOC_COMMENT], TRUE)) {
        continue;
      }
      $token_id = is_array($tokens[$i]) ? $tokens[$i][0] : NULL;
      $part = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
      if (!$started) {
        if ($part === '{') { $started = TRUE; $brace = 1; }
        continue;
      }
      if ($part === '}' && $paren === 0 && $bracket === 0) {
        $brace--;
        if ($brace === 0) { return $conditions . ' ' . $candidate; }
      }
      elseif ($part === '{') { $brace++; }
      elseif ($part === '(') { $paren++; }
      elseif ($part === ')') { $paren--; }
      elseif ($part === '[') { $bracket++; }
      elseif ($part === ']') { $bracket--; }
      if ($brace !== 1 || $paren !== 0 || $bracket !== 0) {
        if (!$in_result) { $candidate .= $part; }
        continue;
      }
      if ($token_id === T_DOUBLE_ARROW) {
        $conditions .= ' ' . $candidate;
        $candidate = '';
        $in_result = TRUE;
      }
      elseif ($part === ',') {
        if (!$in_result) { $conditions .= ' ' . $candidate; }
        $candidate = '';
        $in_result = FALSE;
      }
      elseif (!$in_result) {
        $candidate .= $part;
      }
    }
    return $conditions . ' ' . $candidate;
  }

  /** Read only PHP switch case expressions, excluding consequent statements. */
  private static function readPhpSwitchCaseLabels(array $tokens, int $index): string {
    $outer_alternative = FALSE;
    $nested_alternative_depth = 0;
    $started = FALSE;
    $brace_depth = 0;
    $labels = '';
    for ($i = $index; isset($tokens[$i]); $i++) {
      if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_COMMENT, T_DOC_COMMENT], TRUE)) {
        continue;
      }
      $part = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
      if (!$started) {
        if ($part === '{') {
          $started = TRUE;
          $brace_depth = 1;
        }
        elseif ($part === ':') {
          $started = TRUE;
          $outer_alternative = TRUE;
        }
        continue;
      }
      if (is_array($tokens[$i])) {
        if ($tokens[$i][0] === T_SWITCH && self::phpSwitchUsesAlternativeSyntax($tokens, $i)) {
          $nested_alternative_depth++;
        }
        elseif ($tokens[$i][0] === T_ENDSWITCH) {
          if ($nested_alternative_depth > 0) {
            $nested_alternative_depth--;
          }
          elseif ($outer_alternative) {
            return $labels;
          }
        }
      }
      if ($part === '}') {
        $brace_depth--;
        if (!$outer_alternative && $brace_depth === 0) {
          return $labels;
        }
      }
      elseif ($part === '{') {
        $brace_depth++;
      }
      if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CASE
        || $nested_alternative_depth > 0
        || ($outer_alternative && $brace_depth !== 0)
        || (!$outer_alternative && $brace_depth !== 1)) {
        continue;
      }
      $case = '';
      $paren = $bracket = $brace = $ternary = 0;
      for ($label_index = $i + 1; isset($tokens[$label_index]); $label_index++) {
        if (is_array($tokens[$label_index]) && in_array($tokens[$label_index][0], [T_COMMENT, T_DOC_COMMENT], TRUE)) {
          continue;
        }
        $label_part = is_array($tokens[$label_index]) ? $tokens[$label_index][1] : $tokens[$label_index];
        if ($label_part === '(') { $paren++; }
        elseif ($label_part === ')') { $paren--; }
        elseif ($label_part === '[') { $bracket++; }
        elseif ($label_part === ']') { $bracket--; }
        elseif ($label_part === '{') { $brace++; }
        elseif ($label_part === '}') { $brace--; }
        elseif ($paren === 0 && $bracket === 0 && $brace === 0 && $label_part === '?'
          && ($tokens[$label_index + 1] ?? '') !== '?'
          && ($tokens[$label_index + 1] ?? '') !== '.'
          && ($tokens[$label_index - 1] ?? '') !== '?') { $ternary++; }
        elseif ($paren === 0 && $bracket === 0 && $brace === 0 && $label_part === ':') {
          if ($ternary > 0) { $ternary--; }
          else {
            $i = $label_index;
            break;
          }
        }
        elseif ($paren === 0 && $bracket === 0 && $brace === 0 && $label_part === ';' && $ternary === 0) {
          $i = $label_index;
          break;
        }
        $case .= $label_part;
      }
      $labels .= ' ' . $case;
    }
    return $labels;
  }

  /** Return whether a PHP switch token uses colon/endswitch syntax. */
  private static function phpSwitchUsesAlternativeSyntax(array $tokens, int $index): bool {
    $parenthesis = 0;
    $condition_closed = FALSE;
    for ($i = $index + 1; isset($tokens[$i]); $i++) {
      if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], TRUE)) {
        continue;
      }
      $part = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
      if ($part === '(') {
        $parenthesis++;
      }
      elseif ($part === ')') {
        $parenthesis--;
        if ($parenthesis === 0) {
          $condition_closed = TRUE;
        }
      }
      elseif ($condition_closed) {
        return $part === ':';
      }
    }
    return FALSE;
  }

  private static function readBalancedJsBlock(string $source, int $index): string {
    $length = strlen($source);
    $depth = 1;
    $start = $index;
    $quote = NULL;
    for ($i = $index; $i < $length; $i++) {
      $char = $source[$i];
      if ($quote !== NULL) {
        if ($char === '\\') {
          $i++;
        }
        elseif ($char === $quote) {
          $quote = NULL;
        }
      }
      elseif ($char === '"' || $char === "'" || $char === '`') {
        $quote = $char;
      }
      elseif ($char === '/' && self::isJsRegexStart($source, $i)) {
        $i = self::skipJsRegexLiteral($source, $i);
      }
      elseif ($char === '{') {
        $depth++;
      }
      elseif ($char === '}') {
        $depth--;
        if ($depth === 0) {
          return substr($source, $start, $i - $start);
        }
      }
    }
    return substr($source, $start);
  }

  /** Extract top-level switch labels, excluding words in consequent bodies. */
  private static function readJsCaseExpressions(string $body): string {
    $labels = '';
    $length = strlen($body);
    $brace = $paren = $bracket = 0;
    $quote = NULL;
    for ($i = 0; $i < $length; $i++) {
      $char = $body[$i];
      if ($quote !== NULL) {
        if ($char === '\\') { $i++; }
        elseif ($char === $quote) { $quote = NULL; }
        continue;
      }
      if ($char === '"' || $char === "'" || $char === '`') { $quote = $char; continue; }
      if ($char === '/' && self::isJsRegexStart($body, $i)) { $i = self::skipJsRegexLiteral($body, $i); continue; }
      if ($char === '{') { $brace++; continue; }
      if ($char === '}') { $brace--; continue; }
      if ($char === '(') { $paren++; continue; }
      if ($char === ')') { $paren--; continue; }
      if ($char === '[') { $bracket++; continue; }
      if ($char === ']') { $bracket--; continue; }
      if ($brace !== 0 || $paren !== 0 || $bracket !== 0 || substr($body, $i, 4) !== 'case'
        || preg_match('/[a-zA-Z0-9_$]/', $body[$i - 1] ?? '') === 1
        || preg_match('/[a-zA-Z0-9_$]/', $body[$i + 4] ?? '') === 1) {
        continue;
      }
      $expression = '';
      $ternary = $expr_paren = $expr_bracket = $expr_brace = 0;
      $quote = NULL;
      for ($j = $i + 4; $j < $length; $j++) {
        $part = $body[$j];
        if ($quote !== NULL) {
          $expression .= $part;
          if ($part === '\\') { $expression .= $body[++$j] ?? ''; }
          elseif ($part === $quote) { $quote = NULL; }
          continue;
        }
        if ($part === '"' || $part === "'" || $part === '`') { $quote = $part; $expression .= $part; continue; }
        if ($part === '(') { $expr_paren++; }
        elseif ($part === ')') { $expr_paren--; }
        elseif ($part === '[') { $expr_bracket++; }
        elseif ($part === ']') { $expr_bracket--; }
        elseif ($part === '{') { $expr_brace++; }
        elseif ($part === '}') { $expr_brace--; }
        elseif ($expr_paren === 0 && $expr_bracket === 0 && $expr_brace === 0 && $part === '?'
          && ($body[$j + 1] ?? '') !== '?'
          && ($body[$j + 1] ?? '') !== '.'
          && ($body[$j - 1] ?? '') !== '?') { $ternary++; }
        elseif ($expr_paren === 0 && $expr_bracket === 0 && $expr_brace === 0 && $part === ':') {
          if ($ternary > 0) { $ternary--; }
          else { $i = $j; break; }
        }
        $expression .= $part;
      }
      $labels .= ' ' . $expression;
    }
    return $labels;
  }

  /** Remove JS comments without treating comment-like text in strings as code. */
  private static function stripJsComments(string $source): string {
    $output = '';
    $length = strlen($source);
    $quote = NULL;
    for ($i = 0; $i < $length; $i++) {
      $char = $source[$i];
      if ($quote !== NULL) {
        $output .= $char;
        if ($char === '\\') { $output .= $source[++$i] ?? ''; }
        elseif ($char === $quote) { $quote = NULL; }
        continue;
      }
      if ($char === '"' || $char === "'" || $char === '`') { $quote = $char; $output .= $char; continue; }
      if ($char === '/' && ($source[$i + 1] ?? '') !== '/' && ($source[$i + 1] ?? '') !== '*'
        && self::isJsRegexStart($source, $i)) {
        $start = $i;
        $i = self::skipJsRegexLiteral($source, $i);
        $output .= substr($source, $start, $i - $start + 1);
        continue;
      }
      if ($char === '/' && ($source[$i + 1] ?? '') === '/') {
        while ($i < $length && $source[$i] !== "\n") { $i++; }
        $output .= "\n";
        continue;
      }
      if ($char === '/' && ($source[$i + 1] ?? '') === '*') {
        $end = strpos($source, '*/', $i + 2);
        if ($end === FALSE) { return $output; }
        $output .= str_repeat(' ', $end + 2 - $i);
        $i = $end + 1;
        continue;
      }
      $output .= $char;
    }
    return $output;
  }

  /** Return the contents and ending offset for a balanced JS parenthesis. */
  private static function readBalancedJsParentheses(string $source, int $open): array {
    $length = strlen($source);
    $depth = 0;
    $quote = NULL;
    for ($i = $open; $i < $length; $i++) {
      $char = $source[$i];
      if ($quote !== NULL) {
        if ($char === '\\') {
          $i++;
        }
        elseif ($char === $quote) {
          $quote = NULL;
        }
      }
      elseif ($char === '"' || $char === "'" || $char === '`') {
        $quote = $char;
      }
      elseif ($char === '/' && self::isJsRegexStart($source, $i)) {
        $i = self::skipJsRegexLiteral($source, $i);
      }
      elseif ($char === '(') {
        $depth++;
      }
      elseif ($char === ')') {
        $depth--;
        if ($depth === 0) {
          return [substr($source, $open + 1, $i - $open - 1), $i + 1];
        }
      }
    }
    return [substr($source, $open + 1), $length];
  }

  /** Identify a regex literal by its expression-start context. */
  private static function isJsRegexStart(string $source, int $offset): bool {
    $previous = $offset - 1;
    while ($previous >= 0 && ctype_space($source[$previous])) {
      $previous--;
    }
    if ($previous < 0 || str_contains('([{=,:;!?&|+-*%^~>', $source[$previous])) {
      return TRUE;
    }
    $prefix = substr($source, 0, $previous + 1);
    return preg_match('/\b(?:return|throw|case|yield|await|typeof|void|delete)\s*$/', $prefix) === 1;
  }

  /** Skip a JavaScript regex literal, including escapes and character sets. */
  private static function skipJsRegexLiteral(string $source, int $offset): int {
    $length = strlen($source);
    $in_character_class = FALSE;
    for ($i = $offset + 1; $i < $length; $i++) {
      if ($source[$i] === '\\') {
        $i++;
      }
      elseif ($source[$i] === '[') {
        $in_character_class = TRUE;
      }
      elseif ($source[$i] === ']') {
        $in_character_class = FALSE;
      }
      elseif ($source[$i] === '/' && !$in_character_class) {
        while (isset($source[$i + 1]) && ctype_alpha($source[$i + 1])) {
          $i++;
        }
        return $i;
      }
    }
    return $length - 1;
  }

}
