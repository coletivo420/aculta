<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Template\Attribute;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Applies theme-owned surface settings without inspecting domain context. */
final class SurfaceSettingsHooks {

  public function __construct(
    #[Autowire(service: ThemeSettingsProvider::class)]
    private readonly ThemeSettingsProvider $themeSettings,
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
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
  }

  private function safeValue(string $value, array $allowed, string $fallback): string {
    return in_array($value, $allowed, TRUE) ? $value : $fallback;
  }

}
