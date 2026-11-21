<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Provides authorized frontend topic actions inside Studio Marley.
 *
 * CGU may display these commands, but only Forum controls their permission,
 * target context and existing Form API mutations.
 */
final class ForumMarleyTopicActions {

  public function __construct(
    private readonly DomainSiteManagerInterface $sites,
    private readonly TopicAuthorDeletion $topicState,
  ) {}

  /**
   * Authorizes the exact current Forum topic without any editorial inheritance.
   */
  public function access(AccountInterface $account, ?NodeInterface $node = NULL): AccessResultInterface {
    $permission = AccessResult::allowedIf(
      $account->isAuthenticated() && $account->hasPermission('moderate aculta forum topics'),
    )->cachePerPermissions()->cachePerUser();

    if (!$permission->isAllowed() || $node === NULL || $node->bundle() !== 'aculta_forum_topic'
      || !$node->hasField('field_domain_source') || $node->get('field_domain_source')->isEmpty()
      || $this->sites->getSiteKeyValueForDomainId((string) $node->get('field_domain_source')->target_id) !== 'forum') {
      $denied = AccessResult::forbidden()->cachePerUser()->cachePerPermissions();
      return $node instanceof NodeInterface ? $denied->addCacheableDependency($node) : $denied;
    }

    // Evaluate Node Access in its owner domain. Checking only the current
    // account domain would incorrectly deny a valid Forum topic.
    $targetAccess = $this->sites->runInSite('forum',
      static fn (): AccessResultInterface => $node->access('view', $account, TRUE),
    );
    if (!$targetAccess instanceof AccessResultInterface) {
      return AccessResult::forbidden()->cachePerUser()->cachePerPermissions()->addCacheableDependency($node);
    }
    return $permission->andIf($targetAccess)
      ->addCacheableDependency($node)->addCacheContexts(['domain', 'user.permissions']);
  }

  /**
   * Builds safe links to existing confirmed owner forms (no GET mutations).
   *
   * @return list<array<string, mixed>>
   *   Forum-owned moderation-action render arrays.
   */
  public function buildActions(NodeInterface $topic, AccountInterface $account): array {
    if (!$this->access($account, $topic)->isAllowed()) {
      return [];
    }

    $id = (int) $topic->id();
    $actions = [
      [
        'route' => 'aculta_forum.marley_topic_pin',
        'label' => $topic->isSticky() ? 'Desafixar tópico' : 'Fixar tópico',
        'icon' => 'push-pin-simple',
        'tone' => 'primary',
      ],
      [
        'route' => 'aculta_forum.marley_topic_publish',
        'label' => $topic->isPublished() ? 'Despublicar tópico' : 'Republicar tópico',
        'icon' => $topic->isPublished() ? 'eye-slash' : 'eye',
        'tone' => $topic->isPublished() ? 'warning' : 'success',
      ],
      [
        'route' => 'aculta_forum.marley_topic_close',
        'label' => $this->topicState->isClosed($id) ? 'Reabrir tópico' : 'Fechar tópico',
        'icon' => 'lock',
        'tone' => 'warning',
      ],
      [
        'route' => 'aculta_forum.marley_topic_archive',
        'label' => $this->topicState->isArchived($id) ? 'Desarquivar tópico' : 'Arquivar tópico',
        'icon' => 'archive',
        'tone' => 'neutral',
      ],
    ];

    $output = [];
    foreach ($actions as $action) {
      $url = Url::fromRoute($action['route'], ['node' => $id]);
      if (!$url->access($account)) {
        continue;
      }
      $output[] = [
        '#theme' => 'portal_component',
        '#name' => 'moderation-action',
        '#props' => [
          'label' => $action['label'],
          'url' => $url->toString(),
          'icon' => $action['icon'],
          'variant' => 'regular',
          'tone' => $action['tone'],
        ],
        '#cache' => [
          'contexts' => ['user', 'user.permissions', 'domain'],
          'tags' => $topic->getCacheTags(),
        ],
      ];
    }
    return $output;
  }

}
