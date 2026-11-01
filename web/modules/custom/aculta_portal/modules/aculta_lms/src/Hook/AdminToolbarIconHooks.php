<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Loads the ACULTA LMS icon for users who can access administration. */
final class AdminToolbarIconHooks {

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
  ) {}

  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if (!$this->currentUser->hasPermission('access administration pages')) {
      return;
    }

    $attachments['#attached']['library'][] = 'aculta_lms/admin_toolbar_icon';
    $attachments['#cache']['contexts'][] = 'user.permissions';
  }

  /**
   * Implements hook_menu_links_discovered_alter().
   */
  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(array &$links): void {
    if (isset($links['system.admin_lms'])) {
      // Core Navigation renders the admin sidebar from this icon option. The
      // legacy toolbar CSS remains available for sites that use Toolbar instead.
      $links['system.admin_lms']['options']['icon'] = [
        'pack_id' => 'aculta_lms',
        'icon_id' => 'learning',
      ];
    }

    // View menu-link titles are configuration strings, so the ordinary
    // interface catalog does not translate them consistently in admin pages.
    $view_labels = [
      'views_view:views.classes_admin.page' => [
        'title' => new TranslatableMarkup('Classes'),
        'description' => new TranslatableMarkup('Administer Classes'),
      ],
      'views_view:views.courses_admin.page' => [
        'title' => new TranslatableMarkup('Courses'),
        'description' => new TranslatableMarkup('Administer Courses'),
      ],
    ];
    foreach ($view_labels as $plugin_id => $labels) {
      if (isset($links[$plugin_id])) {
        $links[$plugin_id] = $labels + $links[$plugin_id];
      }
    }
  }

}
