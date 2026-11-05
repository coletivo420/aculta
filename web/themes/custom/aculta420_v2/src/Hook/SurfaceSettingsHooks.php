<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Applies theme-owned surface settings without inspecting domain context. */
final class SurfaceSettingsHooks {

  public function __construct(
    #[Autowire(service: 'theme.settings')]
    private readonly ThemeSettingsProvider $themeSettings,
  ) {}

  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $bodyAttributes = $variables['body_attributes'] ?? NULL;
    if (!$bodyAttributes instanceof Attribute) {
      return;
    }
    $settings = fn(string $name, mixed $default): mixed => $this->themeSettings->getSetting($name, 'aculta420_v2') ?? $default;
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
