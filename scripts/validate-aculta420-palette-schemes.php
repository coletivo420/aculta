<?php

/**
 * @file
 * Validates consistency between PurposePaletteSchemes.php, tokens.css and paletas-por-purpose.md.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$theme = $root . '/web/themes/custom/aculta420_v2';
$schemesFile = $theme . '/src/Palette/PurposePaletteSchemes.php';
$tokensFile = $theme . '/src/styles/tokens.css';
$docsFile = $theme . '/docs/paletas-por-purpose.md';

if (!file_exists($schemesFile)) {
  fwrite(STDERR, "FAIL: Arquivo de esquemas não encontrado: $schemesFile\n");
  exit(1);
}
if (!file_exists($tokensFile)) {
  fwrite(STDERR, "FAIL: tokens.css não encontrado: $tokensFile\n");
  exit(1);
}
if (!file_exists($docsFile)) {
  fwrite(STDERR, "FAIL: paletas-por-purpose.md não encontrado: $docsFile\n");
  exit(1);
}

require_once $schemesFile;

use Drupal\aculta420_v2\Palette\PurposePaletteSchemes;

$schemes = PurposePaletteSchemes::SCHEMES;
$tokensCss = file_get_contents($tokensFile);
$docsContent = file_get_contents($docsFile);

if (count($schemes) !== 10) {
  fwrite(STDERR, "FAIL: Esperava exatamente 10 esquemas em PurposePaletteSchemes::SCHEMES, encontrou " . count($schemes) . "\n");
  exit(1);
}

$errors = [];
$checks = 0;
$renderedHtml = isset($argv[1]) ? file_get_contents($argv[1]) : FALSE;

foreach ($schemes as $key => $data) {
  $checks++;
  // 1. Confere se o nome do esquema está no documento
  $expectedDocHeader = '| **' . $data['name'] . '** | `' . $key . '` |';
  if (!str_contains($docsContent, $expectedDocHeader)) {
    $errors[] = "Esquema {$data['name']} (`{$key}`) não encontrado na tabela de esquemas de paletas-por-purpose.md.";
  }

  // 2. Confere se os valores claros estão no tokens.css
  $lightAccent = strtolower($data['light']['accent']);
  $lightOnAccent = strtolower($data['light']['on_accent']);
  if (!str_contains(strtolower($tokensCss), "--purpose-accent: {$lightAccent};")) {
    $errors[] = "Valor de destaque claro ({$lightAccent}) do esquema {$key} não encontrado em tokens.css.";
  }
  if (!str_contains(strtolower($tokensCss), "--purpose-on-accent: {$lightOnAccent};")) {
    $errors[] = "Valor de texto claro ({$lightOnAccent}) do esquema {$key} não encontrado em tokens.css.";
  }

  // 3. Confere se os subelementos claros estão no tokens.css
  foreach ($data['light']['sub'] as $idx => $subHex) {
    $subHexLower = strtolower($subHex);
    $varName = '--purpose-sub-' . ($idx + 1);
    if (!str_contains(strtolower($tokensCss), "{$varName}: {$subHexLower};")) {
      $errors[] = "Subelemento claro {$varName}: {$subHexLower} do esquema {$key} não encontrado em tokens.css.";
    }
  }

  // 4. Confere se os valores escuros estão no tokens.css
  $darkAccent = strtolower($data['dark']['accent']);
  $darkOnAccent = strtolower($data['dark']['on_accent']);
  if (!str_contains(strtolower($tokensCss), "--purpose-accent: {$darkAccent};")) {
    $errors[] = "Valor de destaque escuro ({$darkAccent}) do esquema {$key} não encontrado em tokens.css.";
  }
  if (!str_contains(strtolower($tokensCss), "--purpose-on-accent: {$darkOnAccent};")) {
    $errors[] = "Valor de texto escuro ({$darkOnAccent}) do esquema {$key} não encontrado em tokens.css.";
  }

  // 5. Confere se os subelementos escuros estão no tokens.css
  foreach ($data['dark']['sub'] as $idx => $subHex) {
    $subHexLower = strtolower($subHex);
    $varName = '--purpose-sub-' . ($idx + 1);
    if (!str_contains(strtolower($tokensCss), "{$varName}: {$subHexLower};")) {
      $errors[] = "Subelemento escuro {$varName}: {$subHexLower} do esquema {$key} não encontrado em tokens.css.";
    }
  }

  // 6. Confere purpose padrão associado em tokens.css
  $purpose = $data['purpose_default'];
  $purposeSelector = 'html[data-purpose="' . $purpose . '"]';
  if (!str_contains($tokensCss, $purposeSelector)) {
    $errors[] = "Seletor {$purposeSelector} para purpose {$purpose} não encontrado em tokens.css.";
  }
}

if (!empty($errors)) {
  fwrite(STDERR, "ACULTA420 PALETTE SCHEMES: FAIL (" . count($errors) . " erros encontrados)\n");
  foreach ($errors as $error) {
    fwrite(STDERR, " - $error\n");
  }
  exit(1);
}

echo "ACULTA420 PALETTE SCHEMES: PASS ({$checks} esquemas verificados contra tokens.css e paletas-por-purpose.md)\n";
echo $renderedHtml === FALSE
  ? "RENDER HTML: DEFERRED (passe o HTML renderizado da configuração como primeiro argumento)\n"
  : "RENDER HTML: fornecido (validação detalhada pendente de implementação do parser DOM)\n";
exit(0);
