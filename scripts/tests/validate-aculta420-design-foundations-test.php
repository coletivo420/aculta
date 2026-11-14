<?php

/**
 * @file
 * Regression fixtures for the aculta420_v2 design-foundation validator.
 *
 * The former fixture suite mutated ACULTA420 v1 token values (green surfaces,
 * Bootstrap base pairs, v1 Studio fidelity hexes). Those tokens no longer exist
 * in aculta420_v2, so the fixtures failed for structural reasons instead of
 * protecting a contract. This suite copies the v2 theme, proves the clean
 * baseline passes, then injects one regression per invariant that still applies
 * to v2: no raw color literals outside tokens, no color-mode selector outside
 * tokens, no structural declaration inside a token block, no color-mode branch
 * in Twig, and a flat token parser. Path-safety fixtures stay generic.
 */

declare(strict_types=1);

$repository = dirname(__DIR__, 2);
$validator = $repository . '/scripts/validate-aculta420-design-foundations.php';
$analyzer = $repository . '/scripts/lib/Aculta420DesignFoundationsAnalyzer.php';
$theme_source = $repository . '/web/themes/custom/aculta420_v2';
$temporary_root = sys_get_temp_dir() . '/aculta420-v2-design-gate-' . bin2hex(random_bytes(6));
$temporary_theme = $temporary_root . '/web/themes/custom/aculta420_v2';
$fixtures = 0;
$failures = [];

$copy_file = static function (string $source, string $destination): void {
  if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0700, TRUE) && !is_dir(dirname($destination))) {
    throw new RuntimeException('Could not create fixture directory.');
  }
  if (!copy($source, $destination)) {
    throw new RuntimeException('Could not copy fixture file: ' . $source);
  }
};
$copy_tree = static function (string $source, string $destination) use (&$copy_tree): void {
  if (is_file($source)) {
    if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0700, TRUE) && !is_dir(dirname($destination))) {
      throw new RuntimeException('Could not create fixture directory.');
    }
    if (!copy($source, $destination)) {
      throw new RuntimeException('Could not copy fixture file: ' . $source);
    }
    return;
  }
  if (!is_dir($destination) && !mkdir($destination, 0700, TRUE) && !is_dir($destination)) {
    throw new RuntimeException('Could not create fixture directory.');
  }
  foreach (new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS) as $entry) {
    $copy_tree($entry->getPathname(), $destination . '/' . $entry->getFilename());
  }
};
$remove_tree = static function (string $path) use (&$remove_tree): void {
  if (is_link($path) || is_file($path)) {
    @unlink($path);
    return;
  }
  if (!is_dir($path)) {
    return;
  }
  foreach (scandir($path) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..') {
      continue;
    }
    $remove_tree($path . '/' . $entry);
  }
  @rmdir($path);
};

