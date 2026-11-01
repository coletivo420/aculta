<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\EventSubscriber;

use Drupal\aculta_forum\Event\ForumCommentSubtreeMovedEvent;
use Drupal\aculta_messenger\Aviso\ForumNoticeTransferInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Transfers aggregate response notices when a published Forum subtree moves.
 */
final class ForumCommentMoveSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly ForumNoticeTransferInterface $notices) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ForumCommentSubtreeMovedEvent::NAME => 'transferNotices'];
  }

  /**
   * Moves each complete source aggregate, merging any existing destination.
   */
  public function transferNotices(ForumCommentSubtreeMovedEvent $event): void {
    if ($event->containsPublishedComment) {
      $this->notices->transferTopicResponseNotices(
        $event->sourceTopicId,
        $event->destinationTopicId,
        $event->commentIds,
      );
    }
  }

}
