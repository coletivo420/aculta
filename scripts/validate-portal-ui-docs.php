<?php

/**
 * Gate de documentação dos tokens e atributos do Portal (docs/portal/TOKENS-E-ATRIBUTOS.md).
 *
 * Confere, no módulo aculta_portal (e submódulos) e no tema aculta420:
 * - todo token data-portal-ui usado no código existe no mapa PortalUiHooks::TOKENS ou em uma família dinâmica documentada;
 * - todo token do mapa está documentado;
 * - todo atributo data-portal-*, data-aculta-* ou data-dialog-* usado no código está documentado (nome exato
 *   ou família com wildcard, ex.: `data-portal-account-*`).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$docPath = $root . '/docs/portal/TOKENS-E-ATRIBUTOS.md';
$doc = is_file($docPath) ? (string) file_get_contents($docPath) : '';
if ($doc === '') {
  fwrite(STDERR, "FAIL: docs/portal/TOKENS-E-ATRIBUTOS.md não existe.\n");
  exit(1);
}

$hooks = (string) file_get_contents($root . '/web/themes/custom/aculta420/src/Hook/PortalUiHooks.php');
if (!preg_match('/TOKENS = \[(.*?)\n  \];/s', $hooks, $m)) {
  fwrite(STDERR, "FAIL: PortalUiHooks::TOKENS não encontrado.\n");
  exit(1);
}
preg_match_all("/^\s*'([a-z0-9-]+)'\s*=>/m", $m[1], $k);
$mapped = array_unique($k[1]);

// Famílias dinâmicas e wildcards documentados: `prefixo-*`.
preg_match_all('/`([a-z0-9-]+\*?)`/', $doc, $all);
$documented = array_unique($all[1]);
$wildcards = array_values(array_map(static fn(string $w) => rtrim($w, '*'), array_filter($documented, static fn(string $w) => str_ends_with($w, '*'))));

// Placeholders de exemplo em comentários (não são tokens reais).
$placeholders = ['token', 'token2'];

$tokensUsed = [];
$attrsUsed = [];
$base = [$root . '/web/modules/custom/aculta_portal', $root . '/web/themes/custom/aculta420'];
foreach ($base as $dir) {
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $file) {
    $path = $file->getPathname();
    if (!preg_match('/\.(php|js|twig)$/', $path) || str_contains($path, '/tests/') || str_contains($path, '/node_modules/')) {
      continue;
    }
    $src = (string) file_get_contents($path);
    if (preg_match_all('/data-portal-ui[\'"\]]*\s*(?:=>|=)\s*[\'"]([a-z0-9-]+)[\'"]|data-portal-ui=\\\\?[\'"]([a-z0-9-]+)/', $src, $t, PREG_SET_ORDER)) {
      foreach ($t as $row) {
        $tokensUsed[($row[1] ?? '') !== '' ? $row[1] : $row[2]][] = substr($path, strlen($root) + 1);
      }
    }
    if (preg_match_all('/\b(data-(?:portal|aculta|dialog)-[a-z0-9-]*[a-z0-9])\b/', $src, $a)) {
      foreach (array_unique($a[1]) as $attr) {
        $attrsUsed[$attr][] = substr($path, strlen($root) + 1);
      }
    }
  }
}

$errors = [];
foreach ($tokensUsed as $token => $files) {
  if (in_array($token, $placeholders, true) || in_array($token, $mapped, true)) {
    continue;
  }
  $family = false;
  foreach ($wildcards as $w) {
    if (str_starts_with($token, $w)) {
      $family = true;
    }
  }
  if (!$family) {
    $errors[] = "token \"$token\" usado em {$files[0]} não está no mapa PortalUiHooks::TOKENS nem em família documentada.";
  }
}
foreach ($mapped as $token) {
  if (!in_array("`$token`", array_map(static fn(string $d) => "`$d`", $documented), true) && !in_array($token, $documented, true)) {
    $errors[] = "token \"$token\" do mapa não está documentado em TOKENS-E-ATRIBUTOS.md.";
  }
}
foreach ($attrsUsed as $attr => $files) {
  if (str_ends_with($attr, '-')) {
    continue;
  }
  $ok = in_array($attr, $documented, true);
  foreach ($wildcards as $w) {
    if (str_starts_with($attr, $w)) {
      $ok = true;
    }
  }
  if (!$ok) {
    $errors[] = "atributo \"$attr\" usado em {$files[0]} não está documentado em TOKENS-E-ATRIBUTOS.md.";
  }
}

if ($errors !== []) {
  foreach ($errors as $e) {
    fwrite(STDERR, "FAIL: $e\n");
  }
  fwrite(STDERR, sprintf("validate-portal-ui-docs: FAIL (%d)\n", count($errors)));
  exit(1);
}
printf("validate-portal-ui-docs: PASS (%d tokens no mapa, %d usados, %d atributos)\n", count($mapped), count($tokensUsed), count($attrsUsed));
exit(0);