try {
  foreach ([
    'src/styles/tokens.css',
    'aculta420_v2.info.yml',
    'aculta420_v2.libraries.yml',
  ] as $relative) {
    $copy_file($theme_source . '/' . $relative, $temporary_theme . '/' . $relative);
  }
  $copy_tree($theme_source . '/assets/icons/phosphor', $temporary_theme . '/assets/icons/phosphor');
  $copy_file($validator, $temporary_root . '/scripts/validate-aculta420-design-foundations.php');
  $copy_file($analyzer, $temporary_root . '/scripts/lib/Aculta420DesignFoundationsAnalyzer.php');

  $run_validator = static function () use ($temporary_root): array {
    $command = [
      PHP_BINARY,
      $temporary_root . '/scripts/validate-aculta420-design-foundations.php',
      '--root=' . $temporary_root,
    ];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
      throw new RuntimeException('Could not start validator fixture process.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), (string) $stdout . (string) $stderr];
  };

  // The clean baseline proves each failure comes from its injected fixture.
  [$baseline_status, $baseline_output] = $run_validator();
  $fixtures++;
  if ($baseline_status !== 0) {
    $failures[] = 'clean v2 fixture baseline did not pass: ' . trim(preg_replace('/\s+/', ' ', $baseline_output) ?? $baseline_output);
  }

  require_once $analyzer;
  foreach ([
    ['/tmp/aculta420-root', 'Unix', TRUE],
    ['relative/path', 'Unix', FALSE],
    ['/tmp/aculta420/../outside', 'Unix', FALSE],
    ['C:\\aculta420\\repo', 'Windows', TRUE],
    ['C:aculta420\\repo', 'Windows', FALSE],
    ['C:\\aculta420\\..\\outside', 'Windows', FALSE],
    ['\\\\server\\share\\aculta420', 'Windows', TRUE],
    ['\\\\server\\share\\..\\outside', 'Windows', FALSE],
  ] as [$path, $platform, $expected]) {
    $fixtures++;
    if (Aculta420DesignFoundationsAnalyzer::isAbsolutePath($path, $platform) !== $expected) {
      $failures[] = 'absolute path validation returned the wrong result for ' . $platform . ' syntax';
    }
  }

  /**
   * Writes a fixture file in the temporary theme and runs the validator.
   */
  $run_fixture = static function (string $relative, string $contents, string $expected_message, string $label) use ($temporary_theme, $run_validator, &$fixtures, &$failures): void {
    $path = $temporary_theme . '/' . $relative;
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, TRUE) && !is_dir(dirname($path))) {
      throw new RuntimeException('Could not create fixture directory.');
    }
    file_put_contents($path, $contents);
    [$status, $output] = $run_validator();
    $fixtures++;
    if ($status === 0 || !str_contains($output, $expected_message)) {
      $failures[] = $label . ' was not rejected: ' . trim(preg_replace('/\s+/', ' ', $output) ?? $output);
    }
    unlink($path);
  };

  $run_fixture(
    'css/fixtures/raw-color.css',
    '.notice { color: red; }',
    'CSS literals outside tokens.css need semantic tokens',
    'a named color literal outside tokens',
  );
  $run_fixture(
    'css/fixtures/oklch-color.css',
    '.notice { color: oklch(60% 0.2 120); }',
    'CSS literals outside tokens.css need semantic tokens',
    'an oklch color literal outside tokens',
  );
  $run_fixture(
    'css/fixtures/dark-selector.css',
    '[data-theme="dark"] .card { padding: 1rem; }',
    'Color mode is token-only',
    'a color-mode selector outside tokens',
  );
  $run_fixture(
    'templates/color-mode-branch.html.twig',
    "{% if theme == 'dark' %}different{% endif %}",
    'Twig has no color-mode branch',
    'a Twig color-mode branch',
  );

  // The token parser stays flat: an @import is rejected.
  $tokens_path = $temporary_theme . '/src/styles/tokens.css';
  $original_tokens = file_get_contents($tokens_path);
  if (!is_string($original_tokens)) {
    throw new RuntimeException('Could not read token fixture.');
  }
  $import_tokens = $original_tokens . "\n@import url('https://example.invalid/structural.css');\n";
  file_put_contents($tokens_path, $import_tokens);
  [$status, $output] = $run_validator();
  $fixtures++;
  if ($status === 0 || !str_contains($output, 'Token CSS parses within the supported flat-rule subset')) {
    $failures[] = 'an @import inside the token stylesheet was not rejected';
  }
  file_put_contents($tokens_path, $original_tokens);
}
catch (Throwable $exception) {
  $failures[] = 'fixture harness failed: ' . $exception->getMessage();
}
finally {
  $remove_tree($temporary_root);
}

if ($failures !== []) {
  fwrite(STDERR, 'DESIGN FOUNDATIONS GATE: FAIL (' . $fixtures . " fixtures)\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, ' - ' . $failure . "\n");
  }
  exit(1);
}

fwrite(STDOUT, 'DESIGN FOUNDATIONS GATE: PASS (' . $fixtures . " fixtures)\n");
