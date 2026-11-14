<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_forum\Access\DiscussionReactionEndpointAccess;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\EntityOwnerInterface;

/**
 * Builds the discussion reaction bar render array.
 */
class DiscussionReactionBuilder {

  public function __construct(
    protected readonly DiscussionReactionManager $reactionManager,
    protected readonly DiscussionReactionEndpointAccess $reactionEndpointAccess,
    protected readonly CsrfTokenGenerator $csrfToken,
  ) {}

  /**
   * Builds the render array for the reaction bar of a comment or forum post.
   */
  public function build(EntityInterface $entity, AccountInterface $account): array {
    $entityType = $entity->getEntityTypeId();
    $entityId = (int) $entity->id();
    $uid = $account->isAuthenticated() ? (int) $account->id() : NULL;
    $authorUid = $entity instanceof EntityOwnerInterface ? (int) $entity->getOwnerId() : 0;
    $isOwnContent = ($uid !== NULL && $uid > 0 && $authorUid > 0 && $uid === $authorUid);

    $reactionsData = $this->reactionManager->loadReactions($entityType, $entityId, $uid);
    $access = $this->reactionEndpointAccess->access($entityType, (string) $entityId, $account);
    $canReact = $access->isAllowed();

    $reactUrl = Url::fromRoute('aculta_forum.reaction_react', [
      'entity_type' => $entityType,
      'entity_id' => $entityId,
    ])->toString();

    $csrfToken = $account->isAuthenticated()
      ? $this->csrfToken->get("aculta_forum:reaction:{$entityType}:{$entityId}")
      : '';

    $allowedReactions = $this->reactionManager->getAllowedReactions();
    $defaultReaction = $this->reactionManager->getDefaultReaction();

    $targetId = "discussion-reactions-{$entityType}-{$entityId}";

    $build = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:discussion-reaction-bar',
      '#props' => [
        'target_id' => $targetId,
        'entity_type' => $entityType,
        'entity_id' => (string) $entityId,
        'reactions' => $reactionsData['reactions'],
        'total_count' => $reactionsData['total_count'],
        'user_reaction' => $reactionsData['user_reaction'],
        'allowed_reactions' => $allowedReactions,
        'default_reaction' => $defaultReaction,
        'can_react' => $canReact,
        'is_own_content' => $isOwnContent,
        'react_url' => $reactUrl,
        'csrf_token' => $csrfToken,
      ],
      '#attached' => [
        'library' => [
          'core/drupal.htmx',
        ],
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'languages:language_interface'],
        'tags' => array_merge($entity->getCacheTags(), [
          "aculta_discussion_reactions:{$entityType}:{$entityId}",
          'config:aculta_forum.settings',
        ]),
      ],
    ];
    $cacheMetadata = CacheableMetadata::createFromRenderArray($build);
    $cacheMetadata->addCacheableDependency($access);
    $cacheMetadata->applyTo($build);

    return $build;
  }

}
