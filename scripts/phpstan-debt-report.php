<?php

declare(strict_types=1);

/**
 * @file
 * Builds a PHPStan debt report from the JSON output of `phpstan analyse`.
 *
 * Usage:
 *   php scripts/phpstan-debt-report.php /tmp/phpstan.json docs/operations/testing/phpstan-debt.md
 *
 * Optional evolution: pass a second JSON as the third argument to compare the
 * current run against a previous snapshot.
 */

$jsonPath = $argv[1] ?? '/tmp/phpstan.json';
$target = $argv[2] ?? NULL;
$previousPath = $argv[3] ?? NULL;

$data = json_decode((string) file_get_contents($jsonPath), TRUE);
if (!is_array($data) || !isset($data['files'])) {
  fwrite(STDERR, "JSON do PHPStan inválido: {$jsonPath}\n");
  exit(1);
}

/**
 * Maps a file path to its owning module or theme.
 */
function phpstan_debt_module(string $path): string {
  // PHPStan emits absolute paths; normalize to a repository-relative path.
  if (preg_match('#(web/(?:modules|themes)/custom/.*|scripts/.*)$#', $path, $normalized) === 1) {
    $path = $normalized[1];
  }
  if (preg_match('#^web/modules/custom/aculta_portal/modules/([^/]+)/#', $path, $m) === 1) {
    return $m[1];
  }
  if (str_starts_with($path, 'web/modules/custom/aculta_portal/')) {
    return 'aculta_portal';
  }
  if (preg_match('#^web/modules/custom/([^/]+)/#', $path, $m) === 1) {
    return $m[1];
  }
  if (preg_match('#^web/themes/custom/([^/]+)/#', $path, $m) === 1) {
    return $m[1];
  }
  if (str_starts_with($path, 'scripts/')) {
    return 'scripts';
  }
  return 'outros';
}

/**
 * Classifies a finding by functional risk.
 */
function phpstan_debt_criticality(string $identifier, string $path): string {
  $highIdentifiers = ['access', 'security', 'sql', 'xss', 'csrf', 'permission', 'argument.type', 'return.type'];
  $highPaths = ['pagamento', 'mercadopago', 'checkout', 'cart', 'access', 'user', 'forum', 'wiki'];
  foreach ($highIdentifiers as $needle) {
    if (str_contains($identifier, $needle)) {
      return 'alta';
    }
  }
  foreach ($highPaths as $needle) {
    if (str_contains(strtolower($path), $needle)) {
      return 'media';
    }
  }
  if (in_array($identifier, ['missingType.return', 'missingType.parameter', 'unused', 'deprecation'], TRUE)) {
    return 'baixa';
  }
  return 'media';
}

$byIdentifier = [];
$byModule = [];
$byCriticality = ['alta' => 0, 'media' => 0, 'baixa' => 0];
$total = 0;
foreach ($data['files'] as $path => $file) {
  $module = phpstan_debt_module($path);
  foreach ($file['messages'] as $message) {
    $total++;
    $identifier = $message['identifier'] ?? 'sem.identificador';
    $byIdentifier[$identifier] = ($byIdentifier[$identifier] ?? 0) + 1;
    $byModule[$module] = ($byModule[$module] ?? 0) + 1;
    $byCriticality[phpstan_debt_criticality($identifier, $path)]++;
  }
}
arsort($byIdentifier);
arsort($byModule);

$lines = [];
$lines[] = '# Dívida PHPStan — ACULTA';
$lines[] = '';
$lines[] = 'Gerado em ' . gmdate('c') . ' por `scripts/phpstan-debt-report.php`.';
$lines[] = '';
$lines[] = "**Total de erros: {$total}**";
$lines[] = '';
$lines[] = '## Por criticidade';
$lines[] = '';
$lines[] = '| Criticidade | Erros |';
$lines[] = '| --- | ---: |';
foreach ($byCriticality as $criticality => $count) {
  $lines[] = "| {$criticality} | {$count} |";
}
$lines[] = '';
$lines[] = '## Dez identificadores mais frequentes';
$lines[] = '';
$lines[] = '| Identificador | Erros |';
$lines[] = '| --- | ---: |';
foreach (array_slice($byIdentifier, 0, 10, TRUE) as $identifier => $count) {
  $lines[] = "| `{$identifier}` | {$count} |";
}
$lines[] = '';
$lines[] = '## Módulos mais problemáticos';
$lines[] = '';
$lines[] = '| Módulo | Erros |';
$lines[] = '| --- | ---: |';
foreach (array_slice($byModule, 0, 20, TRUE) as $module => $count) {
  $lines[] = "| {$module} | {$count} |";
}
$lines[] = '';

if ($previousPath !== NULL && is_file($previousPath)) {
  $previous = json_decode((string) file_get_contents($previousPath), TRUE);
  $previousTotal = 0;
  if (is_array($previous) && isset($previous['files'])) {
    foreach ($previous['files'] as $file) {
      $previousTotal += count($file['messages']);
    }
  }
  $delta = $total - $previousTotal;
  $lines[] = '## Evolução';
  $lines[] = '';
  $lines[] = "| Snapshot | Erros |";
  $lines[] = '| --- | ---: |';
  $lines[] = "| Anterior | {$previousTotal} |";
  $lines[] = "| Atual | {$total} |";
  $lines[] = "| Delta | {$delta} |";
  $lines[] = '';
}

$markdown = implode("\n", $lines) . "\n";
if ($target !== NULL) {
  if (!is_dir(dirname($target))) {
    mkdir(dirname($target), 0775, TRUE);
  }
  file_put_contents($target, $markdown);
  fwrite(STDOUT, "Relatório escrito em {$target} ({$total} erros).\n");
}
else {
  fwrite(STDOUT, $markdown);
}
