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
$assert(($info['base theme'] ?? NULL) === 'bootstrap', 'Drupal Bootstrap remains the sole base theme.');
$assert(($info['enforce_prop_schemas'] ?? FALSE) === TRUE, 'SDC prop schemas are enforced.');
$assert(($info['version'] ?? NULL) === '0.6.0-dev.130', 'Theme metadata marks the current version 0.6.0-dev.130.');
$discussionCss = file_get_contents($themeRoot . '/css/components/discussion.css');
$assert(
  str_contains($discussionCss, '[data-aculta-discussion-announcement]')
  && str_contains($discussionCss, 'clip-path: inset(50%)'),
  'Discussion announcements are visually hidden by the theme while remaining available to assistive technology.',
);
$forumTopicPresenter = file_get_contents($themeRoot . '/templates/node--aculta-forum-topic.html.twig');
$forumTopicStyles = file_get_contents($themeRoot . '/css/components/forum-index.css');
$assert(
  str_contains($forumTopicPresenter, "'aculta-forum-topic--' ~ view_mode")
    && str_contains($forumTopicStyles, '.aculta-forum-topic--teaser')
    && str_contains($forumTopicStyles, '.aculta-forum-topic--full'),
  'Forum topic view-mode classes emitted by Twig have matching teaser and full styles.',
);
$assert(
  str_contains($forumTopicStyles, '.aculta-forum-topic-list-row__topic .field--name-body')
    && str_contains($forumTopicStyles, '.aculta-forum-topic-list-row__topic .aculta-author-card__joined')
    && str_contains($forumTopicStyles, 'grid-template-columns: minmax(0, 1fr) minmax(11rem, 13rem)'),
  'Forum topic list keeps its compact presentation inside Views rows only.',
);
$wikiPresenters = [
  file_get_contents($themeRoot . '/templates/node--wiki-entry--full.html.twig'),
  file_get_contents($themeRoot . '/templates/node--wiki-entry--teaser.html.twig'),
];
$assert(
  !str_contains(implode("\n", $wikiPresenters), 'author_name')
    && !str_contains(implode("\n", $wikiPresenters), 'author_picture'),
  'Wiki full and teaser presenters do not render author metadata.',
);
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
$bootstrapPackages = array_values(array_filter(
  $lock['packages'] ?? [],
  static fn(array $package): bool => ($package['name'] ?? '') === 'drupal/bootstrap',
));
$bootstrapPackage = $bootstrapPackages[0] ?? NULL;
$assert(count($bootstrapPackages) === 1 && ($bootstrapPackage['version'] ?? NULL) === '5.0.2', 'Drupal Bootstrap remains pinned to release 5.0.2.');
$bootstrapFrameworkPackages = array_values(array_filter(
  $lock['packages'] ?? [],
  static fn(array $package): bool => ($package['name'] ?? '') === 'twbs/bootstrap',
));
$assert(count($bootstrapFrameworkPackages) === 1 && ($bootstrapFrameworkPackages[0]['version'] ?? NULL) === 'v5.3.8', 'Bootstrap framework remains pinned to 5.3.8.');
$legacyBootstrapPackages = array_filter(
  $lock['packages'] ?? [],
  static fn(array $package): bool => in_array(($package['name'] ?? ''), ['drupal/bootstrap5', 'drupal/bootstrap_sass'], TRUE),
);
$assert($legacyBootstrapPackages === [], 'Composer has no legacy Bootstrap5 or SASS starter theme package.');
$bootstrapInfo = Yaml::parseFile($root . '/web/themes/contrib/bootstrap/bootstrap.info.yml');
$assert(($bootstrapInfo['version'] ?? NULL) === '5.0.2', 'Installed Drupal Bootstrap base theme matches the locked release.');

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
$adminTheme = (string) \Drupal::config('system.theme')->get('admin');
$assert(isset($syncCoreExtension['theme']['aculta420']), 'Config sync enables ACULTA420.');
$assert(!isset($syncCoreExtension['theme']['aculta']), 'Config sync contains no legacy theme provider.');
$assert(($syncSystemTheme['default'] ?? NULL) === 'aculta420', 'Config sync selects ACULTA420 as default.');
$assert(($syncSystemTheme['admin'] ?? NULL) === $adminTheme, 'Config sync and Runtime agree on the administrative theme.');

