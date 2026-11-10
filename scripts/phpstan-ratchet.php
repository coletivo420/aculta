<?php

declare(strict_types=1);

/**
 * @file
 * Fails when PHPStan reports more errors than the recorded baseline.
 *
 * The project has a large pre-existing PHPStan debt. Instead of masking it
 * with `|| true` or committing a multi-megabyte baseline, this ratchet records
 * the current error count in phpstan-baseline-count.txt and fails only when
 * the count grows. Burning debt down is then rewarded, not required in one go.
 *
 * Usage:
 *   php scripts/phpstan-ratchet.php           # verify
 *   php scripts/phpstan-ratchet.php --update  # record the current count
 */

$root = dirname(__DIR__);
$baselineFile = $root . '/phpstan-baseline-count.txt';
$jsonFile = $root . '/.phpstan-ratchet.json';

$command = implode(' ', array_map('escapeshellarg', [
  PHP_BINARY,
  $root . '/vendor/bin/phpstan',
  'analyse',
  '--no-progress',
  '--error-format=json',
  '--memory-limit=1G',
]));
exec($command . ' > ' . escapeshellarg($jsonFile) . ' 2>/dev/null', $output, $status);

$json = is_file($jsonFile) ? (string) file_get_contents($jsonFile) : '';
@unlink($jsonFile);
$data = json_decode($json, TRUE);
if (!is_array($data) || !isset($data['totals']['file_errors'])) {
  fwrite(STDERR, 'PHPStan não produziu JSON válido (status ' . $status . ").\n");
  exit(1);
}
$count = (int) $data['totals']['file_errors'] + (int) ($data['totals']['errors'] ?? 0);

if (in_array('--update', $argv, TRUE)) {
  file_put_contents($baselineFile, $count . "\n");
  fwrite(STDOUT, "PHPStan ratchet: baseline atualizado para {$count} erros.\n");
  exit(0);
}

$baseline = is_file($baselineFile) ? (int) trim((string) file_get_contents($baselineFile)) : 0;
if ($count > $baseline) {
  fwrite(STDERR, "PHPStan ratchet: {$count} erros > baseline {$baseline}. Corrija ou justifique o aumento.\n");
  exit(1);
}
fwrite(STDOUT, "PHPStan ratchet: {$count} erros <= baseline {$baseline}.\n");
