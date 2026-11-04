<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_lms\Access\ClassActivityDeadlineAccessCheck;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_lms\Notice\LearningEligibility;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\aculta_lms\Notice\LearningEligibility
 */
final class LearningEligibilityTest extends UnitTestCase {

  /**
   * @covers ::courseEnrollmentCacheTags
   */
  public function testCourseEnrollmentCacheTagsIncludeLinkedClassMemberships(): void {
    $class = $this->createMock(GroupInterface::class);
    $class->method('id')->willReturn('22');
    $class->method('bundle')->willReturn('lms_class');
    $other_group = $this->createMock(GroupInterface::class);
    $other_group->method('id')->willReturn('23');
    $other_group->method('bundle')->willReturn('other');
    $class_relationship = $this->createMock(GroupRelationshipInterface::class);
    $class_relationship->method('getEntity')->willReturn($class);
    $other_relationship = $this->createMock(GroupRelationshipInterface::class);
    $other_relationship->method('getEntity')->willReturn($other_group);
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('12');
    $course->method('getRelationships')->with('lms_classes')->willReturn([
      $class_relationship,
      $class_relationship,
      $other_relationship,
    ]);
    $eligibility = new LearningEligibility(
      $this->createMock(EntityTypeManagerInterface::class),
      new ClassActivityDeadlineManager(
        $this->createMock(EntityTypeManagerInterface::class),
        new ClassActivityDeadlineAccessCheck(
          $this->createMock(EntityTypeManagerInterface::class),
          $this->createMock(AccountProxyInterface::class),
        ),
      ),
    );

    self::assertSame([
      'group_relationship_list:plugin:lms_classes:group:12',
      'group_relationship_list:plugin:group_membership:group:22',
    ], $eligibility->courseEnrollmentCacheTags($course));
  }

  /**
   * @covers ::hasCurrentEnrollment
   */
  public function testEnrollmentFollowsCourseClassRelationships(): void {
    $previousContainer = \Drupal::hasContainer() ? \Drupal::getContainer() : NULL;

    $learner = $this->createMock(UserInterface::class);
    $learner->method('id')->willReturn('41');
    $learner->method('isActive')->willReturn(TRUE);
    $outsider = $this->createMock(UserInterface::class);
    $outsider->method('id')->willReturn('42');
    $outsider->method('isActive')->willReturn(TRUE);

    $class = $this->createMock(GroupInterface::class);
    $class->method('id')->willReturn('22');
    $class->method('bundle')->willReturn('lms_class');
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('12');
    $course->method('hasPermission')->with('take course', $this->anything())->willReturn(TRUE);
    $classRelationship = $this->createMock(GroupRelationshipInterface::class);
    $classRelationship->method('getEntity')->willReturn($class);
    $course->method('getRelationships')->with('lms_classes')->willReturn([$classRelationship]);

    $membership = $this->createMock(GroupRelationshipInterface::class);
    $membership->method('getPluginId')->willReturn('group_membership');
    $membership->method('getEntity')->willReturn($learner);
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([100]);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->with([100])->willReturn([$membership]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('group_relationship')->willReturn($storage);
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturn(FALSE);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entityTypeManager);
    $container->set('cache.group_memberships_chained', $cache);
    \Drupal::setContainer($container);

    try {
      $accessCheck = new ClassActivityDeadlineAccessCheck(
        $entityTypeManager,
        $this->createMock(AccountProxyInterface::class),
      );
      $eligibility = new LearningEligibility(
        $entityTypeManager,
        new ClassActivityDeadlineManager($entityTypeManager, $accessCheck),
      );

      self::assertTrue($eligibility->hasCurrentEnrollment($course, $learner));
      self::assertFalse($eligibility->hasCurrentEnrollment($course, $outsider));
    }
    finally {
      if ($previousContainer === NULL) {
        \Drupal::unsetContainer();
      }
      else {
        \Drupal::setContainer($previousContainer);
      }
    }
  }

}
