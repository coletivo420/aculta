<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Access;

use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Limits the topic moderation popup to viewable Forum topics on Forum domain.
 *
 * The route permission is necessary but not sufficient: restricted Group
 * grants and original topic domain are enforced for every requested node.
 */
final class TopicAdminPopupAccess {

  public function __construct(
    private readonly DomainSiteManagerInterface $sites,
  ) {}

  /**
   * Checks the exact topic identified by the route parameter.
   */
  public function access(AccountInterface $account, ?NodeInterface $node = NULL): AccessResultInterface {
    $denied = AccessResult::forbidden()
      ->cachePerUser()
      ->cachePerPermissions()
      ->addCacheContexts(['domain'])
      ->addCacheableDependency($node);

    if (!$account->isAuthenticated()
      || !$account->hasPermission('move aculta forum topics')
      || $node === NULL
      || $node->bundle() !== 'aculta_forum_topic'
      || $this->sites->getCurrentSiteKey()?->value !== 'forum'
      || !$node->hasField('field_domain_source')
      || $node->get('field_domain_source')->isEmpty()) {
      return $denied;
    }

    $domain = $node->get('field_domain_source')->getValue();
    $domainId = is_array($domain) && isset($domain[0]['target_id'])
      && is_scalar($domain[0]['target_id'])
      ? (string) $domain[0]['target_id']
      : '';
    if ($domainId === '' || $this->sites->getSiteKeyValueForDomainId($domainId) !== 'forum') {
      return $denied;
    }

    // Node Access includes membership of the actual restricted Forum Group.
    $view = $node->access('view', $account, TRUE);
    if (!$view->isAllowed()) {
      return $denied->addCacheableDependency($view);
    }

    return AccessResult::allowed()
      ->cachePerUser()
      ->cachePerPermissions()
      ->addCacheContexts(['domain'])
      ->addCacheableDependency($node)
      ->addCacheableDependency($view);
  }

}
