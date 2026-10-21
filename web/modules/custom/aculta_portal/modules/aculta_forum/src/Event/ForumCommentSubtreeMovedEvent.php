<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event emitted after a published comment subtree changes Forum topic.
 */
final class ForumCommentSubtreeMovedEvent extends Event {

  public const NAME = 'aculta_forum.comment_subtree_moved';

  public function __construct(
    public readonly int $sourceTopicId,
    public readonly int $destinationTopicId,
    public readonly bool $containsPublishedComment,
  ) {}

}
