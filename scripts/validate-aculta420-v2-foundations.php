<?php

/**
 * @file
 * Validates theme-wide visual foundations and accessible contrast.
 */

declare(strict_types=1);

/**
 * Validates ACULTA420 V2 semantic foundations and dark-mode contrast pairs.
 *
 * Run from any working directory with:
 * php scripts/validate-aculta420-v2-foundations.php.
 */

$root = dirname(__DIR__);
$theme = $root . '/web/themes/custom/aculta420_v2';
$tokensPath = $theme . '/src/styles/tokens.css';
$basePath = $theme . '/src/styles/base.css';
$layoutPath = $theme . '/src/styles/layout.css';
$librariesPath = $theme . '/aculta420_v2.libraries.yml';
$tokensCss = file_get_contents($tokensPath);
$baseCss = file_get_contents($basePath);
$layoutCss = file_get_contents($layoutPath);
$libraries = file_get_contents($librariesPath);

foreach ([$tokensCss, $baseCss, $layoutCss, $libraries] as $source) {
  if (!is_string($source)) {
    fwrite(STDERR, "V2 foundations: required source file could not be read.\n");
    exit(1);
  }
}

$rootTokens = [];
$darkTokens = [];
if (preg_match('/^:root\s*\{(?<body>.*?)^\}/ms', $tokensCss, $matches) !== 1) {
  fwrite(STDERR, "V2 foundations: base token block is missing.\n");
  exit(1);
}
$rootTokens = token_map($matches['body']);
if (preg_match('/^:root\[data-theme="dark"\]\s*\{(?<body>.*?)^\}/ms', $tokensCss, $matches) !== 1) {
  fwrite(STDERR, "V2 foundations: explicit dark token block is missing.\n");
  exit(1);
}
$darkTokens = token_map($matches['body']);

$assert = static function (bool $condition, string $message): void {
  if (!$condition) {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
  }
  fwrite(STDOUT, "PASS: {$message}\n");
};

$assert(!str_contains($tokensCss, 'prefers-color-scheme'), 'color mode is not selected from the operating system');
$assert(str_contains($tokensCss, '--font-family-base: Inter') && str_contains($tokensCss, '--font-family-heading: Oswald'), 'semantic typography uses the V1 Inter and Oswald families');
$assert(str_contains($libraries, 'fonts.googleapis.com/css2?family=Inter') && str_contains($libraries, 'family=Oswald'), 'Inter and Oswald font faces are loaded by the Drupal library');
$assert(str_contains($tokensCss, '--spacing-md: .75rem') && str_contains($tokensCss, '--spacing-lg-compact: 1.25rem') && str_contains($tokensCss, '--spacing-section: clamp('), 'spacing tokens include the V1 intermediate and section scale');
$assert(str_contains($tokensCss, '--radius-sm:') && str_contains($tokensCss, '--radius-base:') && str_contains($tokensCss, '--radius-lg:'), 'semantic radius scale is present');
$assert(str_contains($baseCss, ':focus-visible') && str_contains($baseCss, 'var(--color-focus-ring)'), 'visible focus uses the semantic focus token');
$assert(str_contains($baseCss, '@media (prefers-reduced-motion: reduce)'), 'reduced-motion preferences suppress nonessential animation');
$assert(str_contains($baseCss, 'overflow-wrap: anywhere') && str_contains($layoutCss, 'grid-template-columns: minmax(0, 1fr)'), 'foundation allows long content to reflow without widening the page grid');

$surfaces = [
  '--color-canvas',
  '--color-surface',
  '--color-surface-raised',
  '--color-surface-institution',
];
$textColors = [
  '--color-text-primary',
  '--color-text-secondary',
  '--color-text-muted',
  '--color-brand-primary',
  '--color-brand-secondary',
  '--color-link-editorial',
  '--color-link-editorial-hover',
];
$contrastPairs = 0;
foreach ($textColors as $foreground) {
  foreach ($surfaces as $background) {
    $ratio = check_contrast($foreground, $background, $rootTokens, $darkTokens);
    $assert($ratio >= 4.5, sprintf('dark %s on %s is %.2f:1 (minimum 4.5:1)', $foreground, $background, $ratio));
    $contrastPairs++;
  }
}

