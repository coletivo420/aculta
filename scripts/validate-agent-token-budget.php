<?php

/**
 * @file
 * Valida integridade minima dos contratos dos agentes e informa custos de contexto.
 *
 * Tamanho de texto, numero de Skills e quantidade de TODOs sao alertas, nunca
 * erros que bloqueiem uma implementacao correta. Estrutura e protecoes nao.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$warnings = [];
$required = [
  'AGENTS.md',
  'CLAUDE.md',
  'docs/operations/AGENT-REVIEW-POLICY.md',
  'docs/operations/GATES.md',
  '.agents/skills',
  '.claude/skills',
  '.codex/skills',
];
foreach ($required as $path) {
  if (!file_exists($root . '/' . $path) && !is_link($root . '/' . $path)) {
    $errors[] = "Contrato ou diretorio obrigatorio ausente: $path";
  }
}
$agents = is_file($root . '/AGENTS.md') ? (string) file_get_contents($root . '/AGENTS.md') : '';
foreach (['segredos', 'autorizacao explicita', 'origin/dev', 'revisao', 'Runtime'] as $essential) {
  if (stripos($agents, $essential) === FALSE) {
    $errors[] = "AGENTS.md sem protecao essencial: $essential";
  }
}
$limits = [
  'AGENTS.md' => 6000,
  'CLAUDE.md' => 3500,
  'TODO.md' => 3500,
];
foreach ($limits as $path => $limit) {
  $file = $root . '/' . $path;
  if (is_file($file) && filesize($file) > $limit) {
    $warnings[] = "$path acima do alvo de contexto ($limit bytes)";
  }
}
$skillFiles = glob($root . '/.agents/skills/*/SKILL.md') ?: [];
if ($skillFiles === []) {
  $errors[] = 'Nenhuma skill canonica encontrada';
}
foreach ($skillFiles as $file) {
  $content = (string) file_get_contents($file);
  $name = basename(dirname($file));
  if (!preg_match('/\A---\n(?:(?!\n---).)*?\nname:\s*' . preg_quote($name, '/') . '\s*\n/s', $content)) {
    // Validador estrutural especializado cobre a ordem flexivel do YAML.
    if (!preg_match('/^name:\s*' . preg_quote($name, '/') . '\s*$/m', $content)) {
      $errors[] = "Skill sem nome canonico: $name";
    }
  }
  if (strlen($content) > 4500) {
    $warnings[] = "$name acima do alvo de 4,5 KB; considerar references/";
  }
}
if (count($skillFiles) > 24) {
  $warnings[] = count($skillFiles) . ' skills: revisar descoberta e descricoes, sem teto artificial';
}
$handoff = $root . '/docs/operations/ESTADO-DA-SESSAO.md';
if (is_file($handoff)) {
  $lines = file($handoff, FILE_IGNORE_NEW_LINES) ?: [];
  $count = count(array_filter($lines, static fn (string $s): bool => preg_match('/^# (?!Estado da sessao)/u', $s) === 1));
  if ($count > 3) {
    $warnings[] = "Handoff possui $count entradas; arquivar ao revisar a proxima fase";
  }
}
foreach ($warnings as $message) {
  fwrite(STDERR, "WARN: $message\n");
}
foreach ($errors as $message) {
  fwrite(STDERR, "FAIL: $message\n");
}
if ($errors !== []) {
  fwrite(STDERR, "AGENT CONTRACT: FAIL (" . count($errors) . " erros)\n");
  exit(1);
}
echo 'AGENT CONTRACT: PASS (' . count($skillFiles) . ' skills; ' . count($warnings) . " avisos)\n";
