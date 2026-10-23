<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Attachment;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\aculta_forum\Entity\ForumPost;
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

  private const TOPIC_SYNC_BATCH_SIZE = 100;

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
   * Reconciles Media embeds in a post and its prior revision.
   */
  public function syncPost(ForumPost $post, bool $deleted = FALSE): void {
    $uuids = array_unique([
      ...$this->postMediaUuids($post),
      ...($post->getOriginal() instanceof ForumPost ? $this->postMediaUuids($post->getOriginal()) : []),
    ]);
    foreach ($uuids as $uuid) {
      $media = $this->loadAttachmentByUuid($uuid);
      if ($media instanceof MediaInterface) {
        $this->syncMedia($media, NULL, NULL, NULL, $deleted ? NULL : $post, $deleted ? (int) $post->id() : NULL);
      }
    }
  }

  /**
   * Reconciles Forum attachments in bounded ID batches when a topic changes.
   */
  public function syncTopic(NodeInterface $topic): void {
    if ($topic->bundle() !== 'aculta_forum_topic' || $topic->isNew()) {
      return;
    }
    $comment_storage = $this->entityTypeManager->getStorage('comment');
    $max_comment_ids = $comment_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', self::COMMENT_BUNDLE)
      ->condition('entity_type', 'node')
      ->condition('entity_id', $topic->id())
      ->condition('field_name', self::COMMENT_FIELD)
      ->sort('cid', 'DESC')
      ->range(0, 1)
      ->execute();
    $max_comment_id = $max_comment_ids === [] ? 0 : (int) reset($max_comment_ids);
    $last_comment_id = 0;
    while ($last_comment_id < $max_comment_id) {
      $comment_ids = $comment_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('comment_type', self::COMMENT_BUNDLE)
        ->condition('entity_type', 'node')
        ->condition('entity_id', $topic->id())
        ->condition('field_name', self::COMMENT_FIELD)
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
        foreach ($this->mediaUuids($comment) as $uuid) {
          $media = $this->loadAttachmentByUuid($uuid);
          if ($media instanceof MediaInterface) {
            $this->syncMedia($media, $comment, $topic);
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
        foreach ($this->postMediaUuids($post) as $uuid) {
          $media = $this->loadAttachmentByUuid($uuid);
          if ($media instanceof MediaInterface) {
            $this->syncMedia($media, NULL, $topic, NULL, $post);
          }
        }
      }
    }
  }

  /**
   * Reconciles a Media update with its current published comment references.
   */
  public function syncMediaEntity(MediaInterface $media, ?int $excluded_gallery_id = NULL): void {
    if ($media->bundle() === self::BUNDLE && $media->hasField(self::PDF_FIELD)) {
      $this->syncMedia($media, NULL, NULL, $excluded_gallery_id);
    }
  }

  /**
   * Checks whether any stored Forum post revision embeds the Media UUID.
   */
  public function hasPostMediaReference(string $uuid): bool {
    return $this->findPostsReferencing($uuid, 'media', TRUE) !== [];
  }

  /**
   * Checks whether any stored Forum post revision embeds the Gallery UUID.
   */
  public function hasPostGalleryReference(string $uuid): bool {
    return $this->findPostsReferencing($uuid, 'media_gallery', TRUE) !== [];
  }

  /**
   * Returns current Forum posts that embed one Gallery UUID.
   *
   * @return \Drupal\aculta_forum\Entity\ForumPost[]
   *   Matching posts keyed by entity ID.
   */
  public function currentPostsReferencingGallery(string $uuid): array {
    return $this->findPostsReferencing($uuid, 'media_gallery');
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
    ?int $excluded_gallery_id = NULL,
    ?ForumPost $current_post = NULL,
    ?int $excluded_post_id = NULL,
  ): void {
    // Publication follows an active reference in a published host, including a
    // restricted topic. Account access is revalidated by the media_access hook
    // and by the private file handler, so the published flag alone never grants
    // access to a non-member.
    $is_public = $this->isPubliclyReferenced(
      $media,
      $current_comment,
      $current_topic,
      $excluded_gallery_id,
      $current_post,
      $excluded_post_id,
      check_access: FALSE,
    );
    if ($media->isPublished() === $is_public) {
      return;
    }
    $is_public ? $media->setPublished() : $media->setUnpublished();
    $media->save();
  }

  /**
   * Checks current access through a published Forum host reference.
   *
   * Anonymous by default, preserving the public rule. When an account is
   * given, the host access is evaluated for that account, so an active
   * associate with membership can read an attachment of a restricted topic
   * while everyone else remains denied.
   *
   * This is repeated at access time because publication status alone can become
   * stale when Node grants or another host access rule changes independently.
   */
  public function hasCurrentPublicReference(MediaInterface $media, ?AccountInterface $account = NULL): bool {
    return $this->isPubliclyReferenced($media, account: $account);
  }

  /**
   * Checks active references, including the current entity during a save.
   *
   * With $check_access FALSE the reference is considered active from a
   * published host without evaluating any account; this drives publication.
   * With TRUE (default) the host access is evaluated for $account, falling
   * back to the anonymous account.
   */
  private function isPubliclyReferenced(
    MediaInterface $media,
    ?CommentInterface $current_comment = NULL,
    ?NodeInterface $current_topic = NULL,
    ?int $excluded_gallery_id = NULL,
    ?ForumPost $current_post = NULL,
    ?int $excluded_post_id = NULL,
    ?AccountInterface $account = NULL,
    bool $check_access = TRUE,
  ): bool {
    // Field references are not reported consistently by Entity Usage; discover
    // Forum galleries from their source field as a second, authoritative path.
    $gallery_ids = $this->entityTypeManager->getStorage('media_gallery')->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_gallery_purpose', 'forum')
      ->condition('images.target_id', $media->id())
      ->execute();
    foreach ($gallery_ids as $gallery_id) {
      if ($excluded_gallery_id !== NULL && (int) $gallery_id === $excluded_gallery_id) {
        continue;
      }
      $gallery = $this->entityTypeManager->getStorage('media_gallery')->load($gallery_id);
      if ($gallery instanceof MediaGalleryInterface && $this->isPublicGalleryReference($gallery, $media, $current_topic, $account, $check_access)) {
        return TRUE;
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
    if ($current_comment instanceof CommentInterface) {
      // EntityQuery may match an older revision of this Comment. Its current
      // saved object is authoritative for this synchronous reconciliation.
      unset($comments[$current_comment->id()]);
      if (in_array($media->uuid(), $this->mediaUuids($current_comment), TRUE)) {
        $comments[$current_comment->id()] = $current_comment;
      }
    }
    foreach ($comments as $comment) {
      if ($comment instanceof CommentInterface
        && $this->isForumComment($comment)
        && in_array($media->uuid(), $this->mediaUuids($comment), TRUE)
        && $this->isPublicComment($comment, $current_topic, $account, $check_access)) {
        return TRUE;
      }
    }

    $posts = $this->findPostsReferencing($media->uuid(), 'media');
    if ($excluded_post_id !== NULL) {
      unset($posts[$excluded_post_id]);
    }
    if ($current_post instanceof ForumPost) {
      unset($posts[$current_post->id()]);
      if (in_array($media->uuid(), $this->postMediaUuids($current_post), TRUE)) {
        $posts[$current_post->id()] = $current_post;
      }
    }
    foreach ($posts as $post) {
      if ($post instanceof ForumPost && $this->isPublicPost($post, $current_topic, $account, $check_access)) {
        return TRUE;
      }
    }
    return FALSE;
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
   * Checks access using the Forum purpose and host publication.
   *
   * With $check_access FALSE, only the published host state is required.
   */
  private function isPublicComment(
    CommentInterface $comment,
    ?NodeInterface $current_topic = NULL,
    ?AccountInterface $account = NULL,
    bool $check_access = TRUE,
  ): bool {
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
    if (!$check_access) {
      return TRUE;
    }
    $account ??= new AnonymousUserSession();
    return (bool) $this->domainPurposeManager->runInPurpose(
      'forum',
      static fn (): bool => $comment->access('view', $account, TRUE)->isAllowed(),
    );
  }

  /**
   * Exposes Forum visibility for Gallery publication synchronization.
   */
  public function isPublicForumComment(CommentInterface $comment, ?NodeInterface $current_topic = NULL, ?AccountInterface $account = NULL): bool {
    return $this->isForumComment($comment) && $this->isPublicComment($comment, $current_topic, $account);
  }

  /**
   * Checks access to a published post in a published Forum topic.
   */
  public function isPublicPost(ForumPost $post, ?NodeInterface $current_topic = NULL, ?AccountInterface $account = NULL, bool $check_access = TRUE): bool {
    $topic = $current_topic instanceof NodeInterface
      && (string) $current_topic->id() === (string) $post->get('topic')->target_id
      ? $current_topic
      : $post->get('topic')->entity;
    if (!$post->isPublished()
      || !$post->isDefaultRevision()
      || !$topic instanceof NodeInterface
      || $topic->bundle() !== 'aculta_forum_topic'
      || !$topic->isPublished()
      || !$topic->hasField('field_domain_source')
      || $topic->get('field_domain_source')->isEmpty()
      || $this->domainPurposeManager->getPurposeForDomainId((string) $topic->get('field_domain_source')->target_id) !== 'forum') {
      return FALSE;
    }
    if (!$check_access) {
      return TRUE;
    }

    $account ??= new AnonymousUserSession();
    return (bool) $this->domainPurposeManager->runInPurpose(
      'forum',
      static fn (): bool => $post->access('view', $account, TRUE)->isAllowed(),
    );
  }

  /**
   * Returns Media UUIDs embedded in a Forum post's body.
   *
   * @return string[]
   *   Unique Media UUIDs.
   */
  private function postMediaUuids(ForumPost $post): array {
    return $this->embeddedUuids($post, 'drupal-media', 'media');
  }

  /**
   * Returns Gallery UUIDs embedded in a Forum post's body.
   *
   * @return string[]
   *   Unique Gallery UUIDs.
   */
  public function postGalleryUuids(ForumPost $post): array {
    return $this->embeddedUuids($post, 'drupal-entity', 'media_gallery');
  }

  /**
   * Returns UUIDs of the selected entity type from a post body.
   *
   * @return string[]
   *   Unique entity UUIDs.
   */
  private function embeddedUuids(ForumPost $post, string $tag, string $entityType): array {
    if (!$post->hasField('body') || $post->get('body')->isEmpty()) {
      return [];
    }
    $document = Html::load((string) $post->get('body')->value);
    $xpath = new \DOMXPath($document);
    $uuids = [];
    foreach ($xpath->query('//' . $tag . '[@data-entity-type="' . $entityType . '"][@data-entity-uuid]') as $element) {
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
   * Loads posts that embed an entity UUID in their current or all revisions.
   *
   * @return \Drupal\aculta_forum\Entity\ForumPost[]
   *   Matching posts keyed by entity ID.
   */
  private function findPostsReferencing(string $uuid, string $entityType, bool $allRevisions = FALSE): array {
    $storage = $this->entityTypeManager->getStorage('aculta_forum_post');
    $query = $storage->getQuery()->accessCheck(FALSE);
    if ($allRevisions) {
      $query->allRevisions();
    }
    $ids = $query
      ->condition('body.value', '%' . $uuid . '%', 'LIKE')
      ->execute();
    if ($ids === []) {
      return [];
    }

    $posts = $allRevisions
      ? $storage->loadMultipleRevisions($ids)
      : $storage->loadMultiple($ids);
    $tag = $entityType === 'media' ? 'drupal-media' : 'drupal-entity';
    $matches = [];
    foreach ($posts as $post) {
      if ($post instanceof ForumPost
        && in_array($uuid, $this->embeddedUuids($post, $tag, $entityType), TRUE)) {
        $matches[$post->id()] = $post;
      }
    }
    return $matches;
  }

  /**
   * Checks whether a public Forum Gallery references this Media.
   */
  private function isPublicGalleryReference(
    MediaGalleryInterface $gallery,
    MediaInterface $media,
    ?NodeInterface $current_topic = NULL,
    ?AccountInterface $account = NULL,
    bool $check_access = TRUE,
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
        && $this->isPublicComment($comment, $current_topic, $account, $check_access)) {
        return TRUE;
      }
    }
    foreach ($this->findPostsReferencing($gallery->uuid(), 'media_gallery') as $post) {
      if ($post instanceof ForumPost
        && in_array($gallery->uuid(), $this->postGalleryUuids($post), TRUE)
        && $this->isPublicPost($post, $current_topic, $account, $check_access)) {
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
