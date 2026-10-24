<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;

/**
 * Produces shared Messenger events from published Forum activity.
 */
interface ForumNoticeEventProducerInterface {

  /**
   * Publishes events for currently eligible followers of a Comment.
   */
  public function publishComment(CommentInterface $comment): int;

  /**
   * Publishes events for currently eligible followers of a ForumPost.
   */
  public function publishPost(ForumPost $post): int;

  /**
   * Publishes a revision event for currently eligible topic followers.
   */
  public function publishTopicUpdate(NodeInterface $topic, int $revisionId): int;

}
