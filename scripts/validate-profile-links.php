<?php

declare(strict_types=1);

/**
 * Guardrail: links para o perfil público (/u/<apelido>) são gerados por rota e propósito, com slug minúsculo
 * derivado do apelido atual; a capitalização de exibição vem do campo original. Links registram cache.
 *
 * Reprova:
 *  - caminho /u/ literal em código (src, templates, twig, js do tema) fora da definição de rota;
 *  - código que referencia a rota aculta_user.perfil_publico sem slug canônico ou sem registrar o perfil na cache;
 *  - controlador que renderiza o slug da rota em vez do apelido salvo para exibição.
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
// Subscriber que responde 404 para slug não canônico: não gera link de perfil, então não há cache de perfil a registrar.
// Justificativa no GUARDRAILS (Links de perfil).
$semCacheDePerfil = [
  $root . '/web/modules/custom/aculta_portal/modules/aculta_user/src/EventSubscriber/PublicProfileNotCanonicalSubscriber.php',
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
  if (str_contains($code, 'aculta_user.perfil_publico') && str_ends_with($path, '.php') && !in_array($path, $semCacheDePerfil, TRUE)) {
    $registra = str_contains($code, 'getCacheTags') || str_contains($code, 'addCacheableDependency') || str_contains($code, "'#cache'");
    if (!$registra) {
      $violations[] = "$rel: gera link de perfil sem registrar o perfil na cache (use getCacheTags()/addCacheableDependency).";
    }
    if (!str_contains($code, 'PublicRouteSlug::normalize') && !str_contains($code, 'AccountNames::publicRouteSlug')) {
      $violations[] = "$rel: normalize o parâmetro com aculta_portal PublicRouteSlug antes de gerar a rota pública.";
    }
  }
}

$namesPath = $root . '/web/modules/custom/aculta_portal/modules/aculta_user/src/Account/AccountNames.php';
$namesCode = is_file($namesPath) ? file_get_contents($namesPath) : '';
if (!str_contains($namesCode, 'public static function publicRouteSlug')
  || !str_contains($namesCode, 'PublicRouteSlug::normalize($apelido)')
  || !str_contains($namesCode, 'self::isValid($slug)')) {
  $violations[] = 'AccountNames::publicRouteSlug() deve usar o normalizador do Portal e validar o slug canônico.';
}

$normalizerPath = $root . '/web/modules/custom/aculta_portal/src/Routing/PublicRouteSlug.php';
$normalizerCode = is_file($normalizerPath) ? file_get_contents($normalizerPath) : '';
if (!str_contains($normalizerCode, 'final class PublicRouteSlug')
  || !str_contains($normalizerCode, 'public static function normalize')
  || !str_contains($normalizerCode, 'mb_strtolower(trim($value)')) {
  $violations[] = 'PublicRouteSlug::normalize() do aculta_portal deve aparar espaços e converter para minúsculas.';
}

$profileControllerPath = $root . '/web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/PublicProfileController.php';
$profileControllerCode = is_file($profileControllerPath) ? file_get_contents($profileControllerPath) : '';
if (!str_contains($profileControllerCode, "get('field_nickname')->value")) {
  $violations[] = 'O perfil público deve exibir o apelido salvo, preservando maiúsculas/minúsculas.';
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
