<?php

/**
 * @file
 * Garante que toda classe mapeada no V2 tem CSS ou exceção documentada.
 *
 * O mapa PortalUiHooks::TOKENS é do tema V2. Este gate impede que a dívida de
 * classes sem estilo volte: cada classe mapeada precisa aparecer em um arquivo
 * CSS do tema ou em docs/classes-sem-css-excecoes.md, com motivo.
 *
 * Também falha se um nome Bootstrap proibido voltar ao mapa.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$theme = $root . '/web/themes/custom/aculta420_v2';
$hookFile = $theme . '/src/Hook/PortalUiHooks.php';
$exceptionsFile = $theme . '/docs/classes-sem-css-excecoes.md';

$forbidden = [
  'bi',
  'bi-envelope',
  'bi-list',
  'bi-pencil',
  'bi-person-circle',
  'breadcrumb',
  'breadcrumb-item',
  'btn',
  'btn-link',
  'form-control',
  'btn-primary',
  'btn-outline-primary',
  'btn-outline-secondary',
  'btn-sm',
];

$fail = static function (string $message): never {
  fwrite(STDERR, "FAIL: $message\n");
  exit(1);
};

if (!is_file($hookFile)) {
  $fail('PortalUiHooks.php não encontrado.');
}

$source = (string) file_get_contents($hookFile);
if (!preg_match('/TOKENS = \[(.*?)\n  \];/s', $source, $mapMatch)) {
  $fail('TOKENS não encontrado em PortalUiHooks.php.');
}

preg_match_all("/'([a-z0-9_-]+)'\s*=>\s*\[([^\]]*)\]/", $mapMatch[1], $entries, PREG_SET_ORDER);

$classes = [];
foreach ($entries as $entry) {
  preg_match_all("/'([^']+)'/", $entry[2], $found);
  foreach ($found[1] as $class) {
    $classes[$class][] = $entry[1];
  }
}

$forbiddenFound = array_values(array_intersect(array_keys($classes), $forbidden));
if ($forbiddenFound !== []) {
  $fail('Nomes Bootstrap proibidos no mapa: ' . implode(', ', $forbiddenFound));
}

$css = '';
$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($theme, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
  if ($file->isFile() && $file->getExtension() === 'css') {
    $css .= "\n" . (string) file_get_contents($file->getPathname());
  }
}

$exceptions = [];
if (is_file($exceptionsFile)) {
  preg_match_all('/^-\s+`([a-z0-9_-]+)`\s+—\s+.+$/m', (string) file_get_contents($exceptionsFile), $documented);
  $exceptions = array_fill_keys($documented[1], TRUE);
}

$missing = [];
foreach (array_keys($classes) as $class) {
  if (preg_match('/(?<![\w-])\.' . preg_quote($class, '/') . '(?![\w-])/', $css) === 1) {
    continue;
  }
  if (isset($exceptions[$class])) {
    continue;
  }
  $missing[] = $class;
}

sort($missing);
if ($missing !== []) {
  fwrite(STDERR, "FAIL: classes mapeadas sem CSS e sem exceção documentada (" . count($missing) . "):\n");
  fwrite(STDERR, implode("\n", $missing) . "\n");
  exit(1);
}

printf(
  "ACULTA420 MAPPED CLASSES: PASS (%d classes, %d exceções documentadas, 0 nomes Bootstrap)\n",
  count($classes),
  count($exceptions),
);
