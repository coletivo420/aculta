<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\DiscussionReactionEntityViewHooks;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Proves which comment hosts are consumers of the discussion engine.
 */
#[CoversClass(DiscussionReactionEntityViewHooks::class)]
#[Group('aculta_forum')]
final class ConsumerCommentHostTest extends UnitTestCase {

  /**
   * A Forum topic is not a consumer; its replies keep the Forum card.
   */
  public function testForumTopicIsNotConsumer(): void {
    self::assertFalse(DiscussionReactionEntityViewHooks::isConsumerHost($this->node('aculta_forum_topic')));
  }

  /**
   * LMS, Wiki, Revista and any other host are consumers and use the compact reply.
   */
  public function testOtherHostsAreConsumers(): void {
    self::assertTrue(DiscussionReactionEntityViewHooks::isConsumerHost($this->node('wiki_entry')));
    self::assertTrue(DiscussionReactionEntityViewHooks::isConsumerHost($this->node('revista')));
    self::assertTrue(DiscussionReactionEntityViewHooks::isConsumerHost($this->node('lms_course')));
  }

  /**
   * A host that is not a node (or missing) is treated as a consumer.
   */
  public function testMissingOrNonNodeHostCountsAsConsumer(): void {
    self::assertTrue(DiscussionReactionEntityViewHooks::isConsumerHost(NULL));
    self::assertTrue(DiscussionReactionEntityViewHooks::isConsumerHost($this->createMock(EntityInterface::class)));
  }

  /**
   * Compact design alternates by display order: reply, mirrored, reply, mirrored.
   */
  public function testCompactDesignAlternatesReplyAndMirrored(): void {
    self::assertSame('start', DiscussionReactionEntityViewHooks::alternatingSide(0));
    self::assertSame('end', DiscussionReactionEntityViewHooks::alternatingSide(1));
    self::assertSame('start', DiscussionReactionEntityViewHooks::alternatingSide(2));
    self::assertSame('end', DiscussionReactionEntityViewHooks::alternatingSide(3));
  }

  /**
   * Builds a node double with the given bundle.
   */
  private function node(string $bundle): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    return $node;
  }

}