$statusPairs = [
  ['--color-status-error-text', '--color-status-error-surface'],
  ['--color-status-ok-text', '--color-status-ok-surface'],
  ['--color-status-warning-text', '--color-status-warning-surface'],
];
foreach ($statusPairs as [$foreground, $background]) {
  $ratio = check_contrast($foreground, $background, $rootTokens, $darkTokens);
  $assert($ratio >= 4.5, sprintf('dark %s on %s is %.2f:1 (minimum 4.5:1)', $foreground, $background, $ratio));
  $contrastPairs++;
}

foreach ([
  ['--color-on-brand', '--color-brand-primary'],
  ['--color-on-highlight', '--color-brand-highlight'],
] as [$foreground, $background]) {
  $ratio = check_contrast($foreground, $background, $rootTokens, $darkTokens);
  $assert($ratio >= 4.5, sprintf('dark %s on %s is %.2f:1 (minimum 4.5:1)', $foreground, $background, $ratio));
  $contrastPairs++;
}

foreach ($surfaces as $background) {
  $ratio = check_contrast('--color-focus-ring', $background, $rootTokens, $darkTokens);
  $assert($ratio >= 3, sprintf('dark focus indicator on %s is %.2f:1 (minimum 3:1)', $background, $ratio));
}

foreach ($surfaces as $background) {
  $ratio = check_contrast('--color-border', $background, $rootTokens, $darkTokens);
  $assert($ratio >= 3, sprintf('dark component border on %s is %.2f:1 (minimum 3:1)', $background, $ratio));
}

fwrite(STDOUT, sprintf("ACULTA420 V2 foundations: PASS (%d text contrast pairs; dark focus and borders checked)\n", $contrastPairs));

/**
 * Extracts custom property declarations from a CSS block.
 *
 * @return array<string, string>
 *   Token names mapped to their CSS values.
 */
function token_map(string $block): array {
  preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);/i', $block, $matches, PREG_SET_ORDER);
  $tokens = [];
  foreach ($matches as $match) {
    $tokens[$match[1]] = trim($match[2]);
  }
  return $tokens;
}

/**
 * Resolves a dark token, falling back to the base token and its var references.
 *
 * @param array<string, string> $base
 *   Base color tokens.
 * @param array<string, string> $dark
 *   Dark-mode color tokens.
 */
function token_color(string $name, array $base, array $dark, int $depth = 0): string {
  if ($depth > 8) {
    throw new RuntimeException("Token reference cycle at {$name}.");
  }
  $value = $dark[$name] ?? $base[$name] ?? NULL;
  if (!is_string($value)) {
    throw new RuntimeException("Required color token {$name} is missing.");
  }
  if (preg_match('/^var\((--[a-z0-9-]+)\)$/i', $value, $matches) === 1) {
    return token_color($matches[1], $base, $dark, $depth + 1);
  }
  if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $value) !== 1) {
    throw new RuntimeException("Color token {$name} is not a resolvable hex color: {$value}");
  }
  return normalize_hex($value);
}

/**
 * Calculates a WCAG contrast ratio for a dark-mode token pair.
 *
 * @param array<string, string> $base
 *   Base color tokens.
 * @param array<string, string> $dark
 *   Dark-mode color tokens.
 *
 * @return float
 *   The contrast ratio.
 */
function check_contrast(string $foreground, string $background, array $base, array $dark): float {
  return contrast_ratio(token_color($foreground, $base, $dark), token_color($background, $base, $dark));
}

/**
 * Normalizes a short or full hexadecimal CSS color.
 *
 * @return string
 *   The six-digit hexadecimal color.
 */
function normalize_hex(string $color): string {
  $hex = ltrim($color, '#');
  if (strlen($hex) === 3) {
    $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
  }
  return '#' . strtolower($hex);
}

/**
 * Calculates WCAG relative luminance for a hexadecimal color.
 *
 * @return float
 *   Relative luminance in the range zero to one.
 */
function relative_luminance(string $color): float {
  $hex = ltrim(normalize_hex($color), '#');
  $channels = [];
  foreach ([0, 2, 4] as $offset) {
    $channel = hexdec(substr($hex, $offset, 2)) / 255;
    $channels[] = $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
  }
  return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/**
 * Calculates the WCAG contrast ratio between two colors.
 *
 * @return float
 *   The contrast ratio.
 */
function contrast_ratio(string $foreground, string $background): float {
  $lum1 = relative_luminance($foreground);
  $lum2 = relative_luminance($background);
  return (max($lum1, $lum2) + 0.05) / (min($lum1, $lum2) + 0.05);
}
