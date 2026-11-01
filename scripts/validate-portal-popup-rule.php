<?php

/**
 * Gate da regra do popup padrão do Portal (docs/portal/POPUP-PADRAO.md).
 *
 * Reprova, no módulo aculta_portal e no tema aculta420 (PHP, JS, Twig):
 * - marcação de <dialog> nativo e chamada showModal() fora de Drupal.dialog;
 * - data-dialog-type diferente de "modal";
 * - tamanho, posição ou classe de diálogo informados pelo módulo (width, maxHeight, position, dialogClass).
 *
 * Arquivos listados na tabela "Exceções explícitas" de POPUP-PADRAO.md ficam fora da regra.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$doc = (string) file_get_contents($root . '/docs/portal/POPUP-PADRAO.md');

// Exceções: linhas "| `caminho` | motivo | aprovado | data |" dentro da seção "## Exceções explícitas".
$exceptions = [];
if (preg_match('/^## Exceções explícitas\s*$(.*?)(?=^## |\z)/ms', $doc, $section)) {
  foreach (preg_split('/\R/', $section[1]) as $line) {
    if (preg_match('/^\|\s*`?([^|`]+?)`?\s*\|/', $line, $m) && $m[1] !== 'Arquivo' && !str_starts_with($m[1], '---')) {
      $exceptions[] = trim($m[1]);
    }
  }
}

$scan = [];
foreach (['web/modules/custom/aculta_portal', 'web/themes/custom/aculta420'] as $base) {
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $base, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $file) {
    $path = $file->getPathname();
    if (!preg_match('/\.(php|js|twig)$/', $path) || str_contains($path, '/tests/') || str_contains($path, '/node_modules/')) {
      continue;
    }
    $scan[] = $path;
  }
}

$errors = [];
foreach ($scan as $path) {
  $rel = substr($path, strlen($root) + 1);
  if (in_array($rel, $exceptions, true)) {
    continue;
  }
  $lines = preg_split('/\R/', (string) file_get_contents($path));
  $usesDrupalDialog = str_contains(implode("\n", $lines), 'Drupal.dialog(');
  foreach ($lines as $i => $line) {
    $n = $i + 1;
    if (preg_match('/<dialog[\s>]/i', $line)) {
      $errors[] = "$rel:$n: <dialog> nativo; use o modal do Drupal (docs/portal/POPUP-PADRAO.md).";
    }
    if (preg_match('/\.showModal\s*\(/', $line) && !$usesDrupalDialog) {
      $errors[] = "$rel:$n: showModal() fora de Drupal.dialog.";
    }
    if (preg_match('/data-dialog-type[\'"\]]*\s*(?:=>|=)\s*[\'"]([a-z]+)[\'"]/', $line, $m) && $m[1] !== 'modal') {
      $errors[] = "$rel:$n: data-dialog-type=\"{$m[1]}\"; o padrão é \"modal\".";
    }
    if (preg_match('/[\'"]?(width|maxHeight|position|dialogClass)[\'"]?\s*(?:=>|:)/', $line, $m)
      && (str_contains($line, 'data-dialog') || str_contains($line, 'dialog') || str_contains($line, 'Json::encode'))
      && str_starts_with($rel, 'web/modules/')) {
      $errors[] = "$rel:$n: o módulo informa {$m[1]} de diálogo; tamanho e posição são do tema.";
    }
  }
}

if ($errors !== []) {
  foreach ($errors as $e) {
    fwrite(STDERR, "FAIL: $e\n");
  }
  fwrite(STDERR, sprintf("validate-portal-popup-rule: FAIL (%d)\n", count($errors)));
  exit(1);
}
printf("validate-portal-popup-rule: PASS (%d arquivos, %d exceções)\n", count($scan), count($exceptions));
exit(0);
