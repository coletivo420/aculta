<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\site_alert\GetAlertsInterface;

/**
 * The single definition of the alert and persistent-notice counts shown in the Messenger surfaces.
 *
 * The header bar, the alerts preview and the account notices all call these methods, so the numbers
 * cannot drift apart. Node access and the read state are applied in queries, not by loading every notice.
 */
final class MessengerCounts {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FlagServiceInterface $flagService,
    private readonly GetAlertsInterface $siteAlerts,
  ) {}

  /**
   * Counts the active Site Alerts this account may see and has not dismissed.
   *
   * Every alert and access result is added to $cacheability, so the caller's render array is invalidated
   * when an alert or its access changes.
   */
  public function visibleActiveAlertCount(AccountInterface $account, CacheableMetadata $cacheability): int {
    $dismissalFlag = $this->flagService->getFlagById('aculta_site_alert_dismissed');
    $count = 0;
    foreach ($this->siteAlerts->getActiveAlerts() as $alert) {
      $cacheability->addCacheableDependency($alert);
      $access = $alert->access('view', $account, TRUE);
      $cacheability->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      if ($dismissalFlag instanceof FlagInterface && $this->flagService->getFlagging($dismissalFlag, $alert, $account) !== NULL) {
        continue;
      }
      $count++;
    }
    return $count;
  }

  /**
   * Counts the visible, published persistent notices this account has not marked as read.
   */
  public function unreadPersistentNoticeCount(AccountInterface $account): int {
    $flag = $this->entityTypeManager->getStorage('flag')->load('aculta_notice_read');
    if (!$flag instanceof FlagInterface) {
      return 0;
    }
    $readIds = $this->entityTypeManager->getStorage('flagging')->getQuery()
      ->accessCheck(FALSE)
      ->condition('flag_id', 'aculta_notice_read')
      ->condition('uid', (int) $account->id())
      ->condition('entity_type', 'node')
      ->execute();
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'aculta_notice')
      ->condition('status', 1);
    if ($readIds !== []) {
      $flagged = [];
      foreach ($this->entityTypeManager->getStorage('flagging')->loadMultiple($readIds) as $flagging) {
        $flagged[] = (int) $flagging->getFlaggableId();
      }
      if ($flagged !== []) {
        $query->condition('nid', $flagged, 'NOT IN');
      }
    }
    return (int) $query->count()->execute();
  }

}
