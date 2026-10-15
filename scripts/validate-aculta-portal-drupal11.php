<?php

declare(strict_types=1);

/**
 * Progressive static gate for the aculta_portal Drupal 11+ standard.
 *
 * The gate started as a P1 debt freeze and is now progressively hardened as
 * modernization phases remove legacy patterns. Resolved debt must not return,
 * while remaining ceilings stay explicit until later phases eliminate them.
 */

$root = dirname(__DIR__);
if (is_file($root . '/vendor/autoload.php')) {
  require_once $root . '/vendor/autoload.php';
}
require_once $root . '/web/modules/custom/aculta_portal/src/Status/PortalHelperRegistry.php';
require_once $root . '/web/modules/custom/aculta_portal/src/Status/PortalImplementationRegistry.php';
$moduleRoot = $root . '/web/modules/custom/aculta_portal';
$srcRoot = $moduleRoot . '/src';
$failures = [];
$checks = 0;

$check = static function (bool $condition, string $message) use (&$failures, &$checks): void {
  $checks++;
  if (!$condition) {
    $failures[] = $message;
  }
};

$read = static function (string $path) use (&$failures): string {
  $content = @file_get_contents($path);
  if ($content === FALSE) {
    $failures[] = 'Cannot read required file: ' . $path;
    return '';
  }
  return $content;
};

$check(is_dir($moduleRoot), 'aculta_portal module directory must exist.');
$check(is_dir($srcRoot), 'aculta_portal src directory must exist.');

$composerLock = $read($root . '/composer.lock');
$coreVersion = NULL;
$composerData = json_decode($composerLock, TRUE);
if (is_array($composerData)) {
  foreach (array_merge($composerData['packages'] ?? [], $composerData['packages-dev'] ?? []) as $package) {
    if (($package['name'] ?? '') === 'drupal/core-recommended') {
      $coreVersion = ltrim((string) ($package['version'] ?? ''), 'v');
      break;
    }
    if ($coreVersion === NULL && ($package['name'] ?? '') === 'drupal/core') {
      $coreVersion = ltrim((string) ($package['version'] ?? ''), 'v');
    }
  }
}
$check(
  is_string($coreVersion)
    && $coreVersion !== ''
    && version_compare($coreVersion, '11.3.0', '>=')
    && version_compare($coreVersion, '12.0.0', '<'),
  'composer.lock must pin Drupal Core within the supported 11.3+ major line.',
);

$installSource = $read($moduleRoot . '/aculta_portal.install');
$check(
  preg_match('/^function\\s+aculta_portal_requirements\\s*\\(/m', $installSource) !== 1,
  'Do not reintroduce deprecated hook_requirements(); use Drupal 11.2+ install/runtime/update requirements APIs.',
);
$info = $read($moduleRoot . '/aculta_portal.info.yml');
$check(
  preg_match('/^core_version_requirement:\s*\^11\.3\s*$/m', $info) === 1,
  'aculta_portal.info.yml must declare core_version_requirement: ^11.3.',
);

