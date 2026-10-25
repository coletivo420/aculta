<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\site_alert\GetAlertsInterface;

/**
 * Defines the alert and persistent-notice counts shown in Messenger surfaces.
 *
 * The header bar, alert preview and account notices share these methods. Node
 * access and read state are applied in queries instead of loading every notice.
 */
final class MessengerCounts {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FlagServiceInterface $flagService,
    private readonly GetAlertsInterface $siteAlerts,
    private readonly NoticeFlagStateLoader $flagStateLoader,
    private readonly ?ModuleHandlerInterface $moduleHandler = NULL,
  ) {}

  /**
   * Counts active Site Alerts this account may see and has not read/dismissed.
   *
   * Alert entities and access results are added to cacheability so the render
   * array is invalidated when an alert or its access changes.
   */
  public function visibleActiveAlertCount(
    AccountInterface $account,
    CacheableMetadata $cacheability,
    ?array $visibleAlerts = NULL,
    ?array $flagStates = NULL,
  ): int {
    $dismissalFlag = $this->flagService->getFlagById('aculta_site_alert_dismissed');
    $readFlag = $account->isAuthenticated()
      ? $this->flagService->getFlagById('aculta_site_alert_read')
      : NULL;
    if ($visibleAlerts === NULL) {
      $visibleAlerts = [];
      foreach ($this->siteAlerts->getActiveAlerts() as $alert) {
        $cacheability->addCacheableDependency($alert);
        $access = $alert->access('view', $account, TRUE);
        $cacheability->addCacheableDependency($access);
        if ($access->isAllowed()) {
          $visibleAlerts[] = $alert;
        }
      }
    }
    if ($flagStates === NULL && $account->isAuthenticated()
      && ($dismissalFlag instanceof FlagInterface || $readFlag instanceof FlagInterface)) {
      $flagStates = $this->flagStateLoader->loadForAccount($visibleAlerts, $account);
    }
    $flagStates ??= [];
    $count = 0;
    foreach ($visibleAlerts as $alert) {
      // Dispensa pertence a contas; não consultar Flag para uid 0 sem sessão.
      if ($account->isAuthenticated()
        && (($dismissalFlag instanceof FlagInterface
            && isset($flagStates[$alert->id()][$dismissalFlag->id()]))
          || ($readFlag instanceof FlagInterface
            && isset($flagStates[$alert->id()][$readFlag->id()])))) {
        continue;
      }
      $count++;
    }
    return $count;
  }

  /**
   * Counts all unread items represented by the unified notice center.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account whose currently accessible items are counted.
   * @param int $personalNoticeCount
   *   Accessible unread Forum and adapter notices.
   * @param int|null $activeAlertCount
   *   Optional count already calculated for the current alert list.
   */
  public function totalUnreadNoticeCount(AccountInterface $account, int $personalNoticeCount, ?int $activeAlertCount = NULL): int {
    $activeAlertCount ??= $this->visibleActiveAlertCount($account, new CacheableMetadata());
    return max(0, $activeAlertCount)
      + $this->unreadPersistentNoticeCount($account)
      + max(0, $personalNoticeCount);
  }

  /**
   * Counts visible, published persistent notices not marked as read.
   */
  public function unreadPersistentNoticeCount(AccountInterface $account): int {
    // No flaggings exist for uid 0, so anonymous visitors have no unread count.
    if (!$account->isAuthenticated()) {
      return 0;
    }
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
    $count = (int) $query->count()->execute();
    $this->moduleHandler?->alter('aculta_messenger_unread_notice_count', $count, $account);
    return $count;
  }

}
