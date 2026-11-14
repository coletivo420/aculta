<?php

declare(strict_types=1);

/**
 * @file
 * Builds the PHPStan debt report from the frozen baseline and an analysis JSON.
 *
 * Preferred usage:
 *   php scripts/phpstan-debt-report.php \
 *     --baseline=phpstan-baseline.neon \
 *     --analysis=/tmp/phpstan.json \
 *     --reference-baseline=/tmp/baseline-reference.neon \
 *     --out=docs/operations/testing/phpstan-debt.md \
 *     --json-out=/tmp/phpstan-debt.json
 *
 * Legacy usage (analysis only):
 *   php scripts/phpstan-debt-report.php /tmp/phpstan.json out.md [previous.json]
 *
 * The analysis JSON must come from the canonical baseline-applied run: it
 * carries the unsuppressed errors and any ignore.unmatched/ignore.count.
 * Frozen debt and its distribution come from the baseline itself. The report
 * never regenerates the baseline.
 */

require_once __DIR__ . '/lib/PhpstanDebt.php';

$positional = [];
$options = [];
foreach (array_slice($argv, 1) as $argument) {
  if (preg_match('/^--([a-z-]+)=(.*)$/s', $argument, $match) === 1) {
    $options[$match[1]] = $match[2];
    continue;
  }
  $positional[] = $argument;
}

$analysisPath = $options['analysis'] ?? ($positional[0] ?? '/tmp/phpstan.json');
$target = $options['out'] ?? ($positional[1] ?? NULL);
$jsonTarget = $options['json-out'] ?? NULL;
$baselinePath = $options['baseline'] ?? NULL;
$referencePath = $options['reference-baseline'] ?? NULL;

$messages = phpstan_report_messages($analysisPath);
$entries = [];
if ($baselinePath !== NULL && $baselinePath !== '') {
  if (!is_file($baselinePath)) {
    fwrite(STDERR, "Baseline não encontrado: {$baselinePath}\n");
    exit(2);
  }
  $entries = PhpstanDebt::parseBaseline((string) file_get_contents($baselinePath));
}
$referenceEntries = [];
if ($referencePath !== NULL && $referencePath !== '') {
  if (!is_file($referencePath)) {
    fwrite(STDERR, "Baseline de referência não encontrado: {$referencePath}\n");
    exit(2);
  }
  $referenceEntries = PhpstanDebt::parseBaseline((string) file_get_contents($referencePath));
}

$uncovered = [];
$suppressionIssues = 0;
foreach ($messages as $message) {
  if (str_starts_with($message['identifier'], 'ignore.')) {
    $suppressionIssues++;
    continue;
  }
  $uncovered[] = $message;
}
$frozenTotal = 0;
foreach ($entries as $entry) {
  $frozenTotal += $entry['count'];
}
$residualTotal = count($uncovered);
$grossTotal = $frozenTotal + $residualTotal;

$moduleTotals = [];
$identifierTotals = [];
$fileTotals = [];
$criticalityTotals = ['alta' => 0, 'media' => 0, 'baixa' => 0];
$productionTests = ['producao' => 0, 'testes' => 0];
foreach ($entries as $entry) {
  $module = PhpstanDebt::module($entry['path']);
  $identifier = $entry['identifier'] !== '' ? $entry['identifier'] : 'sem.identificador';
  $moduleTotals[$module] = ($moduleTotals[$module] ?? 0) + $entry['count'];
  $identifierTotals[$identifier] = ($identifierTotals[$identifier] ?? 0) + $entry['count'];
  $fileTotals[$entry['path']] = ($fileTotals[$entry['path']] ?? 0) + $entry['count'];
  $criticalityTotals[PhpstanDebt::criticality($entry['identifier'], $entry['path'], $entry['message'])] += $entry['count'];
  $productionTests[PhpstanDebt::isTest($entry['path']) ? 'testes' : 'producao'] += $entry['count'];
}
foreach ($uncovered as $message) {
  $module = PhpstanDebt::module($message['path']);
  $identifier = $message['identifier'] !== '' ? $message['identifier'] : 'sem.identificador';
  $moduleTotals[$module] = ($moduleTotals[$module] ?? 0) + 1;
  $identifierTotals[$identifier] = ($identifierTotals[$identifier] ?? 0) + 1;
  $fileTotals[$message['path']] = ($fileTotals[$message['path']] ?? 0) + 1;
  $criticalityTotals[PhpstanDebt::criticality($message['identifier'], $message['path'], $message['message'])]++;
  $productionTests[PhpstanDebt::isTest($message['path']) ? 'testes' : 'producao']++;
}
arsort($moduleTotals);
arsort($identifierTotals);
arsort($fileTotals);

