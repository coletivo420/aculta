<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicyInterface;
use Drupal\aculta_messenger\Aviso\ForumNoticeEventProducer;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\comment\CommentInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Forum-to-Messenger event adapter.
 */
#[Group('aculta_messenger')]
final class ForumNoticeEventProducerTest extends UnitTestCase {

  /**
   * Published Comment events are minimal, per-recipient, and canonical.
   */
  public function testPublishedCommentEmitsOneCanonicalRecipientEvent(): void {
    $topic = $this->createMock(NodeInterface::class);
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('isPublished')->willReturn(TRUE);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getCommentedEntity')->willReturn($topic);
    $comment->method('getOwnerId')->willReturn(4);
    $comment->method('uuid')->willReturn('comment-uuid');
    $comment->method('getEntityTypeId')->willReturn('comment');
    $comment->method('getChangedTime')->willReturn(1_800_000_000);

    $flag = $this->createMock(FlagInterface::class);
    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('getOwnerId')->willReturn(8);
    $flags = $this->createMock(FlagServiceInterface::class);
    $flags->method('getFlagById')->with('seguir_topico')->willReturn($flag);
    $flags->method('getEntityFlaggings')->with($flag, $topic)->willReturn([$flagging]);

    $access = $this->createMock(ForumNoticeAccessPolicyInterface::class);
    $access->method('supportsTopic')->with($topic)->willReturn(TRUE);
    $access->method('canViewComment')->with($comment, 8)->willReturn(TRUE);

    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::once())
      ->method('publish')
      ->with(self::callback(static fn(MessengerNoticeEvent $event): bool =>
        $event->eventType() === ForumNoticeEventProducer::COMMENT_PUBLISHED
        && $event->sourceModule() === 'aculta_forum'
        && $event->sourceEntityType() === 'comment'
        && $event->sourceEntityUuid() === 'comment-uuid'
        && $event->sourceRevisionOrVersion() === NULL
        && $event->recipientUid() === 8
        && $event->purpose() === 'forum'
        && $event->occurredAt()->getTimestamp() === 1_800_000_000
        && $event->idempotencyKey() === 'forum:forum.comment_published:comment-uuid:8'
        && $event->metadata() === []
      ));

    $producer = new ForumNoticeEventProducer($flags, $access, $publisher);
    self::assertSame(1, $producer->publishComment($comment));
  }

  /**
   * The producer excludes the author and recipients without current access.
   */
  public function testCommentEventSkipsAuthorAndInaccessibleFollowers(): void {
    $topic = $this->createMock(NodeInterface::class);
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('isPublished')->willReturn(TRUE);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getCommentedEntity')->willReturn($topic);
    $comment->method('getOwnerId')->willReturn(4);
    $comment->method('uuid')->willReturn('comment-uuid');
    $comment->method('getEntityTypeId')->willReturn('comment');
    $comment->method('getChangedTime')->willReturn(1_800_000_000);

    $flag = $this->createMock(FlagInterface::class);
    $flaggings = [];
    foreach ([4, 8] as $uid) {
      $flagging = $this->createMock(FlaggingInterface::class);
      $flagging->method('getOwnerId')->willReturn($uid);
      $flaggings[] = $flagging;
    }
    $flags = $this->createMock(FlagServiceInterface::class);
    $flags->method('getFlagById')->willReturn($flag);
    $flags->method('getEntityFlaggings')->willReturn($flaggings);

    $access = $this->createMock(ForumNoticeAccessPolicyInterface::class);
    $access->method('supportsTopic')->willReturn(TRUE);
    $access->method('canViewComment')->with($comment, 8)->willReturn(FALSE);

    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::never())->method('publish');

    self::assertSame(0, (new ForumNoticeEventProducer($flags, $access, $publisher))
      ->publishComment($comment));
  }

  /**
   * Topic updates include the published revision in their idempotency key.
   */
  public function testTopicUpdateUsesRevisionIdentity(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('isPublished')->willReturn(TRUE);
    $topic->method('getRevisionId')->willReturn(73);
    $topic->method('getRevisionUserId')->willReturn(4);
    $topic->method('uuid')->willReturn('topic-uuid');
    $topic->method('getEntityTypeId')->willReturn('node');
    $topic->method('getChangedTime')->willReturn(1_800_000_100);

    $flag = $this->createMock(FlagInterface::class);
    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('getOwnerId')->willReturn(8);
    $flags = $this->createMock(FlagServiceInterface::class);
    $flags->method('getFlagById')->willReturn($flag);
    $flags->method('getEntityFlaggings')->willReturn([$flagging]);
    $access = $this->createMock(ForumNoticeAccessPolicyInterface::class);
    $access->method('supportsTopic')->with($topic)->willReturn(TRUE);
    $access->method('canViewTopic')->with($topic, 8)->willReturn(TRUE);

    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::once())
      ->method('publish')
      ->with(self::callback(static fn(MessengerNoticeEvent $event): bool =>
        $event->eventType() === ForumNoticeEventProducer::TOPIC_UPDATED
        && $event->sourceEntityType() === 'node'
        && $event->sourceRevisionOrVersion() === '73'
        && $event->recipientUid() === 8
        && $event->idempotencyKey() === 'forum:forum.topic_updated:topic-uuid:73:8'
      ));

    self::assertSame(1, (new ForumNoticeEventProducer($flags, $access, $publisher))
      ->publishTopicUpdate($topic, 73));
  }

}
