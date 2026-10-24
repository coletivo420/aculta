<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Hook\MessageSubscribeHooks;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Forum-specific Message Subscribe suppression policy.
 */
#[CoversClass(MessageSubscribeHooks::class)]
#[Group('aculta_messenger')]
final class MessageSubscribeHooksTest extends UnitTestCase {

  /**
   * F-D21 removes Message Subscribe recipients for ACULTA Forum topics.
   */
  public function testForumTopicRecipientsAreSuppressed(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $uids = [17 => new \stdClass(), 23 => new \stdClass()];

    (new MessageSubscribeHooks())->suppressForumDelivery($uids, ['entity' => $topic]);

    self::assertSame([], $uids);
  }

  /**
   * F-D21 also suppresses Message Subscribe candidates for Forum Comments.
   */
  public function testCommentOnForumTopicRecipientsAreSuppressed(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getCommentedEntity')->willReturn($topic);
    $uids = [17 => new \stdClass()];

    (new MessageSubscribeHooks())->suppressForumDelivery($uids, ['entity' => $comment]);

    self::assertSame([], $uids);
  }

  /**
   * Message Subscribe candidates remain unchanged outside the Forum.
   */
  public function testOtherContentRetainsSubscribers(): void {
    $article = $this->createMock(NodeInterface::class);
    $article->method('bundle')->willReturn('article');
    $candidate = new \stdClass();
    $uids = [17 => $candidate];

    (new MessageSubscribeHooks())->suppressForumDelivery($uids, ['entity' => $article]);

    self::assertSame([17 => $candidate], $uids);
  }

  /**
   * Missing target context does not suppress unrelated recipients.
   */
  public function testMissingEntityDoesNotSuppressRecipients(): void {
    $candidate = new \stdClass();
    $uids = [17 => $candidate];

    (new MessageSubscribeHooks())->suppressForumDelivery($uids, []);

    self::assertSame([17 => $candidate], $uids);
  }

}
