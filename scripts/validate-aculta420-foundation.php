<?php

/**
 * @file
 * Read-only validation gate for the ACULTA420 V2 theme foundation.
 *
 * Portado da fundação V1 (aculta420) para o tema aculta420_v2, depois da
 * remoção do Bootstrap. O nome do arquivo é mantido para não quebrar as
 * referências em docs/.
 */

declare(strict_types=1);

use Drupal\aculta420_v2\Hook\MailEnvelopeHooks;
use Twig\Source;
use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);

// The gate reads Runtime through Drupal. Composer may autoload Drupal without
// bootstrapping it, so direct CLI invocation must also check the container.
// ACULTA_DRUSH_ROOT lets a worktree use the authorized Runtime checkout's Drush
// while this script still inspects files in its own checkout.
if (!class_exists(\Drupal::class) || !\Drupal::hasContainer()) {
  $drushRoot = getenv('ACULTA_DRUSH_ROOT') ?: $root;
  $drush = $drushRoot . '/vendor/drush/drush/drush.php';
  if (!is_file($drush)) {
    fwrite(STDERR, "ACULTA420 FOUNDATION FAIL: Drush não instalado (vendor/drush/drush/drush.php ausente).\n");
    exit(1);
  }
  passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($drush) . ' scr ' . escapeshellarg(__FILE__), $codigo);
  exit($codigo);
}

$themeName = 'aculta420_v2';
$themeRoot = $root . '/web/themes/custom/' . $themeName;
$checks = [];

$assert = static function (bool $condition, string $message) use (&$checks): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $checks[] = $message;
};

$assert(is_dir($themeRoot), 'ACULTA420 V2 theme directory exists.');
$assert(!is_dir($root . '/web/themes/custom/aculta420'), 'Legacy ACULTA420 V1 theme directory has been removed.');
$assert(!is_file($themeRoot . '/' . $themeName . '.theme'), 'Theme uses OOP hooks; no procedural .theme file exists.');
$assert(!is_file($themeRoot . '/css/style.css'), 'No generic css/style.css catch-all exists.');

$themeHandler = \Drupal::service('theme_handler');
$assert($themeHandler->themeExists($themeName), 'ACULTA420 V2 is discoverable by Drupal.');
$assert(\Drupal::config('system.theme')->get('default') === $themeName, 'ACULTA420 V2 is the default public theme.');
$coreExtension = \Drupal::config('core.extension');
$assert($coreExtension->get('theme.' . $themeName) !== NULL, 'ACULTA420 V2 is enabled in core.extension.');
$assert($coreExtension->get('theme.aculta420') === NULL, 'Legacy ACULTA420 V1 is not enabled in core.extension.');
$assert($coreExtension->get('theme.bootstrap') === NULL && $coreExtension->get('theme.bootstrap5') === NULL, 'No Bootstrap theme is enabled in core.extension.');
$assert($coreExtension->get('theme.aculta') === NULL, 'No legacy theme provider remains enabled.');

$info = Yaml::parseFile($themeRoot . '/' . $themeName . '.info.yml');
$assert(($info['base theme'] ?? NULL) === FALSE, 'ACULTA420 V2 is a base-less theme (no Bootstrap).');
$assert(($info['enforce_prop_schemas'] ?? FALSE) === TRUE, 'SDC prop schemas are enforced.');
$assert(is_string($info['version'] ?? NULL) && trim($info['version']) !== '', 'Theme metadata declares a non-empty version.');

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
$bootstrapPackages = array_filter(
  $lock['packages'] ?? [],
  static fn(array $package): bool => in_array(($package['name'] ?? ''), [
    'drupal/bootstrap',
    'drupal/bootstrap5',
    'drupal/bootstrap_sass',
  ], TRUE),
);
$assert($bootstrapPackages === [], 'Composer has no Drupal Bootstrap, Bootstrap5 or Bootstrap SASS package.');

$hookSourcePath = $themeRoot . '/src/Hook/MailEnvelopeHooks.php';
$assert(is_file($hookSourcePath), 'OOP mail envelope hook class exists in V2.');
$hookSource = file_get_contents($hookSourcePath);
$assert(class_exists(MailEnvelopeHooks::class), 'Drupal can autoload the ACULTA420 V2 mail envelope hook.');
$assert(!str_contains($hookSource, '\\Drupal::'), 'Mail envelope hook uses DI instead of the Drupal service locator.');
$assert(!str_contains($hookSource, 'aculta_portal'), 'Mail envelope hook has no direct dependency on aculta_portal.');
$assert(!str_contains($hookSource, 'aculta420.settings'), 'Mail envelope hook no longer reads the legacy aculta420.settings configuration.');

