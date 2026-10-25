<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexIndexabilityProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexOwnerResolver;
use Drupal\aculta_portal\ContentIndex\ContentIndexAccessContextProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\media\MediaInterface;
use Drupal\media_gallery\MediaGalleryInterface;

/**
 * Projects minimal gallery metadata and explicit image relationships.
 *
 * It does not copy descriptions, credits, rights text, file details or image
 * metadata. Source entities and their Entity Access handlers remain owners.
 */
final class GalleryContentIndexProvider implements ContentIndexProviderInterface, ContentIndexIndexabilityProviderInterface, ContentIndexAccessContextProviderInterface {

  /**
   * {@inheritdoc} */
  public function getProviderId(): string {
    return 'gallery';
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectionVersion(): int {
    return 2;
  }

  /**
   * {@inheritdoc} */
  public function getSupportedEntityTypes(): array {
    return ['media_gallery' => ['media_gallery']];
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessPurpose(ContentEntityInterface $entity): ?string {
    return $entity instanceof MediaGalleryInterface && $entity->bundle() === 'media_gallery'
      ? 'magazine'
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexable(ContentEntityInterface $entity): bool {
    return $entity instanceof MediaGalleryInterface
      && $entity->getEntityTypeId() === 'media_gallery'
      && $entity->bundle() === 'media_gallery';
  }

  /**
   * {@inheritdoc} */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$this->isIndexable($entity)) {
      return NULL;
    }

    $relations = [];
    foreach ([
      'images' => 'gallery_image',
      'field_gallery_cover' => 'gallery_cover',
    ] as $fieldName => $relationType) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }
      foreach ($entity->get($fieldName)->referencedEntities() as $media) {
        if ($media instanceof MediaInterface && $media->bundle() === 'image') {
          $relations[] = new ContentIndexRelation(
            $relationType,
            'media',
            (string) $media->id(),
          );
        }
      }
    }

    return new ContentIndexProjection(
      'media_gallery',
      (string) $entity->id(),
      $entity->language()->getId(),
      'media_gallery',
      (string) $entity->uuid(),
      (string) $entity->getTitle(),
      NULL,
      $entity->isEnabled(),
      max(0, $entity->getCreatedTime()),
      max(0, $entity->getChangedTime()),
      NULL,
      $relations,
      ownerUid: ContentIndexOwnerResolver::resolve($entity),
    );
  }

}
