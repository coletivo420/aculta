<?php

declare(strict_types=1);

/**
 * Read-only Runtime gate for the ACULTA420 0.1.0 Foundation.
 */

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$themeRoot = $root . '/web/themes/custom/aculta420';
$checks = [];

$assert = static function (bool $condition, string $message) use (&$checks): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $checks[] = $message;
};

$assert(is_dir($themeRoot), 'ACULTA420 theme directory exists.');
$assert(!is_dir($root . '/web/themes/custom/aculta'), 'No legacy theme provider directory exists.');
$assert(!is_file($themeRoot . '/aculta420.theme'), 'Theme uses OOP hooks; no procedural .theme file exists.');
$assert(!is_file($themeRoot . '/css/style.css'), 'No generic css/style.css catch-all exists.');
$assert(!is_file($themeRoot . '/css/responsive.css'), 'No generic css/responsive.css catch-all exists.');
$assert(!is_file($themeRoot . '/templates/feed-icon.html.twig'), 'No unused feed-icon override exists.');

$themeHandler = \Drupal::service('theme_handler');
$assert($themeHandler->themeExists('aculta420'), 'ACULTA420 is discoverable by Drupal.');
$assert(\Drupal::config('system.theme')->get('default') === 'aculta420', 'ACULTA420 is the default public theme.');
$coreExtension = \Drupal::config('core.extension');
$assert($coreExtension->get('theme.aculta420') !== NULL, 'ACULTA420 is enabled in core.extension.');
$assert($coreExtension->get('theme.aculta') === NULL, 'No legacy theme provider remains enabled.');

$info = Yaml::parseFile($themeRoot . '/aculta420.info.yml');
$assert(($info['base theme'] ?? NULL) === 'bootstrap5', 'Bootstrap5 remains the sole base theme.');
$assert(($info['enforce_prop_schemas'] ?? FALSE) === TRUE, 'SDC prop schemas are enforced.');
$assert(($info['version'] ?? NULL) === '0.6.0-dev.7', 'Theme metadata marks the current version 0.6.0-dev.7.');
$forbiddenIntegrationModules = ['social_auth', 'social_auth_google', 'captcha', 'turnstile', 'key'];
$themeInfoDependencies = array_map(
  static fn(string $dependency): string => str_contains($dependency, ':')
    ? substr($dependency, strrpos($dependency, ':') + 1)
    : $dependency,
  $info['dependencies'] ?? [],
);
$assert(
  array_intersect($forbiddenIntegrationModules, $themeInfoDependencies) === [],
  'Theme info has no Social Auth, CAPTCHA, Turnstile, or Key dependencies.',
);

$themeBoundaryPatterns = [
  'social_auth' => '/social[-_]auth(?:[-_.][a-z0-9]+)*/i',
  'captcha' => '/captcha/i',
  'turnstile' => '/turnstile/i',
  'oauth_config' => '/GOOGLE_OAUTH_CLIENT_(?:ID|SECRET)|social_auth_google\.settings|social_auth\.network\.(?:redirect|callback)|\/oauth\/google/i',
];
$themeBoundaryCounts = array_fill_keys(array_keys($themeBoundaryPatterns), 0);
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($themeRoot, FilesystemIterator::SKIP_DOTS)) as $file) {
  if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'twig', 'yml', 'yaml', 'js', 'css'], TRUE)) {
    continue;
  }
  $source = file_get_contents($file->getPathname());
  foreach ($themeBoundaryPatterns as $boundary => $pattern) {
    if (preg_match($pattern, $source)) {
      $themeBoundaryCounts[$boundary]++;
    }
  }
}
$assert($themeBoundaryCounts['social_auth'] === 0, 'THEME SOCIAL AUTH REFERENCES: 0');
$assert($themeBoundaryCounts['captcha'] === 0, 'THEME CAPTCHA REFERENCES: 0');
$assert($themeBoundaryCounts['turnstile'] === 0, 'THEME TURNSTILE REFERENCES: 0');
$assert($themeBoundaryCounts['oauth_config'] === 0, 'THEME OAUTH CONFIG REFERENCES: 0');

