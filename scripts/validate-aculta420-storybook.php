<?php

declare(strict_types=1);

/**
 * Ensures the ACULTA420 SDC catalog and Storybook documentation stay complete.
 *
 * Usage: php scripts/validate-aculta420-storybook.php [--root=/path/to/repo]
 */

$root = dirname(__DIR__);
foreach ($argv as $argument) {
  if (str_starts_with($argument, '--root=')) {
    $root = rtrim(substr($argument, 7), DIRECTORY_SEPARATOR);
  }
}

$theme = $root . '/web/themes/custom/aculta420';
$storybook = $root . '/storybook';
$errors = [];
$component_count = 0;
$story_count = 0;
$migrated_document_count = 0;

if (!is_dir($theme . '/components')) {
  fwrite(STDERR, "FAIL: ACULTA420 components directory not found: {$theme}/components\n");
  exit(1);
}

$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($theme . '/components', FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
  if (!$file->isFile() || !str_ends_with($file->getFilename(), '.component.yml')) {
    continue;
  }
  $component_count++;
  $machine_name = substr($file->getFilename(), 0, -strlen('.component.yml'));
  $story_path = $file->getPath() . '/' . $machine_name . '.stories.twig';
  if (!is_file($story_path)) {
    $errors[] = "SDC {$file->getPathname()} has no matching {$machine_name}.stories.twig";
    continue;
  }
  $source = file_get_contents($story_path);
  if ($source === FALSE || !preg_match('/\{%\s*stories\s+[a-zA-Z0-9_]+\s+with\s+\{/', $source)) {
    $errors[] = "Story file {$story_path} does not define a Storybook group";
    continue;
  }
  if (!preg_match('/\{%\s*story\s+[a-zA-Z0-9_]+\s+with\s+\{/', $source)) {
    $errors[] = "Story file {$story_path} has no story entry";
    continue;
  }
  if (!str_contains($source, "include('aculta420:{$machine_name}'")) {
    $errors[] = "Story file {$story_path} must render the matching aculta420:{$machine_name} SDC";
    continue;
  }
  $story_count++;
}

$required_docs = [
  'Welcome.mdx',
  'DesignSystem.mdx',
  'Architecture.mdx',
  'Accessibility.mdx',
  'Development.mdx',
  'AI-agents.mdx',
  'Components.mdx',
];
foreach ($required_docs as $document) {
  $path = $storybook . '/docs/' . $document;
  if (!is_file($path)) {
    $errors[] = "Required Storybook documentation page is missing: {$path}";
    continue;
  }
  $source = file_get_contents($path);
  if ($source === FALSE || !preg_match('/<Meta\s+title=/', $source)) {
    $errors[] = "Storybook documentation page {$path} needs a visible Meta title";
  }
}

$top_level_document_pages = [
  'architecture' => 'Architecture.mdx',
  'design-system' => 'DesignSystem.mdx',
  'accessibility' => 'Accessibility.mdx',
  'development' => 'Development.mdx',
  'components' => 'Components.mdx',
];
foreach (glob($theme . '/docs/*.md') ?: [] as $source_path) {
  $source_name = basename($source_path, '.md');
  if ($source_name === 'README') {
    continue;
  }
  $page = $top_level_document_pages[$source_name]
    ?? 'reference/' . $source_name . '.mdx';
  $page_path = $storybook . '/docs/' . $page;
  $page_source = is_file($page_path) ? file_get_contents($page_path) : FALSE;
  $relative_source = str_contains($page, 'reference/')
    ? '../../../web/themes/custom/aculta420/docs/' . basename($source_path) . '?raw'
    : '../../web/themes/custom/aculta420/docs/' . basename($source_path) . '?raw';
  if ($page_source === FALSE || !str_contains($page_source, $relative_source)) {
    $errors[] = "Theme document {$source_path} is not rendered from its source in Storybook page {$page_path}";
  }
  else {
    $migrated_document_count++;
  }
}

if ($errors !== []) {
  foreach ($errors as $error) {
    fwrite(STDERR, "FAIL: {$error}\n");
  }
  exit(1);
}

printf("PASS: %d ACULTA420 SDCs have Storybook stories; %d theme Markdown documents are rendered in Storybook; %d core guidance pages are present.\n", $component_count, $migrated_document_count, count($required_docs));
