<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\ForumNoticeEventProducerInterface;
use Drupal\aculta_messenger\Plugin\QueueWorker\MessageSubscribeEventWorker;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests age-based retry behavior for queued Forum topic notices.
 */
#[CoversClass(MessageSubscribeEventWorker::class)]
#[Group('aculta_messenger')]
final class MessageSubscribeEventWorkerTest extends TestCase {

  /**
   * A fresh topic without an alias remains queued for Pathauto.
   */
  public function testFreshTopicWithoutAliasIsRequeued(): void {
    $now = 1_800_000_000;
    $node = $this->topic($now - 86400);
    $storage = $this->createMock(RevisionableStorageInterface::class);
    $storage->method('load')->with(10)->willReturn($node);
    $storage->method('loadRevision')->with(20)->willReturn($node);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())->method('getStorage')->with('node')->willReturn($storage);
    $aliasManager = $this->createMock(AliasManagerInterface::class);
    $aliasManager->expects(self::once())
      ->method('getAliasByPath')
      ->with('/node/10', 'pt-br')
      ->willReturn('/node/10');
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())->method('warning');
    $logger->expects(self::never())->method('error');
    $loggerFactory = $this->loggerFactory($logger);
    $producer = $this->createMock(ForumNoticeEventProducerInterface::class);
    $producer->expects(self::never())->method('publishTopicUpdate');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::never())->method('acquire');

    $worker = $this->worker(
      $entityTypeManager,
      $producer,
      $aliasManager,
      $this->time($now),
      $lock,
      $loggerFactory,
    );

    $this->expectException(RequeueException::class);
    $worker->processItem(['node_id' => 10, 'revision_id' => 20]);
  }

  /**
   * A topic without an alias is dropped only after the injected one-week limit.
   */
  public function testStaleTopicWithoutAliasIsDropped(): void {
    $now = 1_800_000_000;
    $node = $this->topic($now - 7 * 86400 - 1);
    $storage = $this->createMock(RevisionableStorageInterface::class);
    $storage->method('load')->with(10)->willReturn($node);
    $storage->method('loadRevision')->with(20)->willReturn($node);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())->method('getStorage')->with('node')->willReturn($storage);
    $aliasManager = $this->createMock(AliasManagerInterface::class);
    $aliasManager->expects(self::once())
      ->method('getAliasByPath')
      ->with('/node/10', 'pt-br')
      ->willReturn('/node/10');
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())->method('error');
    $logger->expects(self::never())->method('warning');
    $loggerFactory = $this->loggerFactory($logger);
    $producer = $this->createMock(ForumNoticeEventProducerInterface::class);
    $producer->expects(self::never())->method('publishTopicUpdate');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::never())->method('acquire');

    $worker = $this->worker(
      $entityTypeManager,
      $producer,
      $aliasManager,
      $this->time($now),
      $lock,
      $loggerFactory,
    );

    $worker->processItem(['node_id' => 10, 'revision_id' => 20]);
  }

  /**
   * Creates a worker with the supplied collaborators.
   */
  private function worker(
    EntityTypeManagerInterface $entityTypeManager,
    ForumNoticeEventProducerInterface $producer,
    AliasManagerInterface $aliasManager,
    TimeInterface $time,
    LockBackendInterface $lock,
    LoggerChannelFactoryInterface $loggerFactory,
  ): MessageSubscribeEventWorker {
    return new MessageSubscribeEventWorker(
      [],
      'aculta_messenger_message_subscribe_event',
      [],
      $entityTypeManager,
      $producer,
      $aliasManager,
      $time,
      $lock,
      $loggerFactory,
    );
  }

  /**
   * Creates a published Forum topic with the requested creation timestamp.
   */
  private function topic(int $created): NodeInterface {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('pt-br');
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(10);
    $node->method('bundle')->willReturn('aculta_forum_topic');
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('getCreatedTime')->willReturn($created);
    $node->method('language')->willReturn($language);
    return $node;
  }

  /**
   * Creates a Drupal clock fixed to the requested request time.
   */
  private function time(int $now): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($now);
    return $time;
  }

  /**
   * Creates a logger factory returning the supplied Messenger logger.
   */
  private function loggerFactory(LoggerInterface $logger): LoggerChannelFactoryInterface {
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->with('aculta_messenger')->willReturn($logger);
    return $factory;
  }

}