$lock = json_decode(file_get_contents($root . '/composer.lock'), TRUE, 512, JSON_THROW_ON_ERROR);
$bootstrapPackage = array_values(array_filter(
  $lock['packages'] ?? [],
  static fn(array $package): bool => ($package['name'] ?? '') === 'drupal/bootstrap5',
))[0] ?? NULL;
$assert(($bootstrapPackage['version'] ?? NULL) === '4.0.8', 'Bootstrap5 remains pinned to the audited 4.0.8 release.');

$hookSourcePath = $themeRoot . '/src/Hook/ThemeHooks.php';
$assert(is_file($hookSourcePath), 'OOP theme hook class exists.');
$hookSource = file_get_contents($hookSourcePath);
$assert(class_exists(\Drupal\aculta420\Hook\ThemeHooks::class), 'Drupal can autoload the ACULTA420 hook class.');
$assert(!str_contains($hookSource, '\\Drupal::'), 'Theme hook class uses DI instead of the Drupal service locator.');
$assert(!str_contains($hookSource, 'aculta_portal'), 'Theme hook class has no direct dependency on aculta_portal.');

$libraries = Yaml::parseFile($themeRoot . '/aculta420.libraries.yml');
$libraryIntegrationDependencies = [];
foreach ($libraries as $libraryName => $definition) {
  foreach (($definition['css'] ?? []) as $group => $assets) {
    foreach (array_keys($assets ?? []) as $asset) {
      if (preg_match('#^https?://#', (string) $asset)) {
        continue;
      }
      $assert(is_file($themeRoot . '/' . $asset), 'Library asset exists: ' . $libraryName . ' -> ' . $asset);
    }
  }
  foreach (array_keys($definition['js'] ?? []) as $asset) {
    if (preg_match('#^https?://#', (string) $asset)) {
      continue;
    }
    $assert(is_file($themeRoot . '/' . $asset), 'Library asset exists: ' . $libraryName . ' -> ' . $asset);
  }
  foreach ($definition['dependencies'] ?? [] as $dependency) {
    $extension = explode('/', (string) $dependency, 2)[0];
    if (in_array($extension, $forbiddenIntegrationModules, TRUE)) {
      $libraryIntegrationDependencies[] = $dependency;
    }
  }
}
$assert(
  $libraryIntegrationDependencies === [],
  'Theme libraries do not depend on Social Auth, CAPTCHA, Turnstile, or Key libraries.',
);

$expectedWebAssets = [
  'aculta420-favicon.ico',
  'logo-aculta420-horizontal-branco-900x300.png',
  // Logo da barra institucional (0.6.0-dev.6): tamanhos 1x e 2x, consumidos pelo page.html.twig.
  'logo-aculta420-horizontal-branco-78x26.webp',
  'logo-aculta420-horizontal-branco-156x52.webp',
];
$actualWebAssets = [];
$webAssetRoot = $themeRoot . '/assets/branding/aculta420/web';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($webAssetRoot, FilesystemIterator::SKIP_DOTS)) as $asset) {
  if ($asset->isFile()) {
    $actualWebAssets[] = str_replace($webAssetRoot . DIRECTORY_SEPARATOR, '', $asset->getPathname());
  }
}
sort($actualWebAssets);
sort($expectedWebAssets);
$assert($actualWebAssets === $expectedWebAssets, 'Runtime branding exports contain only the favicon, the active default logo and the institution bar logo sizes.');

$themeSettings = \Drupal::config('aculta420.settings');
foreach (['institution_data_uuid', 'institution_home_nid', 'institution_transparency_nid'] as $functionalSetting) {
  $assert($themeSettings->get($functionalSetting) === NULL, 'Theme settings do not own functional institutional data: ' . $functionalSetting);
}
$installThemeSettings = Yaml::parseFile($themeRoot . '/config/install/aculta420.settings.yml');
$syncThemeSettings = Yaml::parseFile($root . '/config/sync/aculta420.settings.yml');
foreach (['logo', 'favicon'] as $assetSetting) {
  $assert(($installThemeSettings[$assetSetting]['path'] ?? NULL) === ($syncThemeSettings[$assetSetting]['path'] ?? NULL), 'Fresh-install and sync agree on theme ' . $assetSetting . '.');
}
$syncThemeSettingsForComparison = $syncThemeSettings;
unset($syncThemeSettingsForComparison['_core']);
$assert(
  $installThemeSettings === $syncThemeSettingsForComparison,
  'Fresh-install and sync agree on all ACULTA420 presentation settings.',
);

