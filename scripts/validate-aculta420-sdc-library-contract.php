<?php

/**
 * @file
 * Enforces the ACULTA420 V2 SDC and UI Patterns Library contract.
 *
 * Every SDC must have its own Twig template and at least one UI Patterns
 * story. Three storyless components are grandfathered as explicit ADR-056
 * debt; no new exception is allowed. The ACULTA compatibility module may only
 * redirect to UI Patterns Library and may not catalog or render blocks.
 *
 * Usage: `php scripts/validate-aculta420-sdc-library-contract.php`.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$componentsRoot = $root . '/web/themes/custom/aculta420_v2/components';
$moduleRoot = $root . '/web/modules/custom/aculta_portal/modules/aculta_ui_library';
$errors = [];
$componentCount = 0;
$storyCount = 0;

// These pre-existing storyless SDCs remain pending the ADR-056 consumer audit.
// If any is removed or receives a story, remove it from this debt allowlist.
$storylessDebt = [
  'hint',
  'sales-panel',
  'secret-reveal',
];
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

  if (!is_file($directory . '/' . $name . '.twig')) {
    $errors[] = "SDC {$name} sem template {$name}.twig.";
  }

  $stories = glob($directory . '/' . $name . '.*.story.yml') ?: [];
  $storyCount += count($stories);
  if ($stories === []) {
    $storylessFound[] = $name;
    if (!in_array($name, $storylessDebt, TRUE)) {
      $errors[] = "SDC novo ou não autorizado sem story: {$name}.";
    }
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
$expectedDebt = $storylessDebt;
sort($expectedDebt);
if ($storylessFound !== $expectedDebt) {
  $errors[] = 'A lista explícita de SDCs sem story não corresponde à dívida existente; atualize o allowlist após corrigir ou remover componentes.';
}

// Component directories containing top-level Twig templates must be SDCs.
foreach (glob($componentsRoot . '/*/*.twig') ?: [] as $template) {
  $directory = dirname($template);
  $name = basename($directory);
  if (!is_file($directory . '/' . $name . '.component.yml')) {
    $errors[] = "Template Twig de componente sem manifest SDC: {$template}.";
  }
}

// Keep the custom module as a compatibility redirect, never as a second UI.
$routeFile = $moduleRoot . '/aculta_ui_library.routing.yml';
$redirectFile = $moduleRoot . '/src/Controller/UiPatternsLibraryRedirectController.php';
$expectedSourceFiles = [
  'src/Access/UiLibraryAccessCheck.php',
  'src/Controller/UiPatternsLibraryRedirectController.php',
];
$sourceFiles = [];
if (!is_file($routeFile) || !is_file($redirectFile)) {
  $errors[] = 'Rota/controller de compatibilidade para UI Patterns Library ausente.';
}
else {
  $routes = (string) file_get_contents($routeFile);
  $routeNames = [];
  preg_match_all('/^([a-zA-Z0-9_.]+):\s*$/m', $routes, $routeNames);
  if (($routeNames[1] ?? []) !== ['aculta_ui_library.blocks']) {
    $errors[] = 'aculta_ui_library deve expor somente a rota legada aculta_ui_library.blocks.';
  }
  if (str_contains($routes, 'aculta_ui_library.block_preview')) {
    $errors[] = 'A rota própria de prévia de bloco não pode ser reintroduzida.';
  }
  if (!str_contains($routes, 'UiPatternsLibraryRedirectController::redirectToLibrary')) {
    $errors[] = 'A rota legada não aponta para o controller de redirecionamento.';
  }

  $redirect = (string) file_get_contents($redirectFile);
  if (!str_contains($redirect, "'ui_patterns_library.overview'")) {
    $errors[] = 'O redirect não usa a rota oficial ui_patterns_library.overview.';
  }
}

if (is_dir($moduleRoot . '/src')) {
  $moduleIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($moduleRoot . '/src', FilesystemIterator::SKIP_DOTS),
  );
  foreach ($moduleIterator as $source) {
    if (!$source->isFile() || $source->getExtension() !== 'php') {
      continue;
    }
    $relativePath = substr($source->getPathname(), strlen($moduleRoot) + 1);
    $sourceFiles[] = $relativePath;
    $contents = (string) file_get_contents($source->getPathname());
    if (str_contains($contents, "getStorage('block')") || str_contains($contents, '->build()')) {
      $errors[] = "Catálogo/renderização própria de blocos detectado em {$source->getPathname()}.";
    }
  }
}

sort($sourceFiles);
sort($expectedSourceFiles);
if ($sourceFiles !== $expectedSourceFiles) {
  $errors[] = 'aculta_ui_library deve conter somente o access check e o controller de redirecionamento; não adicionar catálogo ou renderer próprio.';
}

$routeFiles = glob($moduleRoot . '/*.routing.yml') ?: [];
if ($routeFiles !== [$routeFile]) {
  $errors[] = 'aculta_ui_library deve declarar somente uma rota de compatibilidade, sem rotas paralelas.';
}

if ($errors !== []) {
  fwrite(STDERR, "FAIL — validate-aculta420-sdc-library-contract\n");
  foreach ($errors as $error) {
    fwrite(STDERR, "- {$error}\n");
  }
  exit(1);
}

printf(
  "PASS — validate-aculta420-sdc-library-contract (%d SDCs, %d stories; %d exceções storyless existentes; catálogo oficial único)\n",
  $componentCount,
  $storyCount,
  count($storylessFound),
);
