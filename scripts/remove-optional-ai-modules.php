<?php

declare(strict_types=1);

/**
 * Removes optional AI submodules bundled with contrib packages.
 *
 * These modules are not used by ACULTA and are not independent Composer
 * packages, so Composer would otherwise restore them on every install/update.
 */
$modules = [
  'web/modules/contrib/ckeditor5_premium_features/modules/ckeditor5_premium_features_ai',
  'web/modules/contrib/ckeditor5_premium_features/modules/ckeditor5_premium_features_ai_assistant',
  'web/modules/contrib/tool/modules/tool_ai_connector',
  'web/modules/contrib/vvj_core/modules/vvj_core_ai',
];

foreach ($modules as $relative_path) {
  $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
  if (!is_dir($path)) {
    continue;
  }

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST,
  );
  foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
  }
  rmdir($path);
}
