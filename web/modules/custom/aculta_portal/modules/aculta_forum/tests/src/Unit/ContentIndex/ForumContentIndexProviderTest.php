<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\ContentIndex;

use Drupal\aculta_forum\ContentIndex\ForumContentIndexProvider;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_portal\ContentIndex\ContentIndexAccessContextProviderInterface;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\aculta_forum\ContentIndex\ForumContentIndexProvider
 * @group aculta_forum
 */
final class ForumContentIndexProviderTest extends UnitTestCase {

  /**
   * Tests coexistence sources and canonical access purpose.
   *
   * @covers ::getProviderId
   * @covers ::getSupportedEntityTypes
   * @covers ::getAccessPurpose
   */
  public function testDeclaresBothDiscussionSystemsAndForumPurpose(): void {
    $provider = new ForumContentIndexProvider();
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $otherNode = $this->createMock(NodeInterface::class);
    $otherNode->method('bundle')->willReturn('article');

    self::assertInstanceOf(ContentIndexAccessContextProviderInterface::class, $provider);
    self::assertSame('forum', $provider->getProviderId());
    self::assertSame([
      'node' => ['aculta_forum_topic'],
      'aculta_forum_post' => ['aculta_forum_post'],
      'comment' => ['aculta_discussion_post'],
    ], $provider->getSupportedEntityTypes());
    self::assertSame('forum', $provider->getAccessPurpose($topic));
    self::assertNull($provider->getAccessPurpose($otherNode));
  }

  /**
   * Tests both legacy and revisionable replies use the canonical Forum purpose.
   *
   * @covers ::getAccessPurpose
   */
  public function testRepliesUseTheCanonicalForumPurpose(): void {
    $provider = new ForumContentIndexProvider();
    $forumPost = (new \ReflectionClass(ForumPost::class))->newInstanceWithoutConstructor();
    $legacyComment = $this->createMock(CommentInterface::class);
    $legacyComment->method('bundle')->willReturn('aculta_discussion_post');
    $otherComment = $this->createMock(CommentInterface::class);
    $otherComment->method('bundle')->willReturn('comment');

    self::assertSame('forum', $provider->getAccessPurpose($forumPost));
    self::assertSame('forum', $provider->getAccessPurpose($legacyComment));
    self::assertNull($provider->getAccessPurpose($otherComment));
  }

}
