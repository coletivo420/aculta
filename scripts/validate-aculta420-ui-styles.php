<?php

/**
 * @file
 * Gate que valida os plugins de UI Styles do tema ACULTA420 V2.
 *
 * Garante que:
 * 1. O arquivo aculta420_v2.ui_styles.yml existe e é um YAML válido.
 * 2. Cada opção aponta para uma variante existente no componente ou esquema oficial.
 * 3. As classes CSS geradas existem em arquivos CSS do tema e estão no mapa de
 *    PortalUiHooks::TOKENS ou exceções documentadas.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$theme = $root . '/web/themes/custom/aculta420_v2';
$uiStylesFile = $theme . '/aculta420_v2.ui_styles.yml';
$tokensFile = $theme . '/src/Hook/PortalUiHooks.php';
$schemesFile = $theme . '/src/Palette/PurposePaletteSchemes.php';
$buttonCssFile = $theme . '/components/button/button.css';
$cardCssFile = $theme . '/components/card-cultural/card-cultural.css';
$revistaCssFile = $theme . '/components/revista-teaser/revista-teaser.css';

$fail = static function (string $message): never {
  fwrite(STDERR, "FAIL: $message\n");
  exit(1);
};

if (!is_file($uiStylesFile)) {
  $fail("Arquivo aculta420_v2.ui_styles.yml não encontrado.");
}

$yamlContent = file_get_contents($uiStylesFile);
if ($yamlContent === FALSE) {
  $fail("Não foi possível ler aculta420_v2.ui_styles.yml.");
}

// Simple YAML parser for standard ui_styles structures.
// In runtime, symfony/yaml or pecl-yaml may be available, but we can use yaml_parse or Symfony Yaml if available.
if (function_exists('yaml_parse')) {
  $definitions = yaml_parse($yamlContent);
} else {
  require_once $root . '/vendor/autoload.php';
  $definitions = Symfony\Component\Yaml\Yaml::parse($yamlContent);
}

if (!is_array($definitions) || empty($definitions)) {
  $fail("Definições vazias ou inválidas em aculta420_v2.ui_styles.yml.");
}

require_once $schemesFile;
use Drupal\aculta420_v2\Palette\PurposePaletteSchemes;

$tokensSource = (string) file_get_contents($tokensFile);
if (!preg_match('/TOKENS = \[(.*?)\n  \];/s', $tokensSource, $m)) {
  $fail("TOKENS não encontrado em PortalUiHooks.php.");
}
eval('$portalTokens = [' . $m[1] . '];');

$mappedClasses = [];
foreach ($portalTokens as $classes) {
  foreach ((array) $classes as $c) {
    $mappedClasses[$c] = TRUE;
  }
}

$buttonCss = (string) file_get_contents($buttonCssFile);
$cardCss = (string) file_get_contents($cardCssFile);
$revistaCss = (string) file_get_contents($revistaCssFile);

$checks = 0;

foreach ($definitions as $id => $definition) {
  $checks++;
  if (empty($definition['label'])) {
    $fail("Estilo '$id' sem label em aculta420_v2.ui_styles.yml.");
  }
  if (empty($definition['category'])) {
    $fail("Estilo '$id' sem category em aculta420_v2.ui_styles.yml.");
  }
  if (empty($definition['options']) || !is_array($definition['options'])) {
    $fail("Estilo '$id' sem options válidas em aculta420_v2.ui_styles.yml.");
  }

  foreach ($definition['options'] as $classOrKey => $label) {
    $checks++;
    if (empty($label)) {
      $fail("Opção '$classOrKey' do estilo '$id' está com rótulo vazio.");
    }

    if ($id === 'button') {
      // Must exist in button.css and in PortalUiHooks::TOKENS.
      if (!str_contains($buttonCss, '.' . $classOrKey)) {
        $fail("Opção '$classOrKey' não possui variante CSS correspondente em $buttonCssFile.");
      }
      if (!isset($mappedClasses[$classOrKey])) {
        $fail("Classe de botão '$classOrKey' não está mapeada em PortalUiHooks::TOKENS.");
      }
    }
    elseif ($id === 'card_accent') {
      // Must exist in card-cultural.css and in PortalUiHooks::TOKENS.
      if (!str_contains($cardCss, '.' . $classOrKey)) {
        $fail("Opção '$classOrKey' não possui variante CSS correspondente em $cardCssFile.");
      }
      if (!isset($mappedClasses[$classOrKey])) {
        $fail("Classe de faixa de cartão '$classOrKey' não está mapeada em PortalUiHooks::TOKENS.");
      }
    }
    elseif ($id === 'revista_accent') {
      if (!in_array($classOrKey, [
        'revista-teaser--accent-green',
        'revista-teaser--accent-yellow',
        'revista-teaser--accent-red',
      ], TRUE)) {
        $fail("Classe '$classOrKey' não pertence às três escolhas da Revista.");
      }
      if (!str_contains($revistaCss, '.' . $classOrKey)) {
        $fail("Opção '$classOrKey' não possui regra em $revistaCssFile.");
      }
      $token = 'revista-teaser-accent-' . substr($classOrKey, strlen('revista-teaser--accent-'));
      if (!isset($mappedClasses[$classOrKey]) || !isset($portalTokens[$token])) {
        $fail("Classe da Revista '$classOrKey' não está mapeada no PortalUiHooks::TOKENS.");
      }
    }
    elseif ($id === 'purpose_palette') {
      // Must exist in PurposePaletteSchemes::SCHEMES.
      if (!isset(PurposePaletteSchemes::SCHEMES[$classOrKey])) {
        $fail("Opção de paleta '$classOrKey' não existe em PurposePaletteSchemes::SCHEMES.");
      }
    }
  }
}

if (isset($definitions['revista_accent']['options']) && count($definitions['revista_accent']['options']) !== 3) {
  $fail('revista_accent deve expor exatamente três opções fechadas.');
}

echo "ACULTA420 UI STYLES GATE: PASS ({$checks} verificações em aculta420_v2.ui_styles.yml)\n";
exit(0);
