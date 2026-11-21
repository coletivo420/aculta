<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Service;

use Drupal\aculta_cgu\Entity\CguReport;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
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
    if ($this->forumOrigin === NULL || !$this->isForumTarget($entity)) {
      return TRUE;
    }
    return $this->forumOrigin->classify($entity)->origin === DiscussionOrigin::CURRENT;
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
