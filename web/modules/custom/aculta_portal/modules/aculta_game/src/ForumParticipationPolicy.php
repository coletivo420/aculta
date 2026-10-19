<?php

declare(strict_types=1);

namespace Drupal\aculta_game;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\node\NodeInterface;

/**
 * Defines which published comments count as current public Forum activity. */
final class ForumParticipationPolicy {

  /**
   * Whether a newly published comment is eligible for the Forum trigger. */
  public function isEligible(CommentInterface $comment, NodeInterface $topic): bool {
    if (!$comment->isPublished() || !$topic->isPublished()
      || !$this->isLive($comment) || !$this->isLive($topic)
      || $this->hasLegacyAuthor($comment) || $this->hasLegacyAuthor($topic)) {
      return FALSE;
    }

    $original = $comment->getOriginal();
    if ($original !== NULL && $original->isPublished()) {
      return FALSE;
    }

    // Keep the old live Forum path until its planned safe removal. Imported
    // archive entries are excluded by the marker and synchronizable checks.
    $isCurrentForum = $topic->bundle() === 'aculta_forum_topic'
      && $comment->bundle() === 'aculta_discussion_post'
      && $comment->getFieldName() === 'field_aculta_discussion';
    $isContribTransition = $topic->bundle() === 'forum'
      && $comment->bundle() === 'comment_forum'
      && $comment->getFieldName() === 'comment_forum';

    return $isCurrentForum || $isContribTransition;
  }

  /**
   * Restored Growroom archive never earns contemporary achievements. */
  private function hasLegacyAuthor(EntityInterface $entity): bool {
    return $entity instanceof FieldableEntityInterface
      && $entity->hasField('field_autor_legado')
      && !$entity->get('field_autor_legado')->isEmpty();
  }

  /**
   * Migrate Core marks synchronized entities; they must not trigger badges. */
  private function isLive(EntityInterface $entity): bool {
    return !($entity instanceof SynchronizableInterface && $entity->isSyncing());
  }

}
