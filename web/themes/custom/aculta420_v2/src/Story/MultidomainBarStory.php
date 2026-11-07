<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Story;

use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ui_patterns_library\StoryDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Story da barra institucional com valores de exemplo.
 *
 * O tema é só apresentação: a story não lê serviços do Portal nem dados da
 * página. Os valores abaixo ilustram o componente no catálogo; a página real
 * recebe as props do módulo dono pelo preprocess de página.
 */
final class MultidomainBarStory extends StoryDefault implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ExtensionList $themes,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.theme'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function props(): array {
    $themePath = $this->themes->getPath('aculta420_v2');
    return [
      'brand_home_url' => '/',
      'logo_base_url' => base_path() . $themePath . '/assets/logos',
      'logo_alt' => 'ACULTA - Associação Cultural Antiproibicionista',
      'is_account_domain' => FALSE,
      'show_utility_bar' => TRUE,
      'cart_url' => '/loja/carrinho',
      'cart_count' => 2,
      'cart_empty' => FALSE,
      'cart_total' => 'R$ 90,00',
      'cart_sites' => [],
      'cart_checkout_url' => '/loja/carrinho',
      'messages_url' => '/mensagens',
      'messages_count' => 1,
      'messages_all_url' => '/mensagens',
      'alerts_url' => '/alertas',
      'alerts_count' => 1,
      'alerts_count_kind' => 'active',
      'alerts_all_url' => '/alertas',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function slots(): array {
    return [];
  }

}
