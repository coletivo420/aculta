<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Attachment;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\media\MediaInterface;
use Drupal\media_gallery\MediaGalleryInterface;
use Drupal\node\NodeInterface;

/**
 * Keeps Forum attachment Media published only while a public post uses it.
 */
final class ForumAttachmentPublication {

  public const BUNDLE = 'forum_attachment';

  private const COMMENT_BUNDLE = 'aculta_discussion_post';

  private const COMMENT_FIELD = 'field_aculta_discussion';

  private const PDF_FIELD = 'field_media_forum_attachment';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * Reconciles attachments present in a saved comment and its prior revision.
   */
  public function syncComment(CommentInterface $comment, bool $deleted = FALSE): void {
    if (!$this->isForumComment($comment)) {
      return;
    }
    $media_uuids = array_unique([
      ...$this->mediaUuids($comment),
      ...($comment->getOriginal() instanceof CommentInterface ? $this->mediaUuids($comment->getOriginal()) : []),
    ]);
    foreach ($media_uuids as $uuid) {
      $media = $this->loadAttachmentByUuid($uuid);
      if ($media instanceof MediaInterface) {
        $this->syncMedia($media, $deleted ? NULL : $comment);
      }
    }
  }

  /**
   * Reconciles all Forum attachments when their topic publication changes.
   */
  public function syncTopic(NodeInterface $topic): void {
    if ($topic->bundle() !== 'aculta_forum_topic' || $topic->isNew()) {
      return;
    }
    $comment_ids = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', self::COMMENT_BUNDLE)
      ->condition('entity_type', 'node')
      ->condition('entity_id', $topic->id())
      ->condition('field_name', self::COMMENT_FIELD)
      ->execute();
    $comments = $this->entityTypeManager->getStorage('comment')->loadMultiple($comment_ids);
    foreach ($comments as $comment) {
      if (!$comment instanceof CommentInterface) {
        continue;
      }
      foreach ($this->mediaUuids($comment) as $uuid) {
        $media = $this->loadAttachmentByUuid($uuid);
        if ($media instanceof MediaInterface) {
          $this->syncMedia($media, $comment, $topic);
        }
      }
    }
  }

  /**
   * Reconciles a Media update with its current published comment references.
   */
  public function syncMediaEntity(MediaInterface $media): void {
    if ($media->bundle() === self::BUNDLE && $media->hasField(self::PDF_FIELD)) {
      $this->syncMedia($media);
    }
  }

  /**
   * Returns UUIDs of Media embeds in the Forum comment body.
   *
   * @return string[]
   *   Unique Media UUIDs.
   */
  private function mediaUuids(CommentInterface $comment): array {
    if (!$comment->hasField('comment_body') || $comment->get('comment_body')->isEmpty()) {
      return [];
    }
    $document = Html::load((string) $comment->get('comment_body')->value);
    $xpath = new \DOMXPath($document);
    $uuids = [];
    foreach ($xpath->query('//*[self::drupal-media or self::drupal-entity][@data-entity-type="media"][@data-entity-uuid]') as $element) {
      if ($element instanceof \DOMElement) {
        $uuid = trim($element->getAttribute('data-entity-uuid'));
        if ($uuid !== '') {
          $uuids[] = $uuid;
        }
      }
    }
    return array_values(array_unique($uuids));
  }

  /**
   * Reconciles publication from all currently tracked and current references.
   */
  private function syncMedia(
    MediaInterface $media,
    ?CommentInterface $current_comment = NULL,
    ?NodeInterface $current_topic = NULL,
  ): void {
    $is_public = FALSE;
    // Field references are not reported consistently by Entity Usage; discover
    // Forum galleries from their source field as a second, authoritative path.
    $gallery_ids = $this->entityTypeManager->getStorage('media_gallery')->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_gallery_purpose', 'forum')
      ->condition('images.target_id', $media->id())
      ->execute();
    foreach ($gallery_ids as $gallery_id) {
      $gallery = $this->entityTypeManager->getStorage('media_gallery')->load($gallery_id);
      if ($gallery instanceof MediaGalleryInterface && $this->isPublicGalleryReference($gallery, $media, $current_topic)) {
        $is_public = TRUE;
        break;
      }
    }

    $comment_storage = $this->entityTypeManager->getStorage('comment');
    $comment_ids = $comment_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', self::COMMENT_BUNDLE)
      ->condition('entity_type', 'node')
      ->condition('field_name', self::COMMENT_FIELD)
      ->condition('comment_body.value', '%' . $media->uuid() . '%', 'LIKE')
      ->execute();
    $comments = $comment_storage->loadMultiple($comment_ids);
    if ($current_comment instanceof CommentInterface
      && in_array($media->uuid(), $this->mediaUuids($current_comment), TRUE)) {
      $comments[$current_comment->id()] = $current_comment;
    }
    foreach ($comments as $comment) {
      if ($comment instanceof CommentInterface
        && $this->isForumComment($comment)
        && in_array($media->uuid(), $this->mediaUuids($comment), TRUE)
        && $this->isPublicComment($comment, $current_topic)) {
        $is_public = TRUE;
        break;
      }
    }

    if ($media->isPublished() === $is_public) {
      return;
    }
    $is_public ? $media->setPublished() : $media->setUnpublished();
    $media->save();
  }

