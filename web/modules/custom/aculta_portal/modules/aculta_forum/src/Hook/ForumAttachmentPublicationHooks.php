<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Attachment\ForumAttachmentPublication;
use Drupal\aculta_forum\Attachment\ForumGalleryPublication;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\media\MediaInterface;
use Drupal\media_gallery\MediaGalleryInterface;
use Drupal\node\NodeInterface;
use Drupal\aculta_forum\Entity\ForumPost;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Synchronizes Forum attachment publication with post, comment, and topic.
 */
final class ForumAttachmentPublicationHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum.attachment_publication')]
    private readonly ForumAttachmentPublication $publication,
    #[Autowire(service: 'aculta_forum.gallery_publication')]
    private readonly ForumGalleryPublication $galleryPublication,
  ) {}

  /**
   * Reconciles attachment publication after a post, comment, or Media insert.
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($this->isSynchronizingForumPost($entity)) {
      return;
    }
    if ($entity instanceof CommentInterface) {
      $this->publication->syncComment($entity);
      $this->galleryPublication->syncComment($entity);
    }
    elseif ($entity instanceof ForumPost) {
      $this->publication->syncPost($entity);
      $this->galleryPublication->syncPost($entity);
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
   * Reconciles attachments after post, comment, or topic changes.
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if ($this->isSynchronizingForumPost($entity)) {
      return;
    }
    if ($entity instanceof CommentInterface) {
      $this->publication->syncComment($entity);
      $this->galleryPublication->syncComment($entity);
    }
    elseif ($entity instanceof ForumPost) {
      $this->publication->syncPost($entity);
      $this->galleryPublication->syncPost($entity);
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
    elseif ($entity instanceof ForumPost) {
      $this->publication->syncPost($entity, TRUE);
      $this->galleryPublication->syncPost($entity, TRUE);
    }
    elseif ($entity instanceof MediaGalleryInterface) {
      $this->galleryPublication->syncDeletedGallery($entity);
    }
  }

  /**
   * Avoids attachment publication side effects while importing Forum posts.
   */
  private function isSynchronizingForumPost(EntityInterface $entity): bool {
    return $entity instanceof ForumPost
      && $entity instanceof SynchronizableInterface
      && $entity->isSyncing();
  }

}
