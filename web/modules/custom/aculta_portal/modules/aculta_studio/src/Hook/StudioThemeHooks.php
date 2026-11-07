<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Hook;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Registers the neutral Studio presentation hooks owned by this module.
 *
 * The module only declares the data contract. The active theme overrides the
 * templates and adds the classes, icons and layout.
 */
final class StudioThemeHooks {

  public function __construct(
    #[Autowire(service: 'extension.list.module')]
    private readonly ModuleExtensionList $moduleList,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    $path = $this->moduleList->getPath('aculta_studio') . '/templates';

    return [
      'aculta_studio_account' => [
        'variables' => [
          'menu_title' => NULL,
          'menu_items' => [],
          'identity' => [],
          'profile_link' => [],
          'calendar' => [],
          'connections' => [],
          'experiences' => [],
          'experiences_title' => NULL,
          'experiences_description' => NULL,
          'account_url' => NULL,
          'account_link_title' => NULL,
          'account_link_description' => NULL,
          'account_link_label' => NULL,
        ],
        'template' => 'aculta-studio-account',
        'path' => $path,
      ],
      'aculta_studio_account_card' => [
        'variables' => [
          'id' => NULL,
          'kind' => 'card',
          'icon' => NULL,
          'title' => NULL,
          'description' => NULL,
          'tag' => NULL,
          'url' => NULL,
          'action_label' => NULL,
        ],
        'template' => 'aculta-studio-account-card',
        'path' => $path,
      ],
      'aculta_studio_calendar' => [
        'variables' => [
          'title' => NULL,
          'current_month_label' => NULL,
          'previous_url' => NULL,
          'next_url' => NULL,
          'active_filter' => NULL,
          'filters' => [],
          'weeks' => [],
          'selected_date_label' => NULL,
          'selected_day_events' => [],
          'can_add_event' => FALSE,
          'add_event_url' => NULL,
        ],
        'template' => 'aculta-studio-calendar',
        'path' => $path,
      ],
      'aculta_studio_connections' => [
        'variables' => [
          'title' => NULL,
          'connections' => [],
          'empty_message' => NULL,
          'find_connections_label' => NULL,
          'find_connections_url' => NULL,
          'all_connections_label' => NULL,
          'all_connections_url' => NULL,
        ],
        'template' => 'aculta-studio-connections',
        'path' => $path,
      ],
    ];
  }

}
