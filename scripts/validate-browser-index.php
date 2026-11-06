<?php

declare(strict_types=1);

/**
 * Gate do índice dos testes de navegador (tests/browser).
 *
 * Verifica:
 * 1. tests/browser/INDICE.md e os README gerados estão em dia com
 *    tests/browser/gerar-indice.py (executado com --check);
 * 2. toda frente tem README.md e todo routes.json é JSON válido;
 * 3. todo caminho tests/browser/... citado em AGENTS.md, CLAUDE.md, docs/ (fora de
 *    docs/history/) e .claude/ existe, exceto caminhos com placeholder (<frente>, etc.).
 *
 * Para corrigir a falha 1: python3 tests/browser/gerar-indice.py
 * Executar: php scripts/validate-browser-index.php
 */

$root = dirname(__DIR__);
$browser = $root . '/tests/browser';
$failures = [];
$checks = 0;

// 1. Índice atualizado.
$checks++;
$cmd = 'python3 ' . escapeshellarg($browser . '/gerar-indice.py') . ' --check 2>&1';
$output = [];
$code = 0;
exec($cmd, $output, $code);
if ($code !== 0) {
  $failures[] = 'Índice desatualizado ou script indisponível: ' . trim(implode(' ', $output))
    . ' (corrija com: python3 tests/browser/gerar-indice.py)';
}

// 2. Frentes com README e routes.json válido.
$purposes = array_filter(glob($browser . '/*', GLOB_ONLYDIR) ?: [], static fn (string $d): bool => !in_array(basename($d), ['node_modules', '__pycache__'], true));
foreach ($purposes as $purposeDir) {
  $candidates = [];
  if (is_file($purposeDir . '/README.md') && !str_contains((string) file_get_contents($purposeDir . '/README.md', false, null, 0, 400), 'gerar-indice.py')) {
    $candidates[] = $purposeDir;
  }
  foreach (glob($purposeDir . '/*', GLOB_ONLYDIR) ?: [] as $sub) {
    if (basename($sub) !== '__pycache__') {
      $candidates[] = $sub;
    }
  }
  foreach ($candidates as $frente) {
    $rel = substr($frente, strlen($browser) + 1);
    if (basename($frente) === 'capturas' || str_starts_with($rel, 'capturas')) {
      continue;
    }
    $hasScripts = (glob($frente . '/*.{py,mjs,js,sh}', GLOB_BRACE) ?: []) !== [];
    $hasReadme = is_file($frente . '/README.md');
    if ($hasScripts || $hasReadme) {
      $checks++;
      if (!$hasReadme) {
        $failures[] = "tests/browser/$rel/ tem scripts e não tem README.md.";
      }
    }
    if (is_file($frente . '/routes.json')) {
      $checks++;
      json_decode((string) file_get_contents($frente . '/routes.json'), true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $failures[] = "tests/browser/$rel/routes.json não é JSON válido: " . json_last_error_msg() . '.';
      }
    }
  }
}

// 3. Caminhos citados nos documentos de agente existem.
$sources = ['AGENTS.md', 'CLAUDE.md'];
$docsIt = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/docs', FilesystemIterator::SKIP_DOTS));
foreach ($docsIt as $file) {
  $path = $file->getPathname();
  if (!str_ends_with($path, '.md') || str_contains($path, '/docs/history/')) {
    continue;
  }
  $sources[] = substr($path, strlen($root) + 1);
}
$skillsIt = is_dir($root . '/.claude') ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/.claude', FilesystemIterator::SKIP_DOTS)) : [];
foreach ($skillsIt as $file) {
  if (str_ends_with($file->getPathname(), '.md')) {
    $sources[] = substr($file->getPathname(), strlen($root) + 1);
  }
}

$pattern = '#tests/browser/[A-Za-z0-9_./-]+#';
foreach (array_unique($sources) as $source) {
  $full = $root . '/' . $source;
  if (!is_file($full)) {
    continue;
  }
  preg_match_all($pattern, (string) file_get_contents($full), $matches);
  foreach (array_unique($matches[0]) as $ref) {
    $ref = rtrim($ref, '.,;:)`');
    $checks++;
    if (!file_exists($root . '/' . $ref)) {
      $failures[] = "$source cita $ref, que não existe. Use o caminho atual em tests/browser/INDICE.md.";
    }
  }
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "BROWSER INDEX FAIL: $failure\n");
  }
  exit(1);
}

echo sprintf("BROWSER INDEX: PASS (%d checks)\n", $checks);
