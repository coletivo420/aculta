<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
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
final class GalleryContentIndexProvider implements ContentIndexProviderInterface {

  /**
   * {@inheritdoc} */
  public function getProviderId(): string {
    return 'gallery';
  }

  /**
   * {@inheritdoc} */
  public function getSupportedEntityTypes(): array {
    return ['media_gallery' => ['media_gallery']];
  }

  /**
   * {@inheritdoc} */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$entity instanceof MediaGalleryInterface
      || $entity->getEntityTypeId() !== 'media_gallery'
      || $entity->bundle() !== 'media_gallery') {
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
    );
  }

}
