<?php

/**
 * @file
 * Read-only Runtime gate for route site-key metadata.
 */

declare(strict_types=1);

use Drupal\aculta_portal\Domain\DomainRoutePolicy;

$routes = \Drupal::service('router.route_provider')->getAllRoutes();
$invalid = [];
$siteKeyCount = 0;
$canonicalCount = 0;
$sharedCount = 0;

foreach ($routes as $name => $route) {
  if (DomainRoutePolicy::hasInvalidSiteKeyOptions($route)) {
    $invalid[] = (string) $name;
    continue;
  }
  if (DomainRoutePolicy::declaredSiteKey($route) !== NULL) {
    $siteKeyCount++;
  }
  if (DomainRoutePolicy::canonicalSiteKey($route) !== NULL) {
    $canonicalCount++;
  }
  if ($route->getOption('_aculta_site_keys') !== NULL) {
    $sharedCount++;
  }
}

if ($invalid !== []) {
  fwrite(STDERR, 'Invalid or removed site-key metadata: ' . implode(', ', $invalid) . "\n");
  exit(1);
}

$expectedLinksRoutes = [
  'aculta_linkweed.page_public',
  'aculta_linkweed.page_preview',
  'aculta_linkweed.page_qr_code',
  'aculta_linkweed.short_redirect',
  'aculta_linkweed.short_qr_code',
  'aculta_linkweed.landing',
];
if (\Drupal::moduleHandler()->moduleExists('aculta_linkweed')) {
  foreach ($expectedLinksRoutes as $name) {
    if (!isset($routes[$name]) || DomainRoutePolicy::declaredSiteKey($routes[$name]) !== 'links') {
      fwrite(STDERR, 'Links route is missing its site key: ' . $name . "\n");
      exit(1);
    }
  }
}

printf(
  "SITE KEY ROUTE OPTIONS: PASS (%d routes; %d owned, %d canonical, %d shared; no removed options)\n",
  count($routes),
  $siteKeyCount,
  $canonicalCount,
  $sharedCount,
);
