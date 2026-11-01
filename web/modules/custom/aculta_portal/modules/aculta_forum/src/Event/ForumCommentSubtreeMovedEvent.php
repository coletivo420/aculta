<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event emitted after a published comment subtree changes Forum topic.
 */
final class ForumCommentSubtreeMovedEvent extends Event {

  public const NAME = 'aculta_forum.comment_subtree_moved';

  /**
   * Constructs the event with the moved Comment IDs.
   *
   * @param int $sourceTopicId
   *   Source topic ID.
   * @param int $destinationTopicId
   *   Destination topic ID.
   * @param bool $containsPublishedComment
   *   Whether the subtree contains a published Comment.
   * @param int[] $commentIds
   *   Comment IDs moved with the subtree.
   */
  public function __construct(
    public readonly int $sourceTopicId,
    public readonly int $destinationTopicId,
    public readonly bool $containsPublishedComment,
    public readonly array $commentIds = [],
  ) {}

}
