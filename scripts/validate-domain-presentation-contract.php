<?php

declare(strict_types=1);

/**
 * Read-only Runtime gate for ACULTA420 0.2-B Domain Presentation Contract.
 */

use Drupal\aculta_portal\Presentation\DomainPresentationBuilder;

$checks = [];
$assert = static function (bool $condition, string $message) use (&$checks): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $checks[] = $message;
};

$container = \Drupal::getContainer();
$assert($container->has('aculta_portal.presentation.domain'), 'Domain presentation service is registered.');

$builder = $container->get('aculta_portal.presentation.domain');
$assert($builder instanceof DomainPresentationBuilder, 'Domain presentation service uses the canonical builder.');
$domainPurpose = $container->get('aculta_portal.domain_purpose');

$expected = [
  'main' => ['ACULTA', 'ACULTA'],
  'account' => ['Minha conta', 'Conta'],
  'support' => ['Apoio', 'Apoio'],
  'magazine' => ['Observatório da Maconha Coletivo 420', 'Coletivo 420'],
  'wiki' => ['Wiki420', 'Wiki420'],
  'shop' => ['Loja', 'Loja'],
  'courses' => ['Cursos', 'Cursos'],
];

$containsObject = static function (mixed $value) use (&$containsObject): bool {
  if (is_object($value)) {
    return TRUE;
  }
  if (is_array($value)) {
    foreach ($value as $item) {
      if ($containsObject($item)) {
        return TRUE;
      }
    }
  }
  return FALSE;
};

foreach ($expected as $purpose => [$title, $shortTitle]) {
  $presentation = $builder->build($purpose);
  $assert($presentation !== NULL, 'Presentation builds for purpose: ' . $purpose);

  $theme = $presentation->toThemeArray();
  $assert(array_keys($theme) === ['identity', 'regions'], 'Theme contract has only identity and regions: ' . $purpose);
  $assert(
    array_keys($theme['identity']) === ['purpose', 'title', 'short_title', 'home_url', 'logo_alt'],
    'Identity contract has the stable key set: ' . $purpose,
  );
  $assert(
    array_keys($theme['regions']) === ['brand_media', 'navigation', 'actions', 'wiki_search'],
    'Region contract has the stable key set: ' . $purpose,
  );
  $assert($theme['identity']['purpose'] === $purpose, 'Purpose remains stable in presentation: ' . $purpose);
  $assert($theme['identity']['title'] === $title, 'Title matches presentation contract: ' . $purpose);
  $assert($theme['identity']['short_title'] === $shortTitle, 'Short title matches presentation contract: ' . $purpose);
  $assert($theme['identity']['logo_alt'] === $title, 'Brand fallback alt follows the presentation title: ' . $purpose);
  $assert(
    preg_match('#^https?://[^/]+/#', $theme['identity']['home_url']) === 1,
    'Purpose home URL is absolute and prepared by the Portal: ' . $purpose,
  );
  $assert(!$containsObject($theme), 'No object crosses the Portal -> theme boundary: ' . $purpose);
  // Wiki logo and search have real consumers; navigation and actions stay deferred.
  $brand = $theme['regions']['brand_media'];
  $brandIsWiki = $purpose === 'wiki' ? is_array($brand) : $brand === NULL;
  $search = $theme['regions']['wiki_search'];
  $searchIsWiki = $purpose === 'wiki'
    ? is_array($search)
      && ($search['#type'] ?? NULL) === 'form'
      && ($search['#method'] ?? NULL) === 'get'
      && ($search['#attributes']['role'] ?? NULL) === 'search'
      && ($search['q']['#title_display'] ?? NULL) === 'invisible'
      && array_key_exists('#value', $search['q'])
      && in_array('url.query_args:q', $search['#cache']['contexts'] ?? [], TRUE)
    : $search === NULL;
  $assert(
    $brandIsWiki
      && $searchIsWiki
      && $theme['regions']['navigation'] === NULL
      && $theme['regions']['actions'] === NULL,
    'Wiki receives branded media and the shared GET search form; navigation/actions stay deferred: ' . $purpose,
  );

  $contexts = $presentation->getCacheContexts();
  foreach (['domain', 'languages:language_interface', 'url.site'] as $context) {
    $assert(in_array($context, $contexts, TRUE), 'Presentation carries cache context ' . $context . ': ' . $purpose);
  }
  $domain = $domainPurpose->getDomain($purpose);
  $assert($domain !== NULL, 'Configured Domain entity exists for purpose: ' . $purpose);
  $domainTags = $domain->getCacheTags();
  $assert($domainTags !== [], 'Domain entity exposes cache tags: ' . $purpose);
  $assert(
    array_diff($domainTags, $presentation->getCacheTags()) === [],
    'Presentation includes the actual Domain entity cache tags: ' . $purpose,
  );
}

$assert($builder->build('unknown-purpose') === NULL, 'Unknown purpose fails closed without presentation fallback.');

$portalRoot = DRUPAL_ROOT . '/modules/custom/aculta_portal';
$themeRoot = DRUPAL_ROOT . '/themes/custom/aculta420';
$builderSource = file_get_contents($portalRoot . '/src/Presentation/DomainPresentationBuilder.php');
$valueSource = file_get_contents($portalRoot . '/src/Presentation/DomainPresentation.php');
$hooksSource = file_get_contents($portalRoot . '/src/Hook/PortalHooks.php');
$themeHooksSource = file_get_contents($themeRoot . '/src/Hook/ThemeHooks.php');

$assert(!str_contains($builderSource, 'aculta420:'), 'Portal builder does not instantiate ACULTA420 SDCs.');
$assert(!str_contains($builderSource, 'DomainInterface'), 'Presentation builder does not expose DomainInterface.');
$assert(!str_contains($valueSource, "'cacheability'"), 'Theme array has no ad-hoc cacheability field.');
$assert(
  str_contains($hooksSource, "\$variables['domain_presentation']")
    && str_contains($hooksSource, 'addCacheableDependency'),
  'Portal preprocess exports the neutral contract and merges cacheability through Renderer API.',
);
$assert(!str_contains($themeHooksSource, 'aculta_portal'), 'Theme hook class has no direct Portal dependency.');

echo 'DOMAIN PRESENTATION CONTRACT: PASS (' . count($checks) . " checks)\n";
