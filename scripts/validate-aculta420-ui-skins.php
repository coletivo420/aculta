<?php

/**
 * @file
 * Validates the ACULTA UI Skins declarations and Domain ID mapping.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$theme = $root . '/web/themes/custom/aculta420_v2';
$definitions = Yaml::parseFile($theme . '/aculta420_v2.ui_skins.css_variables.yml');
$settings = Yaml::parseFile($root . '/config/sync/aculta420_v2.settings.yml');
$mapping = Yaml::parseFile($root . '/config/sync/aculta_portal.visual_skins.yml');
$installMapping = Yaml::parseFile($root . '/web/modules/custom/aculta_portal/config/install/aculta_portal.visual_skins.yml');
$fail = static function (string $message): never {
  fwrite(STDERR, "ACULTA420 UI SKINS: FAIL — $message\n");
  exit(1);
};
$checks = 0;
$contrast = static function (string $foreground, string $background): float {
  $luminance = static function (string $hex): float {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
      return -1.0;
    }
    $channels = array_map(static function (string $channel): float {
      $value = hexdec($channel) / 255;
      return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, str_split($hex, 2));
    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
  };
  $a = $luminance($foreground);
  $b = $luminance($background);
  if ($a < 0 || $b < 0) {
    return 0.0;
  }
  return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
};
$expectedSkins = [
  'skunk', 'sativa', 'ruderalis', 'afghan', 'acapulco-gold',
  'lemon-haze', 'golden-goat', 'red-kush', 'panama-red', 'red-congolese',
];
if (!is_array($definitions) || count($definitions) !== 5) {
  $fail('Esperadas cinco definições CSS variables UI Skins.');
}
$settingVariables = $settings['third_party_settings']['ui_skins']['css_variables'] ?? NULL;
if (!is_array($settingVariables)) {
  $fail('Configuração de scopes UI Skins ausente em aculta420_v2.settings.');
}
foreach ($definitions as $id => $definition) {
  $checks++;
  $scopes = $definition['default_values'] ?? NULL;
  if (!is_array($scopes) || count($scopes) !== count($expectedSkins) * 2) {
    $fail("$id deve declarar escopos para as dez skins e dois modos.");
  }
  if (($settingVariables[$id] ?? NULL) !== $scopes) {
    $fail("Scopes padrão e Config Sync divergem para $id.");
  }
  foreach ($expectedSkins as $skin) {
    foreach (['light', 'dark'] as $mode) {
      $selector = 'html[data-skin="' . $skin . '"][data-theme="' . $mode . '"]';
      if (!isset($scopes[$selector])) {
        $fail("Escopo ausente: $selector / $id.");
      }
      $checks++;
      if ($id === 'color_accent') {
        $textColor = $settingVariables['color_on_accent'][$selector] ?? NULL;
        $ratio = is_string($textColor) ? $contrast($textColor, $scopes[$selector]) : 0.0;
        if ($ratio < 4.5) {
          $fail(sprintf('Contraste %s/%s abaixo de 4.5:1: %.2f:1.', $skin, $mode, $ratio));
        }
        $checks++;
      }
    }
  }
}
if (($mapping['domains'] ?? NULL) !== ($installMapping['domains'] ?? NULL)) {
  $fail('Config Sync e configuração default do Portal divergem.');
}
if (count($mapping['domains'] ?? []) !== 10 || count(array_diff($mapping['domains'], $expectedSkins)) !== 0) {
  $fail('Mapeamento deve cobrir os dez Domain IDs com skin IDs permitidos.');
}
$legacy = [
  'aculta_purpose_palette',
  'data-purpose-scheme',
  'data-purpose="',
  '--aculta-purpose-',
  'purposePaletteStyle',
];
$activeFiles = [
  $theme . '/src/Hook/SurfaceSettingsHooks.php',
  $theme . '/theme-settings.php',
  $theme . '/src/styles/tokens.css',
];
foreach ($activeFiles as $file) {
  $source = (string) file_get_contents($file);
  foreach ($legacy as $marker) {
    if (str_contains($source, $marker)) {
      $fail("Contrato visual antigo '$marker' ainda está ativo em " . basename($file) . '.');
    }
  }
}
$hookSource = (string) file_get_contents($theme . '/src/Hook/SurfaceSettingsHooks.php');
if (!str_contains($hookSource, "setAttribute('data-skin'")
  || !str_contains($hookSource, "setAttribute('data-site-key'")
  || !str_contains($hookSource, "setAttribute('data-theme'")) {
  $fail('O hook do tema não aplica skin, site key e modo visual.');
}
$checks++;
echo "ACULTA420 UI SKINS: PASS ($checks verificações; 10 skins, 2 modos, contraste ≥4.5:1)\n";
