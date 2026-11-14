<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Story;

use Drupal\aculta_studio\Experience\AccountExperienceProvider;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ui_patterns_library\StoryDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Uses the real account provider's authorized menu slots in the component library.
 */
final class StudioMenuStory extends StoryDefault implements ContainerFactoryPluginInterface {

  /**
   * Cached menu build shared by props() and slots().
   *
   * @var array<string, mixed>|null
   */
  private ?array $menuBuild = NULL;

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
    return $this->menu()['#props'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function slots(): array {
    return $this->menu()['#slots'] ?? [];
  }

  /**
   * Gets the real menu render array from the account provider.
   *
   * @return array<string, mixed>
   *   The studio-menu component build.
   */
  private function menu(): array {
    if ($this->menuBuild === NULL) {
      $build = $this->accountExperienceProvider->build();
      $menu = $build['#slots']['menu'] ?? [];
      $this->menuBuild = is_array($menu) ? $menu : [];
    }
    return $this->menuBuild;
  }

}
