<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Projects current Revista publication metadata and explicit editorial links.
 */
final class MagazineContentIndexProvider implements ContentIndexProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return 'magazine';
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes(): array {
    return ['node' => ['revista']];
  }

  /**
   * {@inheritdoc}
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'revista' || !$entity->isDefaultRevision()) {
      return NULL;
    }

    $domainId = NULL;
    if ($entity->hasField('field_domain_source') && !$entity->get('field_domain_source')->isEmpty()) {
      $domainId = (string) $entity->get('field_domain_source')->target_id;
    }

    $publishedAt = NULL;
    if ($entity->hasField('field_published_at') && !$entity->get('field_published_at')->isEmpty()) {
      $rawDate = (string) $entity->get('field_published_at')->value;
      $timestamp = strtotime($rawDate . ' UTC');
      $publishedAt = $timestamp === FALSE ? NULL : $timestamp;
    }

    $relations = [];
    $this->addRelations($entity, 'field_related_activity', 'related_activity', 'node', $relations);
    $this->addRelations($entity, 'field_media_image', 'editorial_image', 'media', $relations);

    return new ContentIndexProjection(
      'node',
      (string) $entity->id(),
      $entity->language()->getId(),
      $entity->bundle(),
      (string) $entity->uuid(),
      (string) $entity->label(),
      $domainId,
      $entity->isPublished(),
      max(0, (int) $entity->getCreatedTime()),
      max(0, (int) $entity->getChangedTime()),
      $publishedAt,
      array_values($relations),
    );
  }

  /**
   * Adds a relation only for explicitly configured, non-empty references.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   Source Revista publication translation.
   * @param string $fieldName
   *   Configured reference field to read.
   * @param string $relationType
   *   Stable relation type recorded in the catalog.
   * @param string $targetEntityType
   *   Entity type declared by the field configuration.
   * @param array<string, \Drupal\aculta_portal\ContentIndex\ContentIndexRelation> $relations
   *   Relations keyed by type and target identity to suppress duplicates.
   */
  private function addRelations(
    NodeInterface $entity,
    string $fieldName,
    string $relationType,
    string $targetEntityType,
    array &$relations,
  ): void {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return;
    }

    foreach ($entity->get($fieldName)->getValue() as $item) {
      $targetId = is_array($item) ? ($item['target_id'] ?? NULL) : NULL;
      if ($targetId === NULL || $targetId === '') {
        continue;
      }
      $targetId = (string) $targetId;
      $key = implode(':', [$relationType, $targetEntityType, $targetId]);
      $relations[$key] = new ContentIndexRelation($relationType, $targetEntityType, $targetId);
    }
  }

}
