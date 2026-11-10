<?php

declare(strict_types=1);

/**
 * @file
 * Classifies every file under tests/browser without executing it.
 *
 * The browser tree mixes Playwright entrypoints, per-front route contracts,
 * manual review scripts, evidence captures and documentation. This catalog
 * records purpose/front, role, consumers and a destiny for each file so a
 * historical script is not silently kept next to the executable suite.
 */

const BROWSER_ROOT = 'tests/browser';
const ENTRYPOINTS = [
  'playwright.config.mjs',
  'playwright.spec.mjs',
  'playwright.routes.spec.mjs',
  'playwright-runner.mjs',
  'run-playwright.mjs',
  'auth-login.mjs',
  'package.json',
  'package-lock.json',
  'gerar-indice.py',
];
const EVIDENCE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'html', 'har', 'trace', 'zip'];
const SCRIPT_EXTENSIONS = ['py', 'mjs', 'js', 'sh', 'php'];

$root = dirname(__DIR__);
$base = $root . '/' . BROWSER_ROOT;
$files = [];
$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
  if (!$file->isFile()) {
    continue;
  }
  $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
  if (str_contains($relative, '/node_modules/') || str_contains($relative, '/__pycache__/')) {
    continue;
  }
  $files[$relative] = $file->getPathname();
}
ksort($files);

// Index text files for consumer lookups.
$text = [];
foreach ($files as $relative => $path) {
  $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
  if (in_array($extension, ['py', 'mjs', 'js', 'sh', 'md', 'json', 'yml', 'yaml', 'php'], TRUE)) {
    $text[$relative] = (string) file_get_contents($path);
  }
}

$entries = [];
foreach ($files as $relative => $path) {
  $basename = basename($relative);
  $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
  $segments = explode('/', substr($relative, strlen(BROWSER_ROOT) + 1));
  $purpose = $segments[0] ?? '';
  $front = $segments[1] ?? '';

  $role = 'other';
  if (in_array($basename, ENTRYPOINTS, TRUE)) {
    $role = 'entrypoint';
  }
  elseif ($basename === 'routes.json') {
    $role = 'route_contract';
  }
  elseif ($extension === 'md') {
    $role = 'documentation';
  }
  elseif (in_array($extension, EVIDENCE_EXTENSIONS, TRUE)) {
    $role = 'evidence';
  }
  elseif ($extension === 'json' || $extension === 'txt' || $extension === 'csv') {
    $role = 'report_output';
  }
  elseif (in_array($extension, SCRIPT_EXTENSIONS, TRUE)) {
    $role = 'script';
  }

  $consumers = [];
  if ($role === 'script' || $role === 'other') {
    foreach ($text as $otherRelative => $content) {
      if ($otherRelative === $relative) {
        continue;
      }
      if (str_contains($content, $basename) || str_contains($content, $relative)) {
        $consumers[] = $otherRelative;
      }
    }
  }

  $destiny = match (TRUE) {
    in_array($role, ['entrypoint', 'route_contract', 'documentation'], TRUE) => 'manter',
    $role === 'evidence' => 'manter como evidencia',
    in_array($role, ['report_output'], TRUE) => 'manter como relatorio',
    $role === 'script' && $consumers !== [] => 'manter (consumido)',
    $role === 'script' => 'revisar: candidato a arquivo',
    default => 'revisar',
  };

  $entries[] = [
    'path' => $relative,
    'purpose' => $purpose,
    'front' => $front,
    'extension' => $extension,
    'role' => $role,
    'consumers' => $consumers,
    'destiny' => $destiny,
  ];
}

$roles = [];
$destinies = [];
foreach ($entries as $entry) {
  $roles[$entry['role']] = ($roles[$entry['role']] ?? 0) + 1;
  $destinies[$entry['destiny']] = ($destinies[$entry['destiny']] ?? 0) + 1;
}
ksort($roles);
ksort($destinies);

$target = $root . '/docs/operations/testing/browser-inventory.json';
if (!is_dir(dirname($target))) {
  mkdir(dirname($target), 0775, TRUE);
}
$catalog = [
  'generated_at' => gmdate('c'),
  'generator' => 'scripts/audit-browser-scripts.php',
  'total_files' => count($entries),
  'by_role' => $roles,
  'by_destiny' => $destinies,
  'files' => $entries,
];
file_put_contents($target, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

fwrite(STDOUT, 'Browser inventory: ' . count($entries) . " arquivos\n");
foreach ($roles as $role => $count) {
  fwrite(STDOUT, "  papel {$role}: {$count}\n");
}
foreach ($destinies as $destiny => $count) {
  fwrite(STDOUT, "  destino {$destiny}: {$count}\n");
}
