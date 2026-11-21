<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\aculta_forum\Service\ForumIdentityTokens;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Records in-app mention and citation notices for a published Forum comment.
 *
 * A mentioned account and a quoted author are notified once per comment; the
 * author of the comment never notifies themselves.
 */
class ForumMentionNoticeRecorder {

  /**
   * Creates the recorder.
   */
  public function __construct(
    private readonly AvisoService $avisos,
    private readonly ForumIdentityTokens $tokens,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Records the notices created by one comment. Returns how many were recorded.
   */
  public function recordForComment(CommentInterface $comment): int {
    if (!$comment->isPublished() || $comment->bundle() !== 'aculta_discussion_post' || $comment->isSyncing()) {
      return 0;
    }
    if (!$comment->hasField('comment_body') || $comment->get('comment_body')->isEmpty()) {
      return 0;
    }
    $item = $comment->get('comment_body')->first();
    $processed = $item !== NULL ? $item->get('processed')->getValue() : '';
    $html = is_scalar($processed) ? (string) $processed : '';
    $author = (int) $comment->getOwnerId();
    $notified = [];
    $recorded = 0;

    foreach ($this->tokens->mentionedUids($html) as $uid) {
      if ($uid <= 0 || $uid === $author || isset($notified[$uid]) || $this->uidIsInactive($uid)) {
        continue;
      }
      $notified[$uid] = TRUE;
      $recorded += $this->avisos->registrarMencao($comment, $uid);
    }

    foreach ($this->quotedCommentIds($html) as $quotedId) {
      $quoted = $this->entityTypeManager->getStorage('comment')->load($quotedId);
      if (!$quoted instanceof CommentInterface) {
        continue;
      }
      $uid = (int) $quoted->getOwnerId();
      if ($uid <= 0 || $uid === $author || isset($notified[$uid])) {
        continue;
      }
      $notified[$uid] = TRUE;
      $recorded += $this->avisos->registrarCitacao($comment, $uid);
    }

    return $recorded;
  }

  /**
   * Whether the referenced account is missing or blocked.
   */
  private function uidIsInactive(int $uid): bool {
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    return $user === NULL || !$user->isActive();
  }

  /**
   * Comment ids quoted by the processed HTML (data-aculta-quote-cid).
   *
   * @return list<int>
   *   Unique positive comment ids.
   */
  private function quotedCommentIds(string $html): array {
    if (preg_match_all('~<blockquote[^>]*data-aculta-quote-cid="(\d+)"~', $html, $matches) === FALSE) {
      return [];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $matches[1]), static fn(int $id): bool => $id > 0)));
    return $ids;
  }

}
