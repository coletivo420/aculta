<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\csp\Csp;
use Drupal\csp\NonceBuilder;
use Drupal\ui_skins\Hook\PageTop;
use Drupal\aculta420_v2\Support\ComponentLibraryRoutes;
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
    #[Autowire(service: 'csp.nonce_builder')]
    private readonly NonceBuilder $nonceBuilder,
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
      $container->get('csp.nonce_builder'),
    );
  }

  /**
   * Applies UI Skins nonce, color mode, and surface classes to the HTML page.
   *
   * @param array<mixed> $variables
   *   HTML template variables, altered by reference.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $this->addUiSkinsNonce($variables);

    // A theme may provide only one preprocess_html implementation. Keep the
    // Portal's explicit color mode and surface settings in this hook.
    $preference = in_array($variables['aculta_color_mode'] ?? NULL, ['light', 'dark', 'auto'], TRUE)
      ? $variables['aculta_color_mode']
      : 'light';
    $mode = $preference === 'dark' ? 'dark' : 'light';
    $siteKey = $variables['aculta_site_key'] ?? NULL;
    $skinId = $variables['aculta_skin_id'] ?? NULL;
    $isComponentLibrary = ComponentLibraryRoutes::isComponentLibraryRoute($this->currentRouteMatch->getRouteName());
    $htmlAttributes = $variables['html_attributes'] ?? NULL;
    if ($htmlAttributes instanceof Attribute) {
      $htmlAttributes->setAttribute('data-theme', $mode);
      $htmlAttributes->setAttribute('data-color-preference', $preference);
      if (is_string($siteKey) && preg_match('/^[a-z][a-z0-9_]*$/', $siteKey)) {
        $htmlAttributes->setAttribute('data-site-key', $siteKey);
      }
      if (is_string($skinId) && preg_match('/^[a-z][a-z0-9-]*$/', $skinId)) {
        $htmlAttributes->setAttribute('data-skin', $skinId);
      }
    }

    if ($preference === 'auto') {
      $this->attachLibrary($variables, 'aculta420_v2/color-mode-runtime');
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
      'aculta-radius--button-' . $this->safeValue(
        $settings('aculta_radius_button', 'lg'),
        ['base', 'lg', 'full'],
        'lg',
      ),
      'aculta-radius--card-' . $this->safeValue(
        $settings('aculta_radius_card', 'lg'),
        ['base', 'lg'],
        'lg',
      ),
      'aculta-radius--field-' . $this->safeValue(
        $settings('aculta_radius_field', 'base'),
        ['sm', 'base'],
        'base',
      ),
      'aculta-accent--' . $this->safeValue(
        $settings('aculta_accent_override', 'mapping'),
        ['mapping', 'green', 'yellow', 'red'],
        'mapping',
      ),
      (bool) $settings('aculta_show_accent_stripe', TRUE) ? 'aculta-accent-stripe-enabled' : 'aculta-accent-stripe-disabled',
    ]);
    if ($isComponentLibrary) {
      $bodyAttributes->addClass('aculta-component-library-page');
      $this->attachLibrary($variables, 'aculta420_v2/component-library-mode');
    }
    if ($siteKey === 'account') {
      $this->attachLibrary($variables, 'aculta420_v2/portal-account');
      $this->attachLibrary($variables, 'aculta420_v2/account-dialogs');
    }
    if ($siteKey === 'wiki') {
      $this->attachLibrary($variables, 'aculta420_v2/wiki');
    }
  }

  /**
   * Attaches a theme library while preserving other render attachments.
   *
   * @param array<mixed> $variables
   *   HTML template variables.
   * @param string $library
   *   Theme library identifier.
   */
  private function attachLibrary(array &$variables, string $library): void {
    $attached = is_array($variables['#attached'] ?? NULL) ? $variables['#attached'] : [];
    $libraries = is_array($attached['library'] ?? NULL) ? $attached['library'] : [];
    $libraries[] = $library;
    $attached['library'] = $libraries;
    $variables['#attached'] = $attached;
  }

  /**
   * Adds a response nonce to UI Skins' generated inline stylesheet.
   *
   * @param array<mixed> $variables
   *   HTML template variables, altered by reference.
   */
  private function addUiSkinsNonce(array &$variables): void {
    $key = PageTop::PAGE_TOP_CSS_VARIABLES_KEY;
    $pageTop = $variables['page_top'] ?? NULL;
    if (!is_array($pageTop) || !isset($pageTop[$key]) || !is_array($pageTop[$key])) {
      return;
    }

    $placeholder = $this->nonceBuilder->getPlaceholderKey();
    $element = $pageTop[$key];
    $attributes = is_array($element['#attributes'] ?? NULL) ? $element['#attributes'] : [];
    $attributes['nonce'] = $placeholder;
    $element['#attributes'] = $attributes;
    $attached = is_array($element['#attached'] ?? NULL) ? $element['#attached'] : [];
    $cspNonce = is_array($attached['csp_nonce'] ?? NULL) ? $attached['csp_nonce'] : [];
    $cspNonce['style'] = [Csp::POLICY_UNSAFE_INLINE];
    $attached['csp_nonce'] = $cspNonce;
    $placeholders = is_array($attached['placeholders'] ?? NULL) ? $attached['placeholders'] : [];
    $placeholders[$placeholder] = [
      '#lazy_builder' => ['csp.nonce_builder:renderNonce', []],
    ];
    $attached['placeholders'] = $placeholders;
    $element['#attached'] = $attached;
    $pageTop[$key] = $element;
    $variables['page_top'] = $pageTop;
  }

  /**
   * Devolve o valor se ele estiver na lista permitida; senão, o fallback.
   *
   * @param mixed $value
   *   Valor recebido da configuração.
   * @param list<string> $allowed
   *   Valores aceitos.
   * @param string $fallback
   *   Valor usado quando $value não é permitido.
   *
   * @return string
   *   O valor permitido ou o fallback.
   */
  private function safeValue(mixed $value, array $allowed, string $fallback): string {
    return is_string($value) && in_array($value, $allowed, TRUE) ? $value : $fallback;
  }

}
