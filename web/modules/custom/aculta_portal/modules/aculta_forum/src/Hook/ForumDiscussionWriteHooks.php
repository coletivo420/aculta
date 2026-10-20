<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Service\ForumDiscussionWriteGuard;
use Drupal\comment\CommentInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Coordinates normal public Forum writes with comment-tree moves. */
final class ForumDiscussionWriteHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum.discussion_write_guard')]
    private readonly ForumDiscussionWriteGuard $guard,
  ) {}

  /**
 * Acquires the host lock before a Comment write. */
  #[Hook('comment_presave')]
  public function beforeSave(CommentInterface $comment): void {
    $this->guard->beforeCommentSave($comment);
  }

  /**
 * Acquires the same host lock before Core deletes a Comment subtree. */
  #[Hook('comment_predelete')]
  public function beforeDelete(CommentInterface $comment): void {
    $this->guard->beforeCommentDelete($comment);
  }

  /**
 * Keeps the lock until the root Comment transaction ends. */
  #[Hook('comment_insert')]
  #[Hook('comment_update')]
  #[Hook('comment_delete')]
  public function afterSave(CommentInterface $comment): void {
    $this->guard->afterCommentSave($comment);
  }

}
