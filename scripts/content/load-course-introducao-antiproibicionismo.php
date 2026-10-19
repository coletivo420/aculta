<?php

/**
 * Loads the versioned course source into the LMS. Idempotent: matches the course by label,
 * lessons and activities by name; updates what exists and creates what is missing. Deletes nothing.
 *
 * Usage (from the repository root):
 *   php vendor/drush/drush/drush.php php:script load-course-introducao-antiproibicionismo --script-path=scripts/content
 *
 * The course source is scripts/content/courses/introducao-antiproibicionismo.json. It holds no credentials.
 */

use Drupal\group\Entity\Group;
use Drupal\lms\Entity\Activity;
use Drupal\lms\Entity\Lesson;

$source = json_decode(file_get_contents(__DIR__ . '/courses/introducao-antiproibicionismo.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$courseSpec = $source['course'];

$findOne = static function ($storage, array $conditions, string $label): ?object {
  $query = $storage->getQuery()->accessCheck(FALSE);
  foreach ($conditions as $field => $value) {
    $query->condition($field, $value);
  }
  $ids = $query->range(0, 2)->execute();
  if (count($ids) > 1) {
    throw new RuntimeException(sprintf('More than one existing %s matched the pilot identity.', $label));
  }
  return $ids ? $storage->load(reset($ids)) : NULL;
};

$groupStorage = \Drupal::entityTypeManager()->getStorage('group');
$lessonStorage = \Drupal::entityTypeManager()->getStorage('lms_lesson');
$activityStorage = \Drupal::entityTypeManager()->getStorage('lms_activity');

$course = $findOne($groupStorage, ['type' => 'lms_course', 'label' => $courseSpec['label']], 'pilot course');
$course ??= Group::create(['type' => 'lms_course', 'label' => $courseSpec['label'], 'uid' => 1]);
$course->set('field_description', ['value' => $courseSpec['description'], 'format' => 'basic_html']);
$course->setPublished();

$lessonRefs = [];
$report = ['lessons' => [], 'activities' => 0];
foreach ($source['lessons'] as $spec) {
  /** @var \Drupal\lms\Entity\LessonInterface $lesson */
  $lesson = $findOne($lessonStorage, ['lms_lesson_type' => 'lesson', 'name' => $spec['name']], 'pilot lesson');
  $lesson ??= Lesson::create(['lms_lesson_type' => 'lesson', 'name' => $spec['name'], 'randomization' => 0, 'backwards_navigation' => FALSE, 'random_activities' => 0]);
  $lesson->set('name', $spec['name']);
  $lesson->set('description', ['value' => $spec['description'], 'format' => 'basic_html']);

  $activityRefs = [];
  foreach ($spec['activities'] as $act) {
    $activity = $findOne($activityStorage, ['name' => $act['name'], 'type' => $act['type']], 'pilot activity');
    $activity ??= Activity::create(['type' => $act['type'], 'name' => $act['name']]);
    $activity->set('name', $act['name']);
    if ($act['type'] === 'true_false') {
      $activity->set('question', ['value' => $act['question'], 'format' => 'basic_html']);
      $activity->set('bool_expected', (bool) $act['bool_expected']);
      $activity->set('description', $act['description']);
    }
    else {
      $activity->set('field_content', ['value' => $act['content'], 'format' => 'basic_html']);
    }
    $activity->save();
    $activityRefs[] = ['target_id' => $activity->id(), 'data' => ['max_score' => $act['max_score']]];
    $report['activities']++;
  }
  $lesson->set('activities', $activityRefs);
  $lesson->save();
  $lessonRefs[] = ['target_id' => $lesson->id(), 'data' => ['mandatory' => TRUE, 'required_score' => 1]];
  $report['lessons'][] = $lesson->id() . ' ' . $spec['name'];
}

$course->set('lessons', $lessonRefs);
$course->save();

echo json_encode(['course' => $course->id(), 'published' => $course->isPublished(), 'lessons' => $report['lessons'], 'activities' => $report['activities']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