$requiredDocs = [
  $root . '/AGENTS.md' => 'docs/portal/DRUPAL-11-STANDARDS.md',
  $root . '/docs/ANTI-REGRESSION.md' => 'DRUPAL-11-STANDARDS.md',
  $root . '/docs/README.md' => 'DRUPAL-11-STANDARDS.md',
  $root . '/docs/portal/README.md' => 'DRUPAL-11-STANDARDS.md',
  $moduleRoot . '/README.md' => 'DRUPAL-11-STANDARDS.md',
  $moduleRoot . '/CHANGELOG.md' => 'Drupal 11+',
];
foreach ($requiredDocs as $path => $needle) {
  $content = $read($path);
  $check(
    str_contains($content, $needle),
    basename($path) . ' must reference the Drupal 11+ standard/change.',
  );
}
$portalReadme = $read($moduleRoot . '/README.md');
$requirementsController = $read($srcRoot . '/Controller/PortalRequirementsController.php');
$services = $read($moduleRoot . '/aculta_portal.services.yml');
$helperRegistrySource = $read($srcRoot . '/Status/PortalHelperRegistry.php');
$implementationRegistrySource = $read($srcRoot . '/Status/PortalImplementationRegistry.php');
$check(
  str_contains($portalReadme, 'Capacidades e extensões ativas')
    && str_contains($portalReadme, 'PublicRouteSlug::normalize()')
    && str_contains($portalReadme, 'filename_sanitization')
    && str_contains($info, 'slugs públicos canônicos')
    && str_contains($info, 'nomes de upload'),
  'New Portal capabilities must be documented in its README and module description.',
);
$filenameSubscriber = $read($srcRoot . '/EventSubscriber/UploadedFilenameExtensionSubscriber.php');
$check(
  str_contains($filenameSubscriber, 'FileUploadSanitizeNameEvent::class')
    && str_contains($filenameSubscriber, "['lowercaseExtension', -100]")
    && str_contains($filenameSubscriber, 'strtolower($extension)')
    && str_contains($services, 'aculta_portal.uploaded_filename_extension_subscriber:'),
  'Uploaded filename extension normalization must run after File sanitization and before Core security validation.',
);
$check(
  str_contains($requirementsController, 'buildActivePortalFunctions')
    && str_contains($requirementsController, 'getModuleList()')
    && str_contains($requirementsController, 'InfoParserInterface')
    && str_contains($requirementsController, '->parse(')
    && str_contains($requirementsController, 'config:core.extension')
    && str_contains($requirementsController, "['description'] ?? '')"),
  'Portal Status must list enabled Portal extensions from Drupal metadata with extension cache tags.',
);
$check(
  str_contains($requirementsController, 'buildPortalImplementations')
    && str_contains($requirementsController, 'Helpers ativos')
    && str_contains($requirementsController, 'Extras aplicados')
    && str_contains($implementationRegistrySource, 'service_id_collector') === FALSE
    && str_contains($services, 'tag: aculta_portal.helper')
    && str_contains($services, 'tag: aculta_portal.implementation')
    && is_file($root . '/docs/portal/IMPLEMENTATIONS.md')
    && str_contains($read($root . '/docs/portal/IMPLEMENTATIONS.md'), 'service_id_collector'),
  'Portal Status must expose documented helper and applied implementation inventories sourced from active service tags.',
);
if (class_exists(Drupal\Component\Serialization\Yaml::class)) {
  $serviceData = Drupal\Component\Serialization\Yaml::decode($services) ?: [];
  // Optional submodules own their services; Status collects active IDs only.
  foreach (glob($moduleRoot . '/modules/*/*.services.yml') ?: [] as $submoduleServicesPath) {
    $submoduleServices = Drupal\Component\Serialization\Yaml::decode($read($submoduleServicesPath)) ?: [];
    $serviceData['services'] = array_merge($serviceData['services'] ?? [], $submoduleServices['services'] ?? []);
  }
  foreach ([
    'aculta_portal.helper' => Drupal\aculta_portal\Status\PortalHelperRegistry::definitions(),
    'aculta_portal.implementation' => Drupal\aculta_portal\Status\PortalImplementationRegistry::definitions(),
  ] as $tagName => $definitions) {
    $tagged = [];
    foreach (($serviceData['services'] ?? []) as $serviceId => $definition) {
      foreach (($definition['tags'] ?? []) as $tag) {
        if (($tag['name'] ?? NULL) === $tagName) {
          $tagged[] = $serviceId;
        }
      }
    }
    sort($tagged);
    $documented = array_keys($definitions);
    sort($documented);
    $check($tagged === $documented, sprintf('%s tags and Status registry metadata must match exactly.', $tagName));
    foreach ($definitions as $serviceId => $metadata) {
      $check(
        is_file($root . '/' . $metadata['docs']),
        sprintf('%s (%s) must reference existing documentation.', $tagName, $serviceId),
      );
    }
  }
}
foreach (glob($moduleRoot . '/modules/*/*.info.yml') ?: [] as $extensionInfoPath) {
  $extensionInfo = $read($extensionInfoPath);
  $extensionDir = dirname($extensionInfoPath);
  $hasDocs = is_file($extensionDir . '/README.md') || (glob($extensionDir . '/docs/*.md') ?: []) !== [];
  $extensionName = basename($extensionDir);
  $check(
    preg_match('/^name:\s*.+$/m', $extensionInfo) === 1
      && preg_match('/^description:\s*.+$/m', $extensionInfo) === 1
      && $hasDocs,
    $extensionName . ' must declare its summary and have a README or docs page; enabled extensions appear in Portal Status.',
  );
}
$check(
  is_file($root . '/docs/portal/DRUPAL-11-STANDARDS.md'),
  'Normative docs/portal/DRUPAL-11-STANDARDS.md must exist.',
);

$legacySubscriberTags = preg_match_all('/name:\s*kernel\.event_subscriber\b/', $services);
// P7.2: Core renames kernel.event_subscriber <-> event_subscriber in
// RegisterEventSubscribersPass, so the legacy tag is redundant. Listener
// registration was verified identical before and after the migration.
$check(
  is_int($legacySubscriberTags) && $legacySubscriberTags === 0,
  'Portal subscribers must use the canonical event_subscriber tag; legacy kernel.event_subscriber is not allowed.',
);
// DT-P21 adds LmsFriendlyRouteSubscriber (route path for LMS slugs). The count is pinned so
// that every new subscriber is a deliberate change. 0.2.0-dev.16 adds EmailConfirmationSubscriber
// (OAuth login and user creation mark the email as confirmed; política de confirmação de e-mail).
// 0.3.0-dev.4 adds CartFriendlyRouteSubscriber (cart route path /carrinho, slug em português).
// 0.3.0-dev.5 adds DonationFriendlyRouteSubscriber (donation route paths /apoiar, slug em português).
// 0.4.0-dev.18 adds UploadedFilenameExtensionSubscriber after Core's configured filename sanitizer.
// 0.4.0-dev.19 adjusts the account-owned Messenger conversation presentation.
$check(
  preg_match_all('/name:\s*event_subscriber\b/', $services) === 12,
  'Portal must register exactly twelve canonical event_subscriber tags.',
);

$legacyProceduralFunctions = [];

$tokenHooks = $srcRoot . '/Hook/TokenHooks.php';
$tokenHooksSource = $read($tokenHooks);
$check(is_file($tokenHooks), 'P2 TokenHooks class must exist.');
$check(
  substr_count($tokenHooksSource, "#[Hook('token_info')]") === 1,
  'P2 TokenHooks must implement token_info as an OOP hook.',
);
$check(
  substr_count($tokenHooksSource, "#[Hook('tokens')]") === 1,
  'P2 TokenHooks must implement tokens as an OOP hook.',
);

