<?php

declare(strict_types=1);

// The path is relative to the project checkout so PHPUnit does not depend on
// an operator-created directory or on the current working directory.
$browserOutput = dirname(__DIR__) . '/web/sites/simpletest/browser_output';
if (!is_dir($browserOutput) && !mkdir($browserOutput, 0775, TRUE) && !is_dir($browserOutput)) {
  throw new RuntimeException(sprintf('Não foi possível criar a saída PHPUnit: %s', $browserOutput));
}

require dirname(__DIR__) . '/web/core/tests/bootstrap.php';
// In git worktrees, web/core is symlinked to the primary checkout, so core's
// bootstrap scans the primary checkout by default. Ensure local worktree extensions
// (themes, modules) are registered in the autoloader.
$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$worktreeRoot = dirname(__DIR__) . '/web';
foreach (['modules', 'themes'] as $type) {
  $path = $worktreeRoot . '/' . $type;
  if (is_dir($path)) {
    $dirs = drupal_phpunit_find_extension_directories($path);
    foreach (drupal_phpunit_get_extension_namespaces($dirs) as $prefix => $paths) {
      $loader->addPsr4($prefix, $paths);
    }
  }
}

