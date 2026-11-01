<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;

/**
 * Enforces the Forum's notice-only subscriber policy.
 */
final class MessageSubscribeHooks {

  /**
   * Prevents Message Subscribe delivery for Forum topics and their Comments.
   *
   * The contrib subscriber hook combines recipients from all implementations;
   * returning an empty array from that hook does not suppress its Flag
   * recipients. The alter hook is required to enforce the Forum policy.
   *
   * @param array $uids
   *   Message Subscribe delivery candidates, keyed by recipient UID.
   * @param array $values
   *   Context from Message Subscribe, including the target entity.
   */
  #[Hook('message_subscribe_get_subscribers_alter')]
  public function suppressForumDelivery(array &$uids, array $values): void {
    $entity = $values['entity'] ?? NULL;
    $is_forum_topic = $entity instanceof NodeInterface && $entity->bundle() === 'aculta_forum_topic';
    if (!$is_forum_topic && $entity instanceof CommentInterface) {
      $host = $entity->getCommentedEntity();
      $is_forum_topic = $host instanceof NodeInterface && $host->bundle() === 'aculta_forum_topic';
    }
    if ($is_forum_topic) {
      $uids = [];
    }
  }

}
