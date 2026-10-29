<?php

declare(strict_types=1);

namespace Drupal\aculta_events\ContentIndex;

use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_portal\ContentIndex\ContentIndexAccessContextProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexIndexabilityProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexOwnerResolver;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Projeta locais de evento na infraestrutura transversal aculta_index.
 */
final class LocationContentIndexProvider implements ContentIndexProviderInterface, ContentIndexIndexabilityProviderInterface, ContentIndexAccessContextProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return 'event_location';
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectionVersion(): int {
    return 2;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes(): array {
    return ['aculta_event_location' => ['aculta_event_location']];
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessPurpose(ContentEntityInterface $entity): ?string {
    return $entity instanceof EventLocation ? 'eventos' : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexable(ContentEntityInterface $entity): bool {
    return $entity instanceof EventLocation
      && (string) ($entity->get('field_status')->value ?? 'publicado') === 'publicado';
  }

  /**
   * {@inheritdoc}
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$this->isIndexable($entity)) {
      return NULL;
    }

    $relations = [];
    $this->addRelations($entity, 'field_tipo', 'location_type', 'taxonomy_term', $relations);

    $isPublished = (string) ($entity->get('field_status')->value ?? 'publicado') === 'publicado';

    return new ContentIndexProjection(
      'aculta_event_location',
      (string) $entity->id(),
      $entity->language()->getId(),
      'aculta_event_location',
      (string) $entity->uuid(),
      (string) $entity->label(),
      'eventos_aculta_org',
      $isPublished,
      0,
      0,
      relations: array_values($relations),
      ownerUid: ContentIndexOwnerResolver::resolve($entity),
    );
  }

  /**
   * Adiciona relações para campos de referência configurados e preenchidos.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entidade fonte.
   * @param string $fieldName
   *   Nome do campo de referência.
   * @param string $relationType
   *   Tipo estável da relação no índice.
   * @param string $targetEntityType
   *   Tipo da entidade de destino.
   * @param array<string, \Drupal\aculta_portal\ContentIndex\ContentIndexRelation> $relations
   *   Vetor de relações indexadas por chave para evitar duplicatas.
   */
  private function addRelations(
    ContentEntityInterface $entity,
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
