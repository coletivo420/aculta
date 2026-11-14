<?php

declare(strict_types=1);

/**
 * @file
 * Builds a machine-readable catalog of ACULTA custom tests.
 *
 * The scanner is regex based and never executes the test files. It records the
 * execution type, group, base class, test method count, assertion count and a
 * set of low-value heuristics so the maintainer can justify each kept test.
 *
 * Usage:
 *   php scripts/audit-custom-tests.php --json docs/operations/testing/test-catalog.json
 *   php scripts/audit-custom-tests.php --summary
 */

const AUDIT_ROOTS = ['web/modules/custom', 'web/themes/custom'];

/**
 * Returns the execution type from the tests/src path.
 */
function audit_type(string $relative): string {
  $position = strpos($relative, '/tests/src/');
  if ($position === FALSE) {
    return 'unknown';
  }
  $segments = explode('/', substr($relative, $position + strlen('/tests/src/')));
  $leaf = $segments[0] ?? '';
  return match ($leaf) {
    'Unit' => 'unit',
    'Kernel' => 'kernel',
    'Functional' => 'functional',
    'FunctionalJavascript' => 'functionaljs',
    'Performance' => 'performance',
    'Integration' => in_array('Kernel', $segments, TRUE)
      ? 'kernel'
      : (in_array('Unit', $segments, TRUE) ? 'unit' : 'integration'),
    default => 'unknown',
  };
}

/**
 * Returns the owning extension (module or theme) machine name.
 */
function audit_extension(string $relative): string {
  if (preg_match('#web/(?:modules|themes)/custom/(?:[^/]+/)*(?:modules/)?([a-z0-9_]+)/#', $relative, $matches) === 1) {
    return $matches[1];
  }
  return 'unknown';
}

/**
 * Returns the base class used by the test.
 */
function audit_base_class(string $source): string {
  foreach (['KernelTestBase', 'BrowserTestBase', 'UnitTestCase', 'TestCase', 'WebDriverTestBase'] as $class) {
    if (preg_match('/extends\s+\\\\?' . preg_quote($class, '/') . '\b/', $source) === 1) {
      return $class;
    }
  }
  return 'unknown';
}

/**
 * Returns the groups declared with attributes or doc-comments.
 *
 * @return list<string>
 */
function audit_groups(string $source): array {
  preg_match_all('/#\[Group\(\s*[\'"]([^\'"]+)[\'"]\s*\)\]/', $source, $attribute);
  preg_match_all('/@group\s+(\S+)/', $source, $comment);
  $groups = array_values(array_unique(array_merge($attribute[1] ?? [], $comment[1] ?? [])));
  sort($groups);
  return $groups;
}

/**
 * Returns whether the file is a support fixture that must not be discovered.
 */
function audit_is_fixture(string $relative): bool {
  return str_contains($relative, '/tests/fixtures/');
}

/**
 * Scans one test file and returns its catalog entry.
 *
 * @return array<string, mixed>
 */
