<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\comment\CommentInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Marks replies edited by a moderator: "Editado pela moderação".
 *
 * The mark is stored per comment when a user who is not the author saves the edit, and is read
 * when the reply is rendered. Authors editing their own reply are not marked.
 */
final class DiscussionModerationEditHooks {

  /**
   * Key-value collection holding the moderator-edit marks, keyed by comment id.
   */
  public const COLLECTION = 'aculta_forum_comment_moderation_edits';

  /**
   * Key-value collection with the administrative log: one entry per moderator edit (uid and time).
   */
  public const LOG_COLLECTION = 'aculta_forum_comment_moderation_edit_log';

  public function __construct(
    private readonly KeyValueFactoryInterface $keyValue,
    private readonly AccountProxyInterface $currentUser,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Marks a reply edited by a moderator when it is saved by someone other than its author.
   */
  #[Hook('comment_update')]
  public function commentUpdate(CommentInterface $comment): void {
    if ($comment->bundle() !== 'aculta_discussion_post') {
      return;
    }
    if ((int) $comment->getOwnerId() === (int) $this->currentUser->id()) {
      return;
    }
    $cid = (string) $comment->id();
    // A marca é permanente: nunca é removida; o log guarda cada edição de moderador.
    $this->keyValue->get(self::COLLECTION)->set($cid, TRUE);
    $log = $this->editLog((int) $comment->id());
    $log[] = ['uid' => (int) $this->currentUser->id(), 'time' => $this->time->getRequestTime()];
    $this->keyValue->get(self::LOG_COLLECTION)->set($cid, $log);
  }

  /**
   * Administrative log of moderator edits of a reply (uid and timestamp). Not for public display.
   *
   * @return list<array{uid: int, time: int}>
   *   Entries in edit order.
   */
  public function editLog(int $commentId): array {
    $value = $this->keyValue->get(self::LOG_COLLECTION)->get((string) $commentId, []);
    $entries = [];
    if (is_array($value)) {
      foreach ($value as $entry) {
        if (is_array($entry) && isset($entry['uid'], $entry['time']) && is_int($entry['uid']) && is_int($entry['time'])) {
          $entries[] = ['uid' => $entry['uid'], 'time' => $entry['time']];
        }
      }
    }
    return $entries;
  }

  /**
   * Exposes the "edited by moderation" mark to the reply template.
   *
   * @param array<string, mixed> $variables
   *   Comment template variables.
   */
  #[Hook('preprocess_comment')]
  public function preprocessComment(array &$variables): void {
    $comment = $variables['comment'] ?? NULL;
    $variables['edited_by_moderation'] = $comment instanceof CommentInterface
      && $comment->bundle() === 'aculta_discussion_post'
      && $this->isEditedByModeration((int) $comment->id());
  }

  /**
   * Whether a reply carries the "edited by moderation" mark.
   */
  public function isEditedByModeration(int $commentId): bool {
    return (bool) $this->keyValue->get(self::COLLECTION)->get((string) $commentId, FALSE);
  }

}
