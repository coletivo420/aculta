<?php

declare(strict_types=1);

/**
 * @file
 * Generates and verifies the PHPUnit testsuites for ACULTA custom code.
 *
 * PHPUnit does not expand globs inside <directory> entries, and ACULTA keeps
 * Kernel/Unit tests nested under tests/src/Integration/<domain>/<Type>. A suite
 * declared as a wildcard over tests/src/Kernel silently misses those files.
 * the extension tree, classifies every candidate Test.php by its test type and
 * emits one explicit <directory> entry per directory. Running with --check
 * fails when a candidate is not covered by any suite, or when a mandatory suite
 * is empty, so discovery cannot silently regress.
 *
 * Usage:
 *   php scripts/phpunit-suites.php --write   # regenerate phpunit.xml files
 *   php scripts/phpunit-suites.php --check   # verify committed suites
 */

const ACULTA_PHPUNIT_ROOTS = ['web/modules/custom', 'web/themes/custom'];

/**
 * Suites that must always discover at least one test.
 *
 * An empty mandatory suite is a failure, never a silent success. `integration`
 * and `performance` are optional: they are emitted only when they discover
 * tests, but `performance` is still required in its own opt-in document.
 */
const ACULTA_PHPUNIT_REQUIRED_TYPES = ['unit', 'kernel', 'functional', 'functionaljs'];

/**
 * Maps a candidate test file to its execution type.
 */
function aculta_phpunit_test_type(string $relativePath): ?string {
  $needle = '/tests/src/';
  $position = strpos($relativePath, $needle);
  if ($position === FALSE) {
    return NULL;
  }
  $segments = explode('/', substr($relativePath, $position + strlen($needle)));
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
    default => NULL,
  };
}

/**
 * Returns every candidate test file relative to the repository root.
 *
 * Candidates are exactly the PHPUnit tests under an extension's tests/src.
 * Support modules under tests/modules are not test cases and are ignored.
 *
 * @return array<string, string>
 *   Map of relative file path to execution type.
 */
function aculta_phpunit_candidates(string $root): array {
  $candidates = [];
  foreach (ACULTA_PHPUNIT_ROOTS as $scanRoot) {
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
      $relative = substr($file->getPathname(), strlen($root) + 1);
      $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
      if (!str_contains($relative, '/tests/src/')) {
        continue;
      }
      $type = aculta_phpunit_test_type($relative);
      if ($type === NULL) {
        fwrite(STDERR, "Tipo de teste desconhecido: {$relative}\n");
        exit(1);
      }
      $candidates[$relative] = $type;
    }
  }
  ksort($candidates);
  return $candidates;
}

/**
 * Groups candidate files into unique directories per execution type.
 *
 * @return array<string, list<string>>
 *   Map of type to sorted unique directories.
 */
function aculta_phpunit_directories(array $candidates): array {
  $directories = [];
  foreach ($candidates as $relative => $type) {
    $directories[$type][dirname($relative)] = TRUE;
  }
  foreach ($directories as $type => $dirs) {
    $list = array_keys($dirs);
    sort($list);
    // <directory> is recursive, so a parent already covers its descendants.
    // Emitting both would register the same file twice and make PHPUnit warn.
    $minimal = [];
    foreach ($list as $directory) {
      $covered = FALSE;
      foreach ($minimal as $kept) {
        if (str_starts_with($directory, $kept . '/')) {
          $covered = TRUE;
          break;
        }
      }
      if (!$covered) {
        $minimal[] = $directory;
      }
    }
    $directories[$type] = $minimal;
  }
  ksort($directories);
  return $directories;
}

/**
 * Renders one phpunit.xml document.
 *
 * @param array<string, list<string>> $directories
 *   Directories grouped by execution type.
 * @param list<string> $types
 *   Test types to include in this document.
 * @param string $cacheDirectory
 *   PHPUnit cache directory name.
 */
