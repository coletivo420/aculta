<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_learning_contracts\Learning\CourseJourney;
use Drupal\aculta_learning_contracts\Learning\CourseJourneyLesson;
use Drupal\aculta_learning_contracts\Learning\CourseJourneyReaderInterface;
use Drupal\aculta_studio\Hook\CourseJourneyPresentationHooks;
use Drupal\aculta_studio\Learning\CourseJourneyComposer;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Ensures Studio wraps the ACULTA-owned learner activity route.
 */
#[CoversClass(CourseJourneyPresentationHooks::class)]
#[Group('aculta_studio')]
final class CourseJourneyPresentationHooksTest extends UnitTestCase {

  /**
   * Tests the active ACULTA answer submission route uses the room shell.
   */
  public function testAcultaAnswerSubmissionUsesTheLearningRoomShell(): void {
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('course-1');
    $account = $this->createMock(AccountProxyInterface::class);
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('aculta_lms.answer_submission');
    $route_match->method('getParameter')->willReturnMap([
      ['group', $course],
      ['lesson_delta', 1],
      ['activity_delta', 0],
    ]);
    $journey = new CourseJourney(
      access: TRUE,
      courseId: 'course-1',
      courseLabel: 'Curso de redução de danos',
      description: [],
      started: FALSE,
      finished: FALSE,
      pendingEvaluation: FALSE,
      currentLessonId: NULL,
      currentLessonLabel: NULL,
      currentLessonIndex: NULL,
      totalLessons: 1,
      completedLessons: 0,
      progressPercent: 0,
      lessons: [new CourseJourneyLesson('lesson-1', 'Introdução', 1, 'not_started', FALSE, TRUE, NULL)],
      continueUrl: NULL,
      cache: new CacheableMetadata(),
    );
    $journeys = $this->createMock(CourseJourneyReaderInterface::class);
    $journeys->expects(self::once())->method('getCourseJourney')->with('course-1', $account)->willReturn($journey);
    $hooks = new CourseJourneyPresentationHooks(
      $route_match,
      $account,
      $journeys,
      new CourseJourneyComposer($this->getStringTranslationStub()),
    );
    $variables = ['page' => ['content' => ['form' => ['#type' => 'form']]]];

    $hooks->preprocessLearningRoom($variables);

    self::assertSame(
      'aculta-learning-room',
      $variables['page']['content']['#attributes']['data-portal-ui'],
    );
    self::assertArrayHasKey('form', $variables['page']['content']['layout']['content']);
  }

}
