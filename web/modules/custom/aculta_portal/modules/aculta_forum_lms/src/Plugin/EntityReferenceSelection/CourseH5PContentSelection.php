<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Plugin\EntityReferenceSelection;

use Drupal\aculta_lms_h5p\Access\H5PCourseAccessManager;
use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Selects only H5P content editable in the current course Group context. */
#[EntityReferenceSelection(
  id: 'aculta_forum_lms:course_h5p_content',
  label: new TranslatableMarkup('Conteúdos H5P deste curso/turma'),
  group: 'aculta_forum_lms',
  weight: 10,
  entity_types: ['h5p_content'],
)]
final class CourseH5PContentSelection extends DefaultSelection {

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
    private readonly RequestStack $requestStack,
    private readonly H5PCourseAccessManager $h5pAccess,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $module_handler, $current_user, $entity_field_manager, $entity_type_bundle_info, $entity_repository);
  }

  /**
   * {@inheritdoc} */
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
      $container->get('request_stack'),
      $container->get('aculta_lms_h5p.course_access'),
    );
  }

  /**
   * {@inheritdoc} */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0): array {
    $course = $this->courseContext();
    if (!$course instanceof CourseInterface || !$course->hasField('field_h5p_activities') || !$course->access('update', $this->currentUser)->isAllowed()) {
      return [];
    }
    $ids = [];
    foreach ($course->get('field_h5p_activities') as $item) {
      $id = (int) $item->h5p_content_id;
      if ($id > 0 && $this->h5pAccess->editorAccess((int) $course->id(), $id, $this->currentUser)->isAllowed()) {
        $ids[] = $id;
      }
    }
    $entities = $this->entityTypeManager->getStorage('h5p_content')->loadMultiple(array_unique($ids));
    $options = [];
    foreach ($entities as $id => $entity) {
      $label = (string) $entity->get('title')->value;
      if ($match !== NULL && $match !== '' && !$this->matches($label, (string) $match, $match_operator)) {
        continue;
      }
      $options[$id] = $label;
    }
    if ($limit > 0) {
      $options = array_slice($options, 0, $limit, TRUE);
    }
    return $options === [] ? [] : ['h5p_content' => $options];
  }

  /**
   * {@inheritdoc} */
  public function countReferenceableEntities($match = NULL, $match_operator = 'CONTAINS'): int {
    $entities = $this->getReferenceableEntities($match, $match_operator);
    return count($entities['h5p_content'] ?? []);
  }

  /**
   * {@inheritdoc} */
  public function validateReferenceableEntities(array $ids): array {
    $entities = $this->getReferenceableEntities();
    return array_values(array_intersect(array_map('intval', $ids), array_map('intval', array_keys($entities['h5p_content'] ?? []))));
  }

  /**
   * Compares an H5P title with the autocomplete query.
   */
  private function matches(string $label, string $match, string $operator): bool {
    $label = mb_strtolower($label);
    $match = mb_strtolower($match);
    return match (strtoupper($operator)) {
      'STARTS_WITH' => str_starts_with($label, $match),
      'ENDS_WITH' => str_ends_with($label, $match),
      '=' => $label === $match,
      default => str_contains($label, $match),
    };
  }

  /**
   * Resolves the Group context from the route or existing topic relation.
   */
  private function courseContext(): ?CourseInterface {
    $request = $this->requestStack->getCurrentRequest();
    $group = $request?->attributes->get('group');
    if (!$group instanceof GroupInterface && ctype_digit((string) $request?->query->get('group_id'))) {
      $group = $this->entityTypeManager->getStorage('group')->load($request->query->get('group_id'));
    }
    if (!$group instanceof GroupInterface) {
      $entity = $this->configuration['entity'] ?? NULL;
      if (!$entity instanceof NodeInterface || $entity->isNew()) {
        return NULL;
      }
      $relationships = $this->relationshipStorage()->loadByEntity($entity, 'group_node:aculta_lms_topic');
      if (count($relationships) !== 1) {
        return NULL;
      }
      $relationship = reset($relationships);
      $group = $relationship instanceof GroupRelationshipInterface ? $relationship->getGroup() : NULL;
    }
    if (!$group instanceof GroupInterface) {
      return NULL;
    }
    if ($group instanceof CourseInterface) {
      return $group;
    }
    if ($group->bundle() !== 'lms_class') {
      return NULL;
    }
    $parents = $this->relationshipStorage()->loadByEntity($group, 'lms_classes');
    if (count($parents) !== 1) {
      return NULL;
    }
    $parent = reset($parents);
    $course = $parent instanceof GroupRelationshipInterface ? $parent->getGroup() : NULL;
    return $course instanceof CourseInterface ? $course : NULL;
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