$portalSettings = \Drupal::config('aculta_portal.settings');
$assert(trim((string) $portalSettings->get('institution_data_uuid')) !== '', 'Portal owns the institutional block UUID.');
$assert((int) $portalSettings->get('institution_transparency_nid') > 0, 'Portal owns the institutional transparency page reference.');
// P2.1 moved the institutional token lookup from the removed .module to TokenHooks.
$portalModule = (string) file_get_contents(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Hook/TokenHooks.php');
$emptyUuidGuard = strpos($portalModule, "if (\$uuid === '')") ?: FALSE;
$uuidLookup = strpos($portalModule, "loadByProperties(['uuid' => \$uuid])") ?: FALSE;
$assert($emptyUuidGuard !== FALSE && $uuidLookup !== FALSE && $emptyUuidGuard < $uuidLookup, 'Institutional token lookup returns before storage access when its UUID configuration is empty.');

$syncCoreExtension = Yaml::parseFile($root . '/config/sync/core.extension.yml');
$syncSystemTheme = Yaml::parseFile($root . '/config/sync/system.theme.yml');
$assert(isset($syncCoreExtension['theme']['aculta420']), 'Config sync enables ACULTA420.');
$assert(!isset($syncCoreExtension['theme']['aculta']), 'Config sync contains no legacy theme provider.');
$assert(($syncSystemTheme['default'] ?? NULL) === 'aculta420', 'Config sync selects ACULTA420 as default.');

$configStorage = \Drupal::service('config.storage');
$blockContentStorage = \Drupal::entityTypeManager()->getStorage('block_content');
$validateBlockPlacement = static function (array $data, string $configName) use (
  $assert,
  $info,
  $blockContentStorage,
): void {
  $assert(
    ($data['theme'] ?? NULL) === 'aculta420',
    'ACULTA block placement uses the current provider: ' . $configName,
  );

  $regions = $info['regions'] ?? [];
  $assert(
    isset($regions[$data['region'] ?? '']),
    'ACULTA block placement uses a declared theme region: ' . $configName,
  );

  $plugin = (string) ($data['plugin'] ?? '');
  if (!str_starts_with($plugin, 'block_content:')) {
    return;
  }

  $uuid = substr($plugin, strlen('block_content:'));
  $assert(
    ($data['settings']['id'] ?? NULL) === $plugin,
    'Block content plugin and settings IDs match: ' . $configName,
  );
  $hasMatchingDependency = FALSE;
  foreach ($data['dependencies']['content'] ?? [] as $dependency) {
    if (str_ends_with($dependency, ':' . $uuid)) {
      $hasMatchingDependency = TRUE;
      break;
    }
  }
  $assert(
    $hasMatchingDependency,
    'Block content dependency matches the plugin UUID: ' . $configName,
  );
  $assert(
    (bool) $blockContentStorage->loadByProperties(['uuid' => $uuid]),
    'Block content UUID exists: ' . $configName,
  );
};

foreach ($configStorage->listAll('block.block.aculta_') as $configName) {
  $data = $configStorage->read($configName);
  $validateBlockPlacement($data, $configName);
}

foreach (glob($root . '/config/sync/block.block.aculta_*.yml') ?: [] as $path) {
  $data = Yaml::parseFile($path);
  $validateBlockPlacement($data, 'config/sync/' . basename($path));
}

$expectedTemplates = [
  'block--block-content--type--aculta-institution.html.twig',
  'block--system-branding-block.html.twig',
  // Página do produto da loja (0.6.0-dev.7): composição só com componentes SDC do tema.
  'commerce-product--produto-loja.html.twig',
  'aculta-section.html.twig',
  'content/off-canvas-page-wrapper.html.twig',
  'navigation/breadcrumb.html.twig',
  'node--editorial-highlight.html.twig',
  'node--project--teaser.html.twig',
  'page.html.twig',
  'views-view-unformatted--aculta-projects.html.twig',
  'views-view-unformatted--courses-catalog.html.twig',
  'views-view-vvjb.html.twig',
];
$actualTemplates = [];
$templateRoot = $themeRoot . '/templates';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templateRoot, FilesystemIterator::SKIP_DOTS)) as $template) {
  if ($template->isFile() && str_ends_with($template->getFilename(), '.html.twig')) {
    $actualTemplates[] = str_replace($templateRoot . DIRECTORY_SEPARATOR, '', $template->getPathname());
  }
}
sort($expectedTemplates);
sort($actualTemplates);
$assert($actualTemplates === $expectedTemplates, 'Twig override set matches the reviewed Foundation allowlist.');

