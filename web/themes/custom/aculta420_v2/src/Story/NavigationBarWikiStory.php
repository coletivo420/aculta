<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Story;

use Drupal\aculta420_v2\Presentation\WikiBrandMediaProps;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ui_patterns_library\StoryDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Story do navigation-bar com a marca Wiki do tema.
 *
 * Usa o mesmo código de marca do cabeçalho de produção (WikiBrandMediaProps),
 * alimentado por valores de exemplo. O tema não consulta o Portal.
 */
final class NavigationBarWikiStory extends StoryDefault implements ContainerFactoryPluginInterface {

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
    $brandMedia = [
      '#brand_key' => 'wiki420',
      '#alt' => 'Wiki420 - Enciclopédia da cultura canábica',
    ];
    $identity = [
      'home_url' => '/',
      'title' => 'Wiki420',
    ];
    return (new WikiBrandMediaProps($this->themes))->build($brandMedia, $identity) ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function slots(): array {
    return [];
  }

}
