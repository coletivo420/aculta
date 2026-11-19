<?php

/**
 * @file
 * PHPUnit bootstrap for the ACULTA project.
 */

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

/**
 * PHPUnit bootstrap with worktree-local extension autoloading.
 */

// The path is relative to the project checkout so PHPUnit does not depend on
// an operator-created directory or on the current working directory.
$browserOutput = dirname(__DIR__)
  . '/web/sites/simpletest/browser_output';
if (!is_dir($browserOutput) && !mkdir($browserOutput, 0775, TRUE) && !is_dir($browserOutput)) {
  throw new RuntimeException(sprintf('Não foi possível criar a saída PHPUnit: %s', $browserOutput));
}

require dirname(__DIR__) . '/web/core/tests/bootstrap.php';
// In git worktrees, web/core is symlinked to the primary checkout, so Core's
// bootstrap scans the primary checkout by default. Ensure local extensions
// (themes, modules) are registered in the autoloader.
$loader = require dirname(__DIR__) . '/vendor/autoload.php';
if (!$loader instanceof ClassLoader) {
  throw new RuntimeException('O autoloader Composer não foi carregado corretamente.');
}

$worktreeRoot = dirname(__DIR__) . '/web';
foreach (['modules', 'themes'] as $type) {
  $path = $worktreeRoot . '/' . $type;
  if (is_dir($path)) {
    $directories = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($directories);
    foreach ($files as $file) {
      if (!$file instanceof SplFileInfo) {
        continue;
      }
      if (!$file->isFile() || !str_ends_with($file->getFilename(), '.info.yml')) {
        continue;
      }
      $extension = basename($file->getFilename(), '.info.yml');
      $source = dirname($file->getPathname()) . '/src';
      if (!is_dir($source)) {
        continue;
      }
      // Prepend worktree modules so they take precedence over paths discovered
      // by Drupal's bootstrap from the primary checkout.
      $loader->addPsr4('Drupal\\' . $extension . '\\', [$source], TRUE);
    }
  }
}