$sdc = \Drupal::service('plugin.manager.sdc');
$assert($sdc->hasDefinition('aculta420:editorial-card'), 'Drupal discovers aculta420:editorial-card.');
$componentMetadata = Yaml::parseFile($themeRoot . '/components/content/editorial-card/editorial-card.component.yml');
$assert(($componentMetadata['status'] ?? NULL) === 'stable', 'Editorial card keeps its documented stable status.');
$assert(isset($componentMetadata['slots']) && is_array($componentMetadata['slots']), 'Editorial card exposes renderable content as slots.');
$projectMetadata = Yaml::parseFile($themeRoot . '/components/content/project-card/project-card.component.yml');
$assert(($projectMetadata['status'] ?? NULL) === 'experimental', 'Project card is experimental until it proves its contract (0.3.0).');
$assert($sdc->hasDefinition('aculta420:project-card'), 'Drupal discovers aculta420:project-card.');

$twig = \Drupal::service('twig');
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($themeRoot, FilesystemIterator::SKIP_DOTS)) as $file) {
  if (!$file->isFile()) {
    continue;
  }
  $extension = strtolower($file->getExtension());
  if (in_array($extension, ['yml', 'yaml'], TRUE)) {
    Yaml::parseFile($file->getPathname());
  }
  if ($extension === 'twig') {
    // SDC templates are compiled under their namespaced component id, e.g. aculta420:editorial-card.
    $componentName = basename($file->getFilename(), '.twig');
    $isComponent = preg_match('#/components/(content|patterns)/' . preg_quote($componentName, '#') . '/' . preg_quote($componentName, '#') . '\.twig$#', $file->getPathname()) === 1;
    $sourceName = $isComponent ? 'aculta420:' . $componentName : $file->getFilename();
    $source = new \Twig\Source(file_get_contents($file->getPathname()), $sourceName, $file->getPathname());
    $twig->compile($twig->parse($twig->tokenize($source)));
  }
}

$allCss = '';
$nonTokenCss = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($themeRoot, FilesystemIterator::SKIP_DOTS)) as $file) {
  if (!$file->isFile() || strtolower($file->getExtension()) !== 'css') {
    continue;
  }
  $source = file_get_contents($file->getPathname());
  $allCss .= "\n" . $source;
  if ($file->getPathname() !== $themeRoot . '/css/tokens.css') {
    $nonTokenCss .= "\n" . $source;
  }
}
$assert(!preg_match('/(?:^|})\s*a\s*\{|\.region-content\s+a\s*\{|\.node\s+a\s*\{|\.view-content\s+a\s*\{/m', $allCss), 'Theme has no broad editorial anchor selector.');
$assert(!preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)/', $nonTokenCss), 'Literal colors remain centralized in tokens.css.');
$assert(!preg_match('/\b\d+(?:\.\d+)?m?s\b/', $nonTokenCss), 'Motion durations remain centralized in tokens.css.');
foreach ([
  '--aculta-motion-fast: 180ms',
  '--aculta-ease-standard: ease',
  '--aculta-nav-current-bg: var(--aculta-yellow)',
  '--aculta-link-editorial: var(--aculta-red)',
] as $foundationToken) {
  $assert(str_contains($allCss, $foundationToken), 'Foundation token is present: ' . $foundationToken);
}

echo count($checks) . " ACULTA420 Foundation checks passed.\n";
