<?php

declare(strict_types=1);

namespace Drupal\aculta_game;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Defines which first publications count as current public Forum activity.
 */
final class ForumParticipationPolicy {

  public function __construct(
    private readonly ?DomainPurposeManager $domainPurposeManager = NULL,
  ) {}

  /**
   * Whether a newly published comment is eligible for the Forum trigger. */
  public function isEligible(CommentInterface $comment, NodeInterface $topic): bool {
    if (!$comment->isPublished() || !$topic->isPublished()
      || (int) $comment->getOwnerId() < 1
      || !$this->isLive($comment) || !$this->isLive($topic)
      || $this->hasLegacyAuthor($comment) || $this->hasLegacyAuthor($topic)) {
      return FALSE;
    }

    $original = $comment->getOriginal();
    if ($original !== NULL && $original->isPublished()) {
      return FALSE;
    }

    // Only new ACULTA Forum topics count. Drupal Forum content is legacy and
    // must not earn contemporary achievements while it awaits safe removal.
    return $topic->bundle() === 'aculta_forum_topic'
      && $comment->bundle() === 'aculta_discussion_post'
      && $comment->getFieldName() === 'field_aculta_discussion';
  }

  /**
   * Whether a ForumPost has just become a public, published reply.
   */
  public function isEligiblePost(EntityInterface $post): bool {
    if ($post->getEntityTypeId() !== 'aculta_forum_post'
      || !$post instanceof EntityPublishedInterface
      || !$post instanceof RevisionableInterface
      || !$post instanceof EntityOwnerInterface
      || !$post instanceof FieldableEntityInterface
      || !$post->isPublished()
      || !$post->isDefaultRevision()
      || (int) $post->getOwnerId() < 1
      || !$this->isLive($post)
      || $this->hasLegacyAuthor($post)
      || !$post->hasField('topic')
      || $post->get('topic')->isEmpty()) {
      return FALSE;
    }

    $topic = $post->get('topic')->entity;
    if (!$topic instanceof NodeInterface
      || $topic->bundle() !== 'aculta_forum_topic'
      || !$topic->isPublished()
      || !$topic->hasField('field_domain_source')
      || $topic->get('field_domain_source')->isEmpty()
      || $this->domainPurposeManager?->getPurposeForDomainId((string) $topic->get('field_domain_source')->target_id) !== 'forum'
      || !$this->isLive($topic)
      || $this->hasLegacyAuthor($topic)) {
      return FALSE;
    }

    $original = $post->getOriginal();
    return $original === NULL || !$original->isPublished();
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