function aculta_phpunit_render(array $directories, array $types, string $cacheDirectory): string {
  $lines = [];
  $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
  $lines[] = '<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';
  $lines[] = '  bootstrap="tests/phpunit-bootstrap.php"';
  $lines[] = '  colors="true"';
  $lines[] = '  failOnWarning="true"';
  $lines[] = '  cacheDirectory="' . $cacheDirectory . '"';
  $lines[] = '  xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.5/phpunit.xsd">';
  $lines[] = '  <php>';
  $lines[] = '    <ini name="error_reporting" value="32767"/>';
  $lines[] = '    <ini name="memory_limit" value="-1"/>';
  $lines[] = '  </php>';
  $lines[] = '  <testsuites>';
  foreach ($types as $type) {
    $suiteDirectories = $directories[$type] ?? [];
    if ($suiteDirectories === []) {
      if (in_array($type, ACULTA_PHPUNIT_REQUIRED_TYPES, TRUE)) {
        fwrite(STDERR, "Suíte obrigatória vazia: {$type}\n");
        exit(1);
      }
      continue;
    }
    $lines[] = '    <testsuite name="' . $type . '">';
    foreach ($suiteDirectories as $directory) {
      $lines[] = '      <directory suffix="Test.php">' . htmlspecialchars($directory, ENT_XML1) . '</directory>';
    }
    $lines[] = '    </testsuite>';
  }
  $lines[] = '  </testsuites>';
  $lines[] = '</phpunit>';
  return implode("\n", $lines) . "\n";
}

/**
 * Returns candidates that no generated suite covers.
 *
 * A file is covered when its directory equals a declared directory or is a
 * descendant of one, because <directory> is recursive.
 *
 * @return list<string>
 *   Human-readable uncovered file descriptions.
 */
function aculta_phpunit_uncovered(array $candidates, array $directories): array {
  $covered = [];
  foreach ($directories as $type => $dirs) {
    if ($type === 'performance') {
      continue;
    }
    foreach ($dirs as $dir) {
      $covered[] = $dir;
    }
  }
  $uncovered = [];
  foreach ($candidates as $relative => $type) {
    if ($type === 'performance') {
      continue;
    }
    $directory = dirname($relative);
    $isCovered = FALSE;
    foreach ($covered as $declared) {
      if ($directory === $declared || str_starts_with($directory, $declared . '/')) {
        $isCovered = TRUE;
        break;
      }
    }
    if (!$isCovered) {
      $uncovered[] = "Teste fora de qualquer suíte: {$relative} ({$type})";
    }
  }
  return $uncovered;
}

/**
 * Splits Kernel test directories into balanced, non-overlapping shards.
 *
 * @param list<string> $kernelDirectories
 * @param int $shardCount
 * @param string $root
 *
 * @return array<array{directories: list<string>, test_files: int}>
 */
function aculta_phpunit_kernel_shards(array $kernelDirectories, int $shardCount, string $root): array {
  if ($shardCount < 1 || $shardCount > count($kernelDirectories)) {
    throw new InvalidArgumentException('A quantidade de shards deve caber nos diretórios Kernel.');
  }

  $weightedDirectories = [];
  foreach ($kernelDirectories as $directory) {
    $absoluteDirectory = $root . '/' . $directory;
    if (!is_dir($absoluteDirectory)) {
      throw new RuntimeException("Diretório Kernel ausente: {$directory}");
    }
    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($absoluteDirectory, FilesystemIterator::SKIP_DOTS),
    );
    $testFiles = 0;
    foreach ($iterator as $file) {
      if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
        $testFiles++;
      }
    }
    if ($testFiles === 0) {
      throw new RuntimeException("Diretório Kernel sem testes: {$directory}");
    }
    $weightedDirectories[] = ['directory' => $directory, 'test_files' => $testFiles];
  }

  usort($weightedDirectories, static function (array $left, array $right): int {
    return ($right['test_files'] <=> $left['test_files'])
      ?: strcmp($left['directory'], $right['directory']);
  });

  $shards = array_fill(0, $shardCount, ['directories' => [], 'test_files' => 0]);
  foreach ($weightedDirectories as $weightedDirectory) {
    $lightestShard = 0;
    foreach ($shards as $index => $shard) {
      if ($shard['test_files'] < $shards[$lightestShard]['test_files']) {
        $lightestShard = $index;
      }
    }
    $shards[$lightestShard]['directories'][] = $weightedDirectory['directory'];
    $shards[$lightestShard]['test_files'] += $weightedDirectory['test_files'];
  }

  foreach ($shards as &$shard) {
    sort($shard['directories']);
  }
  unset($shard);

  return $shards;
}