$editorialHooks = $srcRoot . '/Hook/EditorialHooks.php';
$editorialHooksSource = $read($editorialHooks);
$check(is_file($editorialHooks), 'P2.2 EditorialHooks class must exist.');
foreach (['metatag_tags_alter', 'node_presave', 'metatags_alter', 'library_info_alter'] as $hookName) {
  $check(
    substr_count($editorialHooksSource, "#[Hook('" . $hookName . "')]") === 1,
    'P2 EditorialHooks must implement ' . $hookName . ' as an OOP hook.',
  );
}

$hookRuntimeSources = '';
foreach (glob($srcRoot . '/Hook/*.php') ?: [] as $hookFile) {
  $hookRuntimeSources .= $read($hookFile);
}
$p2MigratedHooks = [
  'token_info',
  'tokens',
  'metatag_tags_alter',
  'node_presave',
  'metatags_alter',
  'library_info_alter',
];
$p2HookOwners = [
  'token_info' => $tokenHooksSource,
  'tokens' => $tokenHooksSource,
  'metatag_tags_alter' => $editorialHooksSource,
  'node_presave' => $editorialHooksSource,
  'metatags_alter' => $editorialHooksSource,
  'library_info_alter' => $editorialHooksSource,
];
// Drupal 11.1+ runs every #[Hook] method of a module, so distinct concerns may
// live in different classes (e.g. PortalHooks owns route-based metatags_alter
// and login/Profile form_alter). The migrated implementation must exist
// exactly once in its owning class.
foreach ($p2MigratedHooks as $hookName) {
  $check(
    substr_count($p2HookOwners[$hookName], "#[Hook('" . $hookName . "')]") === 1,
    'P2-R migrated hook must be implemented exactly once: ' . $hookName,
  );
}
$check(
  preg_match('/declare\\s*\\(\\s*strict_types\\s*=\\s*1\\s*\\)\\s*;/', $tokenHooksSource) === 1
    && preg_match('/declare\\s*\\(\\s*strict_types\\s*=\\s*1\\s*\\)\\s*;/', $editorialHooksSource) === 1,
  'P2-R TokenHooks and EditorialHooks must keep strict_types=1.',
);
$check(
  !str_contains($tokenHooksSource, '\\Drupal::')
    && !str_contains($editorialHooksSource, '\\Drupal::'),
  'P2-R migrated hook classes must not use Drupal static service locators.',
);
$check(
  preg_match('/function\\s+tokenInfo\\s*\\(\\s*\\)\\s*:\\s*array/', $tokenHooksSource) === 1,
  'P2-R token_info signature must return array.',
);
$check(
  preg_match('/function\\s+tokens\\s*\\([\\s\\S]*?BubbleableMetadata\\s+\\$metadata,?\\s*\\)\\s*:\\s*array/', $tokenHooksSource) === 1,
  'P2-R tokens signature must preserve BubbleableMetadata and array return.',
);
$check(
  preg_match('/function\\s+metatagTagsAlter\\s*\\(array\\s*&\\$definitions\\)\\s*:\\s*void/', $editorialHooksSource) === 1,
  'P2-R metatag_tags_alter signature must preserve definitions by reference.',
);
$check(
  preg_match('/function\\s+nodePresave\\s*\\(NodeInterface\\s+\\$node\\)\\s*:\\s*void/', $editorialHooksSource) === 1,
  'P2-R node_presave signature must preserve NodeInterface.',
);
$check(
  preg_match('/function\\s+metatagsAlter\\s*\\(array\\s*&\\$tags,\\s*array\\s*&\\$context\\)\\s*:\\s*void/', $editorialHooksSource) === 1,
  'P2-R metatags_alter must preserve both tags and context references.',
);
$check(
  preg_match('/function\\s+libraryInfoAlter\\s*\\(array\\s*&\\$libraries,\\s*string\\s+\\$extension\\)\\s*:\\s*void/', $editorialHooksSource) === 1,
  'P2-R library_info_alter signature must match Drupal 11.',
);
foreach ([
  'config.factory',
  'entity_type.manager',
  'aculta_portal.domain_purpose',
  'domain.negotiator',
  'request_stack',
  'file_url_generator',
  'string_translation',
] as $serviceId) {
  $check(
    str_contains($tokenHooksSource, "service: '" . $serviceId . "'"),
    'P2-R TokenHooks must keep explicit DI for service ' . $serviceId . '.',
  );
}
$check(
  str_contains($tokenHooksSource, '$metadata->addCacheableDependency($settings)')
    && str_contains($tokenHooksSource, '$metadata->addCacheableDependency($node)'),
  'P2-R TokenHooks must keep config and node cacheability.',
);
$check(
  str_contains($editorialHooksSource, "'aculta_portal/cep-address'")
    && str_contains($editorialHooksSource, 'SchemaMetatagManager::serialize')
    && str_contains($editorialHooksSource, "'courses'")
    && str_contains($editorialHooksSource, "'wiki'"),
  'P2-R editorial/CEP/Domain behavior invariants must remain present.',
);
$entitySaveHooks = $srcRoot . '/Hook/EntitySaveHooks.php';
$entitySaveHooksSource = $read($entitySaveHooks);
$check(is_file($entitySaveHooks), 'P4.3 EntitySaveHooks class must exist.');
$check(
  substr_count($hookRuntimeSources, "#[Hook('entity_presave')]") === 1,
  'P4.3 entity_presave must be implemented exactly once as an OOP hook.',
);
$check(
  preg_match(
    '/function\\s+entityPresave\\s*\\(EntityInterface\\s+\\$entity\\)\\s*:\\s*void/',
    $entitySaveHooksSource,
  ) === 1,
  'P4.3 EntitySaveHooks::entityPresave() must preserve the Drupal 11 hook_entity_presave signature.',
);
$check(
  preg_match('/declare\\s*\\(\\s*strict_types\\s*=\\s*1\\s*\\)\\s*;/', $entitySaveHooksSource) === 1,
  'P4.3 EntitySaveHooks must declare strict_types=1.',
);
$check(
  !str_contains($entitySaveHooksSource, '\\Drupal::'),
  'P4.3 EntitySaveHooks must not use Drupal static service locators.',
);
$check(
  !str_contains($services, 'Drupal\\aculta_portal\\Hook\\EntitySaveHooks'),
  'P4.3 Hook classes are auto-discovered/autowired; do not add redundant EntitySaveHooks YAML service definitions.',
);
foreach ([
  "'commerce_payment_gateway'",
  'MercadoPagoCredentials::GATEWAY_ID',
  'MercadoPagoCredentials::hasRuntimeCredentials',
  'MercadoPagoCredentials::currentEnvironment',
  'LogicException',
] as $invariant) {
  $check(
    str_contains($entitySaveHooksSource, $invariant),
    'P4.3 Mercado Pago presave invariant must remain present: ' . $invariant,
  );
}

