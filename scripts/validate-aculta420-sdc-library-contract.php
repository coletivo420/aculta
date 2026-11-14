<?php

/**
 * @file
 * Enforces the ACULTA420 V2 SDC and UI Patterns Library contract.
 *
 * Every SDC must have its own Twig template and at least one UI Patterns
 * story. The official UI Patterns Library is the only component catalog; the
 * custom ACULTA library module must remain absent.
 *
 * Usage: `php scripts/validate-aculta420-sdc-library-contract.php`.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$componentsRoot = $root . '/web/themes/custom/aculta420_v2/components';
$coreExtension = $root . '/config/sync/core.extension.yml';
$moduleRoot = $root . '/web/modules/custom/aculta_portal/modules/aculta_ui_library';
$errors = [];
$componentCount = 0;
$storyCount = 0;

if (!is_dir($componentsRoot)) {
  fwrite(STDERR, "FAIL: diretório de componentes SDC não encontrado.\n");
  exit(1);
}

$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($componentsRoot, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $file) {
  if (!$file->isFile() || !str_ends_with($file->getFilename(), '.component.yml')) {
    continue;
  }

  $componentCount++;
  $manifest = $file->getPathname();
  $directory = $file->getPath();
  $name = basename($directory);
  $expectedManifest = $directory . '/' . $name . '.component.yml';
  if ($manifest !== $expectedManifest) {
    $errors[] = "Manifest SDC fora do padrão do diretório: {$manifest}";
  }

  if (!is_file($directory . '/' . $name . '.twig')) {
    $errors[] = "SDC {$name} sem template {$name}.twig.";
  }

  $stories = glob($directory . '/' . $name . '.*.story.yml') ?: [];
  $storyCount += count($stories);
  if ($stories === []) {
    $errors[] = "SDC sem story na UI Patterns Library: {$name}.";
  }

  foreach (glob($directory . '/*.story.yml') ?: [] as $story) {
    if (!str_starts_with(basename($story), $name . '.')) {
      $errors[] = "Story {$story} não pertence ao nome SDC {$name}.";
    }
  }
}

foreach (new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($componentsRoot, FilesystemIterator::SKIP_DOTS),
) as $file) {
  if (!$file->isFile() || !str_ends_with($file->getFilename(), '.story.yml')) {
    continue;
  }
  $directory = $file->getPath();
  $name = basename($directory);
  if (!is_file($directory . '/' . $name . '.component.yml')) {
    $errors[] = "Story sem SDC correspondente: {$file->getPathname()}.";
  }
  if (!str_starts_with($file->getFilename(), $name . '.')) {
    $errors[] = "Nome de story não corresponde ao SDC {$name}: {$file->getPathname()}.";
  }
}

// Component directories containing top-level Twig templates must be SDCs.
foreach (glob($componentsRoot . '/*/*.twig') ?: [] as $template) {
  $directory = dirname($template);
  $name = basename($directory);
  if (!is_file($directory . '/' . $name . '.component.yml')) {
    $errors[] = "Template Twig de componente sem manifest SDC: {$template}.";
  }
}

// The ACULTA duplicate library has been retired; only UI Patterns Library is
// allowed to provide the SDC catalog and stories.
if (file_exists($moduleRoot)) {
  $errors[] = 'O módulo legado aculta_ui_library deve permanecer removido.';
}
if (!is_file($coreExtension)) {
  $errors[] = 'Config Sync core.extension.yml não encontrado.';
}
else {
  $extensions = (string) file_get_contents($coreExtension);
  if (preg_match('/^\s*aculta_ui_library\s*:/m', $extensions)) {
    $errors[] = 'Config Sync não pode habilitar aculta_ui_library.';
  }
  if (!preg_match('/^\s*ui_patterns_library\s*:/m', $extensions)) {
    $errors[] = 'Config Sync deve manter ui_patterns_library habilitado.';
  }
}

if ($errors !== []) {
  fwrite(STDERR, "FAIL — validate-aculta420-sdc-library-contract\n");
  foreach ($errors as $error) {
    fwrite(STDERR, "- {$error}\n");
  }
  exit(1);
}

printf(
  "PASS — validate-aculta420-sdc-library-contract (%d SDCs, %d stories; todos com story; catálogo oficial único)\n",
  $componentCount,
  $storyCount,
);
