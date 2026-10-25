<?php

/**
 * @file
 * Gera e valida a tabela PROJECT-STATUS-INDEX do README raiz.
 *
 * Fonte da tabela: um arquivo de status por componente em docs/operations/status/<machine>.yml
 * (campos: state, next_phase e, quando a versão do manifest não basta, version_note). A versão
 * vem do manifest do componente. Assim cada componente altera só o próprio arquivo, e a tabela
 * do README raiz é gerada, sem conflito entre trabalhos paralelos.
 *
 * Uso:
 *   php scripts/validate-project-status-index.php          valida (falha se a tabela estiver desatualizada)
 *   php scripts/validate-project-status-index.php --write  regenera a tabela do README raiz
 */

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$write = in_array('--write', $argv, TRUE);
$readmePath = $root . '/README.md';
$begin = '<!-- PROJECT-STATUS-INDEX:BEGIN -->';
$end = '<!-- PROJECT-STATUS-INDEX:END -->';
$statusDir = $root . '/docs/operations/status';
$errors = [];

$readme = is_file($readmePath) ? file_get_contents($readmePath) : FALSE;
if (!is_string($readme) || substr_count($readme, $begin) !== 1 || substr_count($readme, $end) !== 1) {
  fwrite(STDERR, "README.md precisa ter os marcadores PROJECT-STATUS-INDEX exatamente uma vez.\n");
  exit(1);
}

$agents = (string) @file_get_contents($root . '/AGENTS.md');
if (!str_contains($agents, 'PROJECT-STATUS-INDEX-GUARD')) {
  $errors[] = 'AGENTS.md precisa manter o marcador PROJECT-STATUS-INDEX-GUARD.';
}

// 1. Manifestos: componentes do Portal e o tema.
$manifests = [];
$portal = $root . '/web/modules/custom/aculta_portal/aculta_portal.info.yml';
if (is_file($portal)) {
  $manifests['aculta_portal'] = $portal;
}
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/web/modules/custom/aculta_portal/modules', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
  if (!$file->isFile() || !str_ends_with($file->getFilename(), '.info.yml') || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)) {
    continue;
  }
  $manifests[substr($file->getFilename(), 0, -strlen('.info.yml'))] = $file->getPathname();
}
$theme = $root . '/web/themes/custom/aculta420/aculta420.info.yml';
if (is_file($theme)) {
  $manifests['aculta420'] = $theme;
}

$manifestVersion = static function (string $manifest): ?string {
  $contents = (string) file_get_contents($manifest);
  return preg_match('/^version:\s*[\'"]?([^\'"\r\n]+)/m', $contents, $m) ? trim($m[1]) : NULL;
};

// 2. Arquivos de status.
$statuses = [];
foreach (glob($statusDir . '/*.yml') ?: [] as $file) {
  $machine = basename($file, '.yml');
  $data = Yaml::parseFile($file);
  $statuses[$machine] = is_array($data) ? $data : [];
}

$missing = array_diff(array_keys($manifests), array_keys($statuses));
$extra = array_diff(array_keys($statuses), array_keys($manifests));
if ($missing !== []) {
  $errors[] = 'Falta arquivo de status para: ' . implode(', ', $missing) . '.';
}
if ($extra !== []) {
  $errors[] = 'Arquivo de status sem componente: ' . implode(', ', $extra) . '.';
}

// 3. Campos, estado e links de cada componente.
$order = array_keys($manifests);
usort($order, static fn (string $a, string $b): int => match (TRUE) {
  $a === 'aculta_portal' => -1,
  $b === 'aculta_portal' => 1,
  $a === 'aculta420' => 1,
  $b === 'aculta420' => -1,
  default => strcmp($a, $b),
});

$rows = [];
foreach ($order as $machine) {
  if (!isset($statuses[$machine])) {
    continue;
  }
  $data = $statuses[$machine];
  $state = (string) ($data['state'] ?? '');
  $next = trim((string) ($data['next_phase'] ?? ''));
  if (!in_array($state, ['Dev', 'RC'], TRUE)) {
    $errors[] = "$machine: state precisa ser Dev ou RC (preRC está aposentado).";
  }
  if ($next === '') {
    $errors[] = "$machine: next_phase é obrigatório.";
  }
  $version = $data['version_note'] ?? $manifestVersion($manifests[$machine]);
  if ($version === NULL || $version === '') {
    $errors[] = "$machine: sem versão no manifest; informe version_note.";
    $version = 'versão não declarada';
  }
  $readmeRel = substr(dirname($manifests[$machine]) . '/README.md', strlen($root) + 1);
  if (!is_file($root . '/' . $readmeRel)) {
    $errors[] = "$machine: README de entrada ausente ($readmeRel).";
  }
  preg_match_all('/\]\(([^)#]+)(?:#[^)]*)?\)/', $next, $links);
  foreach ($links[1] ?? [] as $link) {
    if (!str_starts_with($link, 'http') && !is_file($root . '/' . $link)) {
      $errors[] = "$machine: link local ausente em next_phase: $link.";
    }
  }
  $cell = '`' . ($version === 'versão não declarada' ? '' : $version) . '`';
  if ($version === 'versão não declarada' || isset($data['version_note'])) {
    $cell = (string) ($data['version_note'] ?? 'versão não declarada');
  }
  $rows[] = sprintf('| [`%s`](%s) | %s | %s | %s |', $machine, $readmeRel, $cell, str_replace('|', '\\|', $next), $state);
}

// 4. Tabela gerada e conferência com o README.
$table = implode("\n", array_merge([
  '| Componente | Última versão declarada | Próxima fase registrada | Estado |',
  '| --- | --- | --- | --- |',
], $rows));

$start = strpos($readme, $begin) + strlen($begin);
$stop = strpos($readme, $end);
$current = trim(substr($readme, $start, $stop - $start));

if ($write) {
  $new = substr($readme, 0, $start) . "\n" . $table . "\n" . substr($readme, $stop);
  file_put_contents($readmePath, $new);
  echo "Tabela do README raiz regenerada (" . count($rows) . " componentes).\n";
}
elseif ($current !== $table) {
  $errors[] = 'A tabela do README raiz está desatualizada. Rode: php scripts/validate-project-status-index.php --write';
}

if ($errors !== []) {
  foreach ($errors as $error) {
    fwrite(STDERR, "- $error\n");
  }
  fwrite(STDERR, "PROJECT STATUS INDEX: FAIL\n");
  exit(1);
}

echo 'PROJECT STATUS INDEX: PASS (' . count($rows) . " components; table generated from docs/operations/status)\n";