$entityHooks = $srcRoot . '/Hook/EntityHooks.php';
$entityHooksSource = $read($entityHooks);
$check(is_file($entityHooks), 'P4.2 EntityHooks class must exist.');
$check(
  substr_count($hookRuntimeSources, "#[Hook('entity_access')]") === 1,
  'P4.2 entity_access must be implemented exactly once as an OOP hook.',
);
$check(
  preg_match(
    '/function\\s+entityAccess\\s*\\(\\s*EntityInterface\\s+\\$entity,\\s*\\$operation,\\s*AccountInterface\\s+\\$account,?\\s*\\)\\s*:\\s*AccessResultInterface/',
    $entityHooksSource,
  ) === 1,
  'P4.2 EntityHooks::entityAccess() must preserve the Drupal 11 hook_entity_access signature.',
);
$check(
  preg_match('/declare\\s*\\(\\s*strict_types\\s*=\\s*1\\s*\\)\\s*;/', $entityHooksSource) === 1,
  'P4.2 EntityHooks must declare strict_types=1.',
);
$check(
  !str_contains($entityHooksSource, '\\Drupal::'),
  'P4.2 EntityHooks must use explicit DI instead of Drupal static service locators.',
);
foreach (['aculta_portal.domain_purpose', 'current_route_match', 'request_stack'] as $serviceId) {
  $check(
    str_contains($entityHooksSource, "service: '" . $serviceId . "'"),
    'P4.2 EntityHooks must inject service ' . $serviceId . '.',
  );
}
$check(
  !str_contains($services, 'Drupal\\aculta_portal\\Hook\\EntityHooks'),
  'P4.2 Hook classes are auto-discovered/autowired; do not add redundant EntityHooks YAML service definitions.',
);
foreach ([
  "'wiki_entry'",
  "'wiki_category'",
  "'domain'",
  "'entity.user.edit_form'",
  "'administer users'",
  'AccountRouteSubscriber::isValidCorePasswordResetRequest',
  "['route', 'user']",
  'setCacheMaxAge(0)',
  "'commerce_payment_gateway'",
  "'mercado_pago'",
] as $invariant) {
  $check(
    str_contains($entityHooksSource, $invariant),
    'P4.2 entity access invariant must remain present: ' . $invariant,
  );
}
$check(
  preg_match(
    '/AccessResult::neutral\\(\\)[\\s\\S]{0,220}?cachePerPermissions\\(\\)[\\s\\S]{0,220}?addCacheContexts\\(\\[\'route\', \'user\'\\]\\)[\\s\\S]{0,220}?setCacheMaxAge\\(0\\)/',
    $entityHooksSource,
  ) === 1,
  'P4.2 valid password-reset neutral result must remain request-sensitive and uncacheable.',
);

$formHooks = $srcRoot . '/Hook/FormHooks.php';
$formHooksSource = $read($formHooks);
$check(is_file($formHooks), 'P4.1 FormHooks class must exist.');
$check(
  substr_count($formHooksSource, "#[Hook('form_alter')]") === 1,
  'P4.1 form_alter must be implemented exactly once as an OOP hook.',
);
$check(
  !str_contains($hookRuntimeSources, '#[FormAlter'),
  'P4.1 removed Drupal 11.2 #[FormAlter] attribute must not be used; use #[Hook(\'form_alter\')].',
);
$check(
  preg_match(
    '/function\\s+formAlter\\s*\\(array\\s*&\\$form,\\s*FormStateInterface\\s+\\$formState,\\s*string\\s+\\$formId\\s*\\)\\s*:\\s*void/',
    $formHooksSource,
  ) === 1,
  'P4.1 FormHooks::formAlter() must match the Drupal 11 hook_form_alter signature.',
);
$check(
  preg_match('/declare\\s*\\(\\s*strict_types\\s*=\\s*1\\s*\\)\\s*;/', $formHooksSource) === 1,
  'P4.1 FormHooks must declare strict_types=1.',
);
$check(
  !str_contains($formHooksSource, '\\Drupal::'),
  'P4.1 FormHooks must use explicit DI instead of Drupal static service locators.',
);
foreach (['current_route_match', 'current_user', 'string_translation'] as $serviceId) {
  $check(
    str_contains($formHooksSource, "service: '" . $serviceId . "'"),
    'P4.1 FormHooks must inject service ' . $serviceId . '.',
  );
}
$check(
  !str_contains($services, 'Drupal\\aculta_portal\\Hook\\FormHooks'),
  'P4.1 Hook classes are auto-discovered/autowired; do not add redundant FormHooks YAML service definitions.',
);
foreach ([
  "'change_mail_form'",
  "'user_form'",
  'PaymentGatewayForm',
  "'commerce_donation_pane'",
  "'credentials_test'",
  "'access_token_test'",
  "'client_secret'",
  "'aculta_portal.form_callbacks:changeMailConfirmationMessage'",
  "'aculta_portal.form_callbacks:securityPasswordAfterBuild'",
  "'aculta_portal.form_callbacks:securityPasswordRedirect'",
  "'aculta_portal.form_callbacks:validateDonationAmount'",
] as $invariant) {
  $check(
    str_contains($formHooksSource, $invariant),
    'P4.1 form behavior invariant must remain present: ' . $invariant,
  );
}

