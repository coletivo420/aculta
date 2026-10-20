<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_forum_lms\Context\LmsDiscussionContextProvider;
use Drupal\aculta_forum_lms\Hook\LmsDiscussionEntityAccessHooks;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests direct Node access for LMS topics.
 */
#[Group('aculta_forum_lms')]
final class LmsDiscussionEntityAccessHooksTest extends UnitTestCase {

  /**
   * Ungrouped LMS topics cannot inherit the public Node access default.
   */
  public function testUngroupedLmsTopicIsForbidden(): void {
    $storage = $this->createMock(GroupRelationshipStorageInterface::class);
    $storage->expects(self::once())
      ->method('loadByEntity')
      ->with(self::isInstanceOf(NodeInterface::class), 'group_node:aculta_lms_topic')
      ->willReturn([]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('group_relationship')->willReturn($storage);

    $node = $this->createTopic();
    $hook = new LmsDiscussionEntityAccessHooks(new LmsDiscussionContextProvider($manager));

    self::assertTrue($hook->entityAccess($node, 'view', $this->createMock(AccountInterface::class))->isForbidden());
  }

  /**
   * A valid Group relationship leaves authorization to Group itself.
   */
  public function testValidLmsGroupRelationshipDefersToGroupAccess(): void {
    $group = $this->createMock(GroupInterface::class);
    $this->addCacheability($group);
    $group->method('bundle')->willReturn('lms_course');
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $this->addCacheability($relationship);
    $relationship->method('getGroup')->willReturn($group);

    $storage = $this->createMock(GroupRelationshipStorageInterface::class);
    $storage->method('loadByEntity')->willReturn([$relationship]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('group_relationship')->willReturn($storage);

    $node = $this->createTopic();
    $hook = new LmsDiscussionEntityAccessHooks(new LmsDiscussionContextProvider($manager));

    self::assertTrue($hook->entityAccess($node, 'view', $this->createMock(AccountInterface::class))->isNeutral());
  }

  /**
   * A class discussion without one valid parent course is denied.
   */
  public function testClassTopicWithoutCourseRelationshipIsForbidden(): void {
    $class = $this->createMock(GroupInterface::class);
    $this->addCacheability($class);
    $class->method('bundle')->willReturn('lms_class');
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $this->addCacheability($relationship);
    $relationship->method('getGroup')->willReturn($class);

    $storage = $this->createMock(GroupRelationshipStorageInterface::class);
    $storage->expects(self::exactly(2))
      ->method('loadByEntity')
      ->willReturnOnConsecutiveCalls([$relationship], []);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('group_relationship')->willReturn($storage);

    $hook = new LmsDiscussionEntityAccessHooks(new LmsDiscussionContextProvider($manager));

    self::assertTrue($hook->entityAccess($this->createTopic(), 'view', $this->createMock(AccountInterface::class))->isForbidden());
  }

  /**
   * Creates a persisted LMS topic mock with cache metadata.
   */
  private function createTopic(): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $this->addCacheability($node);
    $node->method('bundle')->willReturn('aculta_lms_topic');
    $node->method('isNew')->willReturn(FALSE);
    $node->method('uuid')->willReturn('topic-uuid');
    $node->method('id')->willReturn('101');
    return $node;
  }

  /**
   * Provides cacheability metadata on an entity mock.
   */
  private function addCacheability(object $entity): void {
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheTags')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
  }

}
