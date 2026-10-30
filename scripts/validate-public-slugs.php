<?php

/**
 * @file
 * Static gate for the public URL directive in AGENTS.md.
 *
 * Reads custom module routes and rejects public paths that contain numeric
 * segments outside parameters or forbidden English terms.
 *
 * Administrative and technical paths are exempt: /admin, /ajax, /api, /_,
 * and *.json.
 * The gate does not bootstrap Drupal, so it runs in any checkout.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);

// Ceiling for contributed public routes (DT-P20). It may only shrink when
// routes are fixed; a new violation outside this list fails the gate.
$contribBaseline = [];

$forbidden = [
  'course', 'courses_', 'group', 'node', 'user', 'lesson', 'activity',
  'answer', 'taxonomy', 'comment', 'forum_', 'block', 'views',
];
$exempt = ['/admin', '/ajax', '/api', '/_', '.json'];

$customRouting = glob($root . '/web/modules/custom/*/*.routing.yml') ?: [];
// Only enabled contributed modules serve public routes. Disabled submodules do
// not register routes; enabling one adds it to core.extension and this scan.
$enabledModules = [];
$extension = Yaml::parseFile($root . '/config/sync/core.extension.yml');
foreach (array_keys($extension['module'] ?? []) as $module) {
  $enabledModules[$module] = TRUE;
}
$contribRoutingByModule = [];
foreach (array_merge(
  glob($root . '/web/modules/contrib/*/*.routing.yml') ?: [],
  glob($root . '/web/modules/contrib/*/*/*.routing.yml') ?: [],
) as $file) {
  $module = basename($file, '.routing.yml');
  if (isset($enabledModules[$module])
    && (!isset($contribRoutingByModule[$module]) || strlen($file) < strlen($contribRoutingByModule[$module]))) {
    // Some development checkouts contain an archived duplicate tree at
    // web/modules/contrib/contrib. Inspect each enabled module only once.
    $contribRoutingByModule[$module] = $file;
  }
}
$contribRouting = array_values($contribRoutingByModule);
$themeRouting = glob($root . '/web/themes/custom/*/*.routing.yml') ?: [];

$violations = function (string $path) use ($exempt, $forbidden): array {
  if (array_filter($exempt, static fn(string $e) => str_contains($path, $e))) {
    return [];
  }
  $public = preg_replace('/\{[^}]+\}/', '{}', $path);
  $segments = array_values(array_filter(explode('/', (string) $public), static fn(string $s) => $s !== '' && $s !== '{}'));
  foreach ($segments as $segment) {
    if (ctype_digit($segment)) {
      return ['numeric segment'];
    }
    foreach ($forbidden as $term) {
      if ($segment === $term || (str_starts_with($segment, $term) && !str_starts_with($segment, $term . '-'))) {
        return ["English term '$term'"];
      }
    }
  }
  return [];
};

$failures = [];
$checks = 0;
$seenContrib = [];

foreach (array_merge($customRouting, $themeRouting) as $file) {
  foreach (Yaml::parseFile($file) ?: [] as $name => $route) {
    $path = is_array($route) ? ($route['path'] ?? NULL) : NULL;
    if (!is_string($path)) {
      continue;
    }
    $checks++;
    foreach ($violations($path) as $reason) {
      $failures[] = basename(dirname($file)) . ": route '$name' ($path) has $reason. Use a Portuguese slug.";
    }
  }
}

// Contributed routes whose public path the Portal replaces (DT-P21). The
// override must exist and carry the friendly path.
$portalOverrides = [
  'lms.group.answer_form' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/LmsFriendlyRouteSubscriber.php',
    'path' => '/curso/{group}/{lesson_delta}/{activity_delta}',
  ],
  'lms_file_upload.answer_file_download' => [
    'file' => $root . '/web/modules/custom/aculta_portal/modules/aculta_lms/src/EventSubscriber/UploadedAnswerRouteSubscriber.php',
    'path' => '/resposta/{lms_answer}/baixar-arquivo',
  ],
  'social_auth.network.redirect' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php',
    'path' => '/oauth/{network}',
  ],
  'social_auth.network.callback' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/SocialAuthCallbackRouteSubscriber.php',
    'path' => '/integracoes/{network}/retorno',
  ],
  'social_auth.user.profiles' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php',
    'path' => '/identidade/{user}/conexoes/oauth',
  ],
  'entity.group.join' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php',
    'path' => '/grupo/{group}/entrar',
  ],
  'entity.group.leave' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php',
    'path' => '/grupo/{group}/sair',
  ],
  'lms.course.start' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php',
    'path' => '/curso/{group}/iniciar',
  ],
  'lms.course.reset_test' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php',
    'path' => '/curso/{group}/reiniciar-avaliacao',
  ],
  'lms.group.self_results' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php',
    'path' => '/grupo/{group}/resultados-curso',
  ],
  'lms.answer.details' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php',
    'path' => '/resposta/{lms_answer}/{js}/detalhes',
  ],
  'entity.lms_answer.edit_form' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php',
    'path' => '/resposta/{lms_answer}/avaliar',
  ],
  'profile.user_page.single' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php',
    'path' => '/identidade/{user}/{profile_type}',
  ],
  'profile.user_page.multiple' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php',
    'path' => '/identidade/{user}/{profile_type}/lista',
  ],
  'profile.user_page.add_form' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php',
    'path' => '/identidade/{user}/{profile_type}/adicionar',
  ],
  'entity.webform_submission.user' => [
    'file' => $root . '/web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php',
    'path' => '/meus-formularios/{user}/{submission_view}',
  ],
];

// Drupal internals whose route names and access contracts are intentionally
// retained for moderation/revision workflows; these are not human URLs.
$technicalRouteExemptions = [
  'entity.group.revision_delete_form' => 'Group revision deletion operation; entity revision access controls it.',
];

foreach ($contribRouting as $file) {
  foreach (Yaml::parseFile($file) ?: [] as $name => $route) {
    $path = is_array($route) ? ($route['path'] ?? NULL) : NULL;
    if (!is_string($path)) {
      continue;
    }
    $checks++;
    if (isset($route['options']['_admin_route']) && $route['options']['_admin_route']) {
      continue;
    }
    if (isset($technicalRouteExemptions[$name])) {
      continue;
    }
    if (isset($portalOverrides[$name])) {
      $override = $portalOverrides[$name];
      $source = is_file($override['file']) ? (string) file_get_contents($override['file']) : '';
      if (!str_contains($source, "'{$override['path']}'")) {
        $failures[] = "contrib: route '$name' is overridden by the Portal, but '{$override['file']}' does not set {$override['path']}.";
      }
      continue;
    }
    if ($violations($path) === []) {
      continue;
    }
    $seenContrib[] = $name;
    if (!in_array($name, $contribBaseline, TRUE)) {
      $failures[] = "contrib: route '$name' ($path) is a new public-slug violation outside the DT-P20 baseline.";
    }
  }
}

foreach (array_diff($contribBaseline, $seenContrib) as $fixed) {
  $failures[] = "contrib: route '$fixed' is no longer a violation. Remove it from the DT-P20 baseline.";
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "PUBLIC SLUGS FAIL: $failure\n");
  }
  fwrite(STDERR, sprintf("PUBLIC SLUGS: FAIL (%d failures in %d routes)\n", count($failures), $checks));
  exit(1);
}

echo sprintf("PUBLIC SLUGS: PASS (%d routes; %d contributed violations in the DT-P20 baseline)\n", $checks, count($contribBaseline));
