<?php

/**
 * @file
 * Static gate for our modules' package label.
 *
 * See docs/portal/COMPONENTES-PROPRIOS.md.
 *
 * Checks that every custom module under web/modules/custom declares package
 * 'ACULTA Portal', so the module administration page shows them in one box.
 *
 * The label is compared after YAML parsing, so quoting style does not hide a
 * variant spelling.
 * The gate does not bootstrap Drupal, so it runs in any checkout.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$ownPackage = 'ACULTA Portal';

$root = dirname(__DIR__);
$customRoot = $root . '/web/modules/custom';
$failures = [];
$checks = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($customRoot, FilesystemIterator::SKIP_DOTS));
$infos = [];
foreach ($iterator as $file) {
  if (str_ends_with($file->getFilename(), '.info.yml')) {
    $infos[] = $file->getPathname();
  }
}
sort($infos);

foreach ($infos as $info) {
  $relative = substr(dirname($info), strlen($root) + 1);
  // Modules under tests/modules are discovery fixtures, not product packages.
  if (preg_match('#(?:^|/)tests/modules(?:/|$)#', $relative)) {
    continue;
  }
  $data = Yaml::parseFile($info);
  if (($data['type'] ?? '') !== 'module') {
    continue;
  }
  $package = (string) ($data['package'] ?? '');
  $checks++;
  if ($package !== $ownPackage) {
    $failures[] = "custom module '$relative' declares package '$package'; it must declare '$ownPackage'.";
  }
}

$checks++;
if ($infos === []) {
  $failures[] = 'no custom module was found under web/modules/custom.';
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "OWN MODULE PACKAGES FAIL: $failure\n");
  }
  fwrite(STDERR, sprintf("OWN MODULE PACKAGES: FAIL (%d failures in %d checks)\n", count($failures), $checks));
  exit(1);
}

echo sprintf("OWN MODULE PACKAGES: PASS (%d checks)\n", $checks);
