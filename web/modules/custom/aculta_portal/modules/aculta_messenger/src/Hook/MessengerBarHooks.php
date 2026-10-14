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
use Drupal\Core\Datetime\TimeInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\site_alert\GetAlertsInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Supplies the neutral, per-member Messenger action to the global shell. */
final class MessengerBarHooks {

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'private_message.service')]
    private readonly PrivateMessageServiceInterface $privateMessageService,
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
  ) {}

  /** Adds a same-origin popup endpoint only for accounts allowed to message. */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
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
      foreach ($alerts as $alert) {
        $alertCacheability->addCacheableDependency($alert);
      }
      $alertUrl = Url::fromRoute('aculta_messenger.alerts_preview')->toString(TRUE);
      $alertCount = count($alerts);
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
      return;
    }

    $url = Url::fromRoute('aculta_messenger.preview')->toString(TRUE);
    $variables['aculta_messenger'] = [
      'url' => $url->getGeneratedUrl(),
      'unread_count' => max(0, $this->privateMessageService->getUnreadThreadCount()),
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
    $lastAccess = $thread->getLastAccessTimestamp($this->currentUser);
    $variables['aculta_messenger_read_status'] = 'read';
    foreach ($thread->getMessages() as $message) {
      if ($lastAccess <= $message->getCreatedTime() && $message->getOwnerId() != $this->currentUser->id()) {
        $variables['aculta_messenger_read_status'] = 'unread';
        break;
      }
    }
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
      $timestamp = strtotime((string) $node->get($field)->value);
      if ($timestamp === FALSE) {
        return $result;
      }
      if ($timestamp > $now) {
        $maxAge = min($maxAge, $timestamp - $now);
      }
      if (($isStart && $now < $timestamp) || (!$isStart && $now >= $timestamp)) {
        return $result->setCacheMaxAge($maxAge);
      }
    }
    $audiences = array_column($node->get('field_aculta_notice_roles')->getValue(), 'value');
    $allowed = (bool) array_intersect($audiences, $account->getRoles());
    return AccessResult::allowedIf($allowed)
      ->addCacheableDependency($node)
      ->cachePerUser()
      ->setCacheMaxAge($maxAge);
  }

  /** Counts visible persistent notices not yet flagged as read by this member. */
  private function getUnreadNoticeCount(): int {
    $flag = $this->entityTypeManager->getStorage('flag')->load('aculta_notice_read');
    if ($flag === NULL) {
      return 0;
    }
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'aculta_notice')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($ids);
    $count = 0;
    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface || !$node->access('view', $this->currentUser)) {
        continue;
      }
      if ($flag instanceof FlagInterface && $this->flagService->getFlagging($flag, $node, $this->currentUser) === NULL) {
        $count++;
      }
    }
    return $count;
  }

}
