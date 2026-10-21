<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Resolves activity discussions within the activity's current course.
 */
final class LmsActivityDiscussionResolver {

  private const NODE_BUNDLE = 'aculta_lms_topic';

  private const TOPIC_RELATIONSHIP = 'group_node:aculta_lms_topic';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ClassActivityDeadlineManager $activityManager,
    private readonly TranslationInterface $stringTranslation,
  ) {}

  /**
   * Builds links only for topics in this course that the account can view.
   *
   * A topic can be attached to the course or one of its classes. Group access
   * remains authoritative, so learners see only the contexts they can access.
   */
  public function build(ActivityInterface $activity, CourseInterface $course, UserInterface $account): array {
    $cache = (new CacheableMetadata())
      ->addCacheableDependency($activity)
      ->addCacheableDependency($course)
      ->addCacheContexts(['user', 'user.permissions', 'user.group_permissions'])
      ->addCacheTags([
        'node_list',
        'group_relationship_list:plugin:' . self::TOPIC_RELATIONSHIP,
        'group_relationship_list:plugin:lms_classes:group:' . $course->id(),
      ]);
    foreach ($course->getRelationships('lms_classes') as $classRelationship) {
      if (!$classRelationship instanceof GroupRelationshipInterface) {
        continue;
      }
      $class = $classRelationship->getEntity();
      $cache->addCacheableDependency($classRelationship);
      if ($class instanceof GroupInterface) {
        $cache->addCacheableDependency($class)
          ->addCacheTags(['group_relationship_list:plugin:lms_classes:entity:' . $class->id()]);
      }
    }
    if (!$this->activityManager->activityBelongsToCourse($activity, $course)) {
      $empty = ['#markup' => ''];
      $cache->applyTo($empty);
      return $empty;
    }

    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::NODE_BUNDLE)
      ->condition('field_lms_activity.target_id', $activity->id())
      ->sort('nid')
      ->execute();
    $items = [];
    $relationshipStorage = $this->relationshipStorage();
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($ids) as $topic) {
      if (!$topic instanceof NodeInterface || $topic->bundle() !== self::NODE_BUNDLE) {
        continue;
      }
      $relationships = $relationshipStorage->loadByEntity($topic, self::TOPIC_RELATIONSHIP);
      if (count($relationships) !== 1) {
        continue;
      }
      $relationship = reset($relationships);
      $group = $relationship instanceof GroupRelationshipInterface ? $relationship->getGroup() : NULL;
      if (!$group instanceof GroupInterface || !$this->belongsToCourse($group, $course, $relationshipStorage, $cache)) {
        continue;
      }
      $access = $topic->access('view', $account, TRUE);
      $cache->addCacheableDependency($topic)
        ->addCacheableDependency($relationship)
        ->addCacheableDependency($group)
        ->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      $items[] = Link::fromTextAndUrl($topic->label(), $topic->toUrl())->toRenderable();
    }

    if ($items === []) {
      $empty = ['#markup' => ''];
      $cache->applyTo($empty);
      return $empty;
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['aculta-lms-activity-discussions']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->stringTranslation->translate('Discussões desta atividade'),
      ],
      'links' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
    // Keep access and relationship cache metadata attached to the render array.
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Checks that a topic's Group is the course or one of its valid classes.
   */
  private function belongsToCourse(GroupInterface $group, CourseInterface $course, GroupRelationshipStorageInterface $storage, CacheableMetadata $cache): bool {
    if ((string) $group->id() === (string) $course->id()) {
      return $group->bundle() === 'lms_course';
    }
    if ($group->bundle() !== 'lms_class') {
      return FALSE;
    }
    $cache->addCacheTags(['group_relationship_list:plugin:lms_classes:entity:' . $group->id()]);
    $parents = $storage->loadByEntity($group, 'lms_classes');
    if (count($parents) !== 1) {
      return FALSE;
    }
    $parent = reset($parents);
    if ($parent instanceof GroupRelationshipInterface) {
      $cache->addCacheableDependency($parent);
    }
    $parentCourse = $parent instanceof GroupRelationshipInterface ? $parent->getGroup() : NULL;
    if ($parentCourse instanceof CourseInterface) {
      $cache->addCacheableDependency($parentCourse);
    }
    return $parentCourse instanceof CourseInterface
      && (string) $parentCourse->id() === (string) $course->id();
  }

  /**
   * Returns the Group relationship storage.
   */
  private function relationshipStorage(): GroupRelationshipStorageInterface {
    $storage = $this->entityTypeManager->getStorage('group_relationship');
    if (!$storage instanceof GroupRelationshipStorageInterface) {
      throw new \UnexpectedValueException('Group relationship storage is unavailable.');
    }
    return $storage;
  }

}
