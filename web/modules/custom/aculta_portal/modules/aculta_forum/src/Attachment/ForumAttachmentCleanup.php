<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Attachment;

use Drupal\aculta_image\FileRemover;
use Drupal\aculta_image\ImageInspector;
use Drupal\comment\CommentInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_usage\EntityUsageInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Deletes unattached Forum uploads after the one-hour retention window.
 */
final class ForumAttachmentCleanup {

  private const RETENTION = 3600;

  private const LIMIT = 25;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityUsageInterface $entityUsage,
    private readonly FileUsageInterface $fileUsage,
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly FileRemover $imageFileRemover,
    private readonly ImageInspector $imageInspector,
  ) {}

  /**
   * Processes a bounded batch of old orphan Media and temporary files.
   */
  public function cleanup(): int {
    $cutoff = $this->time->getRequestTime() - self::RETENTION;
    $removed = $this->cleanupOrphanMedia($cutoff);
    if ($removed < self::LIMIT) {
      $removed += $this->cleanupTemporaryFiles($cutoff, self::LIMIT - $removed);
    }
    return $removed;
  }

  /**
   * Removes unpublished Media only when no live entity embed references it.
   */
  private function cleanupOrphanMedia(int $cutoff): int {
    $storage = $this->entityTypeManager->getStorage('media');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('bundle', ForumAttachmentPublication::BUNDLE)
      ->condition('status', 0)
      ->condition('created', $cutoff, '<=')
      ->sort('created')
      ->range(0, self::LIMIT)
      ->execute();
    $removed = 0;
    foreach ($storage->loadMultiple($ids) as $media) {
      if (!$media instanceof MediaInterface
        || !$media->hasField('field_media_forum_attachment')
        || $media->get('field_media_forum_attachment')->isEmpty()
        || $this->hasLiveReference($media)) {
        continue;
      }
      $file = $media->get('field_media_forum_attachment')->entity;
      if (!$file instanceof FileInterface) {
        continue;
      }

      // Recheck current references immediately before deleting either entity.
      if ($media->isPublished() || $this->hasLiveReference($media)) {
        continue;
      }
      $media->delete();
      if ($this->hasFileReferences($file)) {
        continue;
      }
      if ($this->isImage($file)) {
        $this->imageFileRemover->delete($file, 'anexo órfão do Fórum após uma hora');
      }
      else {
        $this->entityTypeManager->getStorage('file')->delete([$file]);
      }
      $removed++;
    }
    return $removed;
  }

  /**
   * Removes old, unreferenced temporary uploads from the Forum directory.
   */
  private function cleanupTemporaryFiles(int $cutoff, int $limit): int {
    if ($limit < 1) {
      return 0;
    }
    $storage = $this->entityTypeManager->getStorage('file');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', FileInterface::STATUS_PERMANENT, '<>')
      ->condition('created', $cutoff, '<=')
      ->condition('uri', 'private://forum/anexos/%', 'LIKE')
      ->sort('created')
      ->range(0, $limit)
      ->execute();
    $removed = 0;
    foreach ($storage->loadMultiple($ids) as $file) {
      if (!$file instanceof FileInterface || $this->hasFileReferences($file)) {
        continue;
      }
      if ($this->isImage($file)) {
        if ($this->imageFileRemover->delete($file, 'upload temporário órfão do Fórum após uma hora')) {
          $removed++;
        }
      }
      else {
        $this->entityTypeManager->getStorage('file')->delete([$file]);
        $removed++;
      }
    }
    return $removed;
  }

  /**
   * Checks Entity Usage sources against current content and post revisions.
   */
  private function hasLiveReference(MediaInterface $media): bool {
    $gallery_ids = $this->entityTypeManager->getStorage('media_gallery')->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_gallery_purpose', 'forum')
      ->condition('images.target_id', $media->id())
      ->range(0, 1)
      ->execute();
    if ($gallery_ids !== []) {
      return TRUE;
    }

    // Query comment text directly as well as Entity Usage: cleanup must remain
    // safe if contrib tracking is delayed or a usage record was not created.
    $comment_ids = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', 'aculta_discussion_post')
      ->condition('entity_type', 'node')
      ->condition('field_name', 'field_aculta_discussion')
      ->condition('comment_body.value', '%' . $this->database->escapeLike($media->uuid()) . '%', 'LIKE')
      ->execute();
    foreach ($this->entityTypeManager->getStorage('comment')->loadMultiple($comment_ids) as $comment) {
      if ($comment instanceof CommentInterface && $this->commentEmbeds($comment, $media->uuid())) {
        return TRUE;
      }
    }
    if ($this->hasForumPostMediaReference($media->uuid())) {
      return TRUE;
    }

    foreach ($this->entityUsage->listSources($media, FALSE) as $source) {
      $type = (string) ($source['source_type'] ?? '');
      $id = $source['source_id'] ?? NULL;
      if (!$id) {
        // Unknown source types fail closed to prevent deleting live media.
        return TRUE;
      }
      if ($type === 'aculta_forum_post') {
        if ($this->hasForumPostMediaReference($media->uuid())) {
          return TRUE;
        }
        continue;
      }
      if ($type !== 'comment') {
        // Unknown source types fail closed to prevent deleting live media.
        return TRUE;
      }
      $comment = $this->entityTypeManager->getStorage('comment')->load($id);
      if (!$comment instanceof CommentInterface || !$this->isForumComment($comment)) {
        continue;
      }
      if ($this->commentEmbeds($comment, $media->uuid())) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks every Forum post revision for an exact Media embed.
   */
  private function hasForumPostMediaReference(string $uuid): bool {
    $storage = $this->entityTypeManager->getStorage('aculta_forum_post');
    $revision_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('body.value', '%' . $this->database->escapeLike($uuid) . '%', 'LIKE')
      ->execute();
    foreach ($storage->loadMultipleRevisions($revision_ids) as $post) {
      if (!$post->hasField('body') || $post->get('body')->isEmpty()) {
        continue;
      }
      $document = Html::load((string) $post->get('body')->value);
      $xpath = new \DOMXPath($document);
      foreach ($xpath->query('//drupal-media[@data-entity-type="media"][@data-entity-uuid]') as $element) {
        if ($element instanceof \DOMElement && $element->getAttribute('data-entity-uuid') === $uuid) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Checks both File API and Entity Usage references before file deletion.
   */
  private function hasFileReferences(FileInterface $file): bool {
    if ($this->fileUsage->listUsage($file) !== []) {
      return TRUE;
    }
    return (bool) $this->database->select('entity_usage', 'eu')
      ->fields('eu', ['target_id'])
      ->condition('eu.target_type', 'file')
      ->condition('eu.target_id', (string) $file->id())
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Routes image content through aculta_image even when MIME metadata is weak.
   */
  private function isImage(FileInterface $file): bool {
    return str_starts_with((string) $file->getMimeType(), 'image/')
      || $this->imageInspector->dimensions($file->getFileUri()) !== NULL;
  }

  /**
   * Determines whether a source is a Forum comment.
   */
  private function isForumComment(CommentInterface $comment): bool {
    return $comment->bundle() === 'aculta_discussion_post'
      && $comment->getCommentedEntityTypeId() === 'node'
      && $comment->getFieldName() === 'field_aculta_discussion';
  }

  /**
   * Checks the current formatted text for an exact Media UUID embed.
   */
  private function commentEmbeds(CommentInterface $comment, string $uuid): bool {
    if (!$comment->hasField('comment_body') || $comment->get('comment_body')->isEmpty()) {
      return FALSE;
    }
    $document = Html::load((string) $comment->get('comment_body')->value);
    $xpath = new \DOMXPath($document);
    foreach ($xpath->query('//drupal-media[@data-entity-type="media"][@data-entity-uuid]') as $element) {
      if ($element instanceof \DOMElement && $element->getAttribute('data-entity-uuid') === $uuid) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
