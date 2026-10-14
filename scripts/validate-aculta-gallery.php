<?php

/**
 * Gate: higiene do submódulo aculta_gallery (fase de hardening, 0.1.0-dev.10).
 *
 * Verifica no código (sem Runtime):
 * - nenhum service locator (\Drupal::) em src/ do submódulo;
 * - toda EntityQuery em src/ declara accessCheck() explicitamente;
 * - hooks de runtime são OOP (#[Hook]) e não procedurais no módulo;
 * - D13: a restrição ao purpose magazine existe na visualização (hook entity_access) e na listagem (views_query_alter);
 * - o plugin do CKEditor e o diálogo estão declarados e exigem permissão de criação.
 *
 * Só lê arquivos. Não consulta o Runtime.
 */

declare(strict_types=1);

$root = dirname(__DIR__) . '/web/modules/custom/aculta_portal/modules/aculta_gallery';
$errors = [];

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
  if ($f->getExtension() === 'php') {
    $files[] = $f->getPathname();
  }
}
if ($files === []) {
  $errors[] = 'src/ do submódulo vazio ou ausente';
}

foreach ($files as $path) {
  $rel = substr($path, strlen($root) + 1);
  $code = file_get_contents($path);
  if (str_contains($code, '\\Drupal::')) {
    $errors[] = "$rel usa service locator (\\Drupal::)";
  }
  if (preg_match('/->getQuery\(|->condition\(/', $code) && !preg_match('/->accessCheck\(\s*(TRUE|FALSE)\s*\)/', $code)) {
    $errors[] = "$rel tem EntityQuery sem accessCheck() explícito";
  }
}

$purpose = file_get_contents($root . '/src/Hook/GalleryPurposeAccessHooks.php');
if (!str_contains($purpose, "#[Hook('entity_access')]") || !str_contains($purpose, "'magazine'")) {
  $errors[] = 'D13: restrição de visualização (entity_access, purpose magazine) ausente';
}
$listing = is_file($root . '/src/Hook/GalleryListingHooks.php') ? file_get_contents($root . '/src/Hook/GalleryListingHooks.php') : '';
if (!str_contains($listing, "#[Hook('views_query_alter')]") || !str_contains($listing, "'aculta_albuns'")) {
  $errors[] = 'D13: restrição da listagem (views_query_alter em aculta_albuns) ausente';
}

foreach (['CreateGallery.php', 'GalleryEmbedDialogForm.php'] as $name) {
  $hit = array_filter($files, fn($p) => str_ends_with($p, $name));
  if ($hit === []) {
    $errors[] = "$name ausente";
  }
}
$form = file_get_contents($root . '/src/Form/GalleryEmbedDialogForm.php');
if (!str_contains($form, "'add media gallery entities'") && !str_contains(file_get_contents($root . '/aculta_gallery.routing.yml'), 'add media gallery entities')) {
  $errors[] = 'diálogo sem exigir permissão de criação';
}

if ($errors !== []) {
  echo "ACULTA GALLERY HYGIENE: FAIL\n";
  foreach ($errors as $e) {
    echo " - $e\n";
  }
  exit(1);
}
echo 'ACULTA GALLERY HYGIENE: PASS (' . count($files) . " arquivos em src/; D13 nos dois pontos; sem service locator; EntityQuery com accessCheck)\n";
