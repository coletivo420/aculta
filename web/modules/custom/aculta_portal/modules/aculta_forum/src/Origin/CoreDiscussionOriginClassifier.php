<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Origin;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Classifies current ForumPosts and registered contextual discussions.
 *
 * Forum topic Comments are classified as current by default; an installed
 * legacy addon can override that classification when it proves archive origin.
 */
final class CoreDiscussionOriginClassifier implements DiscussionOriginClassifierInterface {

  public function __construct(
    private readonly DiscussionContextResolver $contextResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function classify(EntityInterface $entity): ?DiscussionOriginClassification {
    if ($entity->getEntityTypeId() === 'aculta_forum_post') {
      if (!$entity instanceof FieldableEntityInterface
        || !$entity->hasField('topic')
        || $entity->get('topic')->isEmpty()) {
        return NULL;
      }
      $topic = $entity->get('topic')->entity;
      if (!$topic instanceof NodeInterface
        || $topic->bundle() !== 'aculta_forum_topic'
        || !self::hasCurrentOwner($entity)
        || !self::hasCurrentOwner($topic)) {
        return NULL;
      }
      return DiscussionOriginClassification::fromSources(DiscussionOrigin::CURRENT, [$entity, $topic]);
    }

    if ($entity instanceof NodeInterface && $entity->bundle() === 'aculta_forum_topic') {
      return self::hasCurrentOwner($entity)
        ? DiscussionOriginClassification::from(DiscussionOrigin::CURRENT, $entity)
        : NULL;
    }

    if (!$entity instanceof CommentInterface) {
      return $this->contextResolver->supports($entity)
        ? DiscussionOriginClassification::from(DiscussionOrigin::CONTEXTUAL, $entity)
        : NULL;
    }

    if ($entity->bundle() !== 'aculta_discussion_post'
      || $entity->getFieldName() !== 'field_aculta_discussion') {
      return DiscussionOriginClassification::from(DiscussionOrigin::UNKNOWN, $entity);
    }

    $host = $entity->getCommentedEntity();
    if (!$host instanceof EntityInterface) {
      return DiscussionOriginClassification::from(DiscussionOrigin::UNKNOWN, $entity);
    }

    if ($host instanceof NodeInterface && $host->bundle() === 'aculta_forum_topic') {
      return self::hasCurrentOwner($entity) && self::hasCurrentOwner($host)
        ? DiscussionOriginClassification::fromSources(DiscussionOrigin::CURRENT, [$entity, $host])
        : NULL;
    }

    if (!$this->contextResolver->supports($host)) {
      return NULL;
    }

    return DiscussionOriginClassification::fromSources(DiscussionOrigin::CONTEXTUAL, [$entity, $host]);
  }

  /**
   * A positive Drupal UID is the current-content provenance signal (F-D17).
   */
  private static function hasCurrentOwner(EntityInterface $entity): bool {
    if (!$entity instanceof EntityOwnerInterface) {
      return FALSE;
    }
    $ownerId = $entity->getOwnerId();
    return is_numeric($ownerId) && (int) $ownerId > 0;
  }

}
