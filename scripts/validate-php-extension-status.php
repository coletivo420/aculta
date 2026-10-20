<?php

declare(strict_types=1);

use Drupal\aculta_portal\Status\PhpExtensionRequirements;

$root = dirname(__DIR__);
$lockFile = $root . '/composer.lock';
$controllerFile = $root . '/web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php';
$classFile = $root . '/web/modules/custom/aculta_portal/src/Status/PhpExtensionRequirements.php';
$lock = is_readable($lockFile) ? json_decode((string) file_get_contents($lockFile), TRUE) : NULL;
if (!is_array($lock['packages'] ?? NULL)) {
  fwrite(STDERR, "FAIL: composer.lock ausente ou inválido.\n");
  exit(1);
}

$packages = $lock['packages'];
require_once $classFile;
$detected = PhpExtensionRequirements::build(
  [['name' => 'Gate de cobertura global', 'modules' => [], 'scan_all' => TRUE]],
  $packages,
  [],
);
$detectedExtensions = array_column($detected, 'extension');
$declaredExtensions = [];
foreach ($packages as $package) {
  foreach (array_keys($package['require'] ?? []) as $dependency) {
    if (str_starts_with($dependency, 'ext-')) {
      $declaredExtensions[$dependency] = TRUE;
    }
  }
}
$declaredExtensions = array_keys($declaredExtensions);
sort($declaredExtensions);
sort($detectedExtensions);
if ($detectedExtensions !== $declaredExtensions) {
  fwrite(STDERR, "FAIL: a varredura global não corresponde aos requisitos ext-* de composer.lock.\n");
  fwrite(STDERR, 'Declarados: ' . implode(', ', $declaredExtensions) . "\n");
  fwrite(STDERR, 'Detectados: ' . implode(', ', $detectedExtensions) . "\n");
  exit(1);
}

$controller = (string) file_get_contents($controllerFile);
foreach ([
  "'scan_all' => TRUE",
  "'name' => 'ACULTA Image'",
  "'name' => 'ACULTA PDF Viewer'",
  "'modules' => ['aculta_image']",
  "'modules' => ['aculta_pdf_viewer']",
  "requirements-php-extensions",
] as $required) {
  if (!str_contains($controller, $required)) {
    fwrite(STDERR, "FAIL: painel PHP não contém declaração obrigatória: {$required}\n");
    exit(1);
  }
}

printf("PASS: %d requisitos ext-* de runtime cobertos no detector e no painel.\n", count($declaredExtensions));