// Module-wide totals: owning class + the pre-existing PortalHooks concern.
foreach (['form_alter' => 2, 'metatags_alter' => 2, 'entity_access' => 1, 'entity_presave' => 1] as $hookName => $expected) {
  $check(
    substr_count($hookRuntimeSources, "#[Hook('" . $hookName . "')]") === $expected,
    'Hook implementation count drifted for ' . $hookName . ' (expected ' . $expected . ').',
  );
}

$p4HookSources = [
  'form_alter' => $formHooksSource ?? '',
  'entity_access' => $entityHooksSource ?? '',
  'entity_presave' => $entitySaveHooksSource,
];
foreach ($p4HookSources as $hookName => $source) {
  $check(
    substr_count($source, "#[Hook('" . $hookName . "')]") === 1,
    'P4-R migrated hook must remain exactly-once: ' . $hookName,
  );
  $check(
    preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/', $source) === 1,
    'P4-R migrated hook class must keep strict_types=1: ' . $hookName,
  );
  $check(
    !str_contains($source, '\\Drupal::'),
    'P4-R migrated hook class must remain free of Drupal static service locators: ' . $hookName,
  );
}
foreach ([
  'Drupal\\aculta_portal\\Hook\\FormHooks',
  'Drupal\\aculta_portal\\Hook\\EntityHooks',
  'Drupal\\aculta_portal\\Hook\\EntitySaveHooks',
] as $hookClass) {
  $check(
    !str_contains($services, $hookClass),
    'P4-R OOP hook classes must not receive redundant YAML service definitions: ' . $hookClass,
  );
}
$check(
  !str_contains($hookRuntimeSources, '#[FormAlter'),
  'P4-R removed #[FormAlter] attribute must remain absent.',
);
$check(
  substr_count($formHooksSource ?? '', "'aculta_portal.form_callbacks:changeMailConfirmationMessage'") === 1
    && substr_count($formHooksSource ?? '', "'aculta_portal.form_callbacks:securityPasswordAfterBuild'") === 1
    && substr_count($formHooksSource ?? '', "'aculta_portal.form_callbacks:securityPasswordRedirect'") === 1
    && substr_count($formHooksSource ?? '', "'aculta_portal.form_callbacks:validateDonationAmount'") === 1,
  'P4-R FormHooks must preserve the four P3 service callbacks exactly once.',
);
$check(
  str_contains($entityHooksSource ?? '', "->addCacheContexts(['domain'])")
    && str_contains($entityHooksSource ?? '', '->addCacheableDependency($entity)'),
  'P4-R Wiki access must keep Domain variation and entity cache dependency.',
);
$check(
  preg_match(
    '/AccessResult::neutral\(\)[\s\S]{0,240}?cachePerPermissions\(\)[\s\S]{0,240}?addCacheContexts\(\[\'route\', \'user\'\]\)[\s\S]{0,240}?setCacheMaxAge\(0\)/',
    $entityHooksSource ?? '',
  ) === 1,
  'P4-R password-reset neutral access must remain request-sensitive and uncacheable.',
);
$check(
  str_contains($entitySaveHooksSource, 'MercadoPagoCredentials::hasRuntimeCredentials')
    && str_contains($entitySaveHooksSource, 'MercadoPagoCredentials::currentEnvironment')
    && str_contains($entitySaveHooksSource, 'throw new \\LogicException'),
  'P4-R Mercado Pago presave guard must remain fail-closed on missing runtime credentials.',
);

$formCallbacks = $srcRoot . '/Form/PortalFormCallbacks.php';
$formCallbacksSource = $read($formCallbacks);
$check(is_file($formCallbacks), 'P3.1 PortalFormCallbacks service class must exist.');
$check(
  str_contains($services, 'aculta_portal.form_callbacks:'),
  'P3.1 aculta_portal.form_callbacks service must be registered.',
);
$check(
  str_contains($services, 'Drupal\\aculta_portal\\Form\\PortalFormCallbacks'),
  'P3.1 form callback service must use PortalFormCallbacks.',
);
$check(
  str_contains($editorialHooksSource, "#[Hook('form_node_form_alter')]"),
  'P3.1 EditorialHooks must implement form_node_form_alter as OOP.',
);
$check(
  str_contains($editorialHooksSource, "'aculta_portal.form_callbacks:validateActivity'"),
  'P3.1 activity validation must use the Drupal 11.3+ service callback syntax.',
);
$check(
  str_contains($formCallbacksSource, 'function validateActivity('),
  'P3.1 PortalFormCallbacks must provide validateActivity().',
);

