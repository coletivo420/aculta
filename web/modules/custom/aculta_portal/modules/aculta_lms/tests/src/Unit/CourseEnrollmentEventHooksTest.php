<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Event\CourseEnrollmentEvent;
use Drupal\aculta_lms\Hook\CourseEnrollmentEventHooks;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\EventDispatcher\EventDispatcherInterface;
use Drupal\user\UserInterface;

/**
 *
 */
#[CoversClass(CourseEnrollmentEventHooks::class)]
#[Group('aculta_lms')]
final class CourseEnrollmentEventHooksTest extends UnitTestCase {

  /**
   *
   */
  public function testPublishesEnrollmentForCourseClassMembership(): void {
    $student = $this->createMock(UserInterface::class);
    $student->method('id')->willReturn('27');
    $class = $this->createMock(GroupInterface::class);
    $class->method('bundle')->willReturn('lms_class');
    $class->method('id')->willReturn('11');

    $membership = $this->createMock(GroupMembership::class);
    $membership->method('getPluginId')->willReturn('group_membership');
    $membership->method('getGroup')->willReturn($class);
    $membership->method('getEntity')->willReturn($student);
    $membership->method('uuid')->willReturn('membership-uuid');

    $course = $this->createMock(GroupInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('10');
    $parent = $this->createMock(GroupRelationshipInterface::class);
    $parent->method('getGroup')->willReturn($course);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('loadByProperties')->with([
      'entity_id' => '11',
      'plugin_id' => 'lms_classes',
    ])->willReturn([$parent]);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('group_relationship')->willReturn($storage);

    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::once())->method('dispatch')->with($this->callback(static fn(object $event): bool =>
      $event instanceof CourseEnrollmentEvent
      && $event->membershipUuid === 'membership-uuid'
      && $event->courseId === '10'
      && $event->classId === '11'
      && $event->accountId === '27'
    ));

    (new CourseEnrollmentEventHooks($entity_type_manager, $dispatcher))->entityInsert($membership);
  }

}
