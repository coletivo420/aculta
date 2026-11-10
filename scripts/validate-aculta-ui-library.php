<?php

/**
 * @file
 * Gate ADR-054 P6: catálogo de blocos e prévias reais com acesso restrito.
 *
 * Verifica, com bootstrap Drupal somente leitura:
 * 1. As rotas existem somente no domínio principal e são administrativas.
 * 2. Visitante e usuário autenticado sem permissão são negados.
 * 3. Administrador é autorizado.
 * 4. O catálogo lista os blocos reais do tema e não é cacheável.
 * 5. A prévia de um bloco real renderiza conteúdo, com max-age 0.
 *
 * Uso:
 *   php vendor/drush/drush/drush.php php:script scripts/validate-aculta-ui-library.php
 * ou apenas `php scripts/validate-aculta-ui-library.php` (delega ao Drush).
 */

declare(strict_types=1);

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

// 1. Rotas administrativas e restritas ao MAIN.
$provider = \Drupal::service('router.route_provider');
foreach (['aculta_ui_library.blocks', 'aculta_ui_library.block_preview'] as $name) {
  try {
    $route = $provider->getRouteByName($name);
  }
  catch (\Throwable $e) {
    $failures[] = "Rota ausente: $name";
    $checks++;
    continue;
  }
  $assert(str_starts_with($route->getPath(), '/painel-administrativo/'), "$name não está sob /painel-administrativo.");
  $assert($route->getOption('_aculta_domain_purpose') === 'main', "$name não declara _aculta_domain_purpose=main.");
  $assert($route->getOption('_admin_route') === TRUE, "$name não é marcada como rota administrativa.");
  $assert($route->getRequirement('_permission') === 'administer blocks', "$name não exige administer blocks.");
}

// 2 e 3. Acesso por perfil.
$access = \Drupal::service('access_manager');
$storage = \Drupal::entityTypeManager()->getStorage('user');
$anonymous = new \Drupal\Core\Session\AnonymousUserSession();
$admin = $storage->load(1);
$nonAdmin = NULL;
foreach ([5, 15, 33, 34, 80] as $uid) {
  $candidate = $storage->load($uid);
  if ($candidate && !$candidate->hasPermission('administer blocks')) {
    $nonAdmin = $candidate;
    break;
  }
}
$assert($nonAdmin !== NULL, 'Nenhum usuário não administrador disponível para o teste negativo.');

$params = ['block' => 'aculta420_v2_aculta_site_branding'];
foreach (['aculta_ui_library.blocks', 'aculta_ui_library.block_preview'] as $name) {
  $routeParams = $name === 'aculta_ui_library.block_preview' ? $params : [];
  $assert($access->checkNamedRoute($name, $routeParams, $anonymous, TRUE)->isAllowed() === FALSE, "$name permitiu visitante.");
  if ($nonAdmin !== NULL) {
    $assert($access->checkNamedRoute($name, $routeParams, $nonAdmin, TRUE)->isAllowed() === FALSE, "$name permitiu usuário sem administer blocks.");
  }
  if ($admin !== NULL) {
    $assert($access->checkNamedRoute($name, $routeParams, $admin, TRUE)->isAllowed() === TRUE, "$name negou o administrador.");
  }
}

// 4 e 5. Catálogo e prévia.
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo($admin);
try {
  $controller = new \Drupal\aculta_ui_library\Controller\UiLibraryPreviewController(\Drupal::service('context.repository'), \Drupal::service('router.route_provider'));
  $renderer = \Drupal::service('renderer');

  $catalog = $controller->listBlocks();
  $catalogHtml = (string) $renderer->renderInIsolation($catalog);
  $assert(($catalog['#cache']['max-age'] ?? NULL) === 0, 'O catálogo não declara max-age 0.');
  $assert(substr_count($catalogHtml, 'Abrir prévia real') > 0, 'O catálogo não lista blocos com link de prévia.');

  $block = \Drupal::entityTypeManager()->getStorage('block')->load('aculta420_v2_aculta_site_branding');
  $assert($block !== NULL, 'Bloco de teste aculta420_v2_aculta_site_branding ausente.');
  if ($block !== NULL) {
    $preview = $controller->preview($block);
    $previewHtml = (string) $renderer->renderInIsolation($preview);
    $assert(($preview['#cache']['max-age'] ?? NULL) === 0, 'A prévia não declara max-age 0.');
    $assert(isset($preview['content']), 'A prévia de um bloco acessível não renderizou conteúdo.');
    $assert(trim(strip_tags($previewHtml)) !== '', 'A prévia renderizou vazio.');
  }
}
finally {
  $switcher->switchBack();
}

// 6. Estática: sem \Drupal:: no controlador, com max-age 0 explícito e com verificação isAllowed().
$source = (string) file_get_contents($module . '/src/Controller/UiLibraryPreviewController.php');
$assert(!preg_match('/\\\\Drupal::/', $source), 'O controlador usa \\Drupal:: (viola a injeção de dependência do projeto).');
$assert(substr_count($source, 'setCacheMaxAge(0)') >= 3, 'O controlador não marca max-age 0 em catálogo, prévia e estado indisponível.');
$assert(str_contains($source, 'isAllowed()'), 'O controlador não verifica explicitamente isAllowed() na decisão de acesso do plugin (vulnerabilidade SEC-01).');
$assert(!preg_match('/!\s*\$plugin->access\(/', $source), 'O controlador ainda nega diretamente o retorno de $plugin->access() sem checar isAllowed().');

if ($failures !== []) {
  fwrite(STDERR, "FAIL — validate-aculta-ui-library\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, "  - $failure\n");
  }
  exit(1);
}

echo "PASS — validate-aculta-ui-library ($checks checks)\n";