$moduleCallbacksPath = $moduleRoot . '/aculta_portal.module';
$moduleCallbacksSource = is_file($moduleCallbacksPath) ? $read($moduleCallbacksPath) : '';
$portalHooksSource = $read($srcRoot . '/Hook/PortalHooks.php');

$legacyFormCallbackNames = [
  'aculta_portal_validate_activity',
  'aculta_portal_change_mail_confirmation_message',
  'aculta_portal_security_password_redirect',
  'aculta_portal_security_password_after_build',
  'aculta_portal_account_photo_redirect',
  'aculta_portal_address_redirect',
  'aculta_portal_sync_customer_address_names',
  'aculta_portal_validate_donation_amount',
];
$p3RuntimeSources = [
  $moduleCallbacksSource,
  $portalHooksSource,
  $editorialHooksSource,
  $formHooksSource,
  $formCallbacksSource,
  $services,
];
foreach ($legacyFormCallbackNames as $legacyCallback) {
  $remaining = 0;
  foreach ($p3RuntimeSources as $source) {
    $remaining += substr_count($source, "'" . $legacyCallback . "'");
  }
  $check(
    $remaining === 0,
    'P3.3 legacy form callback must not return: ' . $legacyCallback,
  );
}

$check(
  substr_count($services, '  aculta_portal.form_callbacks:') === 1,
  'P3.3 aculta_portal.form_callbacks must be defined exactly once.',
);
$formCallbackServiceBlock = '';
if (preg_match(
  '/^  aculta_portal\.form_callbacks:\R(?:(?:    ).*(?:\R|$))+/m',
  $services,
  $serviceMatches,
) === 1) {
  $formCallbackServiceBlock = $serviceMatches[0];
}
$check(
  str_contains($formCallbackServiceBlock, 'class: Drupal\\aculta_portal\\Form\\PortalFormCallbacks'),
  'P3.3 form callback service must resolve to PortalFormCallbacks.',
);
foreach ([
  '@messenger',
  '@current_user',
  '@user.data',
  '@entity_type.manager',
  '@string_translation',
] as $dependency) {
  $check(
    str_contains($formCallbackServiceBlock, "'" . $dependency . "'"),
    'P3.3 form callback service must inject ' . $dependency . '.',
  );
}

$formCallbackRegistrations = [
  'validateActivity' => [$editorialHooksSource],
  'changeMailConfirmationMessage' => [$formHooksSource],
  'securityPasswordRedirect' => [$formHooksSource],
  'securityPasswordAfterBuild' => [$formHooksSource],
  'accountPhotoRedirect' => [$portalHooksSource],
  'addressRedirect' => [$portalHooksSource],
  'syncCustomerAddressNames' => [$portalHooksSource],
  'validateDonationAmount' => [$formHooksSource],
];
foreach ($formCallbackRegistrations as $methodName => $sources) {
  $check(
    substr_count($formCallbacksSource, 'function ' . $methodName . '(') === 1,
    'P3.3 PortalFormCallbacks must define ' . $methodName . '() exactly once.',
  );

  $registration = "'aculta_portal.form_callbacks:" . $methodName . "'";
  $registrationCount = 0;
  foreach ($sources as $source) {
    $registrationCount += substr_count($source, $registration);
  }
  $check(
    $registrationCount === 1,
    'P3.3 service callback must be registered exactly once: ' . $methodName,
  );
}

$check(
  preg_match(
    '/function\s+formNodeFormAlter\s*\(array\s*&\$form,\s*FormStateInterface\s+\$formState,\s*string\s+\$formId\s*\)/',
    $editorialHooksSource,
  ) === 1,
  'P3.3 form_node_form_alter must preserve the Drupal 11 base-form hook signature.',
);

$moduleFile = $moduleRoot . '/aculta_portal.module';
$check(
  !is_file($moduleFile),
  'P4-R aculta_portal.module must remain absent after all runtime hooks migrated to OOP.',
);

$supportFormSource = $read($srcRoot . '/Support/Form/SupportForm.php');
$check(
  preg_match('/declare\\s*\\(\\s*strict_types\\s*=\\s*1\\s*\\)\\s*;/', $supportFormSource) === 1,
  'P5.1 SupportForm must declare strict_types=1.',
);
foreach (["get('config.factory')", "get('entity_type.manager')", "get('plugin.manager.block')"] as $dependency) {
  $check(
    str_contains($supportFormSource, $dependency),
    'P5.1 SupportForm must inject ' . $dependency . '.',
  );
}
$check(
  str_contains($supportFormSource, "getStorage('commerce_payment_gateway')->load('mercado_pago')")
    && str_contains($supportFormSource, "\$this->blockManager->createInstance("),
  'P5.1 SupportForm must use injected storage and BlockManager.',
);
$check(
  !str_contains($supportFormSource, 'PaymentGateway::load(')
    && !str_contains($supportFormSource, '\\Drupal::'),
  'P5.1 SupportForm must not use static entity loads or Drupal service locators.',
);

// P5.2-A: courses catalog uses the native Views render element with an
// injected executable factory; views_embed_view() is deprecated in 11.4.0.
$coursesSource = $read($srcRoot . '/Controller/CoursesController.php');
$check(
  str_contains($coursesSource, "'#type' => 'view'")
    && str_contains($coursesSource, "#[Autowire(service: 'views.executable')]")
    && str_contains($coursesSource, "->access(self::CATALOG_DISPLAY)")
    && str_contains($coursesSource, "CATALOG_VIEW = 'courses_catalog'")
    && str_contains($coursesSource, "CATALOG_DISPLAY = 'block_1'")
    && str_contains($coursesSource, "'config:views.view.' . self::CATALOG_VIEW"),
  'P5.2-A courses catalog must keep View/display, access-before-render and fallback cacheability.',
);
$check(
  !str_contains($coursesSource, 'views_embed_view(') && !str_contains($coursesSource, '\\u00'),
  'P5.2-A courses catalog must not use views_embed_view() or literal unicode escapes.',
);

