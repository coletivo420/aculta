<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Attachment\ForumAttachmentPublication;
use Drupal\aculta_forum\Attachment\ForumGalleryPublication;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\media\MediaInterface;
use Drupal\media_gallery\MediaGalleryInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Synchronizes Forum attachment publication with comment and topic lifecycle.
 */
final class ForumAttachmentPublicationHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum.attachment_publication')]
    private readonly ForumAttachmentPublication $publication,
    #[Autowire(service: 'aculta_forum.gallery_publication')]
    private readonly ForumGalleryPublication $galleryPublication,
  ) {}

  /**
   * Reconciles attachment publication after a comment is created.
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity instanceof CommentInterface) {
      $this->publication->syncComment($entity);
      $this->galleryPublication->syncComment($entity);
    }
    elseif ($entity instanceof MediaInterface) {
      $this->publication->syncMediaEntity($entity);
    }
    elseif ($entity instanceof NodeInterface) {
      $this->publication->syncTopic($entity);
      $this->galleryPublication->syncTopic($entity);
    }
    elseif ($entity instanceof MediaGalleryInterface) {
      $this->galleryPublication->syncGalleryEntity($entity);
    }
  }

  /**
   * Reconciles attachments after comment or topic state changes.
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if ($entity instanceof CommentInterface) {
      $this->publication->syncComment($entity);
      $this->galleryPublication->syncComment($entity);
    }
    elseif ($entity instanceof MediaInterface) {
      $this->publication->syncMediaEntity($entity);
    }
    elseif ($entity instanceof NodeInterface) {
      $this->publication->syncTopic($entity);
      $this->galleryPublication->syncTopic($entity);
    }
    elseif ($entity instanceof MediaGalleryInterface) {
      $this->galleryPublication->syncGalleryEntity($entity);
    }
  }

  /**
   * Removes publication inherited from a deleted comment reference.
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if ($entity instanceof CommentInterface) {
      $this->publication->syncComment($entity, TRUE);
      $this->galleryPublication->syncComment($entity, TRUE);
    }
    elseif ($entity instanceof MediaGalleryInterface) {
      $this->galleryPublication->syncDeletedGallery($entity);
    }
  }

}
