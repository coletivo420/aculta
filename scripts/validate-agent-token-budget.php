<?php

declare(strict_types=1);

/**
 * Gate de orçamento de leitura dos agentes (somente leitura; sem vendor, sem Drush).
 *
 * Confere:
 * 1. Tamanho dos documentos de leitura de partida (AGENTS.md, CLAUDE.md, TODO.md e
 *    TODO.md de cada componente). Esses arquivos são reenviados a cada turno.
 * 2. Cobertura e consistência: todo componente com *.info.yml tem TODO.md; a versão
 *    citada bate com o manifesto; o roadmap citado existe; não há seção de histórico.
 * 3. Handoff: docs/operations/ESTADO-DA-SESSAO.md tem no máximo 3 entradas.
 * 4. Trechos literais do AGENTS.md exigidos por outros gates continuam presentes.
 *
 * Limites em bytes são alvos de economia; alterar um limite exige nota no CHANGELOG
 * do tema ou do Portal e justificativa no commit.
 */

$root = dirname(__DIR__);

const LIMIT_AGENTS = 5000;
const LIMIT_CLAUDE = 3000;
const LIMIT_TODO_ROOT = 3000;
const LIMIT_TODO_COMPONENT = 2000;
const LIMIT_ENTRY_TOTAL = 10000;
const MAX_HANDOFF_ENTRIES = 3;

/** Trechos que outros gates exigem no AGENTS.md. Não remover sem atualizar o gate dono. */
const AGENTS_LITERALS = [
  'Endereço centralizado em `aculta_portal_cep` e país exclusivo Brasil (BR)',
  'ACEITE-UNICO',
  'PROJECT-STATUS-INDEX-GUARD',
  'docs/portal/DRUPAL-11-STANDARDS.md',
];

$failures = [];
$report = [];

$size = static function (string $relative) use ($root): int {
  $path = $root . '/' . $relative;
  return is_file($path) ? (int) filesize($path) : -1;
};

/* 1. Documentos de leitura de partida. */
$budgets = [
  'AGENTS.md' => LIMIT_AGENTS,
  'CLAUDE.md' => LIMIT_CLAUDE,
  'TODO.md' => LIMIT_TODO_ROOT,
];
$entryTotal = 0;
foreach ($budgets as $file => $limit) {
  $bytes = $size($file);
  if ($bytes < 0) {
    $failures[] = "$file ausente";
    continue;
  }
  $entryTotal += $bytes;
  if ($bytes > $limit) {
    $failures[] = "$file tem $bytes bytes; limite $limit";
  }
  $report[] = "$file $bytes/$limit";
}

/* 2. TODO por componente. */
$components = [];
$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($root . '/web/modules/custom', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
  if (str_ends_with($file->getFilename(), '.info.yml') && !str_contains($file->getPathname(), '/tests/')) {
    $components[] = $file->getPathname();
  }
}
foreach (glob($root . '/web/themes/custom/*/*.info.yml') ?: [] as $themeInfo) {
  if (!str_contains($themeInfo, '/aculta420/')) {
    $components[] = $themeInfo;
  }
}
sort($components);

$componentCount = 0;
foreach ($components as $info) {
  $componentCount++;
  $dir = dirname($info);
  $rel = substr($dir, strlen($root) + 1);
  $todoPath = $dir . '/TODO.md';
  if (!is_file($todoPath)) {
    $failures[] = "$rel sem TODO.md";
    continue;
  }
  $todo = (string) file_get_contents($todoPath);
  $todoBytes = strlen($todo);
  if ($todoBytes > LIMIT_TODO_COMPONENT) {
    $failures[] = "$rel/TODO.md tem $todoBytes bytes; limite " . LIMIT_TODO_COMPONENT;
  }

  $infoText = (string) file_get_contents($info);
  $version = preg_match('/^version:\s*[\'"]?([^\'"\s#]+)/m', $infoText, $m) ? $m[1] : 'sem versão';
  if (!str_contains($todo, "versão $version (")) {
    $failures[] = "$rel/TODO.md não cita a versão $version do manifesto";
  }

  if (preg_match_all('/`([^`\s]*(?:ROADMAP|roadmap)[^`\s]*\.md)`/', $todo, $paths)) {
    foreach ($paths[1] as $cited) {
      if (!is_file($root . '/' . $cited)) {
        $failures[] = "$rel/TODO.md cita roadmap inexistente: $cited";
      }
    }
  }

  if (preg_match('/^#{2,3} Hist[óo]rico/mu', $todo)) {
    $failures[] = "$rel/TODO.md tem seção de histórico; o TODO não acumula histórico";
  }
}
$report[] = "componentes com TODO: $componentCount";

/* 3. Handoff com no máximo 3 entradas. */
$handoff = $root . '/docs/operations/ESTADO-DA-SESSAO.md';
if (is_file($handoff)) {
  $entries = 0;
  foreach (file($handoff) ?: [] as $line) {
    if (preg_match('/^#{1,2} /', $line) && !preg_match('/^# Estado da sessão\s*$/u', $line)) {
      $entries++;
    }
  }
  if ($entries > MAX_HANDOFF_ENTRIES) {
    $failures[] = "ESTADO-DA-SESSAO.md tem $entries entradas; máximo " . MAX_HANDOFF_ENTRIES;
  }
  $report[] = "handoff $entries/" . MAX_HANDOFF_ENTRIES;
} else {
  $failures[] = 'docs/operations/ESTADO-DA-SESSAO.md ausente';
}

/* 4. Literais exigidos por outros gates. */
$agents = (string) @file_get_contents($root . '/AGENTS.md');
foreach (AGENTS_LITERALS as $literal) {
  if (!str_contains($agents, $literal)) {
    $failures[] = "AGENTS.md perdeu trecho exigido por gate: $literal";
  }
}

$report[] = "entrada total $entryTotal/" . LIMIT_ENTRY_TOTAL;
if ($entryTotal > LIMIT_ENTRY_TOTAL) {
  $failures[] = "leitura de partida soma $entryTotal bytes; limite " . LIMIT_ENTRY_TOTAL;
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
  }
  fwrite(STDERR, 'AGENT TOKEN BUDGET: FAIL (' . count($failures) . ")\n");
  exit(1);
}

echo 'AGENT TOKEN BUDGET: PASS (' . implode('; ', $report) . ")\n";
exit(0);
