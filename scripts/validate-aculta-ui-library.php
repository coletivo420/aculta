<?php

/**
 * @file
 * Gate for the ACULTA compatibility redirect to UI Patterns Library.
 *
 * Verifies that the legacy entry point stays MAIN/admin-only, points to the
 * canonical SDC catalog, and does not expose a second block catalog/preview.
 *
 * Usage: `php scripts/validate-aculta-ui-library.php` (delegates to Drush) or
 * via Drush `php:script`.
 */

declare(strict_types=1);

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\aculta_ui_library\Controller\UiPatternsLibraryRedirectController;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

if (!class_exists(\Drupal::class) || !\Drupal::hasContainer()) {
  $root = dirname(__DIR__);
  $drush = (getenv('ACULTA_DRUSH_ROOT') ?: $root) . '/vendor/drush/drush/drush.php';
  if (!is_file($drush)) {
    fwrite(STDERR, "ACULTA UI LIBRARY FAIL: Drush não instalado.\n");
    exit(1);
  }
  passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($drush) . ' php:script ' . escapeshellarg(__FILE__), $code);
  exit($code);
}

$root = dirname(__DIR__);
$module = $root . '/web/modules/custom/aculta_portal/modules/aculta_ui_library';
$failures = [];
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$failures, &$checks): void {
  $checks++;
  if (!$condition) {
    $failures[] = $message;
  }
};

$provider = \Drupal::service('router.route_provider');
try {
  $route = $provider->getRouteByName('aculta_ui_library.blocks');
  $checks++;
}
catch (\Throwable) {
  $failures[] = 'Rota de compatibilidade aculta_ui_library.blocks ausente.';
  $checks++;
  $route = NULL;
}

if ($route !== NULL) {
  $assert($route->getPath() === '/painel-administrativo/aculta/ui-library/blocos', 'A rota legada mudou de caminho sem plano de compatibilidade.');
  $assert($route->getOption('_aculta_site_key') === 'main', 'A rota não declara _aculta_site_key=main.');
  $assert($route->getOption('_admin_route') === TRUE, 'A rota de compatibilidade não é administrativa.');
  $assert($route->getRequirement('_permission') === 'administer blocks', 'A rota não exige administer blocks.');
}

try {
  $provider->getRouteByName('ui_patterns_library.overview');
  $checks++;
}
catch (\Throwable) {
  $failures[] = 'A rota canônica ui_patterns_library.overview não está disponível.';
  $checks++;
}

$access = \Drupal::service('access_manager');
$anonymous = new AnonymousUserSession();
$admin = \Drupal::entityTypeManager()->getStorage('user')->load(1);
$nonAdmin = NULL;
foreach ([5, 15, 33, 34, 80] as $uid) {
  $candidate = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
  if ($candidate && !$candidate->hasPermission('administer blocks')) {
    $nonAdmin = $candidate;
    break;
  }
}
$assert($nonAdmin !== NULL, 'Nenhum usuário sem administer blocks está disponível para o teste negativo.');

if ($route !== NULL) {
  $assert(!$access->checkNamedRoute('aculta_ui_library.blocks', [], $anonymous, TRUE)->isAllowed(), 'A rota permitiu visitante.');
  if ($nonAdmin !== NULL) {
    $assert(!$access->checkNamedRoute('aculta_ui_library.blocks', [], $nonAdmin, TRUE)->isAllowed(), 'A rota permitiu usuário sem administer blocks.');
  }
  if ($admin !== NULL) {
    $assert($access->checkNamedRoute('aculta_ui_library.blocks', [], $admin, TRUE)->isAllowed(), 'A rota negou o administrador.');
  }
}

$switcher = \Drupal::service('account_switcher');
$switcher->switchTo($admin);
try {
  $controller = UiPatternsLibraryRedirectController::create(\Drupal::getContainer());
  $response = $controller->redirectToLibrary();
  $expected = \Drupal::service('url_generator')->generate('ui_patterns_library.overview', [], UrlGeneratorInterface::ABSOLUTE_PATH);
  $assert($response->getTargetUrl() === $expected, 'A rota antiga não redirecionou à UI Patterns Library.');
  $assert($response->getStatusCode() === 302, 'O redirecionamento não retornou HTTP 302.');
}
finally {
  $switcher->switchBack();
}

try {
  $provider->getRouteByName('aculta_ui_library.block_preview');
  $failures[] = 'A rota própria de prévia de bloco ainda está registrada.';
  $checks++;
}
catch (\Throwable) {
  $checks++;
}

$source = (string) file_get_contents($module . '/src/Controller/UiPatternsLibraryRedirectController.php');
$assert(str_contains($source, 'ui_patterns_library.overview'), 'O controlador não aponta para UI Patterns Library.');
$assert(!str_contains($source, 'getStorage(\'block\')'), 'O controlador ainda consulta blocos configurados.');
$assert(!str_contains($source, '->build()'), 'O controlador ainda renderiza prévias de bloco.');

if ($failures !== []) {
  fwrite(STDERR, "FAIL — validate-aculta-ui-library\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, "  - $failure\n");
  }
  exit(1);
}

echo "PASS — validate-aculta-ui-library ($checks checks)\n";
