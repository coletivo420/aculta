<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Attachment;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\media_gallery\MediaGalleryInterface;
use Drupal\node\NodeInterface;
use Drupal\aculta_forum\Entity\ForumPost;

/**
 * Synchronizes Forum galleries with public post and comment references. */
final class ForumGalleryPublication {

  private const TOPIC_SYNC_BATCH_SIZE = 100;

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
   * Reconciles Gallery embeds in a revisionable Forum post and its original.
   */
  public function syncPost(ForumPost $post, bool $deleted = FALSE): void {
    $uuids = array_unique([
      ...$this->attachmentPublication->postGalleryUuids($post),
      ...($post->getOriginal() instanceof ForumPost
        ? $this->attachmentPublication->postGalleryUuids($post->getOriginal())
        : []),
    ]);
    foreach ($uuids as $uuid) {
      $gallery = $this->loadForumGallery($uuid);
      if ($gallery instanceof MediaGalleryInterface) {
        $this->syncGallery($gallery, NULL, NULL, $deleted ? NULL : $post, $deleted ? (int) $post->id() : NULL);
      }
    }
  }

  /**
   * Reconciles Gallery publication in bounded ID batches after a topic change.
   */
  public function syncTopic(NodeInterface $topic): void {
    if ($topic->bundle() !== 'aculta_forum_topic' || $topic->isNew()) {
      return;
    }
    $comment_storage = $this->entityTypeManager->getStorage('comment');
    $max_comment_ids = $comment_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', 'aculta_discussion_post')
      ->condition('entity_type', 'node')
      ->condition('entity_id', $topic->id())
      ->condition('field_name', 'field_aculta_discussion')
      ->sort('cid', 'DESC')
      ->range(0, 1)
      ->execute();
    $max_comment_id = $max_comment_ids === [] ? 0 : (int) reset($max_comment_ids);
    $last_comment_id = 0;
    while ($last_comment_id < $max_comment_id) {
      $comment_ids = $comment_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('comment_type', 'aculta_discussion_post')
        ->condition('entity_type', 'node')
        ->condition('entity_id', $topic->id())
        ->condition('field_name', 'field_aculta_discussion')
        ->condition('cid', $last_comment_id, '>')
        ->condition('cid', $max_comment_id, '<=')
        ->sort('cid')
        ->range(0, self::TOPIC_SYNC_BATCH_SIZE)
        ->execute();
      if ($comment_ids !== []) {
        $last_comment_id = (int) end($comment_ids);
      }
      else {
        break;
      }
      foreach ($comment_storage->loadMultiple($comment_ids) as $comment) {
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

    $post_storage = $this->entityTypeManager->getStorage('aculta_forum_post');
    $max_post_ids = $post_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('topic.target_id', $topic->id())
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();
    $max_post_id = $max_post_ids === [] ? 0 : (int) reset($max_post_ids);
    $last_post_id = 0;
    while ($last_post_id < $max_post_id) {
      $post_ids = $post_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('topic.target_id', $topic->id())
        ->condition('id', $last_post_id, '>')
        ->condition('id', $max_post_id, '<=')
        ->sort('id')
        ->range(0, self::TOPIC_SYNC_BATCH_SIZE)
        ->execute();
      if ($post_ids !== []) {
        $last_post_id = (int) end($post_ids);
      }
      else {
        break;
      }
      foreach ($post_storage->loadMultiple($post_ids) as $post) {
        if (!$post instanceof ForumPost) {
          continue;
        }
        foreach ($this->attachmentPublication->postGalleryUuids($post) as $uuid) {
          $gallery = $this->loadForumGallery($uuid);
          if ($gallery instanceof MediaGalleryInterface) {
            $this->syncGallery($gallery, NULL, $topic, $post);
          }
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
      if (!$gallery instanceof MediaGalleryInterface || $this->hasContentReference($gallery)) {
        continue;
      }
      // Recheck publication and comments immediately before deleting.
      if ($gallery->isPublished() || $this->hasContentReference($gallery)) {
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
    ?ForumPost $current_post = NULL,
    ?int $excluded_post_id = NULL,
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
    $posts = $this->attachmentPublication->currentPostsReferencingGallery($gallery->uuid());
    if ($excluded_post_id !== NULL) {
      unset($posts[$excluded_post_id]);
    }
    if ($current_post instanceof ForumPost) {
      unset($posts[$current_post->id()]);
      if (in_array($gallery->uuid(), $this->attachmentPublication->postGalleryUuids($current_post), TRUE)) {
        $posts[$current_post->id()] = $current_post;
      }
    }
    foreach ($posts as $post) {
      if ($post instanceof ForumPost && $this->attachmentPublication->isPublicPost($post, $current_topic)) {
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
  private function hasContentReference(MediaGalleryInterface $gallery): bool {
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
    return $this->attachmentPublication->hasPostGalleryReference($gallery->uuid());
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
