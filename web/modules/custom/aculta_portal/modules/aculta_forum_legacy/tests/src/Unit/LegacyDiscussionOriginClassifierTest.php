<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_legacy\Unit;

use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Origin\CoreDiscussionOriginClassifier;
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
   * A Growroom Comment is archive only when its Forum topic is marked.
   *
   * @covers ::classify
   * @dataProvider archiveMarkerProvider
   */
  public function testMarkedCommentIsArchive(bool $commentMarked): void {
    $host = $this->markedTopic(TRUE);
    $comment = $this->markedComment($commentMarked, $host);

    $result = (new LegacyDiscussionOriginClassifier())->classify($comment);

    self::assertNotNull($result);
    self::assertSame(DiscussionOrigin::ARCHIVE, $result->origin);
  }

  /**
   * A Comment marker on a current Forum topic does not override CURRENT.
   *
   * @covers ::classify
   */
  public function testCommentMarkerOnCurrentTopicDoesNotPromoteItToArchive(): void {
    $host = $this->createMock(NodeInterface::class);
    $host->method('bundle')->willReturn('aculta_forum_topic');
    $host->method('getOwnerId')->willReturn(42);
    $host->method('hasField')->with('field_autor_legado')->willReturn(FALSE);

    $comment = $this->markedComment(TRUE, $host);
    $comment->method('getOwnerId')->willReturn(43);

    $legacy = (new LegacyDiscussionOriginClassifier())->classify($comment);
    $current = (new CoreDiscussionOriginClassifier(new DiscussionContextResolver([])))
      ->classify($comment);

    self::assertNull($legacy);
    self::assertNotNull($current);
    self::assertSame(DiscussionOrigin::CURRENT, $current->origin);
  }

  /**
   * Revisionable ForumPosts are never historical Comment archive records.
   *
   * @covers ::classify
   */
  public function testForumPostIsNotClassifiedAsArchive(): void {
    $post = $this->createMock(FieldableEntityInterface::class);
    $post->method('getEntityTypeId')->willReturn('aculta_forum_post');

    $result = (new LegacyDiscussionOriginClassifier())->classify($post);

    self::assertNull($result);
  }

  /**
   * Provides Comment marker states for a marked historical host.
   *
   * @return iterable<string, array{bool}>
   *   Comment marker state.
   */
  public static function archiveMarkerProvider(): iterable {
    yield 'comment marker' => [TRUE];
    yield 'comment without marker' => [FALSE];
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
  private function markedComment(bool $marked, ?NodeInterface $host = NULL): CommentInterface&MockObject {
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
