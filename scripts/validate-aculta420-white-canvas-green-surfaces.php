<?php

/**
 * @file
 * Static contract gate for the ACULTA420 white canvas and green SDCs.
 */

declare(strict_types=1);

$theme = dirname(__DIR__) . '/web/themes/custom/aculta420_v2';
$read = static function (string $path): string {
  $data = @file_get_contents($path);
  if (!is_string($data)) {
    throw new RuntimeException('Cannot read ' . $path);
  }
  return $data;
};
$css = $read($theme . '/src/styles/tokens.css');
$darkAt = strpos($css, ':root[data-theme="dark"]');
if ($darkAt === FALSE) {
  fwrite(STDERR, "FAIL: missing dark tokens\n");
  exit(1);
}
$map = static function (string $src): array {
  preg_match_all('/^\s*(--[a-z0-9-]+):\s*([^;]+);/mi', $src, $matches, PREG_SET_ORDER);
  $out = [];
  foreach ($matches as $match) {
    $out[$match[1]] = trim($match[2]);
  }
  return $out;
};
$light = $map(substr($css, 0, $darkAt));
$dark = $map(substr($css, $darkAt));
$errors = [];
$checks = 0;
$check = static function (bool $ok, string $label) use (&$errors, &$checks): void {
  $checks++;
  if (!$ok) {
    $errors[] = $label;
  }
};
foreach ([
  'light' => [$light, ['--color-canvas' => '#fff', '--color-surface-raised' => '#fff',
    '--color-surface-green-soft' => '#e7eedc', '--color-surface-green-border' => '#6b7d70',
    '--aculta-surface-page' => '#fff', '--aculta-surface-page-rgb' => '255, 255, 255']],
  'dark' => [$dark, ['--color-canvas' => '#171513', '--color-surface-raised' => '#2b2723',
    '--color-surface-green-soft' => '#2a3a2f', '--color-surface-green-border' => '#8fa397']],
] as $mode => [$tokens, $expect]) {
  foreach ($expect as $name => $value) {
    $check(strtolower((string) ($tokens[$name] ?? '')) === $value, $mode . ' ' . $name);
  }
  $check(($tokens['--aculta-surface-green-soft'] ?? '') === 'var(--color-surface-green-soft)', $mode . ' alias');
}
$lum = static function (string $s): float {
  $hex = ltrim($s, '#');
  if (strlen($hex) === 3) {
    $hex = implode('', array_map(static fn(string $ch): string => $ch . $ch, str_split($hex)));
  }
  if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
    throw new RuntimeException('Invalid color ' . $hex);
  }
  $vals = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
  $vals = array_map(static function (int $n): float {
    $v = $n / 255;
    return $v <= .04045 ? $v / 12.92 : (($v + .055) / 1.055) ** 2.4;
  }, $vals);
  return $vals[0] * .2126 + $vals[1] * .7152 + $vals[2] * .0722;
};
$contrast = static function (string $fg, string $bg) use ($lum): float {
  $a = $lum($fg);
  $b = $lum($bg);
  return (max($a, $b) + .05) / (min($a, $b) + .05);
};
$resolve = static function (array $tokens): array {
  foreach ($tokens as $name => $value) {
    if (preg_match('/^var\((--[a-z0-9-]+)\)$/i', $value, $ref) && isset($tokens[$ref[1]])) {
      $tokens[$name] = $tokens[$ref[1]];
    }
  }
  return $tokens;
};
foreach (['light' => $resolve($light), 'dark' => $resolve($dark)] as $mode => $tokens) {
  foreach ([['--color-text-primary', 4.5], ['--color-text-secondary', 4.5], ['--color-surface-green-border', 3.0], ['--color-focus-ring', 3.0]] as [$name, $min]) {
    $r = $contrast($tokens[$name], $tokens['--color-surface-green-soft']);
    $check($r >= $min, $mode . ' ' . $name . ' on green-soft = ' . round($r, 2) . ':1');
  }
}
foreach (['surface', 'surface-panel', 'surface-accent'] as $name) {
  $base = $theme . '/components/' . $name . '/' . $name;
  $schema = $read($base . '.component.yml');
  $twig = $read($base . '.twig');
  $cssFile = $read($base . '.css');
  $story = $read($base . '.default.story.yml');
  $check(str_contains($schema, 'enum: [canvas, raised, subtle, institution, green-soft]') && str_contains($schema, 'default: green-soft'), $name . ' schema');
  $check(str_contains($twig, "tone|default('green-soft')"), $name . ' Twig');
  $check(str_contains($cssFile, '--tone-green-soft') && str_contains($cssFile, 'var(--color-surface-green-border)'), $name . ' CSS');
  $check(str_contains($story, 'tone: green-soft'), $name . ' default story');
}
$styles = $read($theme . '/aculta420_v2.ui_styles.yml');
$check(str_contains($styles, 'aculta-surface aculta-surface--tone-green-soft:'), 'UI Styles');
$layout = $theme . '/components/layout-section/layout-section';
$check(str_contains($read($layout . '.component.yml'), 'enum: [default, raised, green-soft]') && str_contains($read($layout . '.component.yml'), 'default: default'), 'Layout Section preserves default and adds opt-in green-soft');
$check(str_contains($read($layout . '.twig'), "['default', 'raised', 'green-soft']"), 'Layout Section Twig allow-list');
$check(str_contains($read($layout . '.css'), '.layout-section--green-soft') && str_contains($read($layout . '.css'), 'var(--color-surface-green-soft)'), 'Layout Section CSS');
$check(str_contains($styles, 'layout-section--green-soft:'), 'Layout Section UI Styles option');
$check(str_contains($read($theme . '/components/layout-section/layout-section.green-soft.story.yml'), 'tone: green-soft'), 'Layout Section story');
if ($errors !== []) {
  foreach ($errors as $error) {
    fwrite(STDERR, "FAIL: " . $error . "\n");
  }
  exit(1);
}
echo "WHITE CANVAS / GREEN SURFACES: PASS ($checks checks, static only)\n";
