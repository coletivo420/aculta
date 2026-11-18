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
  protected ?array $menuBuild = NULL;

  /**
   * @param array<mixed> $configuration
   *   Plugin configuration.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly AccountExperienceProvider $accountExperienceProvider,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $configuration
   *   Plugin configuration.
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
   *
   * @return array<mixed>
   *   Story props.
   */
  public function props(): array {
    $props = $this->menu()['#props'] ?? [];
    return is_array($props) ? $props : [];
  }

  /**
   * {@inheritdoc}
   *
   * @return array<mixed>
   *   Story slots.
   */
  public function slots(): array {
    $slots = $this->menu()['#slots'] ?? [];
    return is_array($slots) ? $slots : [];
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
      $buildSlots = $build['#slots'] ?? NULL;
      $menu = is_array($buildSlots) ? ($buildSlots['menu'] ?? NULL) : NULL;
      $menuBuild = [];
      if (is_array($menu)) {
        foreach ($menu as $key => $value) {
          if (is_string($key)) {
            $menuBuild[$key] = $value;
          }
        }
      }
      $this->menuBuild = $menuBuild;
    }
    return $this->menuBuild;
  }

}
