<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Routing;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Transliteration\PhpTransliteration;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\LessonInterface;

/**
 * Derives Portuguese public slugs for LMS courses, lessons and activities.
 *
 * The slug comes from the title, so no field is needed. Lessons and activities
 * are addressed by their position in the parent's ordered field; repeated titles
 * in the same scope receive "-2", "-3", and so on, in stored order.
 *
 * Why: the LMS routes use numeric positions (DT-P21). Titles are stable enough
 * for public links, and deriving them keeps the content model unchanged.
 */
final class LmsFriendlySlugs {

  public const COURSE_BUNDLE = 'lms_course';

  private const FALLBACK = 'sem-titulo';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PhpTransliteration $transliteration,
  ) {}

  /**
   * Converts a title into a lowercase ASCII slug with hyphens.
   */
  public function slugify(string $label): string {
    $ascii = strtolower($this->transliteration->transliterate($label, 'pt-br', '?'));
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');
    return $slug === '' ? self::FALLBACK : $slug;
  }

  /**
   * Returns one unique slug per label, keeping the input order and keys.
   *
   * @param array<array-key,string> $labels
   *
   * @return array<array-key,string>
   */
  public function uniqueSlugs(array $labels): array {
    $used = [];
    $slugs = [];
    foreach ($labels as $key => $label) {
      $base = $this->slugify($label);
      $slug = $base;
      $suffix = 2;
      while (isset($used[$slug])) {
        $slug = $base . '-' . $suffix++;
      }
      $used[$slug] = TRUE;
      $slugs[$key] = $slug;
    }
    return $slugs;
  }

  /**
   * Returns the course slug for a course, unique across all courses.
   */
  public function courseSlug(CourseInterface $course): ?string {
    return $this->courseSlugs()[$course->id()] ?? NULL;
  }

  /**
   * Resolves a course slug to its course entity, or NULL.
   */
  public function courseFromSlug(string $slug): ?CourseInterface {
    $id = array_search($slug, $this->courseSlugs(), TRUE);
    if ($id === FALSE) {
      return NULL;
    }
    $course = $this->entityTypeManager->getStorage('group')->load($id);
    return $course instanceof CourseInterface ? $course : NULL;
  }

  /**
   * Returns the lesson at a position, or NULL when the position is empty.
   *
   * The LMS getLesson() fails on a missing position, so the check comes first.
   */
  public function lessonAt(CourseInterface $course, int $delta): ?LessonInterface {
    return $course->getLessonItem($delta) === NULL ? NULL : $course->getLesson($delta);
  }

  /**
   * Returns the lesson slug for a position in the course, or NULL.
   */
  public function lessonSlug(CourseInterface $course, int $delta): ?string {
    return $this->uniqueSlugs($this->labels($course->get('lessons')))[$delta] ?? NULL;
  }

  /**
   * Resolves a lesson slug to its position in the course, or NULL.
   */
  public function lessonDeltaFromSlug(CourseInterface $course, string $slug): ?int {
    $delta = array_search($slug, $this->uniqueSlugs($this->labels($course->get('lessons'))), TRUE);
    return $delta === FALSE ? NULL : $delta;
  }

  /**
   * Returns the activity slug for a position in the lesson, or NULL.
   */
  public function activitySlug(LessonInterface $lesson, int $delta): ?string {
    return $this->uniqueSlugs($this->labels($lesson->get(LessonInterface::ACTIVITIES)))[$delta] ?? NULL;
  }

  /**
   * Resolves an activity slug to its position in the lesson, or NULL.
   */
  public function activityDeltaFromSlug(LessonInterface $lesson, string $slug): ?int {
    $delta = array_search($slug, $this->uniqueSlugs($this->labels($lesson->get(LessonInterface::ACTIVITIES))), TRUE);
    return $delta === FALSE ? NULL : $delta;
  }

  /**
   * Returns the course slugs keyed by course ID, in ID order.
   *
   * @return array<int|string,string>
   */
  private function courseSlugs(): array {
    $storage = $this->entityTypeManager->getStorage('group');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::COURSE_BUNDLE)
      ->sort('id')
      ->execute();
    $labels = [];
    foreach ($storage->loadMultiple($ids) as $id => $course) {
      $labels[$id] = $course->label() ?? '';
    }
    return $this->uniqueSlugs($labels);
  }

  /**
   * Returns the labels of referenced entities, keeping their stored positions.
   *
   * @return array<int,string>
   */
  private function labels(FieldItemListInterface $items): array {
    $labels = [];
    foreach ($items as $delta => $item) {
      $entity = $item->get('entity')->getValue();
      $labels[$delta] = $entity instanceof EntityInterface ? (string) $entity->label() : '';
    }
    return $labels;
  }

}
