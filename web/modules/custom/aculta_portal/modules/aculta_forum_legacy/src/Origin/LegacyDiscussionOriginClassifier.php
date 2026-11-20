<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_legacy\Origin;

use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginClassification;
use Drupal\aculta_forum\Origin\DiscussionOriginClassifierInterface;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Classifies only marked Growroom archive entities.
 *
 * The archive marker is interpreted on a Growroom topic and its Comments. A
 * Comment marker alone cannot promote a current Forum topic into the archive.
 * A matching field on Wiki, Magazine, LMS or another host is not evidence of
 * legacy origin.
 */
final class LegacyDiscussionOriginClassifier implements DiscussionOriginClassifierInterface {

  /**
   * {@inheritdoc}
   */
  public function classify(EntityInterface $entity): ?DiscussionOriginClassification {
    if ($entity instanceof NodeInterface && $entity->bundle() === 'aculta_forum_topic') {
      return $this->hasLegacyAuthor($entity)
        ? DiscussionOriginClassification::from(DiscussionOrigin::ARCHIVE, $entity)
        : NULL;
    }

    if (!$entity instanceof CommentInterface) {
      return NULL;
    }

    if ($entity->bundle() !== 'aculta_discussion_post'
      || $entity->getFieldName() !== 'field_aculta_discussion') {
      return NULL;
    }

    $host = $entity->getCommentedEntity();
    if (!$host instanceof NodeInterface || $host->bundle() !== 'aculta_forum_topic') {
      return NULL;
    }

    // A Comment marker alone cannot promote a current Forum thread into the
    // archive. The host topic is the authoritative archive boundary.
    if (!$this->hasLegacyAuthor($host)) {
      return NULL;
    }

    return DiscussionOriginClassification::fromSources(DiscussionOrigin::ARCHIVE, [$entity, $host]);
  }

  /**
   * Determines whether the archive attribution field has a value.
   */
  private function hasLegacyAuthor(FieldableEntityInterface $entity): bool {
    return $entity->hasField('field_autor_legado')
      && !$entity->get('field_autor_legado')->isEmpty();
  }

}
