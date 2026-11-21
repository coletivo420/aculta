<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Service;

use Drupal\aculta_cgu\Entity\CguReport;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\comment\CommentInterface;
use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;

/**
 * Loads only existing content entities as candidate report targets.
 *
 * ReportService still decides whether the viewer may actually report them.
 */
final class ReportTargetResolver {

  /**
   * Publicly reportable entity types (extend only with an audited CGU owner).
   */
  public const SUPPORTED_TYPES = ['node', 'comment', 'aculta_forum_post', 'aculta_event'];


  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly ?DiscussionOriginResolverInterface $forumOrigin = NULL,
  ) {}

  /**
   * Public CGU actions must not include archived Forum/Growroom records.
   *
   * The Forum's optional origin service is authoritative when installed; CGU
   * does not classify contextual Wiki, LMS or Magazine content as Forum posts.
   */
  public function allowsEntity(ContentEntityInterface $entity): bool {
    if ($entity instanceof CguReport
      || !in_array($entity->getEntityTypeId(), self::SUPPORTED_TYPES, TRUE)) {
      return FALSE;
    }
    if (!$this->isForumTarget($entity)) {
      return TRUE;
    }
    // An archive cannot be reported just because the optional classifier
    // is unavailable. Unknown origin fails closed on the new CGU route.
    return $this->forumOrigin !== NULL
      && $this->forumOrigin->classify($entity)->origin === DiscussionOrigin::CURRENT;
  }

  /**
   * Validates access to both the target and its host before exposing an action.
   *
   * Core Comment view grants do not stand in for an independent host check.
   * ForumPosts must also be readable through their parent topic.
   */
  public function canViewContext(ContentEntityInterface $entity, AccountInterface $account): bool {
    if (!$this->allowsEntity($entity)
      || ($entity instanceof EntityPublishedInterface && !$entity->isPublished())
      || !$entity->access('view', $account)) {
      return FALSE;
    }
    if ($entity instanceof CommentInterface) {
      $host = $entity->getCommentedEntity();
      return $host instanceof EntityInterface
        && (!$host instanceof EntityPublishedInterface || $host->isPublished())
        && $host->access('view', $account);
    }
    if ($entity->getEntityTypeId() === 'aculta_forum_post') {
      if (!$entity->hasField('topic') || $entity->get('topic')->isEmpty()) {
        return FALSE;
      }
      $topic = $entity->get('topic')->entity;
      return $topic instanceof NodeInterface && $topic->bundle() === 'aculta_forum_topic'
        && $topic->isPublished() && $topic->access('view', $account);
    }
    return TRUE;
  }

  private function isForumTarget(EntityInterface $entity): bool {
    if ($entity->getEntityTypeId() === 'aculta_forum_post') {
      return TRUE;
    }
    if ($entity instanceof NodeInterface) {
      return $entity->bundle() === 'aculta_forum_topic';
    }
    if ($entity instanceof CommentInterface) {
      $host = $entity->getCommentedEntity();
      return $host instanceof EntityInterface
        && ($host->getEntityTypeId() === 'aculta_forum_post'
          || ($host instanceof NodeInterface && $host->bundle() === 'aculta_forum_topic'));
    }
    return FALSE;
  }

  public function load(string $type, string|int $id): ?ContentEntityInterface {
    if (!preg_match('/^[a-z][a-z0-9_]*$/D', $type)
      || !ctype_digit((string) $id)
      || (int) $id <= 0
      || !in_array($type, self::SUPPORTED_TYPES, TRUE)
      || !$this->entities->hasDefinition($type)) {
      return NULL;
    }
    $entity = $this->entities->getStorage($type)->load((int) $id);
    return $entity instanceof ContentEntityInterface && $this->allowsEntity($entity)
      ? $entity : NULL;
  }

}
