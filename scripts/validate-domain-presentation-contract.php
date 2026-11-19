<?php

/**
 * @file
 * Validates the bootstrapped Portal-to-theme presentation contract.
 */

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
$builderClass = (new ReflectionClass($builder))->getName();
$assert($builderClass === DomainPresentationBuilder::class, 'Domain presentation service uses the canonical builder.');
$domainSite = $container->get('aculta_portal.domain_site');
$isMainFrontPage = $container->get('path.matcher')->isFrontPage();
$assert(
  $domainSite->getCurrentSiteKey()?->value === 'main',
  'Run this gate with a MAIN site_key URI.',
);

$expected = [
  'main' => ['ACULTA', 'ACULTA'],
  'account' => ['Minha conta', 'Conta'],
  'support' => ['Apoio', 'Apoio'],
  'magazine' => ['Revista 420', 'Coletivo 420'],
  'wiki' => ['Wiki420', 'Wiki420'],
  'shop' => ['Loja', 'Loja'],
  'courses' => ['Cursos', 'Cursos'],
  'forum' => ['Fórum', 'Fórum'],
  'links' => ['Redes sociais da ACULTA', 'Redes sociais'],
  'eventos' => ['Eventos da ACULTA', 'Eventos'],
];

$unexpectedObjects = static function (mixed $value, string $path = '') use (&$unexpectedObjects): array {
  if (is_object($value)) {
    // Form API widgets may carry this Core helper inside their render array.
    // Domain, entity and service objects remain forbidden at the theme edge.
    return $value instanceof \Drupal\Core\Template\Attribute
      && $path === 'regions.wiki_search.preview.#props.attributes'
      ? []
      : [$path . ': ' . get_debug_type($value)];
  }
  if (is_array($value)) {
    $found = [];
    foreach ($value as $key => $item) {
      $childPath = $path === '' ? (string) $key : $path . '.' . (string) $key;
      $found = [...$found, ...$unexpectedObjects($item, $childPath)];
    }
    return $found;
  }
  return [];
};

