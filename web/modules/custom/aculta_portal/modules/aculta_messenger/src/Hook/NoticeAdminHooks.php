<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;

/**
 * Consolidates administrative notice entry points under ACULTA Avisos.
 */
final class NoticeAdminHooks {

  /**
   * Implements hook_menu_links_discovered_alter().
   */
  #[Hook('menu_links_discovered_alter', order: Order::Last)]
  public function moveSiteAlertMenuLink(array &$links): void {
    if (isset($links['site_alert.manage'])) {
      // The collection is embedded under the Avisos tabs. Remove the duplicate
      // standalone Administration link while leaving Site Alert's routes,
      // entity storage, permissions, schedule, and edit/delete forms intact.
      unset($links['site_alert.manage']);
    }

    // The Drupal community feed is not the ACULTA notice administration
    // workflow. Keep the Core module installed, but use the dedicated Avisos
    // menu link below as the single administrative entry point.
    unset($links['announcements_feed.announcement']);

    // Keep the persistent notice content type out of Drupal's generic Create
    // menu. Its add action is provided by the central Avisos administration.
    unset($links['navigation.content.node_type.aculta_notice']);
  }

  /**
   * Implements hook_toolbar_alter().
   */
  #[Hook('toolbar_alter')]
  public function removeCommunityAnnouncementsTab(array &$items): void {
    // announcements_feed adds a community-news tab to the global Drupal
    // toolbar. ACULTA's operational and persistent notices are managed in the
    // Avisos administration section; no Core/contrib code or data is removed.
    unset($items['announcement']);
  }

}
