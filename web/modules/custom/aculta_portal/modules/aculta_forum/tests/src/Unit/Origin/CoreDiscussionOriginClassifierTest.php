<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\Origin;

use Drupal\aculta_forum\Context\DiscussionContextProviderInterface;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Origin\CoreDiscussionOriginClassifier;
use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\EntityOwnerInterface;

/**
 * Tests origin classification that belongs to the reusable Forum engine.
 *
 * @group aculta_forum
 * @coversDefaultClass \Drupal\aculta_forum\Origin\CoreDiscussionOriginClassifier
 */
final class CoreDiscussionOriginClassifierTest extends UnitTestCase {

  /**
   * ForumPosts require a linked topic with proven current ownership.
   *
   * @covers ::classify
   */
  public function testForumPostWithoutTopicIsUnclaimed(): void {
    $classifier = new CoreDiscussionOriginClassifier(new DiscussionContextResolver([]));

    $post = $this->createMock(FieldableEntityInterface::class);
    $post->method('getEntityTypeId')->willReturn('aculta_forum_post');
    $post->method('hasField')->with('topic')->willReturn(FALSE);

    $result = $classifier->classify($post);

    self::assertNull($result);
  }

  /**
   * A ForumPost linked to a current topic inherits current origin.
   *
   * @covers ::classify
   */
  public function testForumPostWithCurrentTopicIsCurrent(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('getOwnerId')->willReturn('12');
    $post = $this->forumPost($topic);

    $result = (new CoreDiscussionOriginClassifier(new DiscussionContextResolver([])))->classify($post);

    self::assertNotNull($result);
    self::assertSame(DiscussionOrigin::CURRENT, $result->origin);
  }

  /**
   * A ForumPost without a current owner cannot establish current provenance.
   *
   * @covers ::classify
   */
  public function testForumPostWithoutCurrentOwnerIsUnclaimed(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('getOwnerId')->willReturn('12');
    $post = $this->forumPost($topic, '0');

    self::assertNull((new CoreDiscussionOriginClassifier(new DiscussionContextResolver([])))->classify($post));
  }

  /**
   * A topic without a current owner cannot establish current provenance.
   *
   * @covers ::classify
   */
  public function testForumPostWithOwnerlessTopicIsUnclaimed(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('getOwnerId')->willReturn('0');
    $post = $this->forumPost($topic);

    self::assertNull((new CoreDiscussionOriginClassifier(new DiscussionContextResolver([])))->classify($post));
  }

  /**
   * Current Growroom topic classification does not inspect legacy fields.
   *
   * @covers ::classify
   */
  public function testForumTopicIsCurrentWithoutReadingLegacyMarkers(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('getOwnerId')->willReturn('12');

    $result = (new CoreDiscussionOriginClassifier(new DiscussionContextResolver([])))->classify($topic);

    self::assertNotNull($result);
    self::assertSame(DiscussionOrigin::CURRENT, $result->origin);
  }

  /**
   * Comments are contextual only when a registered host provider supports them.
   *
   * @covers ::classify
   */
  public function testCommentWithRegisteredHostIsContextual(): void {
    $host = $this->createMock(EntityInterface::class);
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getFieldName')->willReturn('field_aculta_discussion');
    $comment->method('getCommentedEntity')->willReturn($host);
    $provider = $this->createMock(DiscussionContextProviderInterface::class);
    $provider->method('supports')->with($host)->willReturn(TRUE);

    $result = (new CoreDiscussionOriginClassifier(new DiscussionContextResolver([$provider])))->classify($comment);

    self::assertNotNull($result);
    self::assertSame(DiscussionOrigin::CONTEXTUAL, $result->origin);
  }

  /**
   * Registered Wiki, Magazine and LMS hosts claim contextual origin directly.
   *
   * @covers ::classify
   * @dataProvider contextualHostProvider
   */
  public function testRegisteredHostIsContextual(string $entityType): void {
    $host = $this->createMock(EntityInterface::class);
    $host->method('getEntityTypeId')->willReturn($entityType);
    $provider = $this->createMock(DiscussionContextProviderInterface::class);
    $provider->method('supports')->with($host)->willReturn(TRUE);

    $result = (new CoreDiscussionOriginClassifier(new DiscussionContextResolver([$provider])))->classify($host);

    self::assertNotNull($result);
    self::assertSame(DiscussionOrigin::CONTEXTUAL, $result->origin);
  }

  /**
   * Returns supported contextual host types for origin classification.
   *
   * @return iterable<string, array{string}>
   *   Host entity types registered by contextual providers.
   */
  public static function contextualHostProvider(): iterable {
    yield 'Wiki' => ['node'];
    yield 'Magazine' => ['node'];
    yield 'LMS' => ['group'];
  }

  /**
   * Unmarked Forum topic Comments are current by default.
   *
   * @covers ::classify
   */
  public function testUnmarkedForumTopicCommentIsCurrent(): void {
    $host = $this->createMock(NodeInterface::class);
    $host->method('bundle')->willReturn('aculta_forum_topic');
    $host->method('getOwnerId')->willReturn('12');
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getFieldName')->willReturn('field_aculta_discussion');
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->method('getOwnerId')->willReturn('12');
    $result = (new CoreDiscussionOriginClassifier(new DiscussionContextResolver([])))->classify($comment);

    self::assertNotNull($result);
    self::assertSame(DiscussionOrigin::CURRENT, $result->origin);
  }

  /**
   * An unsupported Comment bundle cannot be upgraded to a current origin.
   *
   * @covers ::classify
   */
  public function testUnsupportedCommentBundleIsUnknown(): void {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('comment_forum');
    $classifier = new CoreDiscussionOriginClassifier(new DiscussionContextResolver([]));

    $result = $classifier->classify($comment);

    self::assertNotNull($result);
    self::assertSame(DiscussionOrigin::UNKNOWN, $result->origin);
  }

  /**
   * Creates a fieldable ForumPost mock whose topic reference resolves.
   */
  private function forumPost(NodeInterface $topic, string $ownerId = '12'): FieldableEntityInterface {
    $reference = $this->createMock(FieldItemListInterface::class);
    $reference->method('__get')->with('entity')->willReturn($topic);
    $post = $this->createMockForIntersectionOfInterfaces([
      FieldableEntityInterface::class,
      EntityOwnerInterface::class,
    ]);
    $post->method('getEntityTypeId')->willReturn('aculta_forum_post');
    $post->method('hasField')->with('topic')->willReturn(TRUE);
    $post->method('get')->with('topic')->willReturn($reference);
    $post->method('getOwnerId')->willReturn($ownerId);
    $reference->method('isEmpty')->willReturn(FALSE);
    return $post;
  }

}
