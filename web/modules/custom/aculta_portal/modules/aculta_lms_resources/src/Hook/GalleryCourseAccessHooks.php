<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_resources\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\media_gallery\MediaGalleryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Grants course members view access to galleries linked to their course.
 */
final class GalleryCourseAccessHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * Allows contextual LMS access to a published gallery reference.
   *
   * The Group query deliberately skips entity query access: it only finds
   * candidate references. Each candidate is then authorized with the current
   * course membership or educator permission before access is granted.
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if ($entity->getEntityTypeId() !== 'media_gallery' || $operation !== 'view'
      || !$entity instanceof MediaGalleryInterface || !$entity->isEnabled()
      || $this->domainPurposeManager->getCurrentPurpose() !== 'magazine') {
      return AccessResult::neutral();
    }

    $metadata = (new CacheableMetadata())
      ->addCacheableDependency($entity)
      ->addCacheTags($this->entityTypeManager->getDefinition('group')->getListCacheTags())
      ->addCacheContexts(['domain', 'user', 'user.permissions', 'user.group_permissions']);
    $course_storage = $this->entityTypeManager->getStorage('group');
    $course_ids = $course_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'lms_course')
      ->condition('field_lms_galleries.target_id', $entity->id())
      ->execute();

    foreach ($course_storage->loadMultiple($course_ids) as $course) {
      if (!$course instanceof CourseInterface) {
        continue;
      }
      $take_access = $course->takeAccess($account);
      $can_manage = $course->hasPermission('administer members', $account)
        || $course->hasPermission('grade students', $account);
      $metadata->addCacheableDependency($course)
        ->addCacheableDependency($take_access);
      if ($take_access->isAllowed() || $can_manage) {
        return AccessResult::allowed()->addCacheableDependency($metadata);
      }
    }

    $result = AccessResult::neutral();
    $metadata->setCacheMaxAge(0);
    return $result->addCacheableDependency($metadata);
  }

}