$comparison = $referenceEntries !== [] ? PhpstanDebt::compare($referenceEntries, $entries) : NULL;
$sha = $options['sha'] ?? phpstan_report_git(['rev-parse', 'HEAD']);
$phpstanVersion = $options['phpstan-version'] ?? phpstan_report_phpstan_version();
$scope = $options['scope'] ?? 'web/modules/custom, web/themes/custom, scripts';

$metrics = [
  'generated_at' => gmdate('c'),
  'sha' => $sha,
  'php' => PHP_VERSION,
  'phpstan' => $phpstanVersion,
  'scope' => $scope,
  'level' => (int) ($options['level'] ?? 10),
  'suppression_issues' => $suppressionIssues,
  'baseline' => $baselinePath,
  'reference_baseline' => $referencePath,
  'totals' => [
    'gross' => $grossTotal,
    'frozen' => $frozenTotal,
    'uncovered' => $residualTotal,
  ],
  'baseline_entries' => count($entries),
  'production_tests' => $productionTests,
  'criticality' => $criticalityTotals,
  'by_module' => $moduleTotals,
  'by_identifier' => $identifierTotals,
  'by_file' => array_slice($fileTotals, 0, 50, TRUE),
  'evolution' => $comparison === NULL ? NULL : [
    'removed' => count($comparison['removed']),
    'kept' => count($comparison['kept']),
    'added' => count($comparison['added']),
    'increased' => count($comparison['increased']),
    'decreased' => count($comparison['decreased']),
    'identity_changed' => count($comparison['modified']),
  ],
];

$lines = [];
$lines[] = '# Dívida PHPStan — ACULTA';
$lines[] = '';
$lines[] = sprintf('Gerado em %s por `scripts/phpstan-debt-report.php`.', (string) $metrics['generated_at']);
$lines[] = '';
$lines[] = '| Item | Valor |';
$lines[] = '| --- | --- |';
$lines[] = '| Commit | `' . $sha . '` |';
$lines[] = '| PHP | ' . PHP_VERSION . ' |';
$lines[] = '| PHPStan | ' . $phpstanVersion . ' |';
$lines[] = '| Nível | ' . $metrics['level'] . ' |';
$lines[] = '| Escopo | ' . $scope . ' |';
$lines[] = '| Inconsistências de baseline | ' . $suppressionIssues . ' |';
$lines[] = '';
$lines[] = '## Totais';
$lines[] = '';
$lines[] = '| Medida | Ocorrências |';
$lines[] = '| --- | ---: |';
$lines[] = '| Total bruto no nível 10 | ' . $grossTotal . ' |';
$lines[] = '| Dívida congelada no baseline | ' . $frozenTotal . ' |';
$lines[] = '| Erros não suprimidos | ' . $residualTotal . ' |';
$lines[] = '| Entradas no baseline | ' . count($entries) . ' |';
$lines[] = '| Produção | ' . $productionTests['producao'] . ' |';
$lines[] = '| Testes | ' . $productionTests['testes'] . ' |';
$lines[] = '';
$lines[] = '## Criticidade funcional';
$lines[] = '';
$lines[] = '| Criticidade | Ocorrências |';
$lines[] = '| --- | ---: |';
foreach ($criticalityTotals as $criticality => $count) {
  $lines[] = "| {$criticality} | {$count} |";
}
$lines[] = '';

if ($comparison !== NULL) {
  $lines[] = '## Evolução desde o baseline de referência';
  $lines[] = '';
  $lines[] = '| Classificação | Entradas |';
  $lines[] = '| --- | ---: |';
  $lines[] = '| Dívida removida | ' . count($comparison['removed']) . ' |';
  $lines[] = '| Dívida mantida | ' . count($comparison['kept']) . ' |';
  $lines[] = '| Nova dívida (bloqueante) | ' . count($comparison['added']) . ' |';
  $lines[] = '| Contagem aumentada (bloqueante) | ' . count($comparison['increased']) . ' |';
  $lines[] = '| Contagem reduzida | ' . count($comparison['decreased']) . ' |';
  $lines[] = '| Identidade de erro alterada (revisão) | ' . count($comparison['modified']) . ' |';
  $lines[] = '';
}

