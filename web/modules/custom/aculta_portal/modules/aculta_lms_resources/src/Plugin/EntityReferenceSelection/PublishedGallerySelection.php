<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_resources\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity-reference selection for published galleries attached to LMS courses.
 */
#[EntityReferenceSelection(
  id: 'aculta_lms_resources:published_gallery',
  label: new TranslatableMarkup('Galerias publicadas da Revista'),
  group: 'default',
  weight: 10,
  entity_types: ['media_gallery'],
)]
final class PublishedGallerySelection extends DefaultSelection {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    AccountInterface $current_user,
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityRepositoryInterface $entity_repository,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager,
      $module_handler,
      $current_user,
      $entity_field_manager,
      $entity_type_bundle_info,
      $entity_repository,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity.repository'),
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0): array {
    if (!$this->canSelectForCourse()) {
      return [];
    }
    return $this->domainPurposeManager->runInPurpose(
      'magazine',
      fn (): array => parent::getReferenceableEntities($match, $match_operator, $limit),
    ) ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function countReferenceableEntities($match = NULL, $match_operator = 'CONTAINS'): int {
    if (!$this->canSelectForCourse()) {
      return 0;
    }
    return $this->domainPurposeManager->runInPurpose(
      'magazine',
      fn (): int => parent::countReferenceableEntities($match, $match_operator),
    ) ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableEntities(array $ids): array {
    if (!$this->canSelectForCourse()) {
      return [];
    }
    return $this->domainPurposeManager->runInPurpose(
      'magazine',
      fn (): array => parent::validateReferenceableEntities($ids),
    ) ?? [];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS'): QueryInterface {
    $query = parent::buildEntityQuery($match, $match_operator);
    $query->condition('status', 1);
    return $query;
  }

  /**
   * Restricts the selector to the educator who can manage this course.
   */
  private function canSelectForCourse(): bool {
    $course = $this->configuration['entity'] ?? NULL;
    if (!$course instanceof CourseInterface) {
      return FALSE;
    }
    if ($course->isNew()) {
      return $this->currentUser->hasPermission('create lms_course group')
        || $this->currentUser->hasPermission('administer lms');
    }
    return $course->hasPermission('administer members', $this->currentUser)
      || $course->hasPermission('grade students', $this->currentUser);
  }

}
