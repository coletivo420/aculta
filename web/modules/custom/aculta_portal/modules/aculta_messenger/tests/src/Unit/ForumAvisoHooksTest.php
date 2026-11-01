<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\ForumNoticeEventProducerInterface;
use Drupal\aculta_messenger\Hook\ForumAvisoHooks;
use Drupal\comment\CommentInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Forum updates generate notices only when a reply is approved.
 */
#[Group('aculta_messenger')]
final class ForumAvisoHooksTest extends UnitTestCase {

  /**
   * Updating an already published Comment during a move is not a new reply.
   */
  public function testPublishedCommentUpdateDoesNotRegisterAnotherNotice(): void {
    $original = $this->createMock(CommentInterface::class);
    $original->expects(self::once())->method('isPublished')->willReturn(TRUE);

    $comment = $this->createMock(CommentInterface::class);
    $comment->expects(self::once())->method('isPublished')->willReturn(TRUE);
    $comment->expects(self::once())->method('getOriginal')->willReturn($original);
    $comment->expects(self::never())->method('getCommentedEntity');

    $producer = $this->createMock(ForumNoticeEventProducerInterface::class);
    $producer->expects(self::never())->method('publishComment');
    $hooks = new ForumAvisoHooks($producer);

    $hooks->commentUpdate($comment);
  }

  /**
   * Publishing a previously pending Comment still reaches notice registration.
   */
  public function testApprovalOfPendingCommentStillRegistersThePublishedReply(): void {
    $original = $this->createMock(CommentInterface::class);
    $original->expects(self::once())->method('isPublished')->willReturn(FALSE);

    $comment = $this->createMock(CommentInterface::class);
    $comment->expects(self::once())->method('isPublished')->willReturn(TRUE);
    $comment->expects(self::once())->method('getOriginal')->willReturn($original);
    $producer = $this->createMock(ForumNoticeEventProducerInterface::class);
    $producer->expects(self::once())->method('publishComment')->with($comment);
    $hooks = new ForumAvisoHooks($producer);

    $hooks->commentUpdate($comment);
  }

}