$lines[] = '## Dívida por módulo (prioridade de redução)';
$lines[] = '';
$lines[] = '| Módulo | Ocorrências |';
$lines[] = '| --- | ---: |';
foreach (array_slice($moduleTotals, 0, 30, TRUE) as $module => $count) {
  $lines[] = "| {$module} | {$count} |";
}
$lines[] = '';
$lines[] = '## Identificadores mais frequentes';
$lines[] = '';
$lines[] = '| Identificador | Ocorrências |';
$lines[] = '| --- | ---: |';
foreach (array_slice($identifierTotals, 0, 20, TRUE) as $identifier => $count) {
  $lines[] = "| `{$identifier}` | {$count} |";
}
$lines[] = '';
$lines[] = '## Arquivos com mais dívida';
$lines[] = '';
$lines[] = '| Arquivo | Ocorrências |';
$lines[] = '| --- | ---: |';
foreach (array_slice($fileTotals, 0, 20, TRUE) as $file => $count) {
  $lines[] = "| `{$file}` | {$count} |";
}
$lines[] = '';
$lines[] = '## Como usar';
$lines[] = '';
$lines[] = '- O total congelado é o teto histórico; qualquer erro fora dele reprova o gate.';
$lines[] = '- Ao alterar um arquivo, corrija a dívida desse arquivo antes de publicar.';
$lines[] = '- O baseline não é regenerado automaticamente; reduções exigem remover as entradas correspondentes no mesmo commit.';
$lines[] = '- Refira sempre SHA, versão do PHPStan e escopo ao comparar números.';
$lines[] = '';

$markdown = implode("\n", $lines) . "\n";
if ($target !== NULL && $target !== '') {
  $directory = dirname($target);
  if (!is_dir($directory)) {
    mkdir($directory, 0775, TRUE);
  }
  file_put_contents($target, $markdown);
  fwrite(STDOUT, "Relatório escrito em {$target} ({$grossTotal} ocorrências brutas; {$frozenTotal} congeladas; {$residualTotal} não suprimidas).\n");
}
else {
  fwrite(STDOUT, $markdown);
}
if ($jsonTarget !== NULL && $jsonTarget !== '') {
  $directory = dirname($jsonTarget);
  if (!is_dir($directory)) {
    mkdir($directory, 0775, TRUE);
  }
  file_put_contents($jsonTarget, json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
  fwrite(STDOUT, "Métricas JSON escritas em {$jsonTarget}.\n");
}

/**
 * Reads a PHPStan JSON report.
 *
 * @return list<array{key: string, path: string, identifier: string, message: string}>
 *   One item per reported error.
 */
function phpstan_report_messages(string $path): array {
  if (!is_file($path)) {
    fwrite(STDERR, "Relatório PHPStan não encontrado: {$path}\n");
    exit(2);
  }
  $data = json_decode((string) file_get_contents($path), TRUE);
  if (!is_array($data) || !isset($data['files']) || !is_array($data['files'])) {
    fwrite(STDERR, "JSON do PHPStan inválido ou interrompido: {$path}\n");
    exit(2);
  }
  $root = rtrim((string) getcwd(), '/') . '/';
  $messages = [];
  foreach ($data['files'] as $file => $fileData) {
    if (!is_array($fileData) || !isset($fileData['messages']) || !is_array($fileData['messages'])) {
      fwrite(STDERR, "Estrutura de arquivo inválida no JSON: {$file}\n");
      exit(2);
    }
    $filePath = str_replace('\\', '/', (string) $file);
    if (str_starts_with($filePath, $root)) {
      $filePath = substr($filePath, strlen($root));
    }
    foreach ($fileData['messages'] as $message) {
      if (!is_array($message) || !isset($message['message']) || !is_string($message['message'])) {
        fwrite(STDERR, "Mensagem inválida no JSON: {$file}\n");
        exit(2);
      }
      $identifier = isset($message['identifier']) && is_string($message['identifier']) ? $message['identifier'] : '';
      $entry = [
        'path' => $filePath,
        'identifier' => $identifier,
        'message' => $message['message'],
        'count' => 1,
      ];
      $messages[] = [
        'key' => PhpstanDebt::key($entry),
        'path' => $filePath,
        'identifier' => $identifier,
        'message' => $message['message'],
      ];
    }
  }
  return $messages;
}

/**
 * Runs a git command and returns the first output line.
 *
 * @param list<string> $arguments
 *   Git arguments.
 */
function phpstan_report_git(array $arguments): string {
  $command = 'git ' . implode(' ', array_map('escapeshellarg', $arguments)) . ' 2>/dev/null';
  $output = [];
  $code = 0;
  exec($command, $output, $code);
  return $code === 0 ? trim((string) ($output[0] ?? '')) : '';
}

/**
 * Returns the installed PHPStan version, when available.
 */
function phpstan_report_phpstan_version(): string {
  $binary = __DIR__ . '/../vendor/bin/phpstan';
  if (!is_file($binary)) {
    return 'desconhecida';
  }
  $output = [];
  $code = 0;
  exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($binary) . ' --version 2>/dev/null', $output, $code);
  return $code === 0 ? trim((string) ($output[0] ?? '')) : 'desconhecida';
}
