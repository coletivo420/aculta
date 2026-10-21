<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Field\FieldItemList;
use Drupal\aculta_lms\CourseRoomBuilder;
use Drupal\aculta_lms\Form\CourseEnrollmentPurchaseForm;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\lms\Entity\Lesson;
use Drupal\lms\Entity\LessonInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\aculta_lms\CourseRoomBuilder
 */
final class CourseRoomBuilderTest extends UnitTestCase {

  /**
   * Ensures the course page can build the public enrollment form class.
   */
  public function testUsesEnrollmentPurchaseForm(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $course = $this->createMock(CourseInterface::class);
    $course->method('takeAccess')->with($account)->willReturn(AccessResult::forbidden());
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
      $this->createMock(TranslationInterface::class),
      $formBuilder,
    );

    self::assertSame($form, $builder->build($course)['enrollment']);
  }

  /**
   * Verifies published curriculum uses the parent course access decision.
   */
  public function testShowsPublishedLessonsWithoutGenericEntityViewAccess(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $course_access = AccessResult::allowed()->addCacheContexts(['user.group_permissions']);
    $course = $this->createMock(CourseInterface::class);
    $course->method('takeAccess')->with($account)->willReturn($course_access);
    $course->method('getCacheTags')->willReturn(['group:course']);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);

    $published = $this->lesson('Aula publicada', TRUE);
    $unpublished = $this->lesson('Aula não publicada', FALSE);
    $lesson_field = new class([
      (object) ['entity' => $published],
      (object) ['entity' => $unpublished],
    ]) extends FieldItemList {

      public function __construct(private array $items) {}

      /**
       * {@inheritdoc}
       */
      public function getIterator(): \ArrayIterator {
        return new \ArrayIterator($this->items);
      }

    };
    $course->expects(self::once())->method('get')->with('lessons')->willReturn($lesson_field);

    $builder = new CourseRoomBuilder(
      $account,
      $this->createMock(TranslationInterface::class),
      $this->createMock(FormBuilderInterface::class),
    );

    $build = $builder->build($course);

    self::assertSame('Aula publicada', $build['lessons']['#items'][0]['#plain_text']);
    self::assertCount(1, $build['lessons']['#items']);
    self::assertContains('user.group_permissions', $build['#cache']['contexts']);
  }

  /**
   * Creates a published or unpublished lesson fixture.
   */
  private function lesson(string $label, bool $published): LessonInterface {
    $lesson = $this->createMock(Lesson::class);
    $lesson->method('isPublished')->willReturn($published);
    $lesson->method('label')->willReturn($label);
    $lesson->method('getCacheTags')->willReturn(['lms_lesson:' . $label]);
    $lesson->method('getCacheContexts')->willReturn([]);
    $lesson->method('getCacheMaxAge')->willReturn(-1);
    $lesson->expects(self::never())->method('access');
    $activities = $this->createMock(FieldItemListInterface::class);
    $activities->method('first')->willReturn(NULL);
    $lesson->method('get')->with('activities')->willReturn($activities);
    return $lesson;
  }

}
