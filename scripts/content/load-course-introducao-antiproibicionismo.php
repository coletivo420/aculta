<?php

/**
 * @file
 * Loads the versioned course source into the ACULTA LMS.
 *
 * Idempotent: matches the course Group by label, lessons by title inside the
 * course, and activities by their position in the lesson. Updates what exists
 * and creates what is missing. Deletes nothing.
 *
 * The editorial source still uses "no_answer" for reflective prompts without
 * an automatic answer. The current LMS has no such type, so those items load
 * as ungraded free_text activities: the prompt becomes the question and no
 * correct value is stored.
 *
 * Usage (from the repository root):
 *   php vendor/drush/drush/drush.php php:script
 *   load-course-introducao-antiproibicionismo --script-path=scripts/content.
 *
 * The course source is scripts/content/courses/
 * introducao-antiproibicionismo.json. It holds no credentials.
 */

declare(strict_types=1);

use Drupal\aculta_lms\Entity\ActivityEntity;
use Drupal\aculta_lms\Entity\LessonEntity;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;

$sourceFile = __DIR__ . '/courses/introducao-antiproibicionismo.json';
$source = json_decode((string) file_get_contents($sourceFile), TRUE, 512, JSON_THROW_ON_ERROR);
if (!is_array($source)) {
  throw new RuntimeException('The course source must be a JSON object.');
}
$courseSpec = $source['course'] ?? NULL;
$lessonSpecs = $source['lessons'] ?? NULL;
if (!is_array($courseSpec) || !is_array($lessonSpecs)) {
  throw new RuntimeException('The course source needs a course object and a lessons list.');
}
/**
 * Reads a string from a nullable JSON scalar.
 */
$stringValue = static fn(mixed $value): string => is_string($value) ? $value : '';

$courseLabel = trim($stringValue($courseSpec['label'] ?? NULL));
$courseDescription = $stringValue($courseSpec['description'] ?? NULL);
if ($courseLabel === '') {
  throw new RuntimeException('The pilot course needs a label.');
}

/**
 * Finds at most one entity matching every condition.
 *
 * @param \Drupal\Core\Entity\EntityStorageInterface $storage
 *   Storage to query.
 * @param array<string, int|string> $conditions
 *   Field conditions that identify the entity.
 * @param string $label
 *   Human-readable identity used in the ambiguity error.
 *
 * @return \Drupal\Core\Entity\ContentEntityInterface|null
 *   The single match, or NULL when nothing exists yet.
 */
$findOne = static function (EntityStorageInterface $storage, array $conditions, string $label): ?ContentEntityInterface {
  $query = $storage->getQuery()->accessCheck(FALSE);
  foreach ($conditions as $field => $value) {
    if (!is_string($field) || (!is_int($value) && !is_string($value))) {
      throw new RuntimeException('Entity conditions must map field names to scalar values.');
    }
    $query->condition($field, $value);
  }
  $ids = $query->range(0, 2)->execute();
  if (count($ids) > 1) {
    throw new RuntimeException(sprintf('More than one existing %s matched the pilot identity.', $label));
  }
  $id = array_key_first($ids);
  if ($id === NULL) {
    return NULL;
  }
  $entity = $storage->load($id);
  return $entity instanceof ContentEntityInterface ? $entity : NULL;
};

$entityTypeManager = \Drupal::entityTypeManager();
$groupStorage = $entityTypeManager->getStorage('group');
$lessonStorage = $entityTypeManager->getStorage('aculta_lms_lesson');
$activityStorage = $entityTypeManager->getStorage('aculta_lms_activity');

$course = $findOne($groupStorage, ['type' => 'lms_course', 'label' => $courseLabel], 'pilot course');
if (!$course instanceof GroupInterface) {
  $course = Group::create([
    'type' => 'lms_course',
    'label' => $courseLabel,
    'uid' => 1,
  ]);
}
$course->set('field_description', ['value' => $courseDescription, 'format' => 'basic_html']);
$course->setPublished();
$course->save();
$courseId = (int) $course->id();

$report = ['lessons' => [], 'activities' => 0];
foreach (array_values($lessonSpecs) as $lessonWeight => $lessonSpec) {
  if (!is_array($lessonSpec)) {
    throw new RuntimeException('Every lesson must be a JSON object.');
  }
  $lessonTitle = trim($stringValue($lessonSpec['name'] ?? NULL));
  if ($lessonTitle === '') {
    throw new RuntimeException('Every lesson needs a name.');
  }
  $lessonBody = $stringValue($lessonSpec['description'] ?? NULL);
  $activitySpecs = $lessonSpec['activities'] ?? [];
  if (!is_array($activitySpecs)) {
    throw new RuntimeException(sprintf('Lesson "%s" must list its activities.', $lessonTitle));
  }

  $lesson = $findOne(
    $lessonStorage,
    ['course_id' => $courseId, 'title' => $lessonTitle],
    'pilot lesson',
  );
  if (!$lesson instanceof LessonEntity) {
    $lesson = LessonEntity::create(['course_id' => $courseId]);
  }
  $lesson->set('title', $lessonTitle);
  $lesson->set('body', ['value' => $lessonBody, 'format' => 'aculta_lms_lesson']);
  $lesson->set('published', TRUE);
  $lesson->set('weight', $lessonWeight);
  $lesson->save();
  $lessonId = (int) $lesson->id();

  foreach (array_values($activitySpecs) as $activityWeight => $activitySpec) {
    if (!is_array($activitySpec)) {
      throw new RuntimeException(sprintf('Every activity in "%s" must be a JSON object.', $lessonTitle));
    }
    $sourceType = $stringValue($activitySpec['type'] ?? NULL);
    $configuration = match ($sourceType) {
      'true_false' => [
        'question' => trim($stringValue($activitySpec['question'] ?? NULL)),
        'correct' => ($activitySpec['bool_expected'] ?? FALSE) === TRUE,
      ],
      // Reflective prompts carry prose, not a question with a keyed answer.
      'no_answer' => [
        'question' => trim($stringValue($activitySpec['content'] ?? NULL)),
        'minimum_characters' => 0,
      ],
      default => throw new RuntimeException(sprintf('Unsupported activity type "%s" in "%s".', $sourceType, $lessonTitle)),
    };
    if ($configuration['question'] === '') {
      throw new RuntimeException(sprintf('Activity %d in "%s" needs a prompt.', $activityWeight + 1, $lessonTitle));
    }
    $type = $sourceType === 'no_answer' ? 'free_text' : $sourceType;

    $activity = $findOne(
      $activityStorage,
      ['lesson_id' => $lessonId, 'weight' => $activityWeight],
      'pilot activity',
    );
    if (!$activity instanceof ActivityEntity) {
      $activity = ActivityEntity::create(['lesson_id' => $lessonId]);
    }
    $maxScore = $activitySpec['max_score'] ?? 0;
    $activity->set('type', $type);
    $activity->set('graded', is_numeric($maxScore) && (int) $maxScore > 0);
    $activity->set('weight', $activityWeight);
    $activity->set('answer_config', json_encode($configuration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $activity->save();
    $report['activities']++;
  }

  $report['lessons'][] = $lessonId . ' ' . $lessonTitle;
}

echo json_encode([
  'course' => $courseId,
  'published' => $course->isPublished(),
  'lessons' => $report['lessons'],
  'activities' => $report['activities'],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
