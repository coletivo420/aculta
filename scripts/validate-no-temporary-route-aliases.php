<?php

/**
 * Reject temporary compatibility routes in all custom ACULTA modules.
 *
 * Permanent functional redirects (authentication, checkout, cross-purpose,
 * public short links and QR codes) are not compatibility route aliases.
 *
 * This gate reads code; it does not require a Drupal bootstrap.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$custom = $root . '/web/modules/custom';
$failures = [];
$checked = 0;

$obsoletePaths = [
  '/studio/Marley',
  '/studio/inicio',
  '/ganjalab',
  '/ganjalab420',
  '/studio/ganjalab420',
  '/minha-aprendizagem',
  '/pagamentos',
  '/enderecos',
  '/seguranca/email',
  '/seguranca/email/{user}',
];

if (!is_dir($custom)) {
  fwrite(STDERR, "ROUTE ALIASES FAIL: missing custom modules directory\n");
  exit(1);
}

$files = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($custom, FilesystemIterator::SKIP_DOTS),
);
foreach ($files as $file) {
  if (!$file->isFile() || !str_ends_with($file->getFilename(), '.routing.yml')) {
    continue;
  }
  $checked++;
  $path = $file->getPathname();
  $source = file_get_contents($path);
  if ($source === FALSE) {
    $failures[] = "$path could not be read";
    continue;
  }
  preg_match_all('/^([a-zA-Z0-9_.-]+):\\s*\\R(?<block>(?:(?!^[a-zA-Z0-9_.-]+:\\s*$)[\\s\\S])*)/m', $source, $blocks, PREG_SET_ORDER);
  foreach ($blocks as $block) {
    $id = $block[1];
    if (preg_match('/(?:^|[._-])(?:legacy|old|alias)(?:$|[._-])/i', $id)) {
      $failures[] = "$path contains obsolete route ID $id";
    }
    if (!preg_match('/^\\s*path:\\s*[\'"]?([^\r\n\'"]+)/m', $block['block'], $match)) {
      continue;
    }
    $routePath = trim($match[1]);
    if (in_array($routePath, $obsoletePaths, TRUE)
      || str_starts_with($routePath, '/painel-administrativo/conteudo/forum')) {
      $failures[] = "$path exposes obsolete alias $id at $routePath";
    }
  }
}

// Subscribers can publish redirects without separate routing definitions.
$domainRouteFile = $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php';
$domainRequestFile = $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainPurposeRequestSubscriber.php';
$domainRoute = @file_get_contents($domainRouteFile);
$domainRequest = @file_get_contents($domainRequestFile);
if ($domainRoute === FALSE || $domainRequest === FALSE) {
  $failures[] = 'Portal domain routing sources could not be read';
}
else {
  if (str_contains($domainRoute, 'PortalController::addressRedirect')
    || str_contains($domainRoute, 'PaymentMethodsRedirectController::redirectToOrders')) {
    $failures[] = 'Portal still exposes obsolete Commerce redirect aliases';
  }
  if (!str_contains($domainRequest, "$accountPath === '/minha-conta'")
    || !str_contains($domainRequest, 'Response::HTTP_NOT_FOUND')) {
    $failures[] = 'Technical account prefix must be rejected without an alias';
  }
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "ROUTE ALIASES FAIL: $failure\n");
  }
  fwrite(STDERR, sprintf("ROUTE ALIASES FAIL (%d problems across %d routing files)\n", count($failures), $checked));
  exit(1);
}

printf("ROUTE ALIASES PASS (%d custom routing files inspected)\n", $checked);
