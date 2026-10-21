<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Kernel;

use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\KernelTests\KernelTestBase;
use Drupal\group\PermissionScopeInterface;
use Drupal\lms\Entity\CourseStatus;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\user\UserInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/** Verifies LMS class dashboard counts remain scoped to current class members. */
#[TestGroup('aculta_lms')]
#[RunTestsInSeparateProcesses]
final class ClassDashboardKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /** {@inheritdoc} */
  protected static $modules = [
    'system', 'user', 'file', 'image', 'media', 'entity_usage', 'aculta_image',
    'field', 'filter', 'flexible_permissions', 'group', 'views', 'options',
    'text', 'lms', 'lms_classes', 'aculta_lms',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['group', 'lms', 'lms_classes']);
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('lms_course_status');
  }

  public function testEducatorDashboardAggregatesOnlyCurrentClassMembers(): void {
    $owner = $this->createUser(['administer lms']);
    $educator = $this->createUser(['administer lms']);
    $memberPending = $this->createUser();
    $memberPassed = $this->createUser();
    $outsiderFailed = $this->createUser();
    $teacher = $this->createUser();
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso do painel',
      'uid' => $owner->id(),
    ]);
    $course->save();
    GroupRole::create([
      'id' => 'lms_course-test_teacher',
      'label' => 'Educador de teste',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['grade students', 'take course'],
    ])->save();
    $course->addMember($teacher, ['group_roles' => ['lms_course-test_teacher']]);
    $class = Group::create([
      'type' => 'lms_class',
      'label' => 'Turma do painel',
      'uid' => $owner->id(),
    ]);
    $class->save();
    $course->addRelationship($class, 'lms_classes');
    $class->addMember($memberPending);
    $class->addMember($memberPassed);
    $class->addMember($teacher);

    $this->createStatus($course, $memberPending, CourseStatusInterface::STATUS_NEEDS_EVALUATION);
    $this->createStatus($course, $memberPassed, CourseStatusInterface::STATUS_PASSED);
    $this->createStatus($course, $outsiderFailed, CourseStatusInterface::STATUS_FAILED);
    self::assertTrue($course->hasPermission('grade students', $teacher));

    $manager = $this->container->get('aculta_lms.learning_dashboard');
    self::assertTrue($manager->classDashboardAccess($class, $educator)->isAllowed());
    $metrics = $manager->classDashboard(
      $class,
      $educator,
    );

    self::assertNotNull($metrics);
    self::assertSame(2, $metrics['students']);
    self::assertSame(1, $metrics['pending_evaluation']);
    self::assertSame(1, $metrics['passed']);
    self::assertSame(0, $metrics['failed'], 'The failed status belonging to an outsider is excluded.');
    self::assertArrayNotHasKey('answers', $metrics);
    self::assertArrayNotHasKey('student_ids', $metrics);
    self::assertContains('group_relationship_list:plugin:group_membership:group:' . $class->id(), $metrics['cache']->getCacheTags());
    self::assertContains('group_relationship_list:plugin:group_membership:entity:' . $memberPending->id(), $metrics['cache']->getCacheTags());
    self::assertContains('config:group_role_list', $metrics['cache']->getCacheTags());
    self::assertContains('config:user.role_list', $metrics['cache']->getCacheTags());
    self::assertContains('user.group_permissions', $metrics['cache']->getCacheContexts());

    $this->container->get('current_user')->setAccount($educator);
    $build = $this->container->get('aculta_lms.learning_dashboard_controller')->class($class);
    self::assertSame(0, $build['#cache']['max-age']);
    self::assertContains('user', $build['#cache']['contexts']);
    self::assertContains('group_relationship_list:plugin:group_membership:group:' . $class->id(), $build['#cache']['tags']);
  }

  private function createStatus(Group $course, UserInterface $student, string $status): void {
    CourseStatus::create([
      CourseStatusInterface::COURSE_FIELD => $course->id(),
      'uid' => $student->id(),
      'status' => $status,
      'finished' => $status === CourseStatusInterface::STATUS_PASSED ? 1_700_000_000 : 0,
      'current' => TRUE,
    ])->save();
  }

}