// P5.2-B/P5.3/P5.4-E: Wiki uses injected Views factory, node storage queries
// with explicit access checks, and keeps the WIKI Domain/status filters.
$wikiSource = $read($srcRoot . '/Controller/WikiController.php');
$check(
  !str_contains($wikiSource, 'Views::')
    && !str_contains($wikiSource, '\\Drupal::')
    && !str_contains($wikiSource, 'entityTypeManager()')
    && str_contains($wikiSource, "#[Autowire(service: 'views.executable')]")
    && str_contains($wikiSource, '->buildRenderable($displayId)'),
  'P5.2-B Wiki Views must use the injected executable factory and buildRenderable().',
);
$check(
  substr_count($wikiSource, "getStorage('node')") === 2
    && substr_count($wikiSource, '->getQuery()') === 2
    && substr_count($wikiSource, '->accessCheck(TRUE)') === 2
    && substr_count($wikiSource, "->condition('field_domain_source.target_id', \$wikiDomain->id())") === 2
    && substr_count($wikiSource, "->condition('status', 1)") === 2
    && substr_count($wikiSource, "->access('view')") === 2
    && !str_contains($wikiSource, 'getOwner()')
    && !str_contains($wikiSource, 'getDisplayName()')
    && str_contains($wikiSource, '$this->database->escapeLike($term)'),
  'P5.3 Wiki queries must keep explicit access checks, WIKI Domain/status filters and per-entity access.',
);
$wikiNodeType = $read($root . '/config/sync/node.type.wiki_entry.yml');
$check(
  str_contains($wikiNodeType, 'display_submitted: false'),
  'Public Wiki entry presentation must not expose authorship while node ownership and revisions remain in Drupal.',
);

// P5.4-A/B/C/E: account controllers receive every collaborator explicitly.
$portalControllerSource = $read($srcRoot . '/Controller/PortalController.php');
foreach (["'plugin.manager.block'", "'email_confirmer'", "'current_route_match'", "'current_user'", "'module_handler'", "'config.factory'"] as $serviceId) {
  $check(
    substr_count($portalControllerSource, '$container->get(' . $serviceId . ')') === 1,
    'P5.4 PortalController must inject ' . $serviceId . ' exactly once.',
  );
}
$check(
  !preg_match('/\\$this->(?:currentUser|moduleHandler|config|entityTypeManager)\(/', $portalControllerSource)
    && str_contains($portalControllerSource, '$parameters = $this->routeMatch->getParameters();')
    && preg_match('/try \{[\s\S]*?finally \{[\s\S]*?\$parameters->remove\(\'user\'\)/', $portalControllerSource) === 1,
  'P5.4-C PortalController must not use lazy helpers and must restore route parameters in finally.',
);
$supportControllerSource = $read($srcRoot . '/Support/Controller/SupportController.php');
$check(
  str_contains($supportControllerSource, '$uid > 0')
    && !str_contains($supportControllerSource, 'currentUser()'),
  'P5.4-E support history must stay owner-scoped and never query uid 0.',
);
$routingSource = $read(dirname($srcRoot) . '/aculta_portal.routing.yml');
$check(
  preg_match("/aculta_portal\\.support_my:[\\s\\S]*?_user_is_logged_in: 'TRUE'/", $routingSource) === 1,
  'P5.6 support history route must require an authenticated user.',
);

// P5.4-D: requirements report injects theme handler and app root.
$requirementsSource = $read($srcRoot . '/Controller/PortalRequirementsController.php');
$check(
  str_contains($requirementsSource, "#[Autowire(service: 'theme_handler')]")
    && str_contains($requirementsSource, "#[Autowire(param: 'app.root')]")
    && str_contains($requirementsSource, 'dirname($this->appRoot) . \'/composer.json\'')
    && !preg_match('/\\$this->(?:currentUser|moduleHandler|config)\(/', $requirementsSource),
  'P5.4-D requirements controller must use injected theme handler, app root and services.',
);

// P5.5: storage comes from injected managers and public services only.
// Static Group membership loads are replaced by the group.membership_loader
// service; storage queries remain on injected entity storage.
$coursesManagerSource = $read($srcRoot . '/AccountCoursesManager.php');
$servicesSource = $read(dirname($srcRoot) . '/aculta_portal.services.yml');
$check(
  !str_contains($coursesManagerSource, 'GroupMembership::loadByUser')
    && str_contains($coursesManagerSource, 'GroupMembershipLoaderInterface $membershipLoader')
    && str_contains($coursesManagerSource, '$this->membershipLoader->loadByUser($account)')
    && str_contains($servicesSource, "'@group.membership_loader'"),
  'P5.5 AccountCoursesManager must read Group memberships through the injected group.membership_loader service.',
);
$storageStaticPattern = '/\\\\Drupal::(?:entityTypeManager|entityQuery)\(\)|\\\\Drupal::service\(\'entity_type\.manager\'\)/';
$storageStaticHits = 0;
$storageFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS));
foreach ($storageFiles as $storageFile) {
  if ($storageFile->isFile() && strtolower($storageFile->getExtension()) === 'php') {
    $storageStaticHits += preg_match_all($storageStaticPattern, $read($storageFile->getPathname()));
  }
}
$check(
  $storageStaticHits === 0,
  'P5.5 runtime storage must not use static entity-type or entity-query locators.',
);