foreach ($expected as $siteKey => [$title, $shortTitle]) {
  $presentation = $builder->build($siteKey);
  if ($presentation === NULL) {
    throw new RuntimeException('Presentation must build for site_key: ' . $siteKey);
  }

  $theme = $presentation->toThemeArray();
  $assert(
    array_keys($theme) === ['identity', 'regions', 'layout', 'domain_sites'],
    'Theme contract has identity, regions, layout and domain_sites: ' . $siteKey,
  );
  $assert(is_array($theme['domain_sites']), 'Domain sites are a neutral list: ' . $siteKey);
  if (!is_array($theme['layout']) || !is_array($theme['identity']) || !is_array($theme['regions'])) {
    throw new RuntimeException('Presentation sections must be arrays for site_key: ' . $siteKey);
  }
  $layout = $theme['layout'];
  $assert(
    array_keys($layout) === ['institution_bar_account', 'show_utility_bar', 'links_page', 'show_site_header', 'show_site_menu'],
    'Layout flags are the reviewed visibility set: ' . $siteKey,
  );
  $assert($layout['institution_bar_account'] === ($siteKey === 'account'), 'Account bar variant only for account: ' . $siteKey);
  $assert($layout['show_utility_bar'] === ($siteKey !== 'links'), 'Utility bar is hidden only for links: ' . $siteKey);
  $assert($layout['links_page'] === ($siteKey === 'links'), 'Links page layout only for links: ' . $siteKey);
  $assert(
    $layout['show_site_header'] === in_array($siteKey, ['main', 'wiki'], TRUE),
    'Domain Header is enabled only on MAIN and Wiki: ' . $siteKey,
  );
  $assert($layout['show_site_menu'] === ($siteKey === 'main'), 'Main-site menu is enabled only on MAIN: ' . $siteKey);
  $assert(
    array_keys($theme['identity']) === ['site_key', 'title', 'short_title', 'home_url', 'logo_alt'],
    'Identity contract has the stable key set: ' . $siteKey,
  );
  $assert(
    array_keys($theme['regions']) === ['brand_media', 'navigation', 'actions', 'wiki_search', 'showcase'],
    'Region contract has the stable key set: ' . $siteKey,
  );
  $assert($theme['identity']['site_key'] === $siteKey, 'Stable site_key is present in presentation: ' . $siteKey);
  $assert($theme['identity']['title'] === $title, 'Title matches presentation contract: ' . $siteKey);
  $assert($theme['identity']['short_title'] === $shortTitle, 'Short title matches presentation contract: ' . $siteKey);
  $assert($theme['identity']['logo_alt'] === $title, 'Brand fallback alt follows the presentation title: ' . $siteKey);
  $homeUrl = $theme['identity']['home_url'] ?? NULL;
  if (!is_string($homeUrl)) {
    throw new RuntimeException('Site home URL must be a string for site_key: ' . $siteKey);
  }
  $assert(
    preg_match('#^https?://[^/]+/#', $homeUrl) === 1,
    'Site home URL is absolute and prepared by the Portal: ' . $siteKey,
  );
  $assert(
    $unexpectedObjects($theme) === [],
    'Only the Form API Attribute helper is allowed in a render array at the Portal -> theme boundary: ' . $siteKey,
  );
  // Wiki brand/search have consumers; navigation/actions stay deferred.
  $brand = $theme['regions']['brand_media'];
  $brandIsWiki = $siteKey === 'wiki' ? is_array($brand) : $brand === NULL;
  $search = $theme['regions']['wiki_search'];
  $searchIsWiki = $search === NULL && $siteKey !== 'wiki';
  if ($siteKey === 'wiki' && is_array($search)) {
    $attributes = $search['#attributes'] ?? NULL;
    $query = $search['q'] ?? NULL;
    $cache = $search['#cache'] ?? NULL;
    $cacheContexts = is_array($cache) ? ($cache['contexts'] ?? NULL) : NULL;
    $searchIsWiki = ($search['#type'] ?? NULL) === 'form'
      && ($search['#method'] ?? NULL) === 'get'
      && is_array($attributes)
      && ($attributes['role'] ?? NULL) === 'search'
      && is_array($query)
      && ($query['#title_display'] ?? NULL) === 'invisible'
      && array_key_exists('#value', $query)
      && is_array($cacheContexts)
      && in_array('url.query_args:q', $cacheContexts, TRUE);
  }
  $assert(
    $brandIsWiki
      && $searchIsWiki
      && $theme['regions']['navigation'] === NULL
      && $theme['regions']['actions'] === NULL,
    'Wiki receives branded media and the shared GET search form; navigation/actions stay deferred: ' . $siteKey,
  );
  // MAIN showcase only on its front page; roots and cache tags follow Domains.
  $showcase = $theme['regions']['showcase'];
  $expectedShowcaseSiteKeys = array_values(array_diff(array_keys($expected), ['main']));
  $showcaseIsValid = $showcase === NULL;
  if ($siteKey === 'main' && $isMainFrontPage && is_array($showcase)) {
    $showcaseSiteKeys = [];
    $showcaseIsValid = TRUE;
    foreach ($showcase as $item) {
      if (!is_array($item)
        || array_keys($item) !== ['site_key', 'title', 'url']
        || !is_string($item['site_key'] ?? NULL)) {
        $showcaseIsValid = FALSE;
        break;
      }
      $showcaseSiteKeys[] = $item['site_key'];
    }
    $showcaseIsValid = $showcaseIsValid && $showcaseSiteKeys === $expectedShowcaseSiteKeys;
  }
  $assert(
    $showcaseIsValid,
    'Showcase is only offered on the MAIN front and lists site roots: ' . $siteKey,
  );
  if ($siteKey === 'main' && $isMainFrontPage && is_array($showcase)) {
    foreach ($showcase as $item) {
      if (!is_array($item)
        || !is_string($item['site_key'] ?? NULL)
        || !is_string($item['url'] ?? NULL)) {
        continue;
      }
      $rootUrl = $domainSite->pathUrl($item['site_key'], '/');
      $assert(
        $rootUrl !== NULL && $rootUrl->toString() === $item['url'],
        'Showcase URL is resolved for the configured environment: ' . $item['site_key'],
      );
      $targetDomain = $domainSite->getDomain($item['site_key']);
      if ($targetDomain === NULL) {
        throw new RuntimeException('Showcase Domain must exist: ' . $item['site_key']);
      }
      $assert(
        array_diff($targetDomain->getCacheTags(), $presentation->getCacheTags()) === [],
        'MAIN showcase varies with the target Domain configuration: ' . $item['site_key'],
      );
    }
    $magazine = array_values(array_filter(
      $showcase,
      static fn(mixed $item): bool => is_array($item) && ($item['site_key'] ?? NULL) === 'magazine',
    ));
    $assert(
      count($magazine) === 1 && ($magazine[0]['title'] ?? NULL) === 'Revista 420',
      'MAIN showcase includes Magazine.',
    );
  }

  $contexts = $presentation->getCacheContexts();
  foreach (['domain', 'languages:language_interface', 'url.site', 'url.path.is_front'] as $context) {
    $assert(in_array($context, $contexts, TRUE), 'Presentation carries cache context ' . $context . ': ' . $siteKey);
  }
  $domain = $domainSite->getDomain($siteKey);
  if ($domain === NULL) {
    throw new RuntimeException('Configured Domain entity must exist for site_key: ' . $siteKey);
  }
  $checks[] = 'Configured Domain entity exists for site_key: ' . $siteKey;
  $domainTags = $domain->getCacheTags();
  $assert($domainTags !== [], 'Domain entity exposes cache tags: ' . $siteKey);
  $assert(
    array_diff($domainTags, $presentation->getCacheTags()) === [],
    'Presentation includes the actual Domain entity cache tags: ' . $siteKey,
  );
}

