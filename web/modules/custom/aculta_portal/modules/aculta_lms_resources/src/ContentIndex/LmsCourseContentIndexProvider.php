<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_resources\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexAccessContextProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexAccessPolicyProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\media_gallery\MediaGalleryInterface;
use Drupal\node\NodeInterface;

/**
 * Projects LMS courses and their explicitly configured editorial resources.
 *
 * Progress, lessons, membership, answers and certificates remain owned by LMS.
 */
final class LmsCourseContentIndexProvider implements ContentIndexProviderInterface, ContentIndexAccessContextProviderInterface, ContentIndexAccessPolicyProviderInterface {

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return 'lms_course';
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectionVersion(): int {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes(): array {
    return ['group' => ['lms_course']];
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessPurpose(ContentEntityInterface $entity): ?string {
    return $entity instanceof CourseInterface && $entity->bundle() === 'lms_course'
      ? 'courses'
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(ContentEntityInterface $entity, AccountInterface $account): AccessResultInterface {
    if (!$entity instanceof CourseInterface || $entity->bundle() !== 'lms_course') {
      return AccessResult::forbidden();
    }

    $takeAccess = $entity->takeAccess($account);
    $canManage = $entity->hasPermission('administer members', $account)
      || $entity->hasPermission('grade students', $account);
    return AccessResult::allowedIf($takeAccess->isAllowed() || $canManage)
      ->addCacheableDependency($takeAccess)
      ->addCacheableDependency($entity)
      ->addCacheContexts(['user', 'user.permissions', 'user.group_permissions']);
  }

  /**
   * {@inheritdoc}
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$entity instanceof CourseInterface
      || $entity->getEntityTypeId() !== 'group'
      || $entity->bundle() !== 'lms_course') {
      return NULL;
    }

    $domain = $this->domainPurposeManager->getDomain('courses');
    if ($domain === NULL) {
      return NULL;
    }

    $relations = [];
    $this->appendNodeRelations($entity, 'field_lms_readings', $relations);
    $this->appendGalleryRelations($entity, $relations);

    return new ContentIndexProjection(
      'group',
      (string) $entity->id(),
      $entity->language()->getId(),
      'lms_course',
      (string) $entity->uuid(),
      (string) $entity->label(),
      $domain->id(),
      $entity->isPublished(),
      max(0, (int) $entity->getCreatedTime()),
      max(0, (int) $entity->getChangedTime()),
      NULL,
      $relations,
    );
  }

  /**
   * Adds only explicitly supported editorial node references.
   *
   * @param \Drupal\lms\Entity\Bundle\CourseInterface $course
   *   Course containing the field.
   * @param string $fieldName
   *   Reference field that contains editorial nodes.
   * @param list<\Drupal\aculta_portal\ContentIndex\ContentIndexRelation> $relations
   *   Relations to append.
   */
  private function appendNodeRelations(CourseInterface $course, string $fieldName, array &$relations): void {
    if (!$course->hasField($fieldName) || $course->get($fieldName)->isEmpty()) {
      return;
    }
    foreach ($course->get($fieldName)->referencedEntities() as $target) {
      if (!$target instanceof NodeInterface || !in_array($target->bundle(), ['revista', 'wiki_entry'], TRUE)) {
        continue;
      }
      $relations[] = new ContentIndexRelation('course_reading', 'node', (string) $target->id());
    }
  }

  /**
   * Adds references to galleries selected for course resources.
   *
   * @param \Drupal\lms\Entity\Bundle\CourseInterface $course
   *   Course containing the gallery field.
   * @param list<\Drupal\aculta_portal\ContentIndex\ContentIndexRelation> $relations
   *   Relations to append.
   */
  private function appendGalleryRelations(CourseInterface $course, array &$relations): void {
    $fieldName = 'field_lms_galleries';
    if (!$course->hasField($fieldName) || $course->get($fieldName)->isEmpty()) {
      return;
    }
    foreach ($course->get($fieldName)->referencedEntities() as $target) {
      if (!$target instanceof MediaGalleryInterface || $target->bundle() !== 'media_gallery') {
        continue;
      }
      $relations[] = new ContentIndexRelation('course_gallery', 'media_gallery', (string) $target->id());
    }
  }

}
