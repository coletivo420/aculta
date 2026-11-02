<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_learning_contracts\Learning\CourseJourney;
use Drupal\aculta_learning_contracts\Learning\CourseJourneyLesson;
use Drupal\aculta_studio\Learning\CourseJourneyComposer;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers the Ganjalab journey and learning room composition.
 */
#[CoversClass(CourseJourneyComposer::class)]
#[Group('aculta_studio')]
final class CourseJourneyComposerTest extends UnitTestCase {

  /**
   * Builds a canonical journey fixture.
   */
  private function journey(): CourseJourney {
    return new CourseJourney(
      access: TRUE,
      courseId: 'course-1',
      courseLabel: 'Curso de redução de danos',
      description: [],
      started: TRUE,
      finished: FALSE,
      pendingEvaluation: FALSE,
      currentLessonId: 'lesson-2',
      currentLessonLabel: 'Redução de danos',
      currentLessonIndex: 2,
      totalLessons: 3,
      completedLessons: 1,
      progressPercent: 33,
      lessons: [
        new CourseJourneyLesson(
          id: 'lesson-1',
          label: 'Proibicionismo',
          delta: 0,
          state: 'finished',
          isCurrent: FALSE,
          allowed: TRUE,
          url: Url::fromRoute('lms.group.answer_form', [
            'group' => 'course-1',
            'lesson_delta' => 0,
            'activity_delta' => 0,
          ]),
        ),
        new CourseJourneyLesson(
          id: 'lesson-2',
          label: 'Redução de danos',
          delta: 1,
          state: 'current',
          isCurrent: TRUE,
          allowed: TRUE,
          url: Url::fromRoute('lms.group.answer_form', [
            'group' => 'course-1',
            'lesson_delta' => 1,
            'activity_delta' => 0,
          ]),
        ),
        new CourseJourneyLesson(
          id: 'lesson-3',
          label: 'Direitos humanos',
          delta: 2,
          state: 'not_started',
          isCurrent: FALSE,
          allowed: FALSE,
          url: NULL,
        ),
      ],
      continueUrl: Url::fromRoute('lms.course.start', ['group' => 'course-1']),
      cache: new CacheableMetadata(),
    );
  }

  /**
   * Renders the journey without querying entities or deciding access.
   */
  public function testJourneyListsCanonicalLessonsAndStates(): void {
    $composer = new CourseJourneyComposer($this->getStringTranslationStub());

    $build = $composer->build($this->journey());

    self::assertSame('aculta-learning-journey', $build['#attributes']['data-portal-ui']);
    $items = $build['outline']['list']['#items'];
    self::assertCount(3, $items);
    self::assertSame('finished', $items[0]['#attributes']['data-state']);
    self::assertSame('current', $items[1]['#attributes']['data-state']);
    self::assertSame('locked', $items[2]['#attributes']['data-state']);
    self::assertArrayNotHasKey('link', $items[2]);
    self::assertSame('Aula atual', (string) $items[1]['state']['#value']);
    self::assertSame('Ainda não liberada', (string) $items[2]['state']['#value']);
  }

  /**
   * Wraps the native LMS form in the contextual room shell.
   */
  public function testRoomWrapsContentWithContextHeaderAndOutline(): void {
    $composer = new CourseJourneyComposer($this->getStringTranslationStub());

    $build = $composer->buildRoom($this->journey(), 1, 0, [
      '#markup' => '<form id="lms-answer-form"></form>',
    ]);

    self::assertSame('aculta-learning-room', $build['#attributes']['data-portal-ui']);
    self::assertSame('Redução de danos', $build['header']['#props']['title']);
    self::assertSame('Curso de redução de danos · Aula 2 de 3', $build['header']['#props']['subtitle']);
    self::assertArrayHasKey('back_link', $build['header']['#slots']);
    self::assertSame('details', $build['layout']['outline_mobile']['#type']);
    self::assertArrayHasKey('content', $build['layout']);
  }

}
