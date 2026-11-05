<?php

/**
 * Gate da tabela "Não ler por padrão" de docs/portal/AGENT-TOKEN-ECONOMY.md.
 *
 * Confere, para cada linha com caminho entre crases:
 * - o caminho existe no repositório;
 * - quando a coluna de tamanho traz um valor numérico (ex.: "93 MB", "4,6 MB", "784 KB"), o tamanho real
 *   está entre a metade e o dobro do declarado. Fora disso a tabela envelheceu e deve ser atualizada.
 * Linhas com tamanho "variável", "(Composer)" ou "(Core e contrib)" só têm a existência verificada.
 * Somente leitura; não lê o conteúdo dos arquivos.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$doc = $root . '/docs/portal/AGENT-TOKEN-ECONOMY.md';
$text = is_file($doc) ? (string) file_get_contents($doc) : '';
if ($text === '') {
  fwrite(STDERR, "FAIL: docs/portal/AGENT-TOKEN-ECONOMY.md não existe.\n");
  exit(1);
}
$start = strpos($text, '## Não ler por padrão');
$end = strpos($text, "\n## ", $start + 5);
$section = substr($text, $start, $end === false ? null : $end - $start);

$units = ['B' => 1, 'KB' => 1024, 'MB' => 1024 ** 2, 'GB' => 1024 ** 3];
$failures = [];
$skipped = [];
$checks = 0;

foreach (explode("\n", $section) as $line) {
  if (!preg_match('/^\|\s*`([^`]+)`\s*\|\s*([^|]+?)\s*\|/', $line, $m)) {
    continue;
  }
  $relative = rtrim($m[1], '/');
  $declared = trim($m[2]);
  $path = $root . '/' . $relative;
  if (!file_exists($path) && str_starts_with($declared, '(')) {
    // Dependência gerada (ex.: vendor/ antes do Composer) ou camada de Core/contrib: ausência é aceitável.
    $skipped[] = $relative;
    continue;
  }
  $checks++;
  if (!file_exists($path)) {
    $failures[] = "$relative: caminho da tabela não existe";
    continue;
  }
  if (!preg_match('/^([0-9]+(?:[,.][0-9]+)?)\s*(B|KB|MB|GB)$/u', $declared, $s)) {
    continue;
  }
  $expected = (float) str_replace(',', '.', $s[1]) * $units[$s[2]];
  $actual = (int) trim((string) shell_exec('du -sb ' . escapeshellarg($path) . ' 2>/dev/null | cut -f1'));
  if ($actual <= 0) {
    $failures[] = "$relative: tamanho real não pôde ser medido";
    continue;
  }
  if ($actual < $expected / 2 || $actual > $expected * 2) {
    $failures[] = sprintf('%s: tabela diz %s, real é %.1f MB', $relative, $declared, $actual / 1048576);
  }
}

if ($checks === 0) {
  fwrite(STDERR, "FAIL: nenhuma linha de caminho encontrada na seção \"Não ler por padrão\".\n");
  exit(1);
}
if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
  }
  fwrite(STDERR, sprintf("AGENT ECONOMY PATHS: FAIL (%d)\n", count($failures)));
  exit(1);
}
printf("AGENT ECONOMY PATHS: PASS (%d caminhos; ausentes e aceitos: %s)\n", $checks, $skipped === [] ? 'nenhum' : implode(', ', $skipped));
exit(0);
