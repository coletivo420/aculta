<?php

declare(strict_types=1);

/**
 * Read-only Runtime gate for centralized checkout/payment Domain ownership.
 */

use Drupal\aculta_portal\Domain\DomainRoutePolicy;

$checks = [];
$assert = static function (bool $condition, string $message) use (&$checks): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $checks[] = $message;
};

$routeProvider = \Drupal::service('router.route_provider');
$routes = $routeProvider->getAllRoutes();

$centralRoutes = [];
foreach ($routes as $name => $route) {
  if (!DomainRoutePolicy::isCentralTransactionRouteName((string) $name)) {
    continue;
  }
  $centralRoutes[(string) $name] = $route;
  $assert(
    $route->getOption('_aculta_domain_purpose') === 'main',
    'Central commerce route belongs to MAIN: ' . $name,
  );
  if ($name !== 'commerce_payment.notify') {
    $assert(
      $route->getOption('_aculta_cross_domain_canonical_purpose') === 'main',
      'Browser-facing central commerce route advertises MAIN canonical navigation: ' . $name,
    );
  }
}

$assert($centralRoutes !== [], 'At least one central Commerce payment route was inspected.');

foreach (['commerce_cart.page', 'commerce_checkout.checkout', 'commerce_checkout.form'] as $required) {
  $assert(isset($centralRoutes[$required]), 'Required checkout route is central: ' . $required);
}

foreach (['commerce_payment.checkout.return', 'commerce_payment.checkout.cancel'] as $routeName) {
  if (isset($routes[$routeName])) {
    $assert(
      isset($centralRoutes[$routeName]),
      'Browser-facing payment callback route is central: ' . $routeName,
    );
  }
}

$notify = $routes['commerce_payment.notify'] ?? NULL;
$assert($notify !== NULL, 'Commerce payment notify route exists.');
$assert(
  $notify->getOption('_aculta_domain_purpose') === 'main',
  'Commerce payment notify belongs to MAIN.',
);
$methods = $notify->getMethods();
$assert(
  $methods === ['POST'],
  'Commerce payment notify is POST-only.',
);

$modules = \Drupal::moduleHandler();
if ($modules->moduleExists('aculta_donation')) {
  // Escolha do valor no apoio (SUPPORT); etapa de checkout da doação no MAIN (decisão de 2026-10-17).
  $donationRoutes = \Drupal::service('router.route_provider')->getRoutesByNames([
    'aculta_donation.donation.default',
    'aculta_donation.donation.quick',
    'aculta_donation.donation_controller_formPage',
  ]);
  $assert(count($donationRoutes) === 3, 'Donation flow routes exist.');
  foreach ($donationRoutes as $name => $route) {
    $esperado = \Drupal\aculta_portal\Domain\DomainRoutePolicy::purposeForDonationRoute($name);
    $assert($route->getOption('_aculta_domain_purpose') === $esperado, "Donation route {$name} belongs to the {$esperado} purpose.");
  }
}

$domainPurpose = \Drupal::service('aculta_portal.domain_purpose');
$checkoutUrl = $domainPurpose->routeUrl('main', 'commerce_checkout.checkout');
$assert($checkoutUrl !== NULL, 'Portal can generate absolute MAIN checkout URL.');
$assert(
  preg_match('#^https?://[^/]+/checkout(?:/)?$#', $checkoutUrl->toString()) === 1,
  'MAIN checkout URL is absolute and Domain-managed.',
);

$portalRoot = DRUPAL_ROOT . '/modules/custom/aculta_portal';
$routePolicySource = file_get_contents($portalRoot . '/src/Domain/DomainRoutePolicy.php');
$routeSubscriberSource = file_get_contents($portalRoot . '/src/EventSubscriber/DomainRouteSubscriber.php');
$requestSubscriberSource = file_get_contents($portalRoot . '/src/EventSubscriber/DomainPurposeRequestSubscriber.php');
$hooksSource = file_get_contents($portalRoot . '/src/Hook/PortalHooks.php');

foreach ([
  'commerce_cart.',
  'commerce_checkout.',
  'commerce_payment.checkout.',
  'commerce_payment.notify',
  'aculta_donation.',
] as $family) {
  $assert(
    str_contains($routePolicySource, $family),
    'Shared DomainRoutePolicy owns central commerce route family: ' . $family,
  );
}

$assert(
  str_contains($routeSubscriberSource, 'DomainRoutePolicy::isCentralTransactionRouteName'),
  'Route classification reuses the shared central commerce route policy.',
);
$assert(
  str_contains($requestSubscriberSource, 'getRouteCollectionForRequest')
    && str_contains($requestSubscriberSource, "getOption('_aculta_cross_domain_canonical_purpose')")
    && str_contains($requestSubscriberSource, "getOption('_admin_route')")
    && str_contains($requestSubscriberSource, "getOption('_aculta_domain_purpose')")
    && str_contains($requestSubscriberSource, "['GET', 'HEAD']"),
  'Wrong-host transaction routing uses route metadata, canonicalizes safe navigation, and fails mutations closed.',
);
$assert(
  str_contains($hooksSource, 'DomainRoutePolicy::isCentralTransactionRouteName')
    && str_contains($hooksSource, "routeUrl(
          'main'"),
  'Portal rewrites routed checkout/payment links directly to MAIN.',
);
$assert(
  str_contains($hooksSource, 'DomainRoutePolicy::isTransactionalSeoRouteName')
    && str_contains($hooksSource, 'DomainRoutePolicy::isDonationFlowRouteName'),
  'Transactional noindex metadata uses the centralized route policy.',
);
foreach ([
  'commerce_cart.',
  'commerce_checkout.',
  'commerce_payment.checkout.',
  'commerce_payment.notify',
  'aculta_donation.',
] as $family) {
  $assert(
    !str_contains($hooksSource, $family),
    'PortalHooks does not duplicate Commerce route family: ' . $family,
  );
}

foreach ([$routePolicySource, $routeSubscriberSource, $requestSubscriberSource, $hooksSource] as $source) {
  $assert(
    !str_contains($source, 'https://aculta.org')
      && !str_contains($source, '.toca.net.br'),
    'Payment Domain policy contains no hardcoded environment hostname.',
  );
}

echo 'PAYMENT DOMAIN POLICY: PASS (' . count($checks) . " checks)\n";
