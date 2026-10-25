<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\RestrictedForum;

use Drupal\aculta_forum\Hook\RestrictedForumRelationshipHooks;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumGrantRebuilder;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\node\NodeAccessControlHandlerInterface;
use Drupal\node\NodeGrantDatabaseStorageInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests grant rebuilds triggered by Group relationship changes.
 */
#[CoversClass(RestrictedForumRelationshipHooks::class)]
final class RestrictedForumRelationshipHooksTest extends TestCase {

  /**
   * Inserting a topic relationship rebuilds that topic's grants.
   */
  public function testTopicRelationshipRebuildsTopicGrants(): void {
    $topic = $this->topic(11);
    $grants = $this->createMock(NodeGrantDatabaseStorageInterface::class);
    $grants->expects(self::once())->method('write')->with($topic);
    $hooks = $this->hooks($this->nodeStorage($topic), $grants);
    $hooks->relationshipInsert($this->relationship(RestrictedForumRelationshipHooks::TOPIC_PLUGIN, 11));
  }

  /**
   * Updating and deleting a topic relationship also rebuild.
   */
  public function testTopicRelationshipUpdateAndDeleteRebuild(): void {
    $topic = $this->topic(11);
    $grants = $this->createMock(NodeGrantDatabaseStorageInterface::class);
    $grants->expects(self::exactly(2))->method('write')->with($topic);
    $hooks = $this->hooks($this->nodeStorage($topic), $grants);
    $hooks->relationshipUpdate($this->relationship(RestrictedForumRelationshipHooks::TOPIC_PLUGIN, 11));
    $hooks->relationshipDelete($this->relationship(RestrictedForumRelationshipHooks::TOPIC_PLUGIN, 11));
  }

  /**
   * A topic that no longer loads is skipped without writing grants.
   */
  public function testMissingTopicIsSkipped(): void {
    $grants = $this->createMock(NodeGrantDatabaseStorageInterface::class);
    $grants->expects(self::never())->method('write');
    $hooks = $this->hooks($this->nodeStorage(NULL), $grants);
    $hooks->relationshipInsert($this->relationship(RestrictedForumRelationshipHooks::TOPIC_PLUGIN, 11));
  }

  /**
   * A category relationship rebuilds every topic that uses the category.
   */
  public function testCategoryRelationshipRebuildsCategoryTopics(): void {
    $topic = $this->topic(11);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('getQuery')->willReturn($this->query([11]));
    $storage->expects(self::once())->method('loadMultiple')->with([11])->willReturn([11 => $topic]);
    $grants = $this->createMock(NodeGrantDatabaseStorageInterface::class);
    $grants->expects(self::once())->method('write')->with($topic);
    $hooks = $this->hooks($storage, $grants);
    $hooks->relationshipInsert($this->relationship(RestrictedForumRelationshipHooks::CATEGORY_PLUGIN, 5));
  }

  /**
   * A category without topics writes no grants.
   */
  public function testCategoryWithoutTopicsWritesNothing(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($this->query([]));
    $storage->expects(self::never())->method('loadMultiple');
    $grants = $this->createMock(NodeGrantDatabaseStorageInterface::class);
    $grants->expects(self::never())->method('write');
    $hooks = $this->hooks($storage, $grants);
    $hooks->relationshipInsert($this->relationship(RestrictedForumRelationshipHooks::CATEGORY_PLUGIN, 5));
  }

  /**
   * Membership relationships are ignored to avoid recursion.
   */
  public function testMembershipRelationshipIsIgnored(): void {
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects(self::never())->method('getStorage');
    $manager->expects(self::never())->method('getAccessControlHandler');
    $hooks = $this->hooks(NULL, NULL, $manager);
    $hooks->relationshipInsert($this->relationship('group_membership', 5));
  }

  /**
   * A relationship without entity id is ignored.
   */
  public function testRelationshipWithoutEntityIdIsIgnored(): void {
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects(self::never())->method('getStorage');
    $hooks = $this->hooks(NULL, NULL, $manager);
    $hooks->relationshipInsert($this->relationship(RestrictedForumRelationshipHooks::TOPIC_PLUGIN, 0));
  }

  /**
   * Builds the hooks with a real rebuilder over stubbed collaborators.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface|null $nodeStorage
   *   Armazenamento de nós consultado pelo regravador.
   * @param \Drupal\node\NodeGrantDatabaseStorageInterface|null $grants
   *   Armazenamento de grants esperado.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $manager
   *   Gerenciador pronto, quando o teste dispensa os dois anteriores.
   */
  private function hooks(?EntityStorageInterface $nodeStorage, ?NodeGrantDatabaseStorageInterface $grants, ?EntityTypeManagerInterface $manager = NULL): RestrictedForumRelationshipHooks {
    if ($manager === NULL) {
      $handler = $this->createMock(NodeAccessControlHandlerInterface::class);
      $handler->method('acquireGrants')->willReturn([]);
      $manager = $this->createMock(EntityTypeManagerInterface::class);
      $manager->method('getStorage')->with('node')->willReturn($nodeStorage);
      $manager->method('getAccessControlHandler')->with('node')->willReturn($handler);
    }
    $rebuilder = new RestrictedForumGrantRebuilder(
      $manager,
      $grants ?? $this->createMock(NodeGrantDatabaseStorageInterface::class),
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(LoggerInterface::class),
    );
    return new RestrictedForumRelationshipHooks($manager, $rebuilder);
  }

  /**
   * Returns a node storage fixture loading the given topic.
   */
  private function nodeStorage(?NodeInterface $topic): EntityStorageInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($topic);
    return $storage;
  }

  /**
   * Returns a query fixture resolving to the given ids.
   */
  private function query(array $ids): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn($ids);
    return $query;
  }

  /**
   * Returns a group relationship fixture.
   */
  private function relationship(string $pluginId, int $entityId): GroupRelationshipInterface {
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $relationship->method('getPluginId')->willReturn($pluginId);
    $relationship->method('getEntityId')->willReturn($entityId);
    return $relationship;
  }

  /**
   * Returns a forum topic fixture.
   */
  private function topic(int $nid): NodeInterface {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('id')->willReturn($nid);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    return $topic;
  }

}
