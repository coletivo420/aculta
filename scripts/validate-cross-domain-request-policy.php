<?php

declare(strict_types=1);

/**
 * Read-only Runtime gate for cross-domain request/redirect policy.
 */

$checks = [];
$assert = static function (bool $condition, string $message) use (&$checks): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $checks[] = $message;
};

$root = DRUPAL_ROOT . '/modules/custom/aculta_portal';
$domainSubscriber = file_get_contents($root . '/src/EventSubscriber/DomainPurposeRequestSubscriber.php');
$accountSubscriber = file_get_contents($root . '/src/EventSubscriber/AccountRouteSubscriber.php');
$portalController = file_get_contents($root . '/src/Controller/PortalController.php');

$assert(
  str_contains($domainSubscriber, 'TrustedRedirectResponse'),
  'Domain request policy uses TrustedRedirectResponse for cross-host targets.',
);
$assert(
  str_contains($domainSubscriber, "KernelEvents::RESPONSE => ['onResponse', 1]"),
  'Domain redirect retargeting runs before Core redirect safety at priority 0.',
);
$assert(
  str_contains($domainSubscriber, 'createFromRedirectResponse')
    && str_contains($domainSubscriber, 'setTrustedTargetUrl'),
  'Post-login/OAuth redirect retarget preserves the original redirect response.',
);
$assert(
  str_contains($accountSubscriber, 'TrustedRedirectResponse')
    && str_contains($accountSubscriber, "routeUrl('account'")
    && str_contains($accountSubscriber, "pathUrl('account', '/')"),
  'Account redirects use DomainPurposeManager and secured cross-host responses.',
);
$assert(
  str_contains($accountSubscriber, "['GET', 'HEAD']")
    && str_contains($accountSubscriber, "getCurrentPurpose() !== 'account'"),
  'Account guard blocks mutating cross-domain replay.',
);
$paymentNotification = \Drupal::service('router.route_provider')->getRouteByName('commerce_payment.notify');
$assert(
  $paymentNotification->getMethods() === ['POST']
    && $paymentNotification->getOption('_aculta_domain_purpose') === 'main',
  'Commerce payment notification remains POST-only and belongs to MAIN.',
);
$assert(
  str_contains($portalController, 'TrustedRedirectResponse')
    && str_contains($portalController, "pathUrl('account', '/')"),
  'Legacy user.page redirect is trusted and Domain-managed.',
);

foreach ([$domainSubscriber, $accountSubscriber, $portalController] as $source) {
  $assert(
    !str_contains($source, 'https://aculta.org')
      && !str_contains($source, '.toca.net.br'),
    'Cross-domain redirect code contains no hardcoded environment hostname.',
  );
}

$options = \Drupal::getContainer()->getParameter('session.storage.options');
$cookieDomain = is_array($options) ? trim((string) ($options['cookie_domain'] ?? '')) : '';
$sameSite = is_array($options) ? (string) ($options['cookie_samesite'] ?? '') : '';

$assert($cookieDomain !== '', 'Runtime defines a shared session cookie_domain.');
$assert(str_starts_with($cookieDomain, '.'), 'Shared session cookie_domain starts with a dot.');
$assert($sameSite === 'Lax', 'Runtime session cookie SameSite policy is Lax.');

$currentHost = \Drupal::request()->getHost();
$assert(
  $currentHost === '' || str_ends_with($currentHost, ltrim($cookieDomain, '.')),
  'Current Runtime host belongs to the configured shared cookie domain.',
);

$defaultServices = file_get_contents(DRUPAL_ROOT . '/sites/default/default.services.yml');
$assert(
  str_contains($defaultServices, 'cookie_samesite: Lax'),
  'Drupal baseline keeps SameSite=Lax.',
);

$accountJs = file_get_contents($root . '/js/search-preview.js');
$assert(
  str_contains($accountJs, "credentials: 'same-origin'"),
  'Portal AJAX explicitly uses same-origin credentials (search-preview.js).',
);

// Interceptação AJAX só para mesma origem: isInternalLink() compara a origem do link com a da página, e o handler de clique
// sai antes de preventDefault() quando o link não é interno. Links de outra origem seguem a navegação normal.
$navJs = file_get_contents($root . '/js/account-navigation.js');
$assert(
  str_contains($navJs, 'target.origin === window.location.origin') && str_contains($navJs, 'if (!isInternalLink(anchor)'),
  'Portal AJAX rejects cross-origin fetch interception and leaves cross-purpose links to normal navigation.',
);
$clickHandler = substr($navJs, (int) strpos($navJs, 'if (!isInternalLink(anchor)'), 160);
$assert(
  str_contains($clickHandler, 'return;') && strpos($navJs, 'if (!isInternalLink(anchor)') < strpos($navJs, 'navigate(anchor, anchor.href, true)'),
  'O handler de clique sai antes da interceptação quando o link não é da mesma origem.',
);

echo 'CROSS-DOMAIN REQUEST POLICY: PASS (' . count($checks) . " checks)\n";
