<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\ForumNoticeEventProducer;
use Drupal\aculta_messenger\Aviso\ForumNoticeRegistrarInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\EventSubscriber\ForumNoticeEventSubscriber;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests revalidation of shared Forum notice events before persistence.
 */
#[Group('aculta_messenger')]
final class ForumNoticeEventSubscriberTest extends UnitTestCase {

  /**
   * A canonical Comment event resolves by UUID and targets one recipient.
   */
  public function testCommentEventIsResolvedAndRegisteredForItsRecipient(): void {
    $comment = $this->createMock(CommentInterface::class);
    $comment_storage = $this->createMock(EntityStorageInterface::class);
    $comment_storage->expects(self::once())
      ->method('loadByProperties')
      ->with(['uuid' => 'comment-uuid'])
      ->willReturn([$comment]);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::once())
      ->method('getStorage')
      ->with('comment')
      ->willReturn($comment_storage);
    $notices = $this->createMock(ForumNoticeRegistrarInterface::class);
    $notices->expects(self::once())
      ->method('registrarResposta')
      ->with($comment, 12);

    $event = new MessengerNoticeEvent(
      ForumNoticeEventProducer::COMMENT_PUBLISHED,
      'aculta_forum',
      'comment',
      'comment-uuid',
      NULL,
      12,
      'forum',
      new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
      'forum:forum.comment_published:comment-uuid:12',
    );
    (new ForumNoticeEventSubscriber($entity_type_manager, $notices))->consume($event);
  }

  /**
   * An event with a forged/noncanonical key is ignored before entity lookup.
   */
  public function testNoncanonicalIdempotencyKeyIsIgnored(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::never())->method('getStorage');
    $notices = $this->createMock(ForumNoticeRegistrarInterface::class);
    $notices->expects(self::never())->method('registrarResposta');

    $event = new MessengerNoticeEvent(
      ForumNoticeEventProducer::COMMENT_PUBLISHED,
      'aculta_forum',
      'comment',
      'comment-uuid',
      NULL,
      12,
      'forum',
      new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
      'other-event',
    );
    (new ForumNoticeEventSubscriber($entity_type_manager, $notices))->consume($event);
  }

  /**
   * A topic update resolves the exact source revision before registration.
   */
  public function testTopicUpdateUsesTheDeclaredRevision(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('uuid')->willReturn('topic-uuid');
    $topic_storage = $this->createMock(RevisionableStorageInterface::class);
    $topic_storage->expects(self::once())->method('loadRevision')->with(73)->willReturn($topic);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::once())->method('getStorage')
      ->with('node')->willReturn($topic_storage);
    $notices = $this->createMock(ForumNoticeRegistrarInterface::class);
    $notices->expects(self::once())->method('notificarAtualizacao')->with($topic, 73, 12);

    $event = new MessengerNoticeEvent(
      ForumNoticeEventProducer::TOPIC_UPDATED,
      'aculta_forum',
      'node',
      'topic-uuid',
      '73',
      12,
      'forum',
      new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
      'forum:forum.topic_updated:topic-uuid:73:12',
    );
    (new ForumNoticeEventSubscriber($entity_type_manager, $notices))->consume($event);
  }

}
