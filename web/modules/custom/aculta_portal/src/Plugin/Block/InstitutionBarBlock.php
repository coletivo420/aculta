<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Barra institucional do ACULTA (barra institucional do tema) como bloco.
 *
 * Os dados dinâmicos (conta, carrinho, mensagens, alertas, busca e apresentação do domínio)
 * vêm dos mesmos hooks de pré-processamento que alimentavam a barra fixa da página.
 */
#[Block(
  id: 'aculta_institution_bar',
  admin_label: new TranslatableMarkup('Barra institucional ACULTA'),
  category: new TranslatableMarkup('ACULTA'),
)]
final class InstitutionBarBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Hooks de pré-processamento que preenchem as variáveis da barra.
   *
   * @var class-string[]
   */
  private const PREPROCESS_HOOKS = [
    'Drupal\aculta_portal\Hook\PortalHooks',
    'Drupal\aculta_messenger\Hook\MessengerBarHooks',
    'Drupal\aculta420_v2\Hook\PagePresentationHooks',
  ];

  /**
   * Constructs this service or plugin.
   *
   * @param array<mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param ThemeExtensionList $themes
   *   Theme extension list service.
   * @param ClassResolverInterface $classResolver
   *   Class resolver service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected readonly ThemeExtensionList $themes,
    protected readonly ClassResolverInterface $classResolver,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param ContainerInterface $container
   *   Dependency injection container.
   * @param array<mixed> $configuration
   *   Plugin configuration.
   * @param mixed $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   *
   * @return static
   *   Value returned by the method.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    if (!is_string($plugin_id)) {
      throw new \InvalidArgumentException('Plugin ID must be a string.');
    }
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.theme'),
      $container->get('class_resolver'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
   */
  public function build(): array {
    $variables = [];
    foreach (self::PREPROCESS_HOOKS as $class) {
      if (!class_exists($class) || !method_exists($class, 'preprocessPage')) {
        continue;
      }
      $this->classResolver->getInstanceFromDefinition($class)->preprocessPage($variables);
    }

    $library = $this->get($variables, 'aculta_component_library_page', FALSE) === TRUE;
    $directory = $this->themes->getPath('aculta420_v2');
    $basePath = base_path();
    if (!is_string($basePath)) {
      $basePath = '';
    }

    $props = [
      'brand_home_url' => $this->get($variables, 'aculta_brand_home', '/'),
      'logo_base_url' => $basePath . $directory . '/assets/logos',
      'logo_alt' => (string) new TranslatableMarkup('ACULTA - Associação Cultural Antiproibicionista'),
      'is_account_domain' => (bool) $this->get($variables, 'domain_presentation.layout.institution_bar_account', FALSE),
      'show_utility_bar' => (bool) $this->get($variables, 'domain_presentation.layout.show_utility_bar', TRUE) && !$library,
      'portal_menu' => $library ? NULL : $this->get($variables, 'portal_account_menu', NULL),
      'cart_url' => $this->get($variables, 'aculta_cart.url', NULL),
      'cart_count' => $this->get($variables, 'aculta_cart.count', 0),
      'cart_empty' => $this->get($variables, 'aculta_cart.empty', TRUE),
      'cart_total' => $this->get($variables, 'aculta_cart.total', ''),
      'cart_sites' => $this->get($variables, 'aculta_cart.sites', []),
      'cart_checkout_url' => $this->get($variables, 'aculta_cart.checkout_url', ''),
      'messages_url' => $this->get($variables, 'aculta_messenger.url', NULL),
      'messages_count' => $this->get($variables, 'aculta_messenger.unread_count', 0),
      'messages_all_url' => $this->get($variables, 'aculta_messenger.all_url', ''),
      'alerts_url' => $this->get($variables, 'aculta_alerts.url', NULL),
      'alerts_count' => $this->get($variables, 'aculta_alerts.count', 0),
      'alerts_count_kind' => $this->get($variables, 'aculta_alerts.count_kind', 'active'),
      'alerts_all_url' => $this->get($variables, 'aculta_alerts.all_url', ''),
      'search' => $library ? NULL : $this->get($variables, 'aculta_search_utility', NULL),
    ];

    return [
      '#prefix' => '<div class="page__institution">',
      '#suffix' => '</div>',
      '#theme' => 'portal_component',
      '#name' => 'multidomain-bar',
      '#props' => $props,
      '#attached' => $variables['#attached'] ?? [],
      '#cache' => ['contexts' => ['user', 'url.path', 'url.query_args', 'route'], 'max-age' => 0],
    ];
  }

  /**
   * Lê um caminho pontilhado (ex.: "cart.url") das variáveis, com valor padrão.
   */

  /**
   * Reads a value from the supplied variables.
   *
   * @param array<array-key, mixed> $variables
   *   Template variables.
   * @param string $path
   *   Dotted key path to read.
   * @param mixed $default
   *   Default value when the path is absent.
   */
  private function get(array $variables, string $path, mixed $default): mixed {
    $value = $variables;
    foreach (explode('.', $path) as $key) {
      if (!is_array($value) || !array_key_exists($key, $value)) {
        return $default;
      }
      $value = $value[$key];
    }
    return $value ?? $default;
  }

}
