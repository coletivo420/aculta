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
use Drupal\aculta_messenger\Service\MessengerCounts;
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
    #[Autowire(service: 'aculta_messenger.counts')]
    private readonly MessengerCounts $counts,
  ) {}

  /** Adds a same-origin popup endpoint only for accounts allowed to message. */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    // Site Alerts are public: visitors see the active-alert count, and members also see
    // their unread persistent notices. Only the private messenger is member-only.
    if (isset($variables['page']) && is_array($variables['page'])) {
      $alertStorage = $this->entityTypeManager->getStorage('site_alert');
      $alertCacheability = (new CacheableMetadata())
        ->addCacheContexts(['active_site_alerts'])
        ->addCacheTags($this->entityTypeManager->getDefinition('site_alert')->getListCacheTags())
        ->setCacheMaxAge($alertStorage->getCacheMaxAge());
      if ($this->currentUser->isAuthenticated()) {
        // The alert badge includes this member's unread Flag state.
        $alertCacheability->addCacheContexts(['user'])->setCacheMaxAge(0);
      }
      $alertUrl = Url::fromRoute('aculta_messenger.alerts_preview')->toString(TRUE);
      $alertCount = $this->counts->visibleActiveAlertCount($this->currentUser, $alertCacheability);
      $alertCacheability->addCacheTags($this->entityTypeManager->getDefinition('flagging')->getListCacheTags());
      $unreadNoticeCount = $this->currentUser->isAuthenticated()
        ? $alertCount + $this->counts->unreadPersistentNoticeCount($this->currentUser)
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

  /**
   * The drawer's new-conversation search is loaded by fetch, so its script must already be on the page.
   * Attaching it through page_attachments reaches every page, not only the region render array.
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if (!$this->currentUser->isAuthenticated()
      || !$this->currentUser->hasPermission('use private messaging system')
      || !$this->currentUser->hasPermission('access user profiles')) {
      return;
    }
    $attachments['#attached']['library'][] = 'aculta_portal/search-preview';
    $attachments['#cache']['contexts'][] = 'user.permissions';
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

}
