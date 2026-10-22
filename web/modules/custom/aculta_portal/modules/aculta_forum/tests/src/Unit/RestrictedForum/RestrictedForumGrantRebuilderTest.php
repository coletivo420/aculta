<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\RestrictedForum;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumGrantRebuilder;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeAccessControlHandlerInterface;
use Drupal\node\NodeGrantDatabaseStorageInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests targeted node grant rewriting.
 */
#[CoversClass(RestrictedForumGrantRebuilder::class)]
final class RestrictedForumGrantRebuilderTest extends TestCase {

  /**
   * Topic grants are rewritten and caches invalidated.
   */
  public function testTopicGrantsAreRewrittenAndCachesInvalidated(): void {
    $grants = [[
      'realm' => 'aculta_forum_restricted',
      'gid' => 3,
      'grant_view' => 1,
      'grant_update' => 0,
      'grant_delete' => 0,
    ],
    ];
    $handler = $this->createMock(NodeAccessControlHandlerInterface::class);
    $handler->method('acquireGrants')->willReturn($grants);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getAccessControlHandler')->with('node')->willReturn($handler);
    $storage = $this->createMock(NodeGrantDatabaseStorageInterface::class);
    $storage->expects(self::once())->method('write')->with(self::isInstanceOf(NodeInterface::class), $grants);
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects(self::once())->method('invalidateTags')->with(['node:11', 'node_list']);
    $rebuilder = new RestrictedForumGrantRebuilder($manager, $storage, $invalidator, $this->createMock(LoggerInterface::class));
    self::assertTrue($rebuilder->rebuildForTopic($this->topic()));
  }

  /**
   * Non topic nodes are ignored.
   */
  public function testNonTopicNodesAreIgnored(): void {
    $storage = $this->createMock(NodeGrantDatabaseStorageInterface::class);
    $storage->expects(self::never())->method('write');
    $rebuilder = new RestrictedForumGrantRebuilder(
      $this->createMock(EntityTypeManagerInterface::class),
      $storage,
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(LoggerInterface::class),
    );
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('page');
    $node->method('id')->willReturn(4);
    self::assertFalse($rebuilder->rebuildForTopic($node));
  }

  /**
   * Storage failure is logged and does not throw.
   */
  public function testStorageFailureIsLoggedAndDoesNotThrow(): void {
    $handler = $this->createMock(NodeAccessControlHandlerInterface::class);
    $handler->method('acquireGrants')->willReturn([]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getAccessControlHandler')->willReturn($handler);
    $storage = $this->createMock(NodeGrantDatabaseStorageInterface::class);
    $storage->method('write')->willThrowException(new \RuntimeException('boom'));
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())->method('error');
    $rebuilder = new RestrictedForumGrantRebuilder($manager, $storage, $this->createMock(CacheTagsInvalidatorInterface::class), $logger);
    self::assertFalse($rebuilder->rebuildForTopic($this->topic()));
  }

  /**
   * Category rebuilds every topic of the term.
   */
  public function testCategoryRebuildsEveryTopicOfTheTerm(): void {
    $handler = $this->createMock(NodeAccessControlHandlerInterface::class);
    $handler->method('acquireGrants')->willReturn([]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getAccessControlHandler')->willReturn($handler);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $query = $this->getMockBuilder(QueryInterface::class)->getMock();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([11 => 11, 12 => 12]);
    $nodeStorage->method('getQuery')->willReturn($query);
    $nodeStorage->method('loadMultiple')->willReturn([11 => $this->topic(11), 12 => $this->topic(12)]);
    $manager->method('getStorage')->with('node')->willReturn($nodeStorage);
    $storage = $this->createMock(NodeGrantDatabaseStorageInterface::class);
    $storage->expects(self::exactly(2))->method('write');
    $rebuilder = new RestrictedForumGrantRebuilder($manager, $storage, $this->createMock(CacheTagsInvalidatorInterface::class), $this->createMock(LoggerInterface::class));
    self::assertSame(2, $rebuilder->rebuildForCategory(4));
  }

  /**
   * Invalid term id does nothing.
   */
  public function testInvalidTermIdDoesNothing(): void {
    $rebuilder = new RestrictedForumGrantRebuilder(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(NodeGrantDatabaseStorageInterface::class),
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(LoggerInterface::class),
    );
    self::assertSame(0, $rebuilder->rebuildForCategory(0));
  }

  /**
   * Returns a forum topic fixture.
   */
  private function topic(int $nid = 11): NodeInterface {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('id')->willReturn($nid);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('getCacheContexts')->willReturn([]);
    $topic->method('getCacheTags')->willReturn([]);
    $topic->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    return $topic;
  }

}
