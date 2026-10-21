<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\user\UserInterface;

/**
 * Access contract for public Forum notice creation and transfer.
 */
interface ForumNoticeAccessPolicyInterface {

  /**
   * Whether the entity is a current public Forum topic.
   */
  public function supportsTopic(EntityInterface $entity): bool;

  /**
   * Whether the recipient can currently view a topic.
   */
  public function canViewTopic(?EntityInterface $entity, int|UserInterface $recipient): bool;

  /**
   * Whether the recipient can currently view a published comment.
   */
  public function canViewComment(CommentInterface $comment, int|UserInterface $recipient): bool;

}
