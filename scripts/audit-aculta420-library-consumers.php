<?php

/**
 * @file
 * Generates a source-level inventory of custom Drupal library consumers.
 */

declare(strict_types=1);

use Drupal\Component\Serialization\Yaml;

/**
 * Build a source-level inventory of custom Drupal libraries and literal refs.
 *
 * This intentionally does not claim runtime attachment or route coverage:
 * dynamic service decisions and conditional render paths require Drupal tests.
 */

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
$output = $root . '/web/themes/custom/aculta420/docs/theme-library-consumer-inventory.tsv';
$write = ($argv[1] ?? '') === '--write';
if (isset($argv[1]) && !$write) {
  fwrite(STDERR, "Usage: php scripts/audit-aculta420-library-consumers.php [--write]\n");
  exit(2);
}

$roots = [
  $root . '/web/modules/custom',
  $root . '/web/themes/custom/aculta420',
];
$libraries = [];
$sourceFiles = [];
$sourceExtensions = ['php', 'module', 'inc', 'theme', 'twig', 'yml', 'js', 'mjs'];

$isExcluded = static function (string $path): bool {
  return preg_match('~/(?:tests|docs|vendor|node_modules|\.git)/~', $path) === 1;
};

foreach ($roots as $scanRoot) {
  if (!is_dir($scanRoot)) {
    continue;
  }
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
        $cssCount = 0;
        foreach (($definition['css'] ?? []) as $assets) {
          if (is_array($assets)) {
            $cssCount += count($assets);
          }
        }
        $jsCount = count($definition['js'] ?? []);
        $dependencies = array_values(array_filter($definition['dependencies'] ?? [], 'is_string'));
        $libraries[$extension . '/' . $id] = [
          'css' => $cssCount,
          'js' => $jsCount,
          'dependencies' => implode('|', $dependencies),
          'refs' => [],
        ];
      }
      continue;
    }
    if (in_array(strtolower($file->getExtension()), $sourceExtensions, TRUE)) {
      $sourceFiles[] = $path;
    }
  }
}

$literalLibrary = '~[\'"`]([a-z0-9_]+/[a-z0-9_.-]+)[\'"`]|(?:^|\s)-\s+([a-z0-9_]+/[a-z0-9_.-]+)(?=\s*(?:#.*)?$)~i';
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
    $names = array_filter(array_merge($matches[1], $matches[2] ?? []));
    foreach (array_unique($names) as $name) {
      if (!isset($libraries[$name])) {
        continue;
      }
      $kind = 'literal-reference';
      if (str_contains($line, 'attach_library')) {
        $kind = 'Twig attach_library';
      }
      elseif (str_ends_with($path, '.component.yml')) {
        $kind = 'SDC declaration';
      }
      elseif (str_ends_with($path, '.info.yml') && preg_match('~^\s*-\s+~', $line)) {
        $kind = 'extension default library';
      }
      elseif (str_contains(implode(' ', array_slice($lines, max(0, $index - 8), min(9, $index + 1))), '#attached')) {
        $kind = 'render-array/library';
      }
      elseif (str_contains($line, 'libraries:')) {
        $kind = 'extension-library-declaration';
      }
      $libraries[$name]['refs'][] = [$relative, $index + 1, $kind];
    }
  }
}

$rows = [
  [
    'library', 'css_asset_count', 'js_asset_count', 'dependencies',
    'literal_reference_count', 'reference_type', 'source_file', 'line',
  ],
];
ksort($libraries, SORT_STRING);
foreach ($libraries as $name => $library) {
  if ($library['refs'] === []) {
    $rows[] = [
      $name,
      (string) $library['css'],
      (string) $library['js'],
      $library['dependencies'],
      '0',
      '—',
      '—',
      '—',
    ];
    continue;
  }
  foreach ($library['refs'] as [$source, $line, $kind]) {
    $rows[] = [
      $name,
      (string) $library['css'],
      (string) $library['js'],
      $library['dependencies'],
      (string) count($library['refs']),
      $kind,
      $source,
      (string) $line,
    ];
  }
}

$rendered = implode("\n", array_map(static fn(array $row): string => implode("\t", array_map(
  static fn(string $value): string => str_replace(["\t", "\r", "\n"], ' ', $value),
  $row,
)), $rows)) . "\n";

if ($write) {
  if (file_put_contents($output, $rendered) === FALSE) {
    fwrite(STDERR, "Could not write inventory: {$output}\n");
    exit(1);
  }
  fwrite(STDERR, "Inventory updated: web/themes/custom/aculta420/docs/theme-library-consumer-inventory.tsv\n");
}
else {
  fwrite(STDOUT, $rendered);
}

fwrite(STDERR, sprintf(
  "Custom libraries: %d; source files scanned: %d; literal references: %d\n",
  count($libraries),
  count($sourceFiles),
  array_sum(array_map(static fn(array $library): int => count($library['refs']), $libraries)),
));
