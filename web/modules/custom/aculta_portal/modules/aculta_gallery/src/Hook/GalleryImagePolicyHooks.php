<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\media_gallery\MediaGalleryInterface;

/**
 * Enforces image bundles by Gallery context when Media is referenced. */
final class GalleryImagePolicyHooks {

  /**
   * Rejects non-image documents from Gallery albums.
   */
  #[Hook('media_gallery_presave')]
  public function validateGalleryImages(EntityInterface $entity): void {
    if (!$entity instanceof MediaGalleryInterface) {
      return;
    }
    $is_forum = $entity->hasField('field_gallery_purpose')
      && $entity->get('field_gallery_purpose')->value === 'forum';
    $allowed = $is_forum ? ['forum_attachment'] : ['image'];
    foreach ($entity->get('images')->referencedEntities() as $media) {
      if (!in_array($media->bundle(), $allowed, TRUE)) {
        throw new \InvalidArgumentException('ACULTA Gallery accepts only image media allowed for its publication context.');
      }
      if ($is_forum && (!$media->hasField('field_media_forum_image') || $media->get('field_media_forum_image')->isEmpty())) {
        throw new \InvalidArgumentException('Forum Galleries require private image attachments.');
      }
    }
  }

}
