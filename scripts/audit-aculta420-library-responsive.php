<?php

/**
 * @file
 * Inventories responsive signals in custom Drupal library CSS and JS assets.
 */

declare(strict_types=1);

use Drupal\Component\Serialization\Yaml;

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

$output = $root . '/web/themes/custom/aculta420/docs/custom-library-responsive-inventory.tsv';
$write = ($argv[1] ?? '') === '--write';
if (isset($argv[1]) && !$write) {
  fwrite(STDERR, "Usage: php scripts/audit-aculta420-library-responsive.php [--write]\n");
  exit(2);
}

$roots = [
  $root . '/web/modules/custom',
  $root . '/web/themes/custom/aculta420',
];
$isExcluded = static fn(string $path): bool => preg_match('~/(?:tests|docs|vendor|node_modules|\.git)/~', $path) === 1;
$libraries = [];
$libraryDependencies = [];
$sourceFiles = [];
$sourceExtensions = ['php', 'module', 'inc', 'theme', 'twig', 'yml', 'js', 'mjs'];

foreach ($roots as $scanRoot) {
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS),
  );
  foreach ($iterator as $file) {
    if (!$file->isFile() || $isExcluded($file->getPathname())) {
      continue;
    }
    $path = $file->getPathname();
    if (str_ends_with($path, '.libraries.yml')) {
      $extension = basename($path, '.libraries.yml');
      $definitions = Yaml::decode(file_get_contents($path) ?: '');
      if (!is_array($definitions)) {
        continue;
      }
      foreach ($definitions as $id => $definition) {
        if (!is_array($definition)) {
          continue;
        }
        $key = $extension . '/' . $id;
        $libraries[$key] ??= [];
        $libraryDependencies[$key] = array_values(array_filter($definition['dependencies'] ?? [], 'is_string'));
        foreach (($definition['css'] ?? []) as $group => $assets) {
          if (!is_array($assets)) {
            continue;
          }
          foreach ($assets as $asset => $_options) {
            $libraries[$key][] = [
              'kind' => 'css',
              'definition' => substr($path, strlen($root) + 1),
              'group' => (string) $group,
              'asset' => (string) $asset,
              'base' => dirname($path),
            ];
          }
        }
        foreach (($definition['js'] ?? []) as $asset => $_options) {
          $libraries[$key][] = [
            'kind' => 'js',
            'definition' => substr($path, strlen($root) + 1),
            'group' => 'js',
            'asset' => (string) $asset,
            'base' => dirname($path),
          ];
        }
      }
    }
    elseif (in_array(strtolower($file->getExtension()), $sourceExtensions, TRUE)) {
      $sourceFiles[] = $path;
    }
  }
}

$literalLibrary = '~[\'"`]([a-z0-9_]+/[a-z0-9_.-]+)[\'"`]|(?:^|\s)-\s+([a-z0-9_]+/[a-z0-9_.-]+)(?=\s*(?:#.*)?$)~i';
$consumers = array_fill_keys(array_keys($libraries), []);
foreach ($sourceFiles as $path) {
  $relative = substr($path, strlen($root) + 1);
  $lines = file($path, FILE_IGNORE_NEW_LINES);
  if ($lines === FALSE) {
    continue;
  }
  foreach ($lines as $index => $line) {
    if (!preg_match_all($literalLibrary, $line, $matches)) {
      continue;
    }
    foreach (array_unique(array_filter(array_merge($matches[1], $matches[2] ?? []))) as $name) {
      if (isset($consumers[$name])) {
        $consumers[$name][] = $relative . ':' . ($index + 1);
      }
    }
  }
}

foreach ($libraryDependencies as $parent => $dependencies) {
  foreach ($dependencies as $dependency) {
    if (isset($consumers[$dependency])) {
      $consumers[$dependency][] = 'dependency of ' . $parent;
    }
  }
}

$mediaPattern = '/@media\s+([^{}]+)\{/i';
$containerPattern = '/@container\s+([^{}]+)\{/i';
$dimensionPattern = '/(?<![\w-])(?:(?:min|max)-)?(?:width|height|inline-size|block-size)\s*:\s*[^;{}]+/i';
$overflowPattern = '/(?<![\w-])overflow(?:-[xy])?\s*:\s*[^;{}]+/i';
$jsMediaPattern = '/matchMedia\s*\(\s*([\'"`])(.*?)\1/i';
$jsViewportPattern = '/\b(?:innerWidth|outerWidth|clientWidth|scrollWidth|visualViewport|ResizeObserver|orientationchange)\b/i';
$jsScrollPattern = '/\b(?:scrollWidth|scrollHeight|scrollTop|scrollLeft|scrollTo|scrollIntoView)\b/i';

