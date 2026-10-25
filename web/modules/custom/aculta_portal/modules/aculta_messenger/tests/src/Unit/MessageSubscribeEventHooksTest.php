<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\aculta_messenger\Hook\MessageSubscribeEventHooks;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which public topic events enter the Messenger notice queue.
 */
#[Group('aculta_messenger')]
final class MessageSubscribeEventHooksTest extends UnitTestCase {

  /**
   * A new published ACULTA topic queues its published revision.
   */
  public function testInsertQueuesPublishedTopicRevision(): void {
    $node = $this->topic(23, 8, TRUE);
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->once())
      ->method('createItem')
      ->with(['node_id' => 23, 'revision_id' => 8])
      ->willReturn('item-id');
    $factory = $this->createMock(QueueFactory::class);
    $factory->method('get')->with('aculta_messenger_message_subscribe_event')->willReturn($queue);

    $this->hooks($factory)->entityInsert($node);
  }

  /**
   * A newly published revision of an existing topic queues a notice event.
   */
  public function testUpdateQueuesNewPublishedRevision(): void {
    $original = $this->topic(23, 7, TRUE);
    $node = $this->topic(23, 8, TRUE);
    $node->method('getOriginal')->willReturn($original);
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->once())
      ->method('createItem')
      ->with(['node_id' => 23, 'revision_id' => 8])
      ->willReturn('item-id');
    $factory = $this->createMock(QueueFactory::class);
    $factory->method('get')->willReturn($queue);

    $this->hooks($factory)->entityUpdate($node);
  }

  /**
   * The old contributed Forum bundle is not queued.
   */
  public function testContributedForumBundleIsIgnored(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('forum');
    $node->method('isPublished')->willReturn(TRUE);
    $factory = $this->createMock(QueueFactory::class);
    $factory->expects($this->never())->method('get');

    $this->hooks($factory)->entityInsert($node);
  }

  /**
   * Creates the hook service with a mocked queue.
   */
  private function hooks(QueueFactory $queueFactory): MessageSubscribeEventHooks {
    return new MessageSubscribeEventHooks(
      $queueFactory,
      $this->createMock(LoggerChannelFactoryInterface::class),
    );
  }

  /**
   * Creates a public Forum topic entity double.
   */
  private function topic(int $id, int $revisionId, bool $published): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('aculta_forum_topic');
    $node->method('isPublished')->willReturn($published);
    $node->method('id')->willReturn($id);
    $node->method('getRevisionId')->willReturn($revisionId);
    $node->method('hasField')->willReturn(FALSE);
    return $node;
  }

}