if (!$isMainFrontPage) {
  echo "SHOWCASE: DEFERRED (Drush has no routed MAIN front-page request; Kernel test covers it).\n";
}

$assert($builder->build('unknown-site-key') === NULL, 'Unknown site_key fails closed without presentation fallback.');

$portalRoot = DRUPAL_ROOT . '/modules/custom/aculta_portal';
$themeRoot = DRUPAL_ROOT . '/themes/custom/aculta420_v2';
$builderSource = file_get_contents($portalRoot . '/src/Presentation/DomainPresentationBuilder.php');
$valueSource = file_get_contents($portalRoot . '/src/Presentation/DomainPresentation.php');
$hooksSource = file_get_contents($portalRoot . '/src/Hook/PortalHooks.php');
$themePresentationHooksSource = file_get_contents($themeRoot . '/src/Hook/PagePresentationHooks.php');
if (!is_string($builderSource)
  || !is_string($valueSource)
  || !is_string($hooksSource)
  || !is_string($themePresentationHooksSource)) {
  throw new RuntimeException('Unable to read a source file for the Portal presentation contract.');
}

$assert(!str_contains($builderSource, 'aculta420:'), 'Portal builder does not instantiate ACULTA420 SDCs.');
$assert(!str_contains($builderSource, 'DomainInterface'), 'Presentation builder does not expose DomainInterface.');
$assert(!str_contains($valueSource, "'cacheability'"), 'Theme array has no ad-hoc cacheability field.');
$assert(
  str_contains($hooksSource, "\$variables['domain_presentation']")
    && str_contains($hooksSource, 'addCacheableDependency'),
  'Portal preprocess exports the neutral contract and merges cacheability through Renderer API.',
);
$assert(
  !preg_match('/use\s+Drupal\\\\aculta_portal\\\\|@Autowire\(service:\s*[\'\"]aculta_portal\./', $themePresentationHooksSource),
  'Theme presentation hooks do not depend directly on Portal PHP services.',
);

echo 'DOMAIN PRESENTATION CONTRACT: PASS (' . count($checks) . " checks)\n";
