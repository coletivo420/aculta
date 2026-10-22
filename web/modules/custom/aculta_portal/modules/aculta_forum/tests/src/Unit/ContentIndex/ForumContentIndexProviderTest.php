<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\ContentIndex;

use Drupal\aculta_forum\ContentIndex\ForumContentIndexProvider;
use Drupal\aculta_portal\ContentIndex\ContentIndexAccessContextProviderInterface;
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

}
