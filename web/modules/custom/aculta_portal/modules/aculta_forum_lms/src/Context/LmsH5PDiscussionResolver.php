<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\node\NodeInterface;

/**
 * Resolves forum topics linked directly to an H5P item in a course. */
final class LmsH5PDiscussionResolver {

  private const TOPIC_BUNDLE = 'aculta_lms_topic';

  private const TOPIC_RELATIONSHIP = 'group_node:aculta_lms_topic';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TranslationInterface $stringTranslation,
  ) {}

  /**
   * Builds an access-checked discussion list for a course H5P item. */
  public function build(int $contentId, CourseInterface $course, AccountInterface $account): array {
    $cache = (new CacheableMetadata())
      ->addCacheableDependency($course)
      ->addCacheContexts(['user', 'user.permissions', 'user.group_permissions'])
      ->addCacheTags([
        'node_list',
        'group_relationship_list:plugin:' . self::TOPIC_RELATIONSHIP,
        'group_relationship_list:plugin:lms_classes:group:' . $course->id(),
      ]);
    if ($contentId < 1 || !$course->hasField('field_h5p_activities')) {
      return $this->emptyBuild($cache);
    }
    $belongs = FALSE;
    foreach ($course->get('field_h5p_activities') as $item) {
      if ((int) $item->h5p_content_id === $contentId) {
        $belongs = TRUE;
        break;
      }
    }
    if (!$belongs) {
      return $this->emptyBuild($cache);
    }

    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::TOPIC_BUNDLE)
      ->condition('field_h5p_content.target_id', $contentId)
      ->sort('nid')
      ->execute();
    $storage = $this->relationshipStorage();
    $links = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($ids) as $topic) {
      if (!$topic instanceof NodeInterface || $topic->bundle() !== self::TOPIC_BUNDLE) {
        continue;
      }
      $relationships = $storage->loadByEntity($topic, self::TOPIC_RELATIONSHIP);
      if (count($relationships) !== 1) {
        continue;
      }
      $relationship = reset($relationships);
      $group = $relationship instanceof GroupRelationshipInterface ? $relationship->getGroup() : NULL;
      if (!$group instanceof GroupInterface || !$this->belongsToCourse($group, $course, $storage, $cache)) {
        continue;
      }
      $access = $topic->access('view', $account, TRUE);
      $cache->addCacheableDependency($topic)
        ->addCacheableDependency($relationship)
        ->addCacheableDependency($group)
        ->addCacheableDependency($access);
      if ($access->isAllowed()) {
        $links[] = Link::fromTextAndUrl($topic->label(), $topic->toUrl())->toRenderable();
      }
    }
    if ($links === []) {
      return $this->emptyBuild($cache);
    }
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'lms-h5p-discussions'],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->stringTranslation->translate('Discussões desta atividade'),
      ],
      'links' => ['#theme' => 'item_list', '#items' => $links],
    ];
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Confirms the Group is the course or one of its unique child classes.
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
    $parentCourse = $parent instanceof GroupRelationshipInterface ? $parent->getGroup() : NULL;
    if ($parent instanceof GroupRelationshipInterface) {
      $cache->addCacheableDependency($parent);
    }
    if ($parentCourse instanceof CourseInterface) {
      $cache->addCacheableDependency($parentCourse);
    }
    return $parentCourse instanceof CourseInterface && (string) $parentCourse->id() === (string) $course->id();
  }

  /**
   * Creates an empty render array with its access cache metadata.
   */
  private function emptyBuild(CacheableMetadata $cache): array {
    $build = ['#markup' => ''];
    $cache->applyTo($build);
    return $build;
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
