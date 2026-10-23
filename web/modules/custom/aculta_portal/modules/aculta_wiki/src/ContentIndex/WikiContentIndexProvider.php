<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexIndexabilityProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexOwnerResolver;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\aculta_portal\ContentIndex\ContentIndexAccessContextProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Projects allowlisted metadata and explicitly configured Wiki relationships.
 */
final class WikiContentIndexProvider implements ContentIndexProviderInterface, ContentIndexIndexabilityProviderInterface, ContentIndexAccessContextProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return 'wiki';
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectionVersion(): int {
    return 3;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes(): array {
    return ['node' => ['wiki_entry']];
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessPurpose(ContentEntityInterface $entity): ?string {
    return $entity instanceof NodeInterface && $entity->bundle() === 'wiki_entry'
      ? 'wiki'
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexable(ContentEntityInterface $entity): bool {
    return $entity instanceof NodeInterface && $entity->bundle() === 'wiki_entry' && $entity->isDefaultRevision();
  }

  /**
   * {@inheritdoc}
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$this->isIndexable($entity)) {
      return NULL;
    }

    $domainId = NULL;
    if ($entity->hasField('field_domain_source') && !$entity->get('field_domain_source')->isEmpty()) {
      $domainId = (string) $entity->get('field_domain_source')->target_id;
    }

    $relations = [];
    foreach ([
      'field_wiki_related' => ['related_entry', 'node'],
      'field_wiki_categories' => ['wiki_category', 'taxonomy_term'],
      'field_wiki_image' => ['wiki_image', 'media'],
    ] as $fieldName => [$relationType, $targetEntityType]) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }
      foreach ($entity->get($fieldName)->getValue() as $item) {
        $targetId = $item['target_id'] ?? NULL;
        if ($targetId === NULL || $targetId === '') {
          continue;
        }
        $targetId = (string) $targetId;
        $key = $relationType . ':' . $targetEntityType . ':' . $targetId;
        $relations[$key] = new ContentIndexRelation($relationType, $targetEntityType, $targetId);
      }
    }

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
      relations: array_values($relations),
      ownerUid: ContentIndexOwnerResolver::resolve($entity),
    );
  }

}