  /**
   * Checks that a comment belongs to the standalone public Forum discussion.
   */
  private function isForumComment(CommentInterface $comment): bool {
    return $comment->bundle() === self::COMMENT_BUNDLE
      && $comment->getCommentedEntityTypeId() === 'node'
      && $comment->getFieldName() === self::COMMENT_FIELD;
  }

  /**
   * Checks anonymous access using the Forum purpose and host publication.
   */
  private function isPublicComment(CommentInterface $comment, ?NodeInterface $current_topic = NULL): bool {
    $topic = $current_topic instanceof NodeInterface
      && (string) $current_topic->id() === (string) $comment->getCommentedEntityId()
      ? $current_topic
      : $comment->getCommentedEntity();
    if (!$comment->isPublished()
      || !$topic instanceof NodeInterface
      || $topic->bundle() !== 'aculta_forum_topic'
      || !$topic->isPublished()
      || !$topic->hasField('field_domain_source')
      || $topic->get('field_domain_source')->isEmpty()) {
      return FALSE;
    }
    $purpose = $this->domainPurposeManager->getPurposeForDomainId((string) $topic->get('field_domain_source')->target_id);
    if ($purpose !== 'forum') {
      return FALSE;
    }
    $anonymous = new AnonymousUserSession();
    return (bool) $this->domainPurposeManager->runInPurpose(
      'forum',
      static fn (): bool => $comment->access('view', $anonymous, TRUE)->isAllowed(),
    );
  }

  /**
   * Exposes Forum visibility for Gallery publication synchronization.
   */
  public function isPublicForumComment(CommentInterface $comment, ?NodeInterface $current_topic = NULL): bool {
    return $this->isForumComment($comment) && $this->isPublicComment($comment, $current_topic);
  }

  /**
   * Checks whether a public Forum Gallery references this Media.
   */
  private function isPublicGalleryReference(
    MediaGalleryInterface $gallery,
    MediaInterface $media,
    ?NodeInterface $current_topic = NULL,
  ): bool {
    if (!$gallery->isPublished()
      || !$gallery->hasField('field_gallery_purpose')
      || $gallery->get('field_gallery_purpose')->value !== 'forum') {
      return FALSE;
    }
    $media_ids = array_map('strval', array_column($gallery->get('images')->getValue(), 'target_id'));
    if (!in_array((string) $media->id(), $media_ids, TRUE)) {
      return FALSE;
    }

    $comment_ids = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', self::COMMENT_BUNDLE)
      ->condition('entity_type', 'node')
      ->condition('field_name', self::COMMENT_FIELD)
      ->condition('comment_body.value', '%' . $gallery->uuid() . '%', 'LIKE')
      ->execute();
    foreach ($this->entityTypeManager->getStorage('comment')->loadMultiple($comment_ids) as $comment) {
      if ($comment instanceof CommentInterface
        && $this->isForumComment($comment)
        && in_array($gallery->uuid(), $this->galleryUuids($comment), TRUE)
        && $this->isPublicComment($comment, $current_topic)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns UUIDs of ACULTA Gallery embeds in a comment.
   *
   * @return string[]
   *   Unique gallery UUIDs.
   */
  public function galleryUuids(CommentInterface $comment): array {
    if (!$comment->hasField('comment_body') || $comment->get('comment_body')->isEmpty()) {
      return [];
    }
    $document = Html::load((string) $comment->get('comment_body')->value);
    $xpath = new \DOMXPath($document);
    $uuids = [];
    foreach ($xpath->query('//drupal-entity[@data-entity-type="media_gallery"][@data-entity-uuid]') as $element) {
      if ($element instanceof \DOMElement) {
        $uuid = trim($element->getAttribute('data-entity-uuid'));
        if ($uuid !== '') {
          $uuids[] = $uuid;
        }
      }
    }
    return array_values(array_unique($uuids));
  }

  /**
   * Loads a Forum attachment bundle by stable UUID.
   */
  private function loadAttachmentByUuid(string $uuid): ?MediaInterface {
    $matches = $this->entityTypeManager->getStorage('media')->loadByProperties([
      'uuid' => $uuid,
      'bundle' => self::BUNDLE,
    ]);
    $media = reset($matches);
    return $media instanceof MediaInterface
      && $media->hasField(self::PDF_FIELD)
      && !$media->get(self::PDF_FIELD)->isEmpty()
      ? $media
      : NULL;
  }

}
