<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\comment\CommentInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Revalidates public forum notice eligibility at creation and delivery time.
 */
final class ForumNoticeAccessPolicy {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * Returns whether an entity is a newly managed, non-historical public topic.
   */
  public function supportsTopic(EntityInterface $entity): bool {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'aculta_forum_topic'
      || ($entity instanceof SynchronizableInterface && $entity->isSyncing())) {
      return FALSE;
    }
    return !$entity->hasField('field_autor_legado') || $entity->get('field_autor_legado')->isEmpty();
  }

  /**
   * Checks current published access for a user in the Forum domain.
   */
  public function canViewTopic(?EntityInterface $entity, int|UserInterface $recipient): bool {
    if (!$entity instanceof NodeInterface || !$this->supportsTopic($entity) || !$entity->isPublished()) {
      return FALSE;
    }
    $account = $recipient instanceof UserInterface
      ? $recipient
      : $this->entityTypeManager->getStorage('user')->load($recipient);
    if (!$account instanceof UserInterface || !$account->isActive() || !$entity->hasField('field_domain_source')) {
      return FALSE;
    }
    $forumDomain = $this->domainPurposeManager->getDomain('forum');
    if ($forumDomain === NULL || $entity->get('field_domain_source')->isEmpty()
      || (string) $entity->get('field_domain_source')->target_id !== (string) $forumDomain->id()) {
      return FALSE;
    }

    return (bool) $this->domainPurposeManager->runInPurpose('forum', static function () use ($entity, $account): bool {
      return $entity->access('view', $account, TRUE)->isAllowed();
    });
  }

  /**
   * Checks both the public host and the currently published comment access.
   */
  public function canViewComment(CommentInterface $comment, int|UserInterface $recipient): bool {
    if (!$comment->isPublished() || $comment->bundle() !== 'aculta_discussion_post'
      || ($comment->hasField('field_autor_legado') && !$comment->get('field_autor_legado')->isEmpty())) {
      return FALSE;
    }
    $topic = $comment->getCommentedEntity();
    $account = $recipient instanceof UserInterface
      ? $recipient
      : $this->entityTypeManager->getStorage('user')->load($recipient);
    if (!$account instanceof UserInterface || !$topic instanceof NodeInterface || !$this->canViewTopic($topic, $account)) {
      return FALSE;
    }
    return (bool) $this->domainPurposeManager->runInPurpose('forum', static function () use ($comment, $account): bool {
      return $comment->access('view', $account, TRUE)->isAllowed();
    });
  }

}
