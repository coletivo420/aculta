<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexAccessContextProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\node\NodeInterface;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\comment\CommentInterface;

/**
 * Projects public Forum topics and both discussion systems during migration.
 *
 * Text bodies, authors, rejected comments, and non-default ForumPost revisions
 * are excluded. Access is rechecked in the canonical Forum purpose.
 */
final class ForumContentIndexProvider implements ContentIndexProviderInterface, ContentIndexAccessContextProviderInterface {

  /**
   * {@inheritdoc} */
  public function getProviderId(): string {
    return 'forum';
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectionVersion(): int {
    return 1;
  }

  /**
   * {@inheritdoc} */
  public function getSupportedEntityTypes(): array {
    return [
      'node' => ['aculta_forum_topic'],
      'aculta_forum_post' => ['aculta_forum_post'],
      'comment' => ['aculta_discussion_post'],
    ];
  }

  /**
   * {@inheritdoc} */
  public function getAccessPurpose(ContentEntityInterface $entity): ?string {
    return match (TRUE) {
      $entity instanceof NodeInterface && $entity->bundle() === 'aculta_forum_topic' => 'forum',
      $entity instanceof ForumPost => 'forum',
      $entity instanceof CommentInterface && $entity->bundle() === 'aculta_discussion_post' => 'forum',
      default => NULL,
    };
  }

  /**
   * {@inheritdoc} */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if ($entity instanceof NodeInterface && $entity->bundle() === 'aculta_forum_topic') {
      if ($entity instanceof RevisionableInterface && !$entity->isDefaultRevision()) {
        return NULL;
      }
      return $this->projection(
        $entity,
        (string) $entity->label(),
        $entity->isPublished(),
        $this->domainId($entity),
      );
    }

    if ($entity instanceof ForumPost) {
      if (!$entity->isDefaultRevision()) {
        return NULL;
      }
      $relations = [];
      $topic = $entity->get('topic')->entity;
      if ($topic instanceof NodeInterface && $topic->bundle() === 'aculta_forum_topic') {
        $relations[] = new ContentIndexRelation('forum_topic', 'node', (string) $topic->id());
      }
      $parent = $entity->get('parent')->entity;
      if ($parent instanceof ForumPost) {
        $relations[] = new ContentIndexRelation('forum_parent_post', 'aculta_forum_post', (string) $parent->id());
      }
      return $this->projection($entity, (string) $entity->get('subject')->value, $entity->isPublished(), $this->domainId($topic), $relations, (int) ($entity->get('first_published')->value ?? 0));
    }

    if ($entity instanceof CommentInterface && $entity->bundle() === 'aculta_discussion_post') {
      if ($entity->hasField('field_aculta_rejected') && !$entity->get('field_aculta_rejected')->isEmpty() && (bool) $entity->get('field_aculta_rejected')->value) {
        return NULL;
      }
      $topic = $entity->getCommentedEntity();
      if (!$topic instanceof NodeInterface || $topic->bundle() !== 'aculta_forum_topic') {
        return NULL;
      }
      $relations = [new ContentIndexRelation('forum_topic', 'node', (string) $topic->id())];
      $parent = $entity->getParentComment();
      if ($parent instanceof CommentInterface && $parent->bundle() === 'aculta_discussion_post') {
        $relations[] = new ContentIndexRelation('forum_comment_parent', 'comment', (string) $parent->id());
      }
      return $this->projection($entity, (string) $entity->getSubject(), $entity->isPublished(), $this->domainId($topic), $relations, $this->publishedAt($entity));
    }

    return NULL;
  }

  /**
   * Builds the common minimum projection for a translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Source entity translation.
   * @param string $label
   *   Allowlisted entity label.
   * @param bool $published
   *   Current publication state.
   * @param string|null $domainId
   *   Domain ID of the owning Forum topic.
   * @param list<\Drupal\aculta_portal\ContentIndex\ContentIndexRelation> $relations
   *   Explicit outgoing Forum relations.
   * @param int|null $publishedAt
   *   First publication timestamp, when available.
   */
  private function projection(
    ContentEntityInterface $entity,
    string $label,
    bool $published,
    ?string $domainId,
    array $relations = [],
    ?int $publishedAt = NULL,
  ): ContentIndexProjection {
    $created = $entity instanceof CommentInterface
      ? (int) $entity->getCreatedTime()
      : (int) ($entity->get('created')->value ?? 0);
    $changed = (int) ($entity->get('changed')->value ?? $created);
    return new ContentIndexProjection(
      $entity->getEntityTypeId(),
      (string) $entity->id(),
      $entity->language()->getId(),
      $entity->bundle(),
      (string) $entity->uuid(),
      $label,
      $domainId,
      $published,
      max(0, $created),
      max(0, $changed),
      $publishedAt !== NULL && $publishedAt > 0 ? $publishedAt : NULL,
      $relations,
    );
  }

  /**
   * Reads the stable source domain ID from a Forum topic.
   */
  private function domainId(?NodeInterface $topic): ?string {
    return $topic instanceof NodeInterface
      && $topic->hasField('field_domain_source')
      && !$topic->get('field_domain_source')->isEmpty()
      ? (string) $topic->get('field_domain_source')->target_id
      : NULL;
  }

  /**
   * Reads the existing publication marker without deriving one from edits.
   */
  private function publishedAt(CommentInterface $comment): ?int {
    if ($comment->hasField('field_aculta_published_at') && !$comment->get('field_aculta_published_at')->isEmpty()) {
      return max(0, (int) $comment->get('field_aculta_published_at')->value);
    }
    return NULL;
  }

}