function audit_file(string $root, string $relative): array {
  $source = (string) file_get_contents($root . '/' . $relative);
  $baseClass = audit_base_class($source);
  $type = audit_type($relative);

  // Count test methods (public function test*/probe*/scenario*).
  preg_match_all('/public\s+function\s+(test\w+|\w+Test)\s*\(/', $source, $methods);
  $methodCount = count($methods[1]);

  // Count assertions, including expectException and markTestSkipped signals.
  preg_match_all('/\$this->(?:assert\w+|expectException\w*|fail|markTestSkipped|markTestIncomplete)\s*\(/', $source, $assertions);
  preg_match_all('/self::(?:assert\w+|markTestSkipped)\s*\(/', $source, $staticAssertions);
  // A mock expectation (->expects(...)->method()->with(...)) is an assertion:
  // it fails the test when the collaborator is called with other arguments.
  preg_match_all('/->expects\s*\(/', $source, $expectations);
  $assertionCount = count($assertions[0]) + count($staticAssertions[0]) + count($expectations[0]);

  $flags = [];
  if ($assertionCount === 0) {
    $flags[] = 'zero_assertions';
  }
  // Counting an array literal the test itself builds.
  if (preg_match('/assertCount\(\s*\d+\s*,\s*\[/', $source) === 1) {
    $flags[] = 'counts_literal_array';
  }
  // assertSame($x, $x) or assertEquals($x, $x).
  if (preg_match('/assert(?:Same|Equals)\(\s*([^,]+?),\s*\1\s*[,)]/', $source) === 1) {
    $flags[] = 'same_value_both_sides';
  }
  // Only checks that a file exists and nothing else.
  if (str_contains($source, 'assertFileExists') && $assertionCount <= (preg_match_all('/assertFileExists\s*\(/', $source) ?: 0)) {
    $flags[] = 'file_exists_only';
  }
  // Environment-guarded skips are expected; only an unguarded skip is a
  // permanent skip that needs justification.
  $lines = preg_split('/\R/', $source) ?: [];
  $hasSkip = FALSE;
  $guarded = TRUE;
  foreach ($lines as $index => $line) {
    if (preg_match('/markTestSkipped\s*\(/', $line) === 1) {
      $hasSkip = TRUE;
      $context = implode(' ', array_slice($lines, max(0, $index - 4), 4));
      if (preg_match('/if\s*\(|getenv\(|extension_loaded|function_exists|class_exists|defined\(|SIMPLETEST|proc_open|posix_/i', $context) !== 1) {
        $guarded = FALSE;
      }
    }
  }
  if ($hasSkip) {
    $flags[] = $guarded ? 'environment_skip' : 'unconditional_skip';
  }
  // Static config/string contracts: reads YAML or PHP source without a Drupal base.
  if (($baseClass === 'UnitTestCase' || $baseClass === 'TestCase')
    && (str_contains($source, 'Yaml::parseFile') || str_contains($source, 'file_get_contents'))
    && !str_contains($source, 'createMock')) {
    $flags[] = 'static_config_contract';
  }
  // Mocks only, no entity/container integration.
  if (str_contains($source, 'createMock') && !str_contains($source, 'KernelTestBase') && !str_contains($source, 'BrowserTestBase')) {
    $flags[] = 'mock_isolated';
  }
  // Deprecated doc-comment metadata.
  if (preg_match('/^\s*\*\s*@(group|covers|dataProvider|depends)\b/m', $source) === 1) {
    $flags[] = 'doc_comment_metadata';
  }
  // Names that require a human check, never an automatic removal.
  if (preg_match('/\b(Legacy|Characterization|Migration|Contract)\b/', $source) === 1) {
    $flags[] = 'review_by_name';
  }

  $productionNamespace = NULL;
  if (preg_match('/use\s+(Drupal\\\\[A-Za-z0-9_\\\\]+);/', $source, $match) === 1) {
    $productionNamespace = $match[1];
  }

  return [
    'path' => $relative,
    'extension' => audit_extension($relative),
    'type' => $type,
    'base_class' => $baseClass,
    'groups' => audit_groups($source),
    'test_methods' => $methodCount,
    'assertions' => $assertionCount,
    'assertions_per_test' => $methodCount > 0 ? round($assertionCount / $methodCount, 2) : 0,
    'first_import' => $productionNamespace,
    'fixture' => audit_is_fixture($relative),
    'flags' => $flags,
  ];
}

$root = dirname(__DIR__);
$jsonTarget = NULL;
$summary = FALSE;
foreach (array_slice($argv, 1) as $index => $argument) {
  if ($argument === '--json') {
    $jsonTarget = $argv[$index + 2] ?? NULL;
  }
  if ($argument === '--summary') {
    $summary = TRUE;
  }
}

$entries = [];
foreach (AUDIT_ROOTS as $scanRoot) {
  $base = $root . '/' . $scanRoot;
  if (!is_dir($base)) {
    continue;
  }
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
  );
  foreach ($iterator as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), 'Test.php')) {
      continue;
    }
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
    $entries[] = audit_file($root, $relative);
  }
}
usort($entries, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

$catalog = [
  'generated_at' => gmdate('c'),
  'generator' => 'scripts/audit-custom-tests.php',
  'total_files' => count($entries),
  'by_type' => array_count_values(array_column($entries, 'type')),
  'by_extension' => array_count_values(array_column($entries, 'extension')),
  'flag_counts' => (static function (array $entries): array {
    $counts = [];
    foreach ($entries as $entry) {
      foreach ($entry['flags'] as $flag) {
        $counts[$flag] = ($counts[$flag] ?? 0) + 1;
      }
    }
    ksort($counts);
    return $counts;
  })($entries),
  'files' => $entries,
];

if ($jsonTarget !== NULL) {
  $directory = dirname($jsonTarget);
  if (!is_dir($directory)) {
    mkdir($directory, 0775, TRUE);
  }
  file_put_contents($root . '/' . ltrim($jsonTarget, '/'), json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
  fwrite(STDOUT, 'Catálogo escrito em ' . $jsonTarget . ' (' . count($entries) . " arquivos)\n");
}

if ($summary || $jsonTarget === NULL) {
  fwrite(STDOUT, "Total: {$catalog['total_files']}\n");
  foreach ($catalog['by_type'] as $type => $count) {
    fwrite(STDOUT, "  {$type}: {$count}\n");
  }
  fwrite(STDOUT, "Flags:\n");
  foreach ($catalog['flag_counts'] as $flag => $count) {
    fwrite(STDOUT, "  {$flag}: {$count}\n");
  }
  $low = array_values(array_filter($entries, static fn (array $entry): bool => array_intersect($entry['flags'], ['zero_assertions', 'counts_literal_array', 'same_value_both_sides', 'file_exists_only']) !== []));
  fwrite(STDOUT, 'Candidatos claros de baixo valor: ' . count($low) . "\n");
  foreach ($low as $entry) {
    fwrite(STDOUT, '  ' . $entry['path'] . ' [' . implode(',', $entry['flags']) . "]\n");
  }
}
