<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_legacy\Unit;

use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum_legacy\Origin\LegacyDiscussionOriginClassifier;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests archive host scoping for legacy markers.
 *
 * @group aculta_forum_legacy
 * @coversDefaultClass \Drupal\aculta_forum_legacy\Origin\LegacyDiscussionOriginClassifier
 */
final class LegacyDiscussionOriginClassifierTest extends UnitTestCase {

  /**
   * Marks a Growroom topic as archive.
   *
   * @covers ::classify
   */
  public function testMarkedTopicIsArchive(): void {
    $topic = $this->markedTopic();

    $result = (new LegacyDiscussionOriginClassifier())->classify($topic);

    self::assertNotNull($result);
    self::assertSame(DiscussionOrigin::ARCHIVE, $result->origin);
  }

  /**
   * The marker does not classify a topic on another host as archive.
   *
   * @covers ::classify
   */
  public function testMarkerOnDifferentHostIsIgnored(): void {
    $host = $this->createMock(NodeInterface::class);
    $host->method('bundle')->willReturn('wiki_entry');
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->expects(self::never())->method('hasField');
    $host->expects(self::never())->method('hasField');

    self::assertNull((new LegacyDiscussionOriginClassifier())->classify($comment));
  }

  /**
   * A Growroom Comment is archive when it or its host has legacy attribution.
   *
   * @covers ::classify
   * @dataProvider archiveMarkerProvider
   */
  public function testMarkedCommentIsArchive(bool $commentMarked, bool $topicMarked): void {
    $host = $this->markedTopic($topicMarked);
    $comment = $this->markedComment($commentMarked, $host);

    $result = (new LegacyDiscussionOriginClassifier())->classify($comment);

    self::assertNotNull($result);
    self::assertSame(DiscussionOrigin::ARCHIVE, $result->origin);
  }

  /**
   * A ForumPost linked to an explicitly marked Growroom topic is archive.
   *
   * @covers ::classify
   */
  public function testForumPostLinkedToMarkedTopicIsArchive(): void {
    $topic = $this->markedTopic();
    $reference = $this->createMock(FieldItemListInterface::class);
    $reference->method('__get')->with('entity')->willReturn($topic);
    $reference->method('isEmpty')->willReturn(FALSE);
    $post = $this->createMock(FieldableEntityInterface::class);
    $post->method('getEntityTypeId')->willReturn('aculta_forum_post');
    $post->method('hasField')->willReturnCallback(static fn (string $name): bool => $name === 'topic');
    $post->method('get')->with('topic')->willReturn($reference);

    $result = (new LegacyDiscussionOriginClassifier())->classify($post);

    self::assertNotNull($result);
    self::assertSame(DiscussionOrigin::ARCHIVE, $result->origin);
  }

  /**
   * Provides marker combinations accepted for imported topic threads.
   *
   * @return iterable<string, array{bool, bool}>
   *   Marker combinations.
   */
  public static function archiveMarkerProvider(): iterable {
    yield 'comment marker' => [TRUE, FALSE];
    yield 'topic marker' => [FALSE, TRUE];
  }

  /**
   * Creates a Growroom topic with optional archive attribution.
   */
  private function markedTopic(bool $marked = TRUE): NodeInterface {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $this->configureLegacyMarker($topic, $marked);
    return $topic;
  }

  /**
   * Creates a Forum Comment with optional archive attribution.
   */
  private function markedComment(bool $marked, ?NodeInterface $host = NULL): CommentInterface {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getFieldName')->willReturn('field_aculta_discussion');
    if ($host !== NULL) {
      $comment->method('getCommentedEntity')->willReturn($host);
    }
    $this->configureLegacyMarker($comment, $marked);
    return $comment;
  }

  /**
   * Configures a mock's archive attribution field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface&\PHPUnit\Framework\MockObject\MockObject $entity
   *   Mock entity to configure.
   * @param bool $marked
   *   Whether the attribution field is non-empty.
   */
  private function configureLegacyMarker(FieldableEntityInterface&MockObject $entity, bool $marked): void {
    $entity->method('hasField')->with('field_autor_legado')->willReturn($marked);
    if ($marked) {
      $field = $this->createMock(FieldItemListInterface::class);
      $field->method('isEmpty')->willReturn(FALSE);
      $entity->method('get')->with('field_autor_legado')->willReturn($field);
    }
  }

}
