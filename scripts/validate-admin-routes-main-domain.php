<?php

/**
 * Reject administrative and integration routes outside the MAIN domain.
 *
 * The ACULTA administration is unique and lives on the MAIN purpose:
 * `/painel-administrativo/**` and `/integracoes/**` always resolve on MAIN
 * and must return 404 on every secondary domain. This gate reads code; it
 * does not require a Drupal bootstrap.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$custom = $root . '/web/modules/custom';
$failures = [];
$checkedFiles = 0;
$checkedAdmin = 0;

if (!is_dir($custom)) {
  fwrite(STDERR, "ADMIN ROUTES MAIN FAIL: missing custom modules directory\n");
  exit(1);
}

/**
 * Returns TRUE for /painel-administrativo and /integracoes paths.
 */
function aculta_is_admin_main_path(string $path): bool {
  return $path === '/painel-administrativo'
    || str_starts_with($path, '/painel-administrativo/')
    || $path === '/integracoes'
    || str_starts_with($path, '/integracoes/');
}

$files = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($custom, FilesystemIterator::SKIP_DOTS),
);
foreach ($files as $file) {
  if (!$file->isFile() || !str_ends_with($file->getFilename(), '.routing.yml')) {
    continue;
  }
  $checkedFiles++;
  $path = $file->getPathname();
  $source = file_get_contents($path);
  if ($source === FALSE) {
    $failures[] = "$path could not be read";
    continue;
  }

  preg_match_all(
    '/^([a-zA-Z0-9_.-]+):\s*\R(?<block>(?:(?!^[a-zA-Z0-9_.-]+:\s*$)[\s\S])*)/m',
    $source,
    $blocks,
    PREG_SET_ORDER,
  );
  foreach ($blocks as $block) {
    $id = $block[1];
    if (!preg_match('/^\s*path:\s*[\'"]?([^\r\n\'"]+)/m', $block['block'], $match)) {
      continue;
    }
    $routePath = trim($match[1]);
    if (!aculta_is_admin_main_path($routePath)) {
      continue;
    }
    $checkedAdmin++;

    if (preg_match('/^\s*_aculta_domain_purpose:\s*[\'"]?([^\r\n\'"]+)/m', $block['block'], $purpose)) {
      $value = trim($purpose[1]);
      if ($value !== 'main') {
        $failures[] = "$path route $id declares _aculta_domain_purpose=$value; expected main";
      }
    }

    if (preg_match('/^\s*_aculta_domain_purpose_authoritative:\s*true\b/im', $block['block'])) {
      $failures[] = "$path route $id declares _aculta_domain_purpose_authoritative; administrative routes have no purpose exception";
    }
  }
}

if ($failures !== []) {
  fwrite(STDERR, "ADMIN ROUTES MAIN FAIL\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, " - $failure\n");
  }
  exit(1);
}

printf(
  "ADMIN ROUTES MAIN PASS (%d routing files, %d administrative/integration routes inspected)\n",
  $checkedFiles,
  $checkedAdmin,
);
