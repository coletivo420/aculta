<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Story;

use Drupal\aculta_studio\Experience\AccountExperienceProvider;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ui_patterns_library\StoryDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Uses the real account provider's menu props in the component library.
 */
final class StudioMenuStory extends StoryDefault implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly AccountExperienceProvider $accountExperienceProvider,
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
      $container->get('aculta_studio.experience.conta'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function props(): array {
    $build = $this->accountExperienceProvider->build();
    return [
      'title' => $build['#menu_title'] ?? 'Studio Dashboard',
      'items' => $build['#menu_items'] ?? [],
    ];
  }

}