$rows = [[
  'library', 'kind', 'library_definition', 'asset_group', 'asset', 'status',
  'responsive_query_count', 'responsive_queries', 'container_query_count',
  'dimension_signals', 'overflow_signals', 'viewport_signals', 'scroll_signals', 'consumers',
],
];
$counts = ['css' => 0, 'js' => 0, 'local' => 0, 'external' => 0, 'missing' => 0];
$join = static fn(array $values): string => $values === [] ? '—' : implode(' | ', array_values(array_unique($values)));

ksort($libraries, SORT_STRING);
foreach ($libraries as $name => $assets) {
  foreach ($assets as $entry) {
    $kind = $entry['kind'];
    $counts[$kind]++;
    $asset = $entry['asset'];
    $status = 'local';
    $sourcePath = NULL;
    if (preg_match('~^https?://~i', $asset)) {
      $status = 'external-url';
    }
    elseif (str_starts_with($asset, '/')) {
      $status = 'site-root-or-managed-library';
    }
    else {
      $sourcePath = $entry['base'] . '/' . $asset;
      if (!is_file($sourcePath)) {
        $status = 'missing-local-asset';
      }
    }

    $queries = $containers = $dimensions = $overflows = $viewport = $scroll = [];
    if ($status === 'local') {
      $counts['local']++;
      $content = file_get_contents($sourcePath) ?: '';
      if ($kind === 'css') {
        preg_match_all($mediaPattern, $content, $matches);
        $queries = $matches[1] ?? [];
        preg_match_all($containerPattern, $content, $matches);
        $containers = $matches[1] ?? [];
        preg_match_all($dimensionPattern, $content, $matches);
        $dimensions = $matches[0] ?? [];
        preg_match_all($overflowPattern, $content, $matches);
        $overflows = $matches[0] ?? [];
      }
      else {
        preg_match_all($jsMediaPattern, $content, $matches);
        $queries = $matches[2] ?? [];
        preg_match_all($jsViewportPattern, $content, $matches);
        $viewport = $matches[0] ?? [];
        preg_match_all($jsScrollPattern, $content, $matches);
        $scroll = $matches[0] ?? [];
      }
    }
    elseif (in_array($status, ['external-url', 'site-root-or-managed-library'], TRUE)) {
      $counts['external']++;
    }
    else {
      $counts['missing']++;
    }

    $rows[] = [
      $name,
      $kind,
      $entry['definition'],
      $entry['group'],
      $asset,
      $status,
      (string) count($queries),
      $join(array_map(static fn(string $value): string => preg_replace('/\s+/', ' ', trim($value)), $queries)),
      (string) count($containers),
      $join(array_map(static fn(string $value): string => preg_replace('/\s+/', ' ', trim($value)), $dimensions)),
      $join(array_map(static fn(string $value): string => preg_replace('/\s+/', ' ', trim($value)), $overflows)),
      $join(array_values(array_unique($viewport))),
      $join(array_values(array_unique($scroll))),
      $join($consumers[$name]),
    ];
  }
}

$rendered = implode("\n", array_map(
  static fn(array $row): string => implode("\t", array_map(
    static fn(string $value): string => str_replace(["\t", "\r", "\n"], ' ', $value),
    $row,
  )),
  $rows,
)) . "\n";

if ($write) {
  if (file_put_contents($output, $rendered) === FALSE) {
    fwrite(STDERR, "Could not write inventory: {$output}\n");
    exit(1);
  }
  fwrite(STDERR, "Inventory updated: web/themes/custom/aculta420/docs/custom-library-responsive-inventory.tsv\n");
}
else {
  fwrite(STDOUT, $rendered);
}

fwrite(STDERR, sprintf(
  "Library assets: %d CSS, %d JS; %d local analyzed, %d external/managed, %d missing local\n",
  $counts['css'],
  $counts['js'],
  $counts['local'],
  $counts['external'],
  $counts['missing'],
));
