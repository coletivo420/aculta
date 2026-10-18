<?php

/**
 * Static gate for the package label of our own modules (docs/portal/COMPONENTES-PROPRIOS.md).
 *
 * Checks that every custom module (type: module under web/modules/custom, at any depth) declares
 * package 'ACULTA Portal', so /painel-administrativo/modulos shows all own modules in a single box.
 *
 * The label is compared after YAML parsing, so quoting style does not hide a variant spelling.
 * The gate does not bootstrap Drupal, so it runs in any checkout.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

const OWN_PACKAGE = 'ACULTA Portal';

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
  $data = Yaml::parseFile($info);
  if (($data['type'] ?? '') !== 'module') {
    continue;
  }
  $relative = substr(dirname($info), strlen($root) + 1);
  $package = (string) ($data['package'] ?? '');
  $checks++;
  if ($package !== OWN_PACKAGE) {
    $failures[] = "custom module '$relative' declares package '$package'; it must declare '" . OWN_PACKAGE . "'.";
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
