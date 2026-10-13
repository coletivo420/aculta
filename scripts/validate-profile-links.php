<?php

declare(strict_types=1);

/**
 * Guardrail: links para o perfil público (/u/<apelido>) são sempre gerados a partir do apelido atual, por rota
 * e propósito, nunca escritos à mão, e o código que os gera registra o perfil na cache.
 *
 * Reprova:
 *  - caminho /u/ literal em código (src, templates, twig, js do tema) fora da definição de rota;
 *  - código que referencia a rota aculta_user.perfil_publico sem registrar o perfil na cache (profile tags).
 * Regra e motivo: docs/portal/GUARDRAILS.md (seção "Links de perfil").
 */

$root = dirname(__DIR__);
$scan = [
  $root . '/web/modules/custom',
  $root . '/web/themes/custom/aculta420/src',
  $root . '/web/themes/custom/aculta420/templates',
];
$allowedLiteral = [
  $root . '/web/modules/custom/aculta_portal/modules/aculta_user/aculta_user.routing.yml',
];

$violations = [];
$files = [];
foreach ($scan as $dir) {
  if (!is_dir($dir)) {
    continue;
  }
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $file) {
    if (in_array($file->getExtension(), ['php', 'twig', 'js', 'yml'], TRUE) && !str_contains($file->getPathname(), '/tests/')) {
      $files[] = $file->getPathname();
    }
  }
}

foreach ($files as $path) {
  if (in_array($path, $allowedLiteral, TRUE)) {
    continue;
  }
  $code = file_get_contents($path) ?: '';
  $rel = substr($path, strlen($root) + 1);

  // 1. Caminho /u/ literal: aceito apenas em comentários e texto de documentação dentro do código.
  $noComments = preg_replace('~/\*.*?\*/|//[^\n]*|#[^\n]*~s', '', $code) ?? $code;
  if (preg_match('~[\'"]/u/[^\'"]*[\'"]~', $noComments)) {
    $violations[] = "$rel: caminho /u/ escrito à mão; gere pela rota aculta_user.perfil_publico com o apelido atual.";
  }

  // 2. Geração de link de perfil exige registro do perfil na cache.
  if (str_contains($code, 'aculta_user.perfil_publico') && str_ends_with($path, '.php')) {
    $registra = str_contains($code, 'getCacheTags') || str_contains($code, 'addCacheableDependency') || str_contains($code, "'#cache'");
    if (!$registra) {
      $violations[] = "$rel: gera link de perfil sem registrar o perfil na cache (use getCacheTags()/addCacheableDependency).";
    }
  }
}

if ($violations === []) {
  echo 'PROFILE LINKS: PASS (' . count($files) . " arquivos verificados; links de perfil dinâmicos e com cache)\n";
  exit(0);
}
echo "PROFILE LINKS: FAIL\n";
foreach ($violations as $v) {
  echo " - $v\n";
}
exit(1);
