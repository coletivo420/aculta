<?php

/**
 * @file
 * Enforces the ACULTA420 V2 SDC and UI Patterns Library contract.
 *
 * Every SDC must have its own Twig template and at least one UI Patterns
 * story. No storyless components are allowed. Only the official UI Patterns
 * Library may be enabled as the SDC catalog; the custom ACULTA catalog module
 * must not be enabled in Config Sync.
 *
 * Usage: `php scripts/validate-aculta420-sdc-library-contract.php`.
 */

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
$componentsRoot = $root . '/web/themes/custom/aculta420_v2/components';
$coreExtensionPath = $root . '/config/sync/core.extension.yml';
$errors = [];
$componentCount = 0;
$storyCount = 0;

// Every SDC must have at least one Library story.
$storylessFound = [];

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

  $definition = Yaml::parseFile($manifest);
  foreach (($definition['props']['properties'] ?? []) as $propName => $prop) {
    if (!is_array($prop) || trim((string) ($prop['title'] ?? '')) === '') {
      $errors[] = "SDC {$name}, prop {$propName}: título legível obrigatório para o formulário UI Patterns.";
    }
  }
  foreach (($definition['slots'] ?? []) as $slotName => $slot) {
    if (!is_array($slot) || trim((string) ($slot['title'] ?? '')) === '') {
      $errors[] = "SDC {$name}, slot {$slotName}: título legível obrigatório.";
    }
    if (!is_array($slot) || trim((string) ($slot['description'] ?? '')) === '') {
      $errors[] = "SDC {$name}, slot {$slotName}: descrição obrigatória para orientar composição.";
    }
  }

  if (!is_file($directory . '/' . $name . '.twig')) {
    $errors[] = "SDC {$name} sem template {$name}.twig.";
  }

  $stories = glob($directory . '/' . $name . '.*.story.yml') ?: [];
  $storyCount += count($stories);
  if ($stories === []) {
    $storylessFound[] = $name;
    $errors[] = "SDC sem story: {$name}.";
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

sort($storylessFound);
if ($storylessFound !== []) {
  $errors[] = 'Todos os componentes devem ter ao menos uma story; encontrados: ' . implode(', ', $storylessFound) . '.';
}

// Component directories containing top-level Twig templates must be SDCs.
foreach (glob($componentsRoot . '/*/*.twig') ?: [] as $template) {
  $directory = dirname($template);
  $name = basename($directory);
  if (!is_file($directory . '/' . $name . '.component.yml')) {
    $errors[] = "Template Twig de componente sem manifest SDC: {$template}.";
  }
}

if (!is_file($coreExtensionPath)) {
  $errors[] = 'Config Sync core.extension.yml ausente; não é possível verificar o catálogo habilitado.';
}
else {
  $coreExtension = Yaml::parseFile($coreExtensionPath);
  $modules = is_array($coreExtension['module'] ?? NULL) ? $coreExtension['module'] : [];
  if (!array_key_exists('ui_patterns_library', $modules)) {
    $errors[] = 'UI Patterns Library oficial deve permanecer habilitada no Config Sync.';
  }
  if (array_key_exists('aculta_ui_library', $modules)) {
    $errors[] = 'aculta_ui_library deve ser removido do Config Sync; não manter catálogo ou redirect próprio.';
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
  "PASS — validate-aculta420-sdc-library-contract (%d SDCs, %d stories, %d SDCs sem story; props e slots titulados, slots descritos; UI Patterns Library oficial habilitada sem módulo catálogo customizado)\n",
  $componentCount,
  $storyCount,
  count($storylessFound),
);
