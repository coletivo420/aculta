<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\aculta420_v2\Layout\PurposeContainerLayout;
use Drupal\aculta420_v2\Palette\PurposePaletteSchemes;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Template\Attribute;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Applies theme-owned surface settings without inspecting domain context. */
final class SurfaceSettingsHooks implements ContainerInjectionInterface {

  public function __construct(
    #[Autowire(service: ThemeSettingsProvider::class)]
    private readonly ThemeSettingsProvider $themeSettings,
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'current_route_match')]
    private readonly CurrentRouteMatch $currentRouteMatch,
  ) {}

  /**
   * Creates the hook implementation with Drupal's active services.
   *
   * ContainerInjectionInterface also lets the hook resolve correctly when
   * Drupal's cached hook map predates this class's service definition.
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get(ThemeSettingsProvider::class),
      $container->get('config.factory'),
      $container->get('current_route_match'),
    );
  }

  /**
   * Aplica o modo de cor e as classes de superfície no html.
   *
   * Implementação única de preprocess_html para o tema, para não haver
   * duas implementações concorrendo pelas mesmas variáveis.
   *
   * @param array $variables
   *   Variáveis do template html, alteradas por referência.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    // A theme may provide only one preprocess_html implementation. Keep the
    // Portal's explicit color mode and surface settings in this hook.
    $mode = ($variables['aculta_color_mode'] ?? NULL) === 'dark' ? 'dark' : 'light';
    $purpose = $variables['aculta_purpose'] ?? NULL;
    $isComponentLibrary = PurposeContainerLayout::isComponentLibraryRoute($this->currentRouteMatch->getRouteName());
    $palette = theme_get_setting('aculta_purpose_palette');
    $paletteStyle = self::purposePaletteStyle($palette, $purpose);
    $containerSettings = theme_get_setting('aculta_purpose_container');
    $containerStyle = $isComponentLibrary ? NULL : PurposeContainerLayout::resolveContainerStyle($containerSettings, $purpose);
    $htmlAttributes = $variables['html_attributes'] ?? NULL;
    if ($htmlAttributes instanceof Attribute) {
      $htmlAttributes->setAttribute('data-theme', $mode);
      if ($isComponentLibrary) {
        $htmlAttributes->removeAttribute('data-purpose-layout');
        $htmlAttributes->removeAttribute('data-purpose-style');
      }
      // Chave neutra fornecida pelo Portal; não é hostname nem regra de domínio.
      // Aceita apenas purposes canônicos aprovados (F3).
      $allowedPurposes = ['main', 'account', 'support', 'magazine', 'wiki', 'shop', 'courses', 'links', 'forum', 'eventos'];
      if (is_string($purpose) && in_array($purpose, $allowedPurposes, TRUE)) {
        $htmlAttributes->setAttribute('data-purpose', $purpose);
      }
      $styles = [];
      if ($paletteStyle !== NULL) {
        $htmlAttributes->setAttribute('data-purpose-scheme', $paletteStyle['scheme']);
        foreach ($paletteStyle['variables'] as $name => $value) {
          $styles[] = $name . ': ' . $value . ';';
        }
      }
      if ($containerStyle !== NULL) {
        $htmlAttributes->setAttribute('data-purpose-layout', $containerStyle['mode']);
        if ($containerStyle['style'] !== NULL) {
          $htmlAttributes->setAttribute('data-purpose-style', $containerStyle['style']);
        }
        foreach ($containerStyle['variables'] as $name => $value) {
          $styles[] = $name . ': ' . $value . ';';
        }
      }
      // Cor de faixa do Studio (purpose account), disponível em qualquer página para as caixas que a acompanham.
      $studioStyle = self::purposePaletteStyle($palette, 'account');
      if ($studioStyle !== NULL) {
        foreach ($studioStyle['variables'] as $name => $value) {
          if (str_contains($name, '-sub-2')) {
            $styles[] = str_replace('--aculta-purpose-', '--aculta-studio-', $name) . ': ' . $value . ';';
          }
        }
      }
      if (!empty($styles)) {
        $htmlAttributes->setAttribute('style', implode(' ', $styles));
      }
    }

    // Core's html template renders `attributes` on <body>. `body_attributes`
    // is not part of the preprocess_html contract.
    $bodyAttributes = $variables['attributes'] ??= new Attribute();
    if (!$bodyAttributes instanceof Attribute) {
      $bodyAttributes = $variables['attributes'] = new Attribute((array) $bodyAttributes);
    }
    $settings = function (string $name, mixed $default): mixed {
      $value = $this->themeSettings->getSetting($name, 'aculta420_v2');
      $value ??= $this->configFactory->get('aculta420_v2.settings')->get($name);
      return $value ?? $default;
    };
    $bodyAttributes->addClass([
      'aculta-radius--button-' . $this->safeValue((string) $settings('aculta_radius_button', 'lg'), ['base', 'lg', 'full'], 'lg'),
      'aculta-radius--card-' . $this->safeValue((string) $settings('aculta_radius_card', 'lg'), ['base', 'lg'], 'lg'),
      'aculta-radius--field-' . $this->safeValue((string) $settings('aculta_radius_field', 'base'), ['sm', 'base'], 'base'),
      'aculta-accent--' . $this->safeValue((string) $settings('aculta_accent_override', 'mapping'), ['mapping', 'green', 'yellow', 'red'], 'mapping'),
      (bool) $settings('aculta_show_accent_stripe', TRUE) ? 'aculta-accent-stripe-enabled' : 'aculta-accent-stripe-disabled',
    ]);
    if ($isComponentLibrary) {
      $bodyAttributes->addClass('aculta-component-library-page');
      $variables['#attached']['library'][] = 'aculta420_v2/component-library-mode';
    }
  }

  /**
   * Resolves the selected palette into CSS variables for both color modes.
   *
   * @return array{scheme: string, variables: array<string, string>}|null
   */
  public static function purposePaletteStyle(mixed $palette, mixed $purpose): ?array {
    $allowedPurposes = ['main', 'account', 'support', 'magazine', 'wiki', 'shop', 'courses', 'links', 'forum', 'eventos'];
    if (!is_array($palette) || !is_string($purpose) || !in_array($purpose, $allowedPurposes, TRUE)) {
      return NULL;
    }
    $schemeKey = $palette[$purpose] ?? NULL;
    if (!is_string($schemeKey) || !isset(PurposePaletteSchemes::SCHEMES[$schemeKey])) {
      return NULL;
    }
    $scheme = PurposePaletteSchemes::SCHEMES[$schemeKey];
    $variables = [];
    foreach (['light', 'dark'] as $mode) {
      $prefix = '--aculta-purpose-' . $mode . '-';
      $variables[$prefix . 'accent'] = $scheme[$mode]['accent'];
      $variables[$prefix . 'on-accent'] = $scheme[$mode]['on_accent'];
      foreach ($scheme[$mode]['sub'] as $index => $value) {
        $variables[$prefix . 'sub-' . ($index + 1)] = $value;
      }
    }
    return ['scheme' => $schemeKey, 'variables' => $variables];
  }

  /**
   * Devolve o valor se ele estiver na lista permitida; senão, o fallback.
   *
   * @param string $value
   *   Valor recebido da configuração.
   * @param array $allowed
   *   Valores aceitos.
   * @param string $fallback
   *   Valor usado quando $value não é permitido.
   *
   * @return string
   *   O valor permitido ou o fallback.
   */
  private function safeValue(string $value, array $allowed, string $fallback): string {
    return in_array($value, $allowed, TRUE) ? $value : $fallback;
  }

}
