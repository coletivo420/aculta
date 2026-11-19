<?php

/**
 * @file
 * Gate ADR-054/055: UI Skins substitui o motor visual por purpose.
 *
 * Verifica a estrutura do formulário administrativo do tema e o schema de
 * configuração após o corte do motor de container.
 *
 * Regras:
 * 1. O formulário não pode oferecer `aculta_container_purpose`.
 * 2. O formulário não pode conter o rótulo "Layout de container".
 * 3. O formulário mantém superfícies/UI Styles e delega CSS variables ao
 *    UI Skins.
 * 4. O schema não deve mais conter `aculta_purpose_container` nem
 *    `aculta420_v2.purpose_container_layout`.
 * 5. Não há formulário/schema de paleta antiga nem código de resolução por
 *    purpose.
 *
 * Uso: php scripts/validate-aculta420-theme-settings.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$theme = $root . '/web/themes/custom/aculta420_v2';
$failures = [];
$checks = 0;

$read = static function (string $path) use ($failures): ?string {
  if (!is_file($path)) {
    $failures[] = "Arquivo ausente: $path";
    return NULL;
  }
  return (string) file_get_contents($path);
};

$source = $read($theme . '/theme-settings.php');
$schema = $read($theme . '/config/schema/aculta420_v2.schema.yml');
$skins = $read($theme . '/aculta420_v2.ui_skins.css_variables.yml');

$assert = static function (bool $condition, string $message) use (&$failures, &$checks): void {
  $checks++;
  if (!$condition) {
    $failures[] = $message;
  }
};

if ($source !== NULL) {
  preg_match_all("/\\\$form\\['([a-z0-9_]+)'\\]/i", $source, $matches);
  $formKeys = array_values(array_unique($matches[1] ?? []));

  $assert(!in_array('aculta_container_purpose', $formKeys, TRUE), 'O formulário ainda declara aculta_container_purpose.');
  $assert(!in_array('aculta_purpose_layout', $formKeys, TRUE), 'O formulário ainda declara aculta_purpose_layout.');
  $assert(!str_contains($source, 'Layout de container'), 'O formulário ainda contém o rótulo "Layout de container".');
  $assert(in_array('aculta_superficies', $formKeys, TRUE), 'O grupo de superfícies (raios/acento) não está declarado.');
  $assert(!str_contains($source, 'aculta_purpose_palette'), 'O formulário ainda lê a paleta antiga.');
  $assert(!str_contains($source, 'aculta_cores_purpose'), 'O grupo visual por purpose ainda está ativo.');
  $assert(str_contains($source, "'#type' => 'ui_styles_styles'"), 'O controle UI Styles da Revista não está declarado.');
}

if ($schema !== NULL) {
  $assert(!str_contains($schema, 'aculta_purpose_container:'), 'O schema aculta_purpose_container ainda existe após o corte.');
  $assert(!str_contains($schema, 'aculta420_v2.purpose_container_layout:'), 'O schema purpose_container_layout ainda existe após o corte.');
  $assert(!str_contains($schema, 'aculta_purpose_palette:'), 'O schema da paleta antiga ainda existe.');
}

if ($skins !== NULL) {
  $assert(str_contains($skins, 'color_accent:'), 'A definição UI Skins color_accent está ausente.');
  $assert(str_contains($skins, 'color_on_accent:'), 'A definição UI Skins color_on_accent está ausente.');
}

$assert(!is_file($theme . '/src/Layout/PurposeContainerLayout.php'), 'PurposeContainerLayout ainda existe após o corte.');
$assert(!is_file($theme . '/src/Palette/PurposePaletteSchemes.php'), 'PurposePaletteSchemes ainda existe.');
$assert(!is_file($theme . '/src/Purpose/Purposes.php'), 'Enum visual Purposes ainda existe no tema.');
$assert(is_file($theme . '/src/Support/ComponentLibraryRoutes.php'), 'ComponentLibraryRoutes não existe.');

if ($failures !== []) {
  fwrite(STDERR, "FAIL — validate-aculta420-theme-settings\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, "  - $failure\n");
  }
  exit(1);
}

echo "PASS — validate-aculta420-theme-settings ($checks checks)\n";
