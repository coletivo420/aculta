<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\RestrictedForum;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumGrantRebuilder;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeAccessControlHandlerInterface;
use Drupal\node\NodeGrantDatabaseStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ServerInterface;
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
   * A grant rewrite tracks the topic and its posts for Search API reindexing.
   */
  public function testGrantRewriteTracksSearchReindex(): void {
    $handler = $this->createMock(NodeAccessControlHandlerInterface::class);
    $handler->method('acquireGrants')->willReturn([]);
    $index = $this->createMock(IndexInterface::class);
    $index->method('status')->willReturn(TRUE);
    $tracked = [];
    $index->method('trackItemsInserted')->willReturnCallback(
      static function (string $datasource, array $ids) use (&$tracked): void {
        $tracked[] = [$datasource, $ids];
      },
    );
    $deleted = [];
    $server = $this->createMock(ServerInterface::class);
    $server->method('deleteItems')->willReturnCallback(
      static function (IndexInterface $index, array $ids) use (&$deleted): void {
        $deleted[] = $ids;
      },
    );
    $index->method('getServerInstance')->willReturn($server);
    $post55 = $this->createMock(EntityInterface::class);
    $post55->method('id')->willReturn(55);
    $post55->method('getTypedData')->willReturn($this->createMock(ComplexDataInterface::class));
    $post56 = $this->createMock(EntityInterface::class);
    $post56->method('id')->willReturn(56);
    $post56->method('getTypedData')->willReturn($this->createMock(ComplexDataInterface::class));
    $node_datasource = $this->createMock(DatasourceInterface::class);
    $node_datasource->method('getItemId')->willReturn('11:pt-br');
    $post_datasource = $this->createMock(DatasourceInterface::class);
    $post_datasource->method('getItemId')->willReturnOnConsecutiveCalls('55:pt-br', '56:pt-br');
    $index->method('getDatasource')->willReturnCallback(
      static fn (string $id): DatasourceInterface => $id === 'entity:node' ? $node_datasource : $post_datasource,
    );
    $index_storage = $this->createMock(EntityStorageInterface::class);
    $index_storage->method('load')->with('aculta_forum')->willReturn($index);
    $post_query = $this->getMockBuilder(QueryInterface::class)->getMock();
    $post_query->method('accessCheck')->willReturnSelf();
    $post_query->method('condition')->willReturnSelf();
    $post_query->method('execute')->willReturn([55, 56]);
    $post_storage = $this->createMock(EntityStorageInterface::class);
    $post_storage->method('getQuery')->willReturn($post_query);
    $post_storage->method('loadMultiple')->willReturn([55 => $post55, 56 => $post56]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getAccessControlHandler')->willReturn($handler);
    $manager->method('hasDefinition')->with('search_api_index')->willReturn(TRUE);
    $manager->method('getStorage')->willReturnCallback(
      static fn (string $entity_type): EntityStorageInterface => $entity_type === 'search_api_index' ? $index_storage : $post_storage,
    );
    $rebuilder = new RestrictedForumGrantRebuilder(
      $manager,
      $this->createMock(NodeGrantDatabaseStorageInterface::class),
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(LoggerInterface::class),
    );
    self::assertTrue($rebuilder->rebuildForTopic($this->topic()));
    self::assertSame([
      ['entity:node', ['11:pt-br']],
      ['entity:aculta_forum_post', ['55:pt-br', '56:pt-br']],
    ], $tracked);
    self::assertSame([
      ['entity:node/11:pt-br'],
      ['entity:aculta_forum_post/55:pt-br', 'entity:aculta_forum_post/56:pt-br'],
    ], $deleted);
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
    $topic->method('getTypedData')->willReturn($this->createMock(ComplexDataInterface::class));
    return $topic;
  }

}
