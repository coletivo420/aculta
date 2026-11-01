<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexIndexabilityProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexOwnerResolver;
use Drupal\aculta_portal\ContentIndex\ContentIndexAccessContextProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\entity_usage\EntityUsageInterface;
use Drupal\media\MediaInterface;

/**
 * Projects current Revista publication metadata and explicit editorial links.
 */
final class MagazineContentIndexProvider implements ContentIndexProviderInterface, ContentIndexIndexabilityProviderInterface, ContentIndexAccessContextProviderInterface {

  public function __construct(
    private readonly EntityUsageInterface $entityUsage,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return 'magazine';
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
    return ['node' => ['revista']];
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessPurpose(ContentEntityInterface $entity): ?string {
    return $entity instanceof NodeInterface && $entity->bundle() === 'revista'
      ? 'magazine'
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexable(ContentEntityInterface $entity): bool {
    return $entity instanceof NodeInterface && $entity->bundle() === 'revista' && $entity->isDefaultRevision();
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

    $publishedAt = NULL;
    if ($entity->hasField('field_published_at') && !$entity->get('field_published_at')->isEmpty()) {
      $rawDate = (string) $entity->get('field_published_at')->value;
      $timestamp = strtotime($rawDate . ' UTC');
      $publishedAt = $timestamp === FALSE ? NULL : $timestamp;
    }

    $relations = [];
    $this->addRelations($entity, 'field_related_activity', 'related_activity', 'node', $relations);
    $this->addRelations($entity, 'field_media_image', 'editorial_image', 'media', $relations);
    $this->addRelations($entity, 'field_editorial_gallery', 'editorial_gallery', 'media_gallery', $relations);
    $this->addEmbeddedVideos($entity, $relations);

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
      ownerUid: ContentIndexOwnerResolver::resolve($entity),
    );
  }

  /**
   * Adds remote videos explicitly tracked as embeds in the current revision.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The current Revista publication revision.
   * @param array<string, \Drupal\aculta_portal\ContentIndex\ContentIndexRelation> $relations
   *   Relations keyed by type and target identity to suppress duplicates.
   */
  private function addEmbeddedVideos(NodeInterface $entity, array &$relations): void {
    $targets = $this->entityUsage->listTargets($entity, (int) $entity->getRevisionId());
    $videoIds = [];
    foreach ($targets['media'] ?? [] as $targetId => $usages) {
      foreach ($usages as $usage) {
        if (($usage['method'] ?? '') === 'media_embed'
          && ($usage['field_name'] ?? '') === 'body') {
          $videoIds[(string) $targetId] = (string) $targetId;
          break;
        }
      }
    }
    if ($videoIds === []) {
      return;
    }

    foreach ($this->entityTypeManager->getStorage('media')->loadMultiple(array_values($videoIds)) as $media) {
      if (!$media instanceof MediaInterface || $media->bundle() !== 'remote_video') {
        continue;
      }
      $targetId = (string) $media->id();
      $key = 'embedded_video:media:' . $targetId;
      $relations[$key] = new ContentIndexRelation('embedded_video', 'media', $targetId);
    }
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