$configStorage = \Drupal::service('config.storage');
$blockContentStorage = \Drupal::entityTypeManager()->getStorage('block_content');
$validateBlockPlacement = static function (array $data, string $configName) use (
  $assert,
  $themeHandler,
  $adminTheme,
  $blockContentStorage,
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
  $expectedTheme = $adminOnly ? $adminTheme : 'aculta420';
  $placementTheme = (string) ($data['theme'] ?? '');
  $assert(
    $placementTheme === $expectedTheme,
    'ACULTA block placement uses its public or admin theme according to path visibility: ' . $configName,
  );

  $placementInfo = $themeHandler->themeExists($placementTheme)
    ? $themeHandler->getTheme($placementTheme)->info
    : [];
  $regions = $placementInfo['regions'] ?? [];
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
  // Formulários Commerce preservam a apresentação ACULTA dos dois panes de apoio.
  'commerce-checkout-pane--commerce-donation-pane.html.twig',
  'commerce-checkout-pane--commerce-memorial-pane.html.twig',
  // Página do produto da loja (0.6.0-dev.7): composição só com componentes SDC do tema.
  'commerce-product--produto-loja.html.twig',
  // Formulários nativos de doação e resumo do pedido.
  'aculta-donation-back.html.twig',
  'aculta-donation-donate.html.twig',
  'aculta-donation-quick.html.twig',
  'aculta-donation-summary-container.html.twig',
  'aculta-donation-summary.html.twig',
  'aculta-portal-account-terms-modal.html.twig',
  'aculta-portal-photo-editor.html.twig',
  'aculta-portal-shell.html.twig',
  'aculta-section.html.twig',
  'aculta-user-author-card.html.twig',
  'content/off-canvas-page-wrapper.html.twig',
  'navigation/breadcrumb.html.twig',
  'node--editorial-highlight.html.twig',
  'node--project--teaser.html.twig',
  // ACULTA Forum topic uses the aculta_user card and omits Core's unfiltered user picture/byline.
  'node--aculta-forum-topic.html.twig',
  // Shared discussion comment presenter added in 0.6.0-dev.96.
  'comment--aculta-discussion-post.html.twig',
  // Caixa do Google com logo do tema e estado "Conectado" (f96bb4d, já no main; a allowlist não foi atualizada na época).
  'login-with.html.twig',
  // Modelo padrão da Revista (0.6.0-dev.21): Notícia e blog chamam o mesmo componente revista-publication.
  'node--article--full.html.twig',
  'node--blog--full.html.twig',
  // Site Alert keeps native scheduling/access and delegates presentation to the theme SDC.
  'site-alert.html.twig',
  // Private Message thread wrapper exposes only its native rendered content and supplied read state.
  'private-message-thread.html.twig',
  // Wiki article and teaser presenters intentionally omit submitted-byline metadata.
  'node--wiki-entry--full.html.twig',
  'node--wiki-entry--teaser.html.twig',
  // Página pública LINKS e micropáginas usam presenters neutros e SDCs do tema.
  'node--social-links.html.twig',
  'aculta-links-page.html.twig',
  'aculta-links-landing.html.twig',
  // A View Growroom fornece os campos renderizados ao tema.
  'views-view-fields--aculta-forum-public-topics.html.twig',
  // Categories View fields are reassembled through the Forum category SDC.
  'views-view-fields--aculta-forum-categories--page-categories.html.twig',
  // The landing-page category block reuses the same neutral fields and SDC.
  'views-view-fields--aculta-forum-categories--block-landing.html.twig',
  // The categories page uses a grid wrapper while preserving each row's attributes.
  'views-view-unformatted--aculta-forum-categories--page-categories.html.twig',
  'page.html.twig',
  'portal-cart-status.html.twig',
  'portal-cart-panel.html.twig',
  'portal-payment-notice.html.twig',
  'portal-search-trigger.html.twig',
  'views-view-unformatted--aculta-projects.html.twig',
  'views-view-unformatted--courses-catalog.html.twig',
  'views-view-vvjb.html.twig',
  // Álbumes: os campos e a View permanecem Drupal; o tema compõe o SDC card overlay.
  'views-view-fields--aculta-albuns.html.twig',
  'views/views-view-table.html.twig',
  // Envelope HTML dos e-mails do aculta_mailer: cores dos tokens, claro e escuro inline (clientes de e-mail não carregam tokens.css).
  'aculta-mailer-wrap.html.twig',
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
$assert($sdc->hasDefinition('aculta420:card'), 'Drupal discovers aculta420:card.');
$assert($sdc->hasDefinition('aculta420:figure'), 'Drupal discovers aculta420:figure.');
$assert($sdc->hasDefinition('aculta420:quote'), 'Drupal discovers aculta420:quote.');
$assert($sdc->hasDefinition('aculta420:forum-category'), 'Drupal discovers aculta420:forum-category.');
$cardMetadata = Yaml::parseFile($themeRoot . '/components/patterns/card/card.component.yml');
$assert(isset($cardMetadata['variants']['overlay']), 'The composed card exposes the overlay presentation.');
$assert(is_file($themeRoot . '/templates/views-view-fields--aculta-albuns.html.twig'), 'Album View fields use the theme card presenter.');
$assert(
  is_file($themeRoot . '/templates/views-view-fields--aculta-forum-categories--page-categories.html.twig')
    && is_file($themeRoot . '/templates/views-view-fields--aculta-forum-categories--block-landing.html.twig')
    && is_file($themeRoot . '/templates/views-view-unformatted--aculta-forum-categories--page-categories.html.twig'),
  'Forum category View keeps the reviewed page and landing SDC presenters and responsive page wrapper.',
);
$assert($sdc->hasDefinition('aculta420:editorial-card'), 'Drupal discovers aculta420:editorial-card.');
$componentMetadata = Yaml::parseFile($themeRoot . '/components/content/editorial-card/editorial-card.component.yml');
$assert(($componentMetadata['status'] ?? NULL) === 'stable', 'Editorial card keeps its documented stable status.');
$assert(isset($componentMetadata['slots']) && is_array($componentMetadata['slots']), 'Editorial card exposes renderable content as slots.');
$projectMetadata = Yaml::parseFile($themeRoot . '/components/content/project-card/project-card.component.yml');
$assert(($projectMetadata['status'] ?? NULL) === 'experimental', 'Project card is experimental until it proves its contract (0.3.0).');
$assert($sdc->hasDefinition('aculta420:project-card'), 'Drupal discovers aculta420:project-card.');
$wikiMetadata = Yaml::parseFile($themeRoot . '/components/patterns/wiki-entry/wiki-entry.component.yml');
$assert(($wikiMetadata['status'] ?? NULL) === 'experimental', 'Wiki entry pattern remains experimental pending runtime and visual validation.');
$assert($sdc->hasDefinition('aculta420:wiki-entry'), 'Drupal discovers aculta420:wiki-entry.');
$messengerComponents = [
  'aculta420:messenger-list',
  'aculta420:messenger-item',
  'aculta420:messenger-compose',
  'aculta420:messenger-notification',
  'aculta420:institutional-notice',
  'aculta420:unread-badge',
  'aculta420:messenger-state',
];
foreach ($messengerComponents as $componentId) {
  $assert($sdc->hasDefinition($componentId), 'Drupal discovers ' . $componentId . '.');
}

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
    $themeRelativePath = str_replace($themeRoot . DIRECTORY_SEPARATOR, '', $file->getPathname());
    // Drupal's Twig loader resolves regular theme templates through the theme
    // namespace; the bare basename is not a registered loader key.
    $sourceName = $isComponent ? 'aculta420:' . $componentName : '@aculta420/' . $themeRelativePath;
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
