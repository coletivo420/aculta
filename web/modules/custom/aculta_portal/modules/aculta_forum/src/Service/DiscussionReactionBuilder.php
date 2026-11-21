<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_forum\Access\DiscussionReactionEndpointAccess;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Url;

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
   *
   * @return array<mixed>
   *   Render array.
   */
  public function build(EntityInterface $entity, AccountInterface $account): array {
    $entityType = $entity->getEntityTypeId();
    $entityId = (int) $entity->id();
    $uid = $account->isAuthenticated() ? (int) $account->id() : NULL;
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

    $authorUid = $entity instanceof EntityOwnerInterface ? (int) $entity->getOwnerId() : 0;
    $viewerUid = $uid ?? 0;
    $authorStats = $this->reactionManager->getUserStats($authorUid);
    $viewerStats = $authorUid === $viewerUid
      ? $authorStats
      : $this->reactionManager->getUserStats($viewerUid);

    $targetId = "discussion-reactions-{$entityType}-{$entityId}";

    $build = [
      '#theme' => 'portal_component',
      '#name' => 'discussion-reaction-bar',
      '#props' => [
        'target_id' => $targetId,
        'entity_type' => $entityType,
        'entity_id' => (string) $entityId,
        'reactions' => $reactionsData['reactions'],
        'total_count' => $reactionsData['total_count'],
        'positive_count' => $reactionsData['positive_count'],
        'dislike_count' => $reactionsData['dislike_count'],
        'author_uid' => $authorUid,
        'author_reactions_received' => $authorStats['reactions_received'],
        'viewer_uid' => $viewerUid,
        'viewer_reactions_given' => $viewerStats['reactions_given'],
        'user_reaction' => $reactionsData['user_reaction'],
        'allowed_reactions' => $allowedReactions,
        'default_reaction' => $defaultReaction,
        'can_react' => $canReact,
        'react_url' => $reactUrl,
        'csrf_token' => $csrfToken,
      ],
      '#attached' => [
        'library' => [
          'core/drupal.htmx',
          'aculta_forum/reaction-counter-sync',
        ],
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'languages:language_interface'],
        'tags' => array_merge($entity->getCacheTags(), [
          "aculta_discussion_reactions:{$entityType}:{$entityId}",
          'config:aculta_forum.settings',
          "aculta_user_reactions:{$authorUid}",
          "aculta_user_reactions:{$viewerUid}",
        ]),
      ],
    ];
    $cacheMetadata = CacheableMetadata::createFromRenderArray($build);
    $cacheMetadata->addCacheableDependency($access);
    $cacheMetadata->applyTo($build);

    return $build;
  }

}
