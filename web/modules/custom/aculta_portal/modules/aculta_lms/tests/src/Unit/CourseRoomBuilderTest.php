<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\ClassRosterManager;
use Drupal\aculta_lms\CourseRoomBuilder;
use Drupal\aculta_lms\Form\CourseEnrollmentPurchaseForm;
use Drupal\aculta_lms\Learning\CourseJourney;
use Drupal\aculta_lms\Learning\CourseJourneyReaderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers the course room builder against the ACULTA journey contract.
 */
#[CoversClass(CourseRoomBuilder::class)]
final class CourseRoomBuilderTest extends UnitTestCase {

  /**
   * Ensures the course page can build the public enrollment form class.
   */
  public function testUsesEnrollmentPurchaseForm(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('course-1');
    $course->method('hasPermission')->with('administer members', $account)->willReturn(FALSE);
    $course->method('getCacheTags')->willReturn([]);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);

    $form = ['#plain_text' => 'enrollment form'];
    $formBuilder = $this->createMock(FormBuilderInterface::class);
    $formBuilder->expects(self::once())
      ->method('getForm')
      ->with(CourseEnrollmentPurchaseForm::class, $course)
      ->willReturn($form);

    $builder = new CourseRoomBuilder(
      $account,
      $formBuilder,
      $this->createMock(ClassRosterManager::class),
      $this->reader($this->journey(FALSE)),
    );

    self::assertSame($form, $builder->build($course)['enrollment']);
  }

  /**
   * Verifies the LMS builder composes rosters and leaves the journey to Studio.
   */
  public function testAuthorizedCourseBuildsRosterWithoutComposingJourney(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('course-1');
    $course->method('getCacheTags')->willReturn(['group:course-1']);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);

    $rosters = $this->createMock(ClassRosterManager::class);
    $rosters->expects(self::once())->method('build')->with($course, $account)->willReturn([
      'classes' => ['class-1'],
    ]);

    $builder = new CourseRoomBuilder(
      $account,
      $this->createMock(FormBuilderInterface::class),
      $rosters,
      $this->reader($this->journey(TRUE)),
    );
    $build = $builder->build($course);

    self::assertIsArray($build['participant_rosters'] ?? NULL);
    self::assertIsArray($build['participant_rosters']['classes'] ?? NULL);
    self::assertSame(['class-1'], $build['participant_rosters']['classes']);
    self::assertArrayNotHasKey('aculta_learning_journey', $build);
    self::assertIsArray($build['#cache'] ?? NULL);
    self::assertIsArray($build['#cache']['contexts'] ?? NULL);
    self::assertContains('user.group_permissions', $build['#cache']['contexts']);
  }

  /**
   * Builds one journey fixture with an authorized or denied result.
   */
  private function journey(bool $access): CourseJourney {
    return new CourseJourney(
      access: $access,
      courseId: 'course-1',
      courseLabel: 'Curso',
      description: [],
      started: FALSE,
      finished: FALSE,
      pendingEvaluation: FALSE,
      currentLessonId: NULL,
      currentLessonLabel: NULL,
      currentLessonIndex: NULL,
      totalLessons: 0,
      completedLessons: 0,
      progressPercent: 0,
      lessons: [],
      continueUrl: NULL,
      cache: new CacheableMetadata(),
    );
  }

  /**
   * Returns a fake journey reader so the builder never touches Drupal LMS.
   */
  private function reader(?CourseJourney $journey): CourseJourneyReaderInterface {
    return new class($journey) implements CourseJourneyReaderInterface {

      /**
       * Stores the journey returned for every request.
       */
      public function __construct(private readonly ?CourseJourney $journey) {}

      /**
       * {@inheritdoc}
       */
      public function getCourseJourney(string $course_id, AccountInterface $account): ?CourseJourney {
        return $this->journey;
      }

    };
  }

}
