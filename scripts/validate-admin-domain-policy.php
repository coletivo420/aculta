<?php

declare(strict_types=1);

/**
 * Read-only Runtime gate for the centralized ACULTA admin Domain policy.
 */

$checks = [];
$assert = static function (bool $condition, string $message) use (&$checks): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $checks[] = $message;
};

$routeProvider = \Drupal::service('router.route_provider');
$routes = $routeProvider->getAllRoutes();

$adminRoutes = 0;
$adminPrefixRoutes = 0;
foreach ($routes as $name => $route) {
  $path = $route->getPath();
  $isAdmin = (bool) $route->getOption('_admin_route');
  $isAdminPrefix = $path === '/painel-administrativo'
    || str_starts_with($path, '/painel-administrativo/');

  if ($isAdmin) {
    $adminRoutes++;
    $assert(
      $route->getOption('_aculta_domain_purpose') === 'main',
      'Admin route belongs to MAIN: ' . $name,
    );
  }

  if ($isAdminPrefix) {
    $adminPrefixRoutes++;
    $assert(
      $route->getOption('_aculta_domain_purpose') === 'main',
      'Admin-prefix route belongs to MAIN: ' . $name,
    );
  }
}

$assert($adminRoutes > 0, 'At least one Drupal admin route was inspected.');
$assert($adminPrefixRoutes > 0, 'At least one /painel-administrativo route was inspected.');

$userEdit = $routeProvider->getRouteByName('entity.user.edit_form');
$assert(
  $userEdit->getOption('_aculta_domain_purpose') === 'main',
  'entity.user.edit_form is canonically classified as MAIN.',
);
$assert(
  str_starts_with($userEdit->getPath(), '/painel-administrativo/'),
  'entity.user.edit_form uses the centralized admin path.',
);

$domainPurpose = \Drupal::service('aculta_portal.domain_purpose');
$adminUrl = $domainPurpose->pathUrl('main', '/painel-administrativo');
$assert($adminUrl !== NULL, 'DomainPurposeManager can build the MAIN admin URL.');
$assert(
  preg_match('#^https?://[^/]+/painel-administrativo$#', $adminUrl->toString()) === 1,
  'MAIN admin URL is absolute and Domain-managed.',
);

$portalRoot = DRUPAL_ROOT . '/modules/custom/aculta_portal';
$subscriberSource = file_get_contents(
  $portalRoot . '/src/EventSubscriber/DomainPurposeRequestSubscriber.php'
);
$routeSource = file_get_contents(
  $portalRoot . '/src/EventSubscriber/DomainRouteSubscriber.php'
);
$resolverSource = file_get_contents(
  $portalRoot . '/src/Domain/ContentPurposeResolver.php'
);
$themeRoot = DRUPAL_ROOT . '/themes/custom/aculta420';

$assert(
  str_contains($subscriberSource, 'canonicalMainNavigationResponse')
    && str_contains($subscriberSource, "pathUrl('main', \$request->getPathInfo())"),
  'Admin canonicalization uses DomainPurposeManager instead of a hardcoded host.',
);
$assert(
  str_contains($subscriberSource, "['GET', 'HEAD']")
    && str_contains($subscriberSource, 'return $this->notFoundResponse();'),
  'Only safe admin navigation redirects cross-domain; mutating methods fail closed.',
);
$assert(
  str_contains($subscriberSource, 'catch (MethodNotAllowedException)')
    && str_contains($subscriberSource, "\$this->domainPurposeManager->getCurrentPurpose() !== 'main'")
    && str_contains($subscriberSource, "\$event->setResponse(\$this->notFoundResponse())"),
  'Wrong-method requests to existing admin routes also fail closed on secondary purposes.',
);
$assert(
  str_contains($subscriberSource, '$request->query->all()')
    && str_contains($subscriberSource, "setOption('query', \$query)"),
  'Admin canonicalization preserves query parameters.',
);
$assert(
  substr_count($subscriberSource, 'isPasswordResetEditException(') >= 3,
  'Core password-reset exception is shared by both request stages.',
);
$assert(
  str_contains($subscriberSource, 'isValidCorePasswordResetRequest'),
  'Admin policy preserves Core one-time password-reset validation.',
);
$assert(
  str_contains($subscriberSource, 'notFoundResponse')
    && str_contains($subscriberSource, 'canonicalContentPurpose'),
  'Public wrong-purpose enforcement remains fail-closed.',
);
$assert(
  !str_contains($subscriberSource, 'https://aculta.org')
    && !str_contains($subscriberSource, 'http://aculta.org'),
  'Admin policy contains no hardcoded production hostname.',
);
$assert(
  !str_contains($subscriberSource, '.toca.net.br'),
  'Admin policy contains no hardcoded Homelab hostname.',
);
$assert(
  str_contains($routeSource, "\$accountRoutes['entity.user.edit_form']")
    && str_contains($routeSource, "\$route->setOption('_aculta_domain_purpose', 'main')"),
  'User edit is excluded from generic ACCOUNT classification and classified as MAIN.',
);
$adminPrecedence = strpos($resolverSource, "if (\$route->getOption('_admin_route')");
$wikiResolution = strpos($resolverSource, "in_array(\$routeName, self::WIKI_NODE_ROUTES");
$assert(
  $adminPrecedence !== FALSE
    && $wikiResolution !== FALSE
    && $adminPrecedence < $wikiResolution,
  'Content purpose resolution gives MAIN admin ownership precedence over Wiki/content ownership.',
);

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($themeRoot, FilesystemIterator::SKIP_DOTS)) as $file) {
  if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'twig', 'js'], TRUE)) {
    continue;
  }
  $source = file_get_contents($file->getPathname());
  $assert(
    !str_contains($source, '/painel-administrativo'),
    'Theme does not own admin-domain routing: ' . str_replace($themeRoot . '/', '', $file->getPathname()),
  );
}

echo 'ADMIN DOMAIN POLICY: PASS (' . count($checks) . " checks)\n";
