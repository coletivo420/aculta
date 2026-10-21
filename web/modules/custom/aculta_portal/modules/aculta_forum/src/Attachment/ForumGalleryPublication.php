<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Attachment;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\media_gallery\MediaGalleryInterface;
use Drupal\node\NodeInterface;

/**
 * Synchronizes Forum-created Gallery albums with public comment references. */
final class ForumGalleryPublication {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ForumAttachmentPublication $attachmentPublication,
  ) {}

  /**
   * Reconciles galleries embedded in a saved Forum comment and its original. */
  public function syncComment(CommentInterface $comment, bool $deleted = FALSE): void {
    if (!$this->isForumComment($comment)) {
      return;
    }
    $uuids = array_unique([
      ...$this->attachmentPublication->galleryUuids($comment),
      ...($comment->getOriginal() instanceof CommentInterface
        ? $this->attachmentPublication->galleryUuids($comment->getOriginal())
        : []),
    ]);
    foreach ($uuids as $uuid) {
      $gallery = $this->loadForumGallery($uuid);
      if ($gallery instanceof MediaGalleryInterface) {
        $this->syncGallery($gallery, $deleted ? NULL : $comment);
      }
    }
  }

  /**
   * Reconciles Gallery publication after the host topic changes state. */
  public function syncTopic(NodeInterface $topic): void {
    if ($topic->bundle() !== 'aculta_forum_topic' || $topic->isNew()) {
      return;
    }
    $comment_ids = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', 'aculta_discussion_post')
      ->condition('entity_type', 'node')
      ->condition('entity_id', $topic->id())
      ->condition('field_name', 'field_aculta_discussion')
      ->execute();
    foreach ($this->entityTypeManager->getStorage('comment')->loadMultiple($comment_ids) as $comment) {
      if (!$comment instanceof CommentInterface) {
        continue;
      }
      foreach ($this->attachmentPublication->galleryUuids($comment) as $uuid) {
        $gallery = $this->loadForumGallery($uuid);
        if ($gallery instanceof MediaGalleryInterface) {
          $this->syncGallery($gallery, $comment, $topic);
        }
      }
    }
  }

  /**
   * Reconciles a Forum-purpose Gallery after its own state or images change. */
  public function syncGalleryEntity(MediaGalleryInterface $gallery): void {
    $original = $gallery->getOriginal();
    if ($original instanceof MediaGalleryInterface && $this->isForumGallery($original)) {
      $current_media_ids = array_map('strval', array_column($gallery->get('images')->getValue(), 'target_id'));
      foreach ($original->get('images')->referencedEntities() as $media) {
        if ($media instanceof MediaInterface
          && $media->bundle() === ForumAttachmentPublication::BUNDLE
          && !in_array((string) $media->id(), $current_media_ids, TRUE)) {
          $this->attachmentPublication->syncMediaEntity($media, (int) $gallery->id());
        }
      }
    }
    $this->syncGallery($gallery);
  }

  /**
   * Reconciles attachment visibility after a Forum Gallery is deleted. */
  public function syncDeletedGallery(MediaGalleryInterface $gallery): void {
    if (!$this->isForumGallery($gallery)) {
      return;
    }
    foreach ($gallery->get('images')->referencedEntities() as $media) {
      if ($media instanceof MediaInterface && $media->bundle() === ForumAttachmentPublication::BUNDLE) {
        $this->attachmentPublication->syncMediaEntity($media);
      }
    }
  }

  /**
   * Deletes unpublished Forum galleries with no comment embed after one hour. */
  public function cleanupOrphans(int $cutoff, int $limit = 25): int {
    $storage = $this->entityTypeManager->getStorage('media_gallery');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 0)
      ->condition('created', $cutoff, '<=')
      ->condition('field_gallery_purpose', 'forum')
      ->sort('created')
      ->range(0, $limit)
      ->execute();
    $removed = 0;
    foreach ($storage->loadMultiple($ids) as $gallery) {
      if (!$gallery instanceof MediaGalleryInterface || $this->hasCommentReference($gallery)) {
        continue;
      }
      // Recheck publication and comments immediately before deleting.
      if ($gallery->isPublished() || $this->hasCommentReference($gallery)) {
        continue;
      }
      $gallery->delete();
      $removed++;
    }
    return $removed;
  }

  /**
   * Synchronizes one Forum Gallery and its attached Forum Media.
   */
  private function syncGallery(
    MediaGalleryInterface $gallery,
    ?CommentInterface $current_comment = NULL,
    ?NodeInterface $current_topic = NULL,
  ): void {
    if (!$this->isForumGallery($gallery)) {
      return;
    }
    $is_public = FALSE;
    $comment_ids = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', 'aculta_discussion_post')
      ->condition('entity_type', 'node')
      ->condition('field_name', 'field_aculta_discussion')
      ->condition('comment_body.value', '%' . $gallery->uuid() . '%', 'LIKE')
      ->execute();
    $comments = $this->entityTypeManager->getStorage('comment')->loadMultiple($comment_ids);
    if ($current_comment instanceof CommentInterface
      && in_array($gallery->uuid(), $this->attachmentPublication->galleryUuids($current_comment), TRUE)) {
      $comments[$current_comment->id()] = $current_comment;
    }
    foreach ($comments as $comment) {
      if ($comment instanceof CommentInterface
        && in_array($gallery->uuid(), $this->attachmentPublication->galleryUuids($comment), TRUE)
        && $this->attachmentPublication->isPublicForumComment($comment, $current_topic)) {
        $is_public = TRUE;
        break;
      }
    }

    if ($gallery->isPublished() !== $is_public) {
      $is_public ? $gallery->setPublished() : $gallery->setUnpublished();
      $gallery->save();
    }
    foreach ($gallery->get('images')->referencedEntities() as $media) {
      if ($media instanceof MediaInterface && $media->bundle() === ForumAttachmentPublication::BUNDLE) {
        $this->attachmentPublication->syncMediaEntity($media);
      }
    }
  }

  /**
   * Determines whether a Gallery belongs to the contextual Forum workflow. */
  private function isForumGallery(MediaGalleryInterface $gallery): bool {
    return $gallery->hasField('field_gallery_purpose')
      && $gallery->get('field_gallery_purpose')->value === 'forum';
  }

  /**
   * Checks for any current comment reference, including drafts and pending posts. */
  private function hasCommentReference(MediaGalleryInterface $gallery): bool {
    $comment_ids = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', 'aculta_discussion_post')
      ->condition('entity_type', 'node')
      ->condition('field_name', 'field_aculta_discussion')
      ->condition('comment_body.value', '%' . $gallery->uuid() . '%', 'LIKE')
      ->execute();
    foreach ($this->entityTypeManager->getStorage('comment')->loadMultiple($comment_ids) as $comment) {
      if ($comment instanceof CommentInterface
        && $this->isForumComment($comment)
        && in_array($gallery->uuid(), $this->attachmentPublication->galleryUuids($comment), TRUE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks the Forum comment host and field contract. */
  private function isForumComment(CommentInterface $comment): bool {
    return $comment->bundle() === 'aculta_discussion_post'
      && $comment->getCommentedEntityTypeId() === 'node'
      && $comment->getFieldName() === 'field_aculta_discussion';
  }

  /**
   * Loads only a Gallery whose purpose is Forum. */
  private function loadForumGallery(string $uuid): ?MediaGalleryInterface {
    $matches = $this->entityTypeManager->getStorage('media_gallery')->loadByProperties(['uuid' => $uuid]);
    $gallery = reset($matches);
    return $gallery instanceof MediaGalleryInterface && $this->isForumGallery($gallery) ? $gallery : NULL;
  }

}
