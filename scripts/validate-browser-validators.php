<?php

declare(strict_types=1);

/**
 * Gate for the browser validators (DT-T09): no DevTools port or site origin is hard-coded.
 *
 * Every scripts/*.mjs must read its endpoints through scripts/lib/browser-env.mjs, which
 * takes ACULTA_DEVTOOLS_PORT and ACULTA_SITE_ORIGIN from the environment. Run:
 *   php scripts/validate-browser-validators.php
 */

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

$helper = $root . '/scripts/lib/browser-env.mjs';
$checks++;
if (!is_file($helper)) {
  $failures[] = 'scripts/lib/browser-env.mjs is missing.';
}
else {
  $source = (string) file_get_contents($helper);
  foreach (['ACULTA_DEVTOOLS_PORT', 'ACULTA_SITE_ORIGIN'] as $variable) {
    $checks++;
    if (!str_contains($source, $variable)) {
      $failures[] = "The helper does not read $variable.";
    }
  }
}

// Literal ports, localhost origins and the old 9223/8080 defaults must not appear in validators.
$forbidden = '/localhost:\d{2,5}|127\.0\.0\.1:\d{2,5}|\b9223\b|\b9333\b/';
foreach (glob($root . '/scripts/*.mjs') ?: [] as $file) {
  $checks++;
  $source = (string) file_get_contents($file);
  if (preg_match_all($forbidden, $source, $matches) > 0) {
    $failures[] = basename($file) . ' hard-codes an endpoint: ' . implode(', ', array_unique($matches[0])) . '.';
  }
  // A validator that talks to the browser or the site must import the helper.
  $checks++;
  if (preg_match('/\b(fetch\(|new WebSocket\()/', $source) === 1 && !str_contains($source, "lib/browser-env.mjs")) {
    $failures[] = basename($file) . ' contacts the browser or the site without the browser-env helper.';
  }
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "BROWSER VALIDATORS FAIL: $failure\n");
  }
  exit(1);
}

echo sprintf("BROWSER VALIDATORS: PASS (%d checks)\n", $checks);
