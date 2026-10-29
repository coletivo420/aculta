<?php

declare(strict_types=1);

namespace Drupal\aculta_events\ContentIndex;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_portal\ContentIndex\ContentIndexAccessContextProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexIndexabilityProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexOwnerResolver;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Projeta eventos na infraestrutura transversal aculta_index.
 */
final class EventContentIndexProvider implements ContentIndexProviderInterface, ContentIndexIndexabilityProviderInterface, ContentIndexAccessContextProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return 'event';
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
    return ['aculta_event' => ['aculta_event']];
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessPurpose(ContentEntityInterface $entity): ?string {
    return $entity instanceof Event ? 'eventos' : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexable(ContentEntityInterface $entity): bool {
    // Rascunhos são confinados e não entram na descoberta cultural pública.
    return $entity instanceof Event
      && (string) $entity->get('field_status')->value !== 'rascunho';
  }

  /**
   * {@inheritdoc}
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$this->isIndexable($entity)) {
      return NULL;
    }

    $relations = [];
    $this->addRelations($entity, 'field_tipo', 'event_type', 'taxonomy_term', $relations);
    $this->addRelations($entity, 'field_organizador', 'organizer', 'user', $relations);

    $isPublished = (string) $entity->get('field_status')->value === 'publicado';

    return new ContentIndexProjection(
      'aculta_event',
      (string) $entity->id(),
      $entity->language()->getId(),
      'aculta_event',
      (string) $entity->uuid(),
      (string) $entity->label(),
      'eventos_aculta_org',
      $isPublished,
      0,
      0,
      NULL,
      array_values($relations),
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