// P6.2: URL-bearing tokens declare the request-dependent cache contexts they
// read (scheme/host via url.site, alias environment via domain) and the
// purpose Domain entity behind the image path.
$tokenSource = $read($srcRoot . '/Hook/TokenHooks.php');
$check(
  substr_count($tokenSource, "addCacheContexts(['url.site', 'domain'])") === 2,
  'P6.2 canonical and image tokens must declare url.site and domain cache contexts.',
);
$check(
  str_contains($tokenSource, "getDomain(\$domainPurpose)")
    && str_contains($tokenSource, 'addCacheableDependency($purposeDomain)'),
  'P6.2 image token must depend on the purpose Domain entity it is built from.',
);

// P7.3: every Portal request-phase listener ignores subrequests.
foreach ([
  'src/EventSubscriber/AccountRouteSubscriber.php',
  'src/EventSubscriber/CepLookupRateLimitSubscriber.php',
  'src/EventSubscriber/DomainPurposeRequestSubscriber.php',
  'src/Commerce/MercadoPago/WebhookEventSubscriber.php',
] as $requestListener) {
  $check(
    str_contains($read($moduleRoot . '/' . $requestListener), 'isMainRequest()'),
    $requestListener . ' must guard request handling with isMainRequest().',
  );
}

// Login destinations: the account menu varies by page because its Log in link
// carries the current page as destination. Removing these contexts would let a
// cached block show another page's destination.
$blockBuildSource = $read($srcRoot . '/Hook/BlockBuildHooks.php');
$check(
  str_contains($blockBuildSource, "#[Hook('block_build_alter')]")
    && str_contains($blockBuildSource, "'system_menu_block:account'")
    && str_contains($blockBuildSource, "addCacheContexts(['url.path', 'url.query_args'])"),
  'Account menu block must vary by url.path and url.query_args so login destinations are per page.',
);

$serviceLocatorCeilings = [
];
$viewsWrapperCeilings = [
];
$staticLoadCeilings = [
];
// S2 (DT-P02): teto vazio. Todo arquivo de src/ declara strict_types=1.
$strictTypesDebt = [];

$phpFiles = [];
$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
  if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
    $phpFiles[] = $file->getPathname();
  }
}
sort($phpFiles);
$check(count($phpFiles) >= 38, 'Portal runtime PHP inventory must not unexpectedly shrink below the P1 baseline without gate review.');

$locatorPattern = '/\\\\Drupal::(?:service|entityTypeManager|database|request|routeMatch|currentUser|config|messenger|entityQuery)\s*\(/';
$viewsPattern = '/\b(?:views_embed_view|Views::getView)\s*\(/';
$staticLoadPattern = '/\b(?:Node|User|Role|PaymentGateway|ProfileType|FieldConfig|FieldStorageConfig)::(?:load|loadByName)\s*\(/';

foreach ($phpFiles as $path) {
  $source = $read($path);
  $relative = 'src/' . str_replace('\\', '/', substr($path, strlen($srcRoot) + 1));

  $locatorCount = preg_match_all($locatorPattern, $source);
  $locatorCeiling = $serviceLocatorCeilings[$relative] ?? 0;
  $check(
    is_int($locatorCount) && $locatorCount <= $locatorCeiling,
    $relative . ' exceeds service-locator debt ceiling ' . $locatorCeiling . '.',
  );

  $viewsCount = preg_match_all($viewsPattern, $source);
  $viewsCeiling = $viewsWrapperCeilings[$relative] ?? 0;
  $check(
    is_int($viewsCount) && $viewsCount <= $viewsCeiling,
    $relative . ' exceeds Views-wrapper debt ceiling ' . $viewsCeiling . '.',
  );

  $staticLoadCount = preg_match_all($staticLoadPattern, $source);
  $staticLoadCeiling = $staticLoadCeilings[$relative] ?? 0;
  $check(
    is_int($staticLoadCount) && $staticLoadCount <= $staticLoadCeiling,
    $relative . ' exceeds static entity-load debt ceiling ' . $staticLoadCeiling . '.',
  );

  if (!in_array($relative, $strictTypesDebt, TRUE)) {
    $check(
      preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/', $source) === 1,
      $relative . ' must declare strict_types=1.',
    );
  }
}

// S1 (DT-P01): templates do módulo não usam classes do tema (aculta-*). A apresentação do Portal usa
// nomes neutros de contrato (portal-*). Atributos data-aculta-* são contrato de JS e ficam fora desta regra.
$templateDir = $moduleRoot . '/templates';
foreach (glob($templateDir . '/*.twig') ?: [] as $template) {
  $source = (string) file_get_contents($template);
  $relative = substr($template, strlen($root) + 1);
  $check(
    preg_match('/\bclass\s*=\s*"[^"]*\baculta-/', $source) !== 1,
    $relative . ' must not use aculta-* classes (S1 / DT-P01).',
  );
}

if ($failures !== []) {
  fwrite(STDERR, "ACULTA PORTAL DRUPAL 11+ GATE: FAIL\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, '- ' . $failure . "\n");
  }
  exit(1);
}

echo 'ACULTA PORTAL DRUPAL 11+ GATE: PASS (' . $checks . " checks; progressive Drupal 11+ baseline)\n";
