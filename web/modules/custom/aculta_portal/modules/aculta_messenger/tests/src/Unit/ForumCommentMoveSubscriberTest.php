<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_forum\Event\ForumCommentSubtreeMovedEvent;
use Drupal\aculta_messenger\Aviso\ForumNoticeTransferInterface;
use Drupal\aculta_messenger\EventSubscriber\ForumCommentMoveSubscriber;
use Drupal\Tests\UnitTestCase;

/**
 * Tests moving the notification aggregate with a Forum comment subtree. */
final class ForumCommentMoveSubscriberTest extends UnitTestCase {

  /**
   * Published subtrees trigger aggregate transfer. */
  public function testPublishedSubtreeTransfersNoticeRecords(): void {
    $notices = $this->createMock(ForumNoticeTransferInterface::class);
    $notices->expects(self::once())
      ->method('transferTopicResponseNotices')
      ->with(10, 20)
      ->willReturn(2);
    $subscriber = new ForumCommentMoveSubscriber($notices);

    $subscriber->transferNotices(new ForumCommentSubtreeMovedEvent(10, 20, TRUE));
  }

  /**
   * Pending-only subtrees leave existing notices at their original topic. */
  public function testPendingOnlySubtreeDoesNotTransferNotices(): void {
    $notices = $this->createMock(ForumNoticeTransferInterface::class);
    $notices->expects(self::never())->method('transferTopicResponseNotices');
    $subscriber = new ForumCommentMoveSubscriber($notices);

    $subscriber->transferNotices(new ForumCommentSubtreeMovedEvent(10, 20, FALSE));
  }

}
