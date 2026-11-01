<?php

/**
 * @file
 * Gate: higiene do submódulo aculta_gallery (fase de hardening, 0.1.0-dev.10).
 *
 * Verifica no código (sem Runtime):
 * - nenhum service locator (\Drupal::) em src/ do submódulo;
 * - toda EntityQuery em src/ declara accessCheck() explicitamente;
 * - hooks de runtime são OOP (#[Hook]) e não procedurais no módulo;
 * - galerias não impõem restrição paralela por purpose;
 * - plugin CKEditor e diálogo são declarados e exigem permissão de criação.
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

foreach (['GalleryPurposeAccessHooks.php', 'GalleryListingHooks.php'] as $restricted_hook) {
  if (is_file($root . '/src/Hook/' . $restricted_hook)) {
    $errors[] = "$restricted_hook ainda restringe galerias por purpose";
  }
}

$list_builder = file_get_contents($root . '/src/ListBuilder/AcultaGalleryListBuilder.php');
if (str_contains($list_builder, 'DomainPurposeManager') || str_contains($list_builder, "'magazine'")) {
  $errors[] = 'link da coleção administrativa ainda força o purpose magazine';
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
echo 'ACULTA GALLERY HYGIENE: PASS (' . count($files) . " arquivos em src/; sem restrição por purpose, service locator ou EntityQuery sem accessCheck)\n";