$libraries = Yaml::parseFile($themeRoot . '/' . $themeName . '.libraries.yml');
$libraryIntegrationDependencies = [];
foreach ($libraries as $libraryName => $definition) {
  foreach (($definition['css'] ?? []) as $assets) {
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
    if (in_array($extension, $forbiddenIntegrationModules, TRUE) || str_starts_with((string) $dependency, 'bootstrap5/')) {
      $libraryIntegrationDependencies[] = $dependency;
    }
  }
}
$assert(
  $libraryIntegrationDependencies === [],
  'Theme libraries do not depend on Social Auth, CAPTCHA, Turnstile, Key or Bootstrap libraries.',
);

// Branding: V2 owns its favicon and logo; config/sync points to them.
$assert(is_file($themeRoot . '/assets/icons/aculta420-favicon.ico'), 'V2 favicon asset exists.');
$assert(is_file($themeRoot . '/' . ($info['logo'] ?? '')), 'V2 logo declared in info exists.');

// A configuração da V1 (aculta420.settings e bootstrap.settings) foi
// removida: o V2 usa os próprio assets.
$assert(!is_file($root . '/config/sync/aculta420.settings.yml'), 'Config sync has no legacy aculta420.settings file.');
$assert(!is_file($root . '/config/sync/bootstrap.settings.yml'), 'Config sync has no legacy bootstrap.settings file.');
$assert(\Drupal::config('aculta420.settings')->isNew(), 'Runtime has no legacy aculta420.settings configuration.');