$root = dirname(__DIR__);
$mode = $argv[1] ?? '--check';
$kernelShardCount = NULL;
if (preg_match('/^--kernel-shards=(\d+)$/', $mode, $matches) === 1) {
  $kernelShardCount = (int) $matches[1];
}
elseif (!in_array($mode, ['--write', '--check'], TRUE)) {
  fwrite(STDERR, "Uso: php scripts/phpunit-suites.php [--write|--check|--kernel-shards=N]\n");
  exit(2);
}

$candidates = aculta_phpunit_candidates($root);
$directories = aculta_phpunit_directories($candidates);
$kernelDirectories = $directories['kernel'] ?? [];
if ($kernelShardCount !== NULL) {
  try {
    $shards = aculta_phpunit_kernel_shards($kernelDirectories, $kernelShardCount, $root);
  }
  catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
  }
  $matrix = ['include' => []];
  foreach ($shards as $index => $shard) {
    $matrix['include'][] = [
      'shard' => $index + 1,
      'test_files' => $shard['test_files'],
      'directories' => implode(' ', $shard['directories']),
    ];
  }
  $json = json_encode($matrix, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
  $githubOutput = getenv('GITHUB_OUTPUT');
  if ($githubOutput !== FALSE) {
    file_put_contents($githubOutput, "kernel_shards={$json}\n", FILE_APPEND | LOCK_EX);
  }
  fwrite(STDOUT, $json . "\n");
  exit(0);
}
$defaultTypes = ['unit', 'kernel', 'integration', 'functional', 'functionaljs'];
if (($directories['performance'] ?? []) === []) {
  fwrite(STDERR, "Suíte de performance vazia: os benchmarks opt-in desapareceram.\n");
  exit(1);
}
$defaultXml = aculta_phpunit_render($directories, $defaultTypes, '.phpunit.cache');
$performanceXml = aculta_phpunit_render($directories, ['performance'], '.phpunit.performance.cache');

if ($mode === '--write') {
  file_put_contents($root . '/phpunit.xml', $defaultXml);
  file_put_contents($root . '/phpunit.performance.xml', $performanceXml);
  $counts = array_count_values($candidates);
  ksort($counts);
  $summary = [];
  foreach ($counts as $type => $count) {
    $summary[] = "{$type}={$count}";
  }
  fwrite(STDOUT, 'phpunit.xml escrito: ' . implode(', ', $summary) . "\n");
  exit(0);
}

$failures = [];
if (!is_file($root . '/phpunit.xml') || file_get_contents($root . '/phpunit.xml') !== $defaultXml) {
  $failures[] = 'phpunit.xml está desatualizado; rode scripts/phpunit-suites.php --write.';
}
if (!is_file($root . '/phpunit.performance.xml') || file_get_contents($root . '/phpunit.performance.xml') !== $performanceXml) {
  $failures[] = 'phpunit.performance.xml está desatualizado; rode scripts/phpunit-suites.php --write.';
}
$uncovered = aculta_phpunit_uncovered($candidates, $directories);
if ($uncovered !== []) {
  $failures[] = implode("\n", $uncovered);
}
if ($failures !== []) {
  fwrite(STDERR, implode("\n", $failures) . "\n");
  exit(1);
}
$counts = array_count_values($candidates);
ksort($counts);
$summary = [];
foreach ($counts as $type => $count) {
  $summary[] = "{$type}={$count}";
}
fwrite(STDOUT, 'Descoberta OK: ' . implode(', ', $summary) . "\n");
exit(0);
