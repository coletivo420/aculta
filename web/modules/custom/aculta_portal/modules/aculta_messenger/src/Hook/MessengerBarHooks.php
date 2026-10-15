<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\aculta_messenger\Service\UnreadThreadCounter;
use Drupal\aculta_messenger\Support\StorageDateTime;
use Drupal\site_alert\GetAlertsInterface;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Supplies the neutral, per-member Messenger action to the global shell. */
final class MessengerBarHooks {

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'aculta_messenger.unread_thread_counter')]
    private readonly UnreadThreadCounter $unreadThreadCounter,
    #[Autowire(service: 'site_alert.get_alerts')]
    private readonly GetAlertsInterface $siteAlerts,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'renderer')]
    private readonly RendererInterface $renderer,
    #[Autowire(service: 'datetime.time')]
    private readonly TimeInterface $time,
    #[Autowire(service: 'flag')]
    private readonly FlagServiceInterface $flagService,
    #[Autowire(service: 'aculta_user.friendship_manager')]
    private readonly FriendshipManagerInterface $friendshipManager,
  ) {}

  /** Adds a same-origin popup endpoint only for accounts allowed to message. */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    // Site Alerts are public: visitors see the active-alert count, and members also see
    // their unread persistent notices. Only the private messenger is member-only.
    if (isset($variables['page']) && is_array($variables['page'])) {
      $alertStorage = $this->entityTypeManager->getStorage('site_alert');
      $alerts = $this->siteAlerts->getActiveAlerts();
      $alertCacheability = (new CacheableMetadata())
        ->addCacheContexts(['active_site_alerts'])
        ->addCacheTags($this->entityTypeManager->getDefinition('site_alert')->getListCacheTags())
        ->setCacheMaxAge($alertStorage->getCacheMaxAge());
      if ($this->currentUser->isAuthenticated()) {
        // The alert badge includes this member's unread Flag state.
        $alertCacheability->addCacheContexts(['user'])->setCacheMaxAge(0);
      }
      $dismissalFlag = $this->flagService->getFlagById('aculta_site_alert_dismissed');
      foreach ($alerts as $alert) {
        $alertCacheability->addCacheableDependency($alert);
      }
      $alertUrl = Url::fromRoute('aculta_messenger.alerts_preview')->toString(TRUE);
      $alertCount = 0;
      foreach ($alerts as $alert) {
        $access = $alert->access('view', $this->currentUser, TRUE);
        $alertCacheability->addCacheableDependency($access);
        if (!$access->isAllowed()) {
          continue;
        }
        if ($dismissalFlag instanceof FlagInterface && $this->flagService->getFlagging($dismissalFlag, $alert, $this->currentUser) !== NULL) {
          continue;
        }
        $alertCount++;
      }
      $alertCacheability->addCacheTags($this->entityTypeManager->getDefinition('flagging')->getListCacheTags());
      $unreadNoticeCount = $this->currentUser->isAuthenticated()
        ? $alertCount + $this->getUnreadNoticeCount()
        : $alertCount;
      $variables['aculta_alerts'] = [
        'url' => $alertUrl->getGeneratedUrl(),
        'count' => $unreadNoticeCount,
        'count_kind' => $this->currentUser->isAuthenticated() ? 'attention' : 'active',
      ];
      $this->renderer->addCacheableDependency($variables['page'], $alertCacheability);
      $this->renderer->addCacheableDependency($variables['page'], $alertUrl);
    }

    if (!$this->currentUser->isAuthenticated()
      || !$this->currentUser->hasPermission('use private messaging system')
      || !$this->currentUser->hasPermission('access user profiles')) {
      $variables['aculta_messenger_trigger'] = [];
      return;
    }

    $url = Url::fromRoute('aculta_messenger.preview')->toString(TRUE);
    $variables['aculta_messenger'] = [
      'url' => $url->getGeneratedUrl(),
      'unread_count' => $this->unreadThreadCounter->count() + count($this->friendshipManager->getIncomingRequests()),
    ];

    if (isset($variables['page']) && is_array($variables['page'])) {
      $cacheability = (new CacheableMetadata())
        ->addCacheContexts(['user'])
        ->setCacheMaxAge(0)
        ->addCacheableDependency($url);
      $this->renderer->addCacheableDependency($variables['page'], $cacheability);
    }
  }

  /** Supplies the per-member native thread state as a presentation-neutral value. */
  #[Hook('preprocess_private_message_thread')]
  public function preprocessPrivateMessageThread(array &$variables): void {
    if (($variables['view_mode'] ?? $variables['elements']['#view_mode'] ?? NULL) !== 'inbox') {
      return;
    }
    $thread = $variables['private_message_thread'] ?? $variables['elements']['#private_message_thread'] ?? NULL;
    if (!$thread instanceof PrivateMessageThreadInterface) {
      return;
    }
    $variables['aculta_messenger_read_status'] = $this->unreadThreadCounter->isUnread($thread) ? 'unread' : 'read';
    $variables['elements']['#cache']['contexts'][] = 'user';
    $variables['elements']['#cache']['max-age'] = 0;
  }

  /** Applies publication window and role audience to persistent notices. */
  #[Hook('node_access')]
  public function nodeAccess(NodeInterface $node, string $op, AccountInterface $account): AccessResult {
    if ($node->bundle() !== 'aculta_notice' || $op !== 'view') {
      return AccessResult::neutral();
    }
    $result = AccessResult::forbidden()->addCacheableDependency($node)->cachePerUser();
    if ($account->hasPermission('administer nodes') || $account->hasPermission('bypass node access')) {
      return AccessResult::neutral()->addCacheableDependency($node);
    }
    if (!$node->isPublished() || !$node->hasField('field_aculta_notice_roles') || $node->get('field_aculta_notice_roles')->isEmpty()) {
      return $result;
    }
    $now = $this->time->getRequestTime();
    $maxAge = Cache::PERMANENT;
    foreach (['field_aculta_notice_start' => TRUE, 'field_aculta_notice_end' => FALSE] as $field => $isStart) {
      if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
        continue;
      }
      $timestamp = StorageDateTime::toTimestamp((string) $node->get($field)->value);
      if ($timestamp === NULL) {
        return $result;
      }
      if ($timestamp > $now) {
        $untilTransition = $timestamp - $now;
        $maxAge = $maxAge === Cache::PERMANENT
          ? $untilTransition
          : min($maxAge, $untilTransition);
      }
      if (($isStart && $now < $timestamp) || (!$isStart && $now >= $timestamp)) {
        return $result->setCacheMaxAge($maxAge);
      }
    }
    $audiences = array_column($node->get('field_aculta_notice_roles')->getValue(), 'value');
    $allowed = (bool) array_intersect($audiences, $account->getRoles());
    // A non-matching audience must deny access, not return neutral: another
    // node access grant could otherwise make a segmented notice visible.
    $audienceAccess = $allowed ? AccessResult::allowed() : AccessResult::forbidden();
    return $audienceAccess
      ->addCacheableDependency($node)
      ->cachePerUser()
      ->setCacheMaxAge($maxAge);
  }

  /**
   * Counts visible persistent notices not yet flagged as read by this member.
   *
   * Node access is applied by the query and the read state is excluded in SQL, so no notice is loaded
   * only to be discarded.
   */
  private function getUnreadNoticeCount(): int {
    $flag = $this->entityTypeManager->getStorage('flag')->load('aculta_notice_read');
    if ($flag === NULL) {
      return 0;
    }
    $readIds = $this->entityTypeManager->getStorage('flagging')->getQuery()
      ->accessCheck(FALSE)
      ->condition('flag_id', 'aculta_notice_read')
      ->condition('uid', (int) $this->currentUser->id())
      ->condition('entity_type', 'node')
      ->execute();
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'aculta_notice')
      ->condition('status', 1);
    if ($readIds !== []) {
      $readNodeIds = $this->entityTypeManager->getStorage('flagging')->loadMultiple($readIds);
      $flagged = array_values(array_filter(array_map(
        static fn($flagging) => (int) $flagging->getFlaggableId(),
        $readNodeIds,
      )));
      if ($flagged !== []) {
        $query->condition('nid', $flagged, 'NOT IN');
      }
    }
    return (int) $query->count()->execute();
  }

}
