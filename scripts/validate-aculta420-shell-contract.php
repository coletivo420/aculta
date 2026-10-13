<?php

declare(strict_types=1);

/**
 * Read-only Runtime gate for ACULTA420 0.2-B.3/B.4 shell contract consumption.
 */

$root = dirname(__DIR__);
$themeRoot = $root . '/web/themes/custom/aculta420';
require_once $root . '/scripts/lib/Aculta420ShellContractAnalyzer.php';
$checks = [];

$assert = static function (bool $condition, string $message) use (&$checks): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $checks[] = $message;
};

$findings = Aculta420ShellContractAnalyzer::analyze($themeRoot);
$assert(
  $findings === [],
  'Static shell boundary analyzer passes: ' . ($findings === [] ? 'clean' : implode(' | ', $findings)),
);

$hookPath = $themeRoot . '/src/Hook/ThemeHooks.php';
$pagePath = $themeRoot . '/templates/page.html.twig';
$hookSource = file_get_contents($hookPath);
$pageSource = file_get_contents($pagePath);
$institutionBarCss = file_get_contents($themeRoot . '/css/components/institution-bar.css');
$headerCss = file_get_contents($themeRoot . '/css/components/header.css');

$assert(
  str_contains($pageSource, '<div class="aculta-institution-bar">')
    && !str_contains($pageSource, 'aculta-page-top'),
  'Institution Bar is not grouped with the Domain Header in a sticky wrapper.',
);
$assert(
  preg_match('/\.aculta-institution-bar\s*\{[^}]*position:\s*sticky\s*;/s', $institutionBarCss) === 1
    && preg_match('/\.aculta-header\s*\{[^}]*position:\s*sticky\s*;/s', $headerCss) !== 1,
  'Only the Institution Bar is sticky; the Domain Header scrolls with page content.',
);

$assert(
  str_contains($hookSource, "\$variables['domain_presentation']['identity']"),
  'ThemeHooks consumes the neutral domain_presentation identity contract.',
);
require_once $hookPath;
$hooks = new \Drupal\aculta420\Hook\ThemeHooks(
  \Drupal::service('path.matcher'),
  \Drupal::service('config.factory'),
  \Drupal::service('entity_type.manager'),
  \Drupal::routeMatch(),
  \Drupal::service('extension.list.theme'),
);

$complete = [
  'domain_presentation' => [
    'identity' => [
      'purpose' => 'wiki',
      'title' => 'Wiki420',
      'short_title' => 'Wiki420',
      'home_url' => 'https://wiki.example.test/',
      'logo_alt' => 'Wiki420',
    ],
    'regions' => [
      'brand_media' => NULL,
      'navigation' => NULL,
      'actions' => NULL,
      'wiki_search' => NULL,
    ],
  ],
  'page' => ['header' => []],
];
$hooks->preprocessPage($complete);
$assert(
  ($complete['aculta_domain_brand_fallback'] ?? NULL) === [
    'label' => 'Wiki420',
    'home_url' => 'https://wiki.example.test/',
  ],
  'Complete neutral identity is reduced to the minimal textual branding fallback.',
);

$assert(
  ($complete['aculta_has_system_branding'] ?? NULL) === FALSE,
  'Missing system branding block enables the neutral fallback path.',
);

$withHeader = [
  'domain_presentation' => $complete['domain_presentation'],
  'page' => [
    'header' => [
      'branding' => [
        '#plugin_id' => 'system_branding_block',
        '#markup' => 'Existing branding',
      ],
    ],
  ],
];
$hooks->preprocessPage($withHeader);
$assert(
  ($withHeader['aculta_has_system_branding'] ?? FALSE) === TRUE,
  'Existing system branding block remains the primary branding path.',
);

$partial = [
  'domain_presentation' => [
    'identity' => [
      'purpose' => 'wiki',
      'title' => 'Wiki420',
      'home_url' => '',
    ],
  ],
  'page' => ['header' => []],
];
$hooks->preprocessPage($partial);
$assert(
  array_key_exists('aculta_domain_brand_fallback', $partial)
    && $partial['aculta_domain_brand_fallback'] === NULL,
  'Incomplete identity fails safely to NULL instead of inventing functional fallback.',
);

$absent = ['page' => ['header' => []]];
$hooks->preprocessPage($absent);
$assert(
  array_key_exists('aculta_domain_brand_fallback', $absent)
    && $absent['aculta_domain_brand_fallback'] === NULL,
  'Absent domain_presentation preserves a NULL presentation fallback.',
);

$extra = [
  'domain_presentation' => [
    'identity' => [
      'purpose' => 'main',
      'title' => 'ACULTA',
      'short_title' => '',
      'home_url' => 'https://example.test/',
      'logo_alt' => '',
      'unexpected' => new stdClass(),
    ],
  ],
  'page' => ['header' => []],
];
$hooks->preprocessPage($extra);
$assert(
  ($extra['aculta_domain_brand_fallback'] ?? NULL) === [
    'label' => 'ACULTA',
    'home_url' => 'https://example.test/',
  ],
  'Theme adapter ignores unused logo_alt, empty short_title, and unknown/object fields.',
);

$portalContract = DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Presentation/DomainPresentationBuilder.php';
$portalSource = file_get_contents($portalContract);
$assert(
  !str_contains($portalSource, 'aculta420:'),
  'Portal presentation builder still has no ACULTA420 SDC dependency.',
);

echo 'ACULTA420 B.4 SHELL CONTRACT: PASS (' . count($checks) . " checks)\n";
