<?php

declare(strict_types=1);

/**
 * Barreira até a versão 1.0: sem caminho de rota duplicado, sem título de menu duplicado e sem texto aposentado
 * no código, nos testes ou na documentação atual. Regra em docs/operations/RELEASES.md
 * (seção "Caminhos e textos únicos até a 1.0"). Lista de textos aposentados: scripts/institution/RETIRED-TEXTS.json.
 */

$root = dirname(__DIR__);
$failures = [];

// 1. Caminhos de rota: cada path aparece em uma única rota.
$paths = [];
foreach (glob($root . '/web/modules/custom/**/*.routing.yml') ?: [] as $file) {
  $routes = yaml_parse_file_safe($file);
  foreach ($routes as $name => $route) {
    if (!is_array($route) || !isset($route['path'])) {
      continue;
    }
    $paths[$route['path']][] = $name . ' (' . basename($file) . ')';
  }
}
foreach ($paths as $path => $owners) {
  if (count($owners) > 1) {
    $failures[] = "Caminho de rota duplicado: $path → " . implode(', ', $owners);
  }
}

// 2. Títulos de menu: um título aparece em um único link de menu.
$titles = [];
foreach (glob($root . '/web/modules/custom/**/*.links.menu.yml') ?: [] as $file) {
  foreach (yaml_parse_file_safe($file) as $id => $link) {
    if (is_array($link) && isset($link['title'])) {
      $titles[(string) $link['title']][] = $id . ' (' . basename($file) . ')';
    }
  }
}
foreach ($titles as $title => $owners) {
  if (count($owners) > 1) {
    $failures[] = "Título de menu duplicado: \"$title\" → " . implode(', ', $owners);
  }
}

// 3. Textos aposentados não podem reaparecer no código, nos testes ou na documentação atual.
$retired = json_decode((string) file_get_contents(__DIR__ . '/institution/RETIRED-TEXTS.json'), TRUE)['texts'] ?? [];
$scan = array_merge(
  glob($root . '/web/modules/custom/**/*.{php,yml,twig,js,module,install,inc}', GLOB_BRACE) ?: [],
  glob($root . '/docs/**/*.md') ?: [],
  glob($root . '/docs/*.md') ?: [],
  [$root . '/AGENTS.md'],
);
foreach ($scan as $file) {
  // O histórico vive no CHANGELOG; ali o texto antigo pode ficar.
  if (basename($file) === 'CHANGELOG.md' || !is_file($file)) {
    continue;
  }
  $content = (string) file_get_contents($file);
  foreach ($retired as $entry) {
    if (str_contains($content, (string) $entry['text'])) {
      $failures[] = 'Texto aposentado em ' . substr($file, strlen($root) + 1) . ': "' . $entry['text'] . '" (usar "' . $entry['replaced_by'] . '")';
    }
  }
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
  }
  exit(1);
}
echo "UNIQUE PATHS AND TEXTS GATE: PASS\n";

/** Lê YAML de rotas e menus sem depender de extensão: usa o parser do Symfony do vendor. */
function yaml_parse_file_safe(string $file): array {
  require_once dirname(__DIR__) . '/vendor/autoload.php';
  $data = \Symfony\Component\Yaml\Yaml::parseFile($file);
  return is_array($data) ? $data : [];
}
