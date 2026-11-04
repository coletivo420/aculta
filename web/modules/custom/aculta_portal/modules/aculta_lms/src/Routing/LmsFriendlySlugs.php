<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Transliteration\PhpTransliteration;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Derives Portuguese slugs for Group courses and ACULTA lesson content.
 *
 * Lesson titles and stable activity types are ordered by weight and ID.
 * Repeated values in the same scope receive numeric suffixes.
 */
final class LmsFriendlySlugs {

  public const COURSE_BUNDLE = 'lms_course';

  /**
   * Cache tag invalidated when a lesson is created, updated, or deleted.
   */
  public const LESSON_LIST_CACHE_TAG = 'aculta_lms_lesson_list';

  /**
   * Cache tag invalidated when an activity is created, updated, or deleted.
   */
  public const ACTIVITY_LIST_CACHE_TAG = 'aculta_lms_activity_list';

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
   *   Labels to convert into unique slugs.
   *
   * @return array<array-key,string>
   *   Slugs with the same keys and order as the input.
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
  public function courseSlug(GroupInterface $course): ?string {
    return $this->courseSlugs()[$course->id()] ?? NULL;
  }

  /**
   * Resolves a course slug to its course entity, or NULL.
   */
  public function courseFromSlug(string $slug): ?GroupInterface {
    $id = array_search($slug, $this->courseSlugs(), TRUE);
    if ($id === FALSE) {
      return NULL;
    }
    $course = $this->entityTypeManager->getStorage('group')->load($id);
    return $course instanceof GroupInterface && $course->bundle() === self::COURSE_BUNDLE ? $course : NULL;
  }

  /**
   * Returns the lesson at a position, or NULL when the position is empty.
   *
   * Returns NULL when the requested course position does not exist.
   */
  public function lessonAt(GroupInterface $course, int $delta): ?ContentEntityInterface {
    return $this->lessons($course)[$delta] ?? NULL;
  }

  /**
   * Returns an ACULTA activity at the requested lesson/activity positions.
   */
  public function activityAt(GroupInterface $course, int $lessonDelta, int $activityDelta): ?ContentEntityInterface {
    $lesson = $this->lessonAt($course, $lessonDelta);
    if (!$lesson instanceof ContentEntityInterface) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('aculta_lms_activity');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('lesson_id', (int) $lesson->id())
      ->sort('weight')
      ->sort('id')
      ->execute();
    $activities = array_values(array_filter(
      $storage->loadMultiple($ids),
      static fn(mixed $activity): bool => $activity instanceof ContentEntityInterface
        && $activity->getEntityTypeId() === 'aculta_lms_activity',
    ));
    return $activities[$activityDelta] ?? NULL;
  }

  /**
   * Returns the lesson slug for a position in the course, or NULL.
   */
  public function lessonSlug(GroupInterface $course, int $delta): ?string {
    return $this->uniqueSlugs($this->lessonTitles($course))[$delta] ?? NULL;
  }

  /**
   * Resolves a lesson slug to its position in the course, or NULL.
   */
  public function lessonDeltaFromSlug(GroupInterface $course, string $slug): ?int {
    $delta = array_search($slug, $this->uniqueSlugs($this->lessonTitles($course)), TRUE);
    return $delta === FALSE ? NULL : $delta;
  }

  /**
   * Returns the activity slug for a position in the lesson, or NULL.
   */
  public function activitySlug(ContentEntityInterface $lesson, int $delta): ?string {
    return $this->uniqueSlugs($this->activityTypes($lesson))[$delta] ?? NULL;
  }

  /**
   * Resolves an activity slug to its position in the lesson, or NULL.
   */
  public function activityDeltaFromSlug(ContentEntityInterface $lesson, string $slug): ?int {
    $delta = array_search($slug, $this->uniqueSlugs($this->activityTypes($lesson)), TRUE);
    return $delta === FALSE ? NULL : $delta;
  }

  /**
   * Returns the course slugs keyed by course ID, in ID order.
   *
   * @return array<int|string,string>
   *   Slugs keyed by Group course ID.
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
   * Returns the titles of own lessons in stored course order.
   *
   * @return array<int,string>
   *   Lesson titles in weight and ID order.
   */
  private function lessonTitles(GroupInterface $course): array {
    $titles = [];
    foreach ($this->lessons($course) as $delta => $lesson) {
      $titles[$delta] = (string) self::fieldValue($lesson, 'title');
    }
    return $titles;
  }

  /**
   * Returns the own lessons for a course, ordered by weight and ID.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   ACULTA lesson entities.
   */
  private function lessons(GroupInterface $course): array {
    $storage = $this->entityTypeManager->getStorage('aculta_lms_lesson');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('course_id', (int) $course->id())
      ->sort('weight')
      ->sort('id')
      ->execute();
    return array_values(array_filter(
      $storage->loadMultiple($ids),
      static fn(mixed $lesson): bool => $lesson instanceof ContentEntityInterface
        && $lesson->getEntityTypeId() === 'aculta_lms_lesson',
    ));
  }

  /**
   * Returns the stable activity types in stored lesson order.
   *
   * @return array<int,string>
   *   Stable activity types in weight and ID order.
   */
  private function activityTypes(ContentEntityInterface $lesson): array {
    $storage = $this->entityTypeManager->getStorage('aculta_lms_activity');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('lesson_id', (int) $lesson->id())
      ->sort('weight')
      ->sort('id')
      ->execute();
    $types = [];
    foreach ($storage->loadMultiple($ids) as $activity) {
      if ($activity instanceof ContentEntityInterface && $activity->getEntityTypeId() === 'aculta_lms_activity') {
        $types[] = (string) self::fieldValue($activity, 'type');
      }
    }
    return $types;
  }

  /**
   * Returns the scalar value of an entity field.
   */
  private static function fieldValue(ContentEntityInterface $entity, string $field): mixed {
    $values = $entity->get($field)->getValue();
    return $values[0]['value'] ?? NULL;
  }

}
