<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit\Moderation;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\comment\CommentInterface;
use Drupal\aculta_user\Moderation\ReportQueue;
use Drupal\flag\FlaggingInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that historic Growroom content is omitted from report moderation.
 */
#[Group('aculta_user')]
final class ReportQueueTest extends UnitTestCase {

  /**
   * Historical topics are filtered from the report queue.
   */
  public function testHistoricalTopicFlaggingIsNotQueued(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('hasField')->with('field_autor_legado')->willReturn(TRUE);
    $marker = $this->createMock(FieldItemListInterface::class);
    $marker->method('isEmpty')->willReturn(FALSE);
    $topic->method('get')->with('field_autor_legado')->willReturn($marker);

    self::assertSame([], $this->queueFor('node', '42', $topic)->items()['items']);
  }

  /**
   * Current Forum report flaggings remain queued.
   */
  public function testCurrentTopicFlaggingRemainsQueued(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('hasField')->with('field_autor_legado')->willReturn(FALSE);

    $result = $this->queueFor('node', '42', $topic)->items();
    self::assertCount(1, $result['items']);
    self::assertSame(['entity_type' => 'node', 'entity_id' => 42, 'total' => 1, 'ultima' => 123456], $result['items'][0]);
  }

  /**
   * Shared comments from other contexts cannot leak into the Forum queue.
   */
  public function testCommentFromAnotherContextIsNotQueued(): void {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('hasField')->with('field_autor_legado')->willReturn(FALSE);
    $comment->method('getCommentedEntity')->willReturn($this->createMock(NodeInterface::class));

    self::assertSame([], $this->queueFor('comment', '84', $comment)->items()['items']);
  }

  /**
   * Builds a queue with one flagged entity and its target.
   */
  private function queueFor(string $entityType, string $entityId, EntityInterface $topic): ReportQueue {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([1]);

    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('getFlaggableType')->willReturn($entityType);
    $flagging->method('getFlaggableId')->willReturn($entityId);
    $flagging->method('getCreatedTime')->willReturn(123456);

    $flaggingStorage = $this->createMock(EntityStorageInterface::class);
    $flaggingStorage->method('getQuery')->willReturn($query);
    $flaggingStorage->method('loadMultiple')->with([1])->willReturn([1 => $flagging]);

    $topicStorage = $this->createMock(EntityStorageInterface::class);
    $topicStorage->method('loadMultiple')->with([$entityId])->willReturn([(int) $entityId => $topic]);

    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnMap([
      ['flagging', $flaggingStorage],
      [$entityType, $topicStorage],
    ]);

    return new ReportQueue($manager);
  }

}