$portalSettings = \Drupal::config('aculta_portal.settings');
$assert(trim((string) $portalSettings->get('institution_data_uuid')) !== '', 'Portal owns the institutional block UUID.');
$assert((int) $portalSettings->get('institution_transparency_nid') > 0, 'Portal owns the institutional transparency page reference.');
// P2.1 moved the institutional token lookup to TokenHooks.
$portalModule = (string) file_get_contents(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Hook/TokenHooks.php');
$emptyUuidGuard = strpos($portalModule, "if (\$uuid === '')") ?: FALSE;
$uuidLookup = strpos($portalModule, "loadByProperties(['uuid' => \$uuid])") ?: FALSE;
$assert($emptyUuidGuard !== FALSE && $uuidLookup !== FALSE && $emptyUuidGuard < $uuidLookup, 'Institutional token lookup returns before storage access when its UUID configuration is empty.');

$syncCoreExtension = Yaml::parseFile($root . '/config/sync/core.extension.yml');
$syncSystemTheme = Yaml::parseFile($root . '/config/sync/system.theme.yml');
$adminTheme = (string) \Drupal::config('system.theme')->get('admin');
$assert(isset($syncCoreExtension['theme'][$themeName]), 'Config sync enables ACULTA420 V2.');
$assert(!isset($syncCoreExtension['theme']['aculta420']) && !isset($syncCoreExtension['theme']['bootstrap']), 'Config sync contains no V1 or Bootstrap theme.');
$assert(($syncSystemTheme['default'] ?? NULL) === $themeName, 'Config sync selects ACULTA420 V2 as default.');
$assert(($syncSystemTheme['admin'] ?? NULL) === $adminTheme, 'Config sync and Runtime agree on the administrative theme.');

$configStorage = \Drupal::service('config.storage');
$blockContentStorage = \Drupal::entityTypeManager()->getStorage('block_content');
$declaredRegions = $info['regions'] ?? [];
$validateBlockPlacement = static function (array $data, string $configName) use (
  $assert,
  $themeName,
  $adminTheme,
  $blockContentStorage,
  $declaredRegions,
): void {
  $paths = preg_split('/\R/', trim((string) ($data['visibility']['request_path']['pages'] ?? ''))) ?: [];
  $paths = array_values(array_filter(array_map('trim', $paths), static fn (string $path): bool => $path !== ''));
  $adminOnly = $paths !== [];
  foreach ($paths as $path) {
    if (str_starts_with($path, '!')
      || (!str_starts_with($path, '/admin/') && !str_starts_with($path, '/painel-administrativo/'))) {
      $adminOnly = FALSE;
      break;
    }
  }
  $expectedTheme = $adminOnly ? $adminTheme : $themeName;
  $assert(
    ($data['theme'] ?? '') === $expectedTheme,
    'ACULTA block placement uses its public or admin theme according to path visibility: ' . $configName,
  );
  $assert(
    isset($declaredRegions[$data['region'] ?? '']),
    'ACULTA block placement uses a declared V2 theme region: ' . $configName,
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
  $assert($hasMatchingDependency, 'Block content dependency matches the plugin UUID: ' . $configName);
  $assert(
    (bool) $blockContentStorage->loadByProperties(['uuid' => $uuid]),
    'Block content UUID exists: ' . $configName,
  );
};

foreach ($configStorage->listAll('block.block.' . $themeName . '_') as $configName) {
  $validateBlockPlacement($configStorage->read($configName), $configName);
}

foreach (glob($root . '/config/sync/block.block.' . $themeName . '_*.yml') ?: [] as $path) {
  $validateBlockPlacement(Yaml::parseFile($path), 'config/sync/' . basename($path));
}

// Templates: the V2 presenters the theme depends on must exist.
$requiredTemplates = [
  'layout/page.html.twig',
  'layout/html.html.twig',
  'aculta-mailer-wrap.html.twig',
  'content/node--aculta-forum-topic.html.twig',
  'views/views-view-fields--aculta-forum-public-topics.html.twig',
  'views/views-view-fields--aculta-forum-categories--page-categories.html.twig',
  'views/views-view-unformatted--aculta-forum-categories--page-categories.html.twig',
];
foreach ($requiredTemplates as $template) {
  $assert(is_file($themeRoot . '/templates/' . $template), 'Required V2 template exists: ' . $template);
}
$assert(!is_dir($themeRoot . '/templates/bootstrap'), 'No Bootstrap template overrides exist in V2.');

// SDC: each component folder has metadata and Drupal discovers it under
// the V2 namespace.
$sdc = \Drupal::service('plugin.manager.sdc');
foreach (glob($themeRoot . '/components/*', GLOB_ONLYDIR) ?: [] as $componentDir) {
  $componentName = basename($componentDir);
  $metadata = $componentDir . '/' . $componentName . '.component.yml';
  $assert(is_file($metadata), 'SDC metadata exists: ' . $componentName);
  $assert($sdc->hasDefinition($themeName . ':' . $componentName), 'Drupal discovers ' . $themeName . ':' . $componentName . '.');
}

// Twig: every template and component compiles.
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
    $componentName = basename($file->getFilename(), '.twig');
    // Só o template que tem o mesmo nome da pasta é componente SDC;
    // partials (ex.: _content) não são.
    $isComponent = basename(dirname($file->getPathname())) === $componentName && str_contains($file->getPathname(), '/components/');
    $sourceName = $isComponent
      ? $themeName . ':' . $componentName
      : '@' . $themeName . '/' . str_replace($themeRoot . DIRECTORY_SEPARATOR, '', $file->getPathname());
    $source = new Source(file_get_contents($file->getPathname()), $sourceName, $file->getPathname());
    $twig->compile($twig->parse($twig->tokenize($source)));
  }
}

// CSS: literal colors and motion durations stay in the V2 token file.
$tokensPath = $themeRoot . '/src/styles/tokens.css';
$assert(is_file($tokensPath), 'V2 design tokens file exists.');
$allCss = '';
$nonTokenCss = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($themeRoot, FilesystemIterator::SKIP_DOTS)) as $file) {
  if (!$file->isFile() || strtolower($file->getExtension()) !== 'css') {
    continue;
  }
  $source = file_get_contents($file->getPathname());
  $allCss .= "\n" . $source;
  if ($file->getPathname() !== $tokensPath) {
    $nonTokenCss .= "\n" . $source;
  }
}
$assert(!preg_match('/(?:^|})\s*a\s*\{|\.region-content\s+a\s*\{|\.node\s+a\s*\{|\.view-content\s+a\s*\{/m', $allCss), 'Theme has no broad editorial anchor selector.');
$assert(!preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)/', $nonTokenCss), 'Literal colors remain centralized in src/styles/tokens.css.');
$assert(!preg_match('/\b\d+(?:\.\d+)?m?s\b/', $nonTokenCss), 'Motion durations remain centralized in src/styles/tokens.css.');
foreach ([
  '--color-brand-primary:',
  '--color-canvas:',
  '--color-focus-ring:',
  '--color-link-editorial:',
] as $foundationToken) {
  $assert(str_contains($allCss, $foundationToken), 'Foundation token is present: ' . $foundationToken);
}

echo count($checks) . " ACULTA420 V2 Foundation checks passed.\n";
