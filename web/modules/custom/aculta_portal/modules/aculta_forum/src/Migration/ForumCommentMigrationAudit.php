<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Migration;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\comment\CommentInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\node\NodeInterface;

/**
 * Builds a read-only inventory of Forum Comments for migration planning.
 *
 * @phpstan-type ResponseNoticeReport array{available: bool, records: int, records_with_comment_marker: int, records_with_post_marker: int, markers_in_scope: int, markers_outside_scope: int, markers_missing: int, markers_for_other_topic: int, post_markers_in_scope: int, post_markers_outside_scope: int, post_markers_missing: int, post_markers_for_other_topic: int}
 * @phpstan-type AuditReport array{source_comments: int, eligible_comments: int, invalid_topic: int, wrong_purpose: int, published_comments: int, unpublished_comments: int, rejected_comments: int, comments_with_legacy_author: int, anonymous_comments_without_legacy_author: int, comments_with_missing_owner: int, comments_with_missing_legacy_author: int, comments_with_empty_body_translation: int, comments_with_incompatible_body_format: int, comments_with_rejected_published_translation: int, comments_with_publication_timestamp: int, comments_with_translations: int, media_embeds: int, gallery_embeds: int, unique_media_references: int, media_embeds_without_uuid: int, missing_media_references: int, unique_gallery_references: int, gallery_embeds_without_uuid: int, missing_gallery_references: int, flaggings: int, flaggings_by_flag: array<string, int>, flaggings_global: int, flaggings_with_missing_owner: int, anonymous_flaggings_without_session: int, flaggings_without_valid_created_time: int, flaggings_without_post_flag_configuration: int, topics: int, parent_outside_scope: int, parent_missing: int, parent_cross_topic: int, parent_after_child: int, self_parent: int, body_formats: array<string, int>, languages: array<string, int>, response_notices: ResponseNoticeReport, existing_revisionable_posts: int}
 */
final class ForumCommentMigrationAudit {

  private const COMMENT_TYPE = 'aculta_discussion_post';

  private const COMMENT_FIELD = 'field_aculta_discussion';

  private const TOPIC_BUNDLE = 'aculta_forum_topic';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainSiteManager $siteManager,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Inspects source Comments in bounded batches without changing entities.
   *
   * @return AuditReport
   *   Counts only; no content, names, email addresses, or entity IDs.
   */
  public function inspect(int $batch_size = 200): array {
    $batch_size = $this->validateBatchSize($batch_size);

    $storage = $this->entityTypeManager->getStorage('comment');
    $last_source_ids = $this->queryIds($this->sourceCommentQuery()
      ->sort('cid', 'DESC')
      ->range(0, 1)
      ->execute());
    $max_source_id = $last_source_ids === [] ? 0 : (int) reset($last_source_ids);
    $source_comments = $max_source_id > 0
      ? $this->sourceCommentQuery()
        ->condition('cid', $max_source_id, '<=')
        ->count()
        ->execute()
      : 0;
    $report = [
      'source_comments' => (int) $source_comments,
      'eligible_comments' => 0,
      'invalid_topic' => 0,
      'wrong_purpose' => 0,
      'published_comments' => 0,
      'unpublished_comments' => 0,
      'rejected_comments' => 0,
      'comments_with_legacy_author' => 0,
      'anonymous_comments_without_legacy_author' => 0,
      'comments_with_missing_owner' => 0,
      'comments_with_missing_legacy_author' => 0,
      'comments_with_empty_body_translation' => 0,
      'comments_with_incompatible_body_format' => 0,
      'comments_with_rejected_published_translation' => 0,
      'comments_with_publication_timestamp' => 0,
      'comments_with_translations' => 0,
      'media_embeds' => 0,
      'gallery_embeds' => 0,
      'unique_media_references' => 0,
      'media_embeds_without_uuid' => 0,
      'missing_media_references' => 0,
      'unique_gallery_references' => 0,
      'gallery_embeds_without_uuid' => 0,
      'missing_gallery_references' => 0,
      'flaggings' => 0,
      'flaggings_by_flag' => [],
      'flaggings_global' => 0,
      'flaggings_with_missing_owner' => 0,
      'anonymous_flaggings_without_session' => 0,
      'flaggings_without_valid_created_time' => 0,
      'flaggings_without_post_flag_configuration' => 0,
      'topics' => 0,
      'parent_outside_scope' => 0,
      'parent_missing' => 0,
      'parent_cross_topic' => 0,
      'parent_after_child' => 0,
      'self_parent' => 0,
      'body_formats' => [],
      'languages' => [],
      'response_notices' => $this->emptyResponseNoticeReport(),
      'existing_revisionable_posts' => 0,
    ];
    $topic_ids = [];
    $media_uuids = [];
    $gallery_uuids = [];

    $last_comment_id = 0;
    while ($last_comment_id < $max_source_id) {
      $batch = $this->queryIds($this->sourceCommentQuery()
        ->condition('cid', $last_comment_id, '>')
        ->condition('cid', $max_source_id, '<=')
        ->sort('cid')
        ->range(0, $batch_size)
        ->execute());
      if ($batch === []) {
        break;
      }
      $last_comment_id = (int) end($batch);
      $eligible_comment_ids = [];
      $parent_references = [];
      $owner_references = [];
      $legacy_author_references = [];
      $anonymous_comment_ids = [];
      $anonymous_comment_lookup = [];
      foreach ($storage->loadMultiple($batch) as $comment) {
        $topic = $comment->getCommentedEntity();
        if (!$topic instanceof NodeInterface || $topic->bundle() !== self::TOPIC_BUNDLE) {
          $report['invalid_topic']++;
          continue;
        }
        if (!$topic->hasField('field_domain_source') || $topic->get('field_domain_source')->isEmpty()
          || $this->siteManager->getSiteKeyValueForDomainId((string) $topic->get('field_domain_source')->target_id) !== 'forum') {
          $report['wrong_purpose']++;
          continue;
        }

        $comment_id = (string) $comment->id();
        $topic_id = (string) $topic->id();
        $report['eligible_comments']++;
        $eligible_comment_ids[] = $comment_id;
        $topic_ids[$topic_id] = TRUE;
        $owner_id = (int) ($comment->getOwnerId() ?? 0);
        if ($owner_id > 0) {
          $owner_references[$comment_id] = (string) $owner_id;
        }
        else {
          $anonymous_comment_ids[] = $comment_id;
          $anonymous_comment_lookup[$comment_id] = TRUE;
        }
        $translations = $comment->getTranslationLanguages();
        if (count($translations) > 1) {
          $report['comments_with_translations']++;
        }
        $rejected = $comment->hasField('field_aculta_rejected')
          && (bool) $comment->get('field_aculta_rejected')->value;
        $has_empty_body = FALSE;
        $has_incompatible_format = FALSE;
        $has_rejected_published_translation = FALSE;
        foreach ($translations as $language) {
          $language_id = $language->getId();
          $report['languages'][$language_id] = ($report['languages'][$language_id] ?? 0) + 1;
          $translation = $comment->getTranslation($language_id);
          $body = $translation->hasField('comment_body') && !$translation->get('comment_body')->isEmpty()
            ? $translation->get('comment_body')
            : NULL;
          $body_value = $body === NULL ? '' : $this->fieldString($body->value);
          $format = $body === NULL ? '' : $this->fieldString($body->format);
          if (trim($body_value) === '') {
            $has_empty_body = TRUE;
          }
          if ($format !== 'forum') {
            $has_incompatible_format = TRUE;
          }
          if ($rejected && $translation->isPublished()) {
            $has_rejected_published_translation = TRUE;
          }
          if ($body !== NULL) {
            $report['body_formats'][$format] = ($report['body_formats'][$format] ?? 0) + 1;
            $this->countEmbeds($body_value, $report, $media_uuids, $gallery_uuids);
          }
        }
        $report['comments_with_empty_body_translation'] += (int) $has_empty_body;
        $report['comments_with_incompatible_body_format'] += (int) $has_incompatible_format;
        $report['comments_with_rejected_published_translation'] += (int) $has_rejected_published_translation;
        if ($comment->isPublished()) {
          $report['published_comments']++;
        }
        else {
          $report['unpublished_comments']++;
        }
        if ($rejected) {
          $report['rejected_comments']++;
        }
        if ($comment->hasField('field_autor_legado') && !$comment->get('field_autor_legado')->isEmpty()) {
          $report['comments_with_legacy_author']++;
          $legacy_author_references[$comment_id] = (string) $comment->get('field_autor_legado')->target_id;
        }
        if ($comment->hasField('field_aculta_published_at') && !$comment->get('field_aculta_published_at')->isEmpty()) {
          $report['comments_with_publication_timestamp']++;
        }

        $parent_id = $comment->hasParentComment()
          ? (string) $comment->get('pid')->target_id
          : '';
        if ($parent_id !== '') {
          $parent_references[$comment_id] = [
            'parent_id' => $parent_id,
            'topic_id' => $topic_id,
          ];
        }
      }

      if ($owner_references !== []) {
        $users = $this->entityTypeManager->getStorage('user')->loadMultiple(array_values(array_unique($owner_references)));
        foreach ($owner_references as $owner_id) {
          if (!isset($users[$owner_id])) {
            $report['comments_with_missing_owner']++;
          }
        }
      }
      if ($legacy_author_references !== []) {
        $legacy_authors = $this->entityTypeManager->getStorage('aculta_legado_autor')->loadMultiple(array_values(array_unique($legacy_author_references)));
        foreach ($legacy_author_references as $comment_id => $legacy_author_id) {
          if (!isset($legacy_authors[$legacy_author_id])) {
            $report['comments_with_missing_legacy_author']++;
            if (isset($anonymous_comment_lookup[$comment_id])) {
              $report['anonymous_comments_without_legacy_author']++;
            }
          }
        }
      }
      foreach ($anonymous_comment_ids as $comment_id) {
        if (!isset($legacy_author_references[$comment_id])) {
          $report['anonymous_comments_without_legacy_author']++;
        }
      }

      if ($parent_references !== []) {
        $parents = $storage->loadMultiple(array_values(array_unique(array_column($parent_references, 'parent_id'))));
        foreach ($parent_references as $comment_id => $reference) {
          $parent_id = $reference['parent_id'];
          if ($parent_id === $comment_id) {
            $report['self_parent']++;
            continue;
          }
          $parent = $parents[$parent_id] ?? NULL;
          if (!$parent instanceof CommentInterface) {
            $report['parent_missing']++;
            continue;
          }
          $parent_topic_id = $this->eligibleTopicId($parent);
          if ($parent_topic_id === NULL) {
            $report['parent_outside_scope']++;
          }
          elseif ($parent_topic_id !== $reference['topic_id']) {
            $report['parent_cross_topic']++;
          }
          elseif ((int) $parent_id > (int) $comment_id) {
            $report['parent_after_child']++;
          }
        }
      }

      if ($eligible_comment_ids !== []) {
        $flagging_storage = $this->entityTypeManager->getStorage('flagging');
        $flagging_ids = $this->queryIds($flagging_storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('entity_type', 'comment')
          ->condition('entity_id', $eligible_comment_ids, 'IN')
          ->execute());
        $flaggings = $flagging_storage->loadMultiple($flagging_ids);
        $flagging_owner_ids = [];
        foreach ($flaggings as $flagging) {
          if (!$flagging instanceof FlaggingInterface) {
            continue;
          }
          $owner_id = (int) $flagging->getOwnerId();
          if ($owner_id > 0) {
            $flagging_owner_ids[] = (string) $owner_id;
          }
        }
        $flagging_owners = $flagging_owner_ids === [] ? [] : $this->entityTypeManager
          ->getStorage('user')
          ->loadMultiple(array_unique($flagging_owner_ids));
        foreach ($flaggings as $flagging) {
          if (!$flagging instanceof FlaggingInterface) {
            continue;
          }
          $flag_id = (string) $flagging->getFlagId();
          $report['flaggings']++;
          $report['flaggings_by_flag'][$flag_id] = ($report['flaggings_by_flag'][$flag_id] ?? 0) + 1;
          $owner_id = (int) $flagging->getOwnerId();
          if ($owner_id > 0 && !isset($flagging_owners[$owner_id])) {
            $report['flaggings_with_missing_owner']++;
          }
          if ($owner_id === 0 && trim($this->fieldString($flagging->get('session_id')->value)) === '') {
            $report['anonymous_flaggings_without_session']++;
          }
          if ((bool) $flagging->get('global')->value) {
            $report['flaggings_global']++;
          }
          if ((int) $flagging->getCreatedTime() <= 0) {
            $report['flaggings_without_valid_created_time']++;
          }
        }
      }
    }

    $report['topics'] = count($topic_ids);
    $report['unique_media_references'] = count($media_uuids);
    $report['missing_media_references'] = $this->countMissingEntities('media', array_keys($media_uuids), $batch_size);
    $report['unique_gallery_references'] = count($gallery_uuids);
    $report['missing_gallery_references'] = $this->countMissingEntities('media_gallery', array_keys($gallery_uuids), $batch_size);
    $report['response_notices'] = $this->inspectResponseNotices(
      array_keys($topic_ids),
      $batch_size,
    );
    if ($report['flaggings_by_flag'] !== []) {
      $source_flag_ids = array_keys($report['flaggings_by_flag']);
      $target_flag_ids = array_map(
        static fn (string $source_flag_id): string => $source_flag_id === 'sinalizar_comentario'
          ? 'sinalizar_post_forum'
          : $source_flag_id,
        $source_flag_ids,
      );
      $flags = $this->entityTypeManager->getStorage('flag')->loadMultiple(array_unique($target_flag_ids));
      foreach ($report['flaggings_by_flag'] as $flag_id => $flagging_count) {
        $target_flag_id = $flag_id === 'sinalizar_comentario' ? 'sinalizar_post_forum' : $flag_id;
        $flag = $flags[$target_flag_id] ?? NULL;
        $bundles = $flag instanceof FlagInterface ? $flag->getBundles() : [];
        $supports_post = $flag instanceof FlagInterface
          && $flag->getFlaggableEntityTypeId() === 'aculta_forum_post'
          && ($bundles === [] || in_array('aculta_forum_post', $bundles, TRUE));
        if (!$supports_post) {
          $report['flaggings_without_post_flag_configuration'] += $flagging_count;
        }
      }
    }
    ksort($report['body_formats']);
    ksort($report['languages']);
    ksort($report['flaggings_by_flag']);
    $report['existing_revisionable_posts'] = (int) $this->entityTypeManager
      ->getStorage('aculta_forum_post')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    return $report;
  }

  /**
   * Resolves the eligible public Forum topic for a legacy Forum Comment.
   */
  private function eligibleTopicId(CommentInterface $comment): ?string {
    if ($comment->bundle() !== self::COMMENT_TYPE || $comment->getFieldName() !== self::COMMENT_FIELD) {
      return NULL;
    }
    $topic = $comment->getCommentedEntity();
    if (!$topic instanceof NodeInterface || $topic->bundle() !== self::TOPIC_BUNDLE
      || !$topic->hasField('field_domain_source') || $topic->get('field_domain_source')->isEmpty()) {
      return NULL;
    }
    if ($this->siteManager->getSiteKeyValueForDomainId((string) $topic->get('field_domain_source')->target_id) !== 'forum') {
      return NULL;
    }
    return (string) $topic->id();
  }

  /**
   * Creates the stable base query for legacy Forum Comments.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   Entity query limited to the source Comment type and field.
   */
  private function sourceCommentQuery(): QueryInterface {
    return $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', self::COMMENT_TYPE)
      ->condition('entity_type', 'node')
      ->condition('field_name', self::COMMENT_FIELD);
  }

  /**
   * Inventories Messenger response aggregates and their latest Comment IDs.
   *
   * @param list<string> $topic_ids
   *   Eligible public Forum topic IDs.
   * @param int<1, 1000> $batch_size
   *   Bounded number of notices or Comments loaded in each batch.
   *
   * @return ResponseNoticeReport
   *   Aggregate counts only, or an unavailable marker when Messenger is off.
   */
  private function inspectResponseNotices(array $topic_ids, int $batch_size): array {
    $result = $this->emptyResponseNoticeReport();
    if (!$result['available'] || $topic_ids === []) {
      return $result;
    }

    $notice_storage = $this->entityTypeManager->getStorage('aculta_aviso');
    $comment_storage = $this->entityTypeManager->getStorage('comment');

    foreach (array_chunk($topic_ids, $batch_size) as $topic_batch) {
      $max_notice_ids = $this->queryIds($this->responseNoticeQuery($topic_batch)
        ->sort('id', 'DESC')
        ->range(0, 1)
        ->execute());
      $max_notice_id = $max_notice_ids === [] ? 0 : (int) reset($max_notice_ids);
      $last_notice_id = 0;
      while ($last_notice_id < $max_notice_id) {
        $notice_ids = $this->queryIds($this->responseNoticeQuery($topic_batch)
          ->condition('id', $last_notice_id, '>')
          ->condition('id', $max_notice_id, '<=')
          ->sort('id')
          ->range(0, $batch_size)
          ->execute());
        if ($notice_ids === []) {
          break;
        }
        $last_notice_id = (int) end($notice_ids);

        $markers = [];
        $post_markers = [];
        $marker_comment_ids = [];
        $marker_post_ids = [];
        foreach ($notice_storage->loadMultiple($notice_ids) as $notice) {
          if (!$notice instanceof ContentEntityInterface) {
            continue;
          }
          $result['records']++;
          $comment_id = $this->fieldInteger($notice->get('ultimo_comentario')->value);
          if ($comment_id > 0) {
            $result['records_with_comment_marker']++;
            $comment_id = (string) $comment_id;
            $markers[$notice->id()] = [
              'comment_id' => $comment_id,
              'topic_id' => $this->fieldString($notice->get('assunto_nid')->value),
            ];
            $marker_comment_ids[$comment_id] = $comment_id;
          }
          $post_id = $this->fieldInteger($notice->get('ultimo_post')->value);
          if ($post_id > 0) {
            $result['records_with_post_marker']++;
            $post_id = (string) $post_id;
            $post_markers[$notice->id()] = [
              'post_id' => $post_id,
              'topic_id' => $this->fieldString($notice->get('assunto_nid')->value),
            ];
            $marker_post_ids[$post_id] = $post_id;
          }
        }

        $eligible_topic_by_comment = [];
        $existing_comment_ids = [];
        foreach (array_chunk(array_values($marker_comment_ids), $batch_size) as $comment_batch) {
          foreach ($comment_storage->loadMultiple($comment_batch) as $comment) {
            $comment_id = (string) $comment->id();
            $existing_comment_ids[$comment_id] = TRUE;
            $topic_id = $this->eligibleTopicId($comment);
            if ($topic_id !== NULL) {
              $eligible_topic_by_comment[$comment_id] = $topic_id;
            }
          }
        }
        foreach ($markers as $marker) {
          $comment_id = $marker['comment_id'];
          if (array_key_exists($comment_id, $eligible_topic_by_comment)) {
            $result['markers_in_scope']++;
            if ($eligible_topic_by_comment[$comment_id] !== $marker['topic_id']) {
              $result['markers_for_other_topic']++;
            }
          }
          elseif (array_key_exists($comment_id, $existing_comment_ids)) {
            $result['markers_outside_scope']++;
          }
          else {
            $result['markers_missing']++;
          }
        }

        $existing_posts = [];
        $post_topic_by_id = [];
        $post_storage = $this->entityTypeManager->getStorage('aculta_forum_post');
        foreach (array_chunk(array_values($marker_post_ids), $batch_size) as $post_batch) {
          foreach ($post_storage->loadMultiple($post_batch) as $post) {
            if (!$post instanceof ContentEntityInterface) {
              continue;
            }
            $post_id = (string) $post->id();
            $existing_posts[$post_id] = TRUE;
            $topic = $post->get('topic')->entity;
            if ($topic instanceof NodeInterface && $topic->bundle() === self::TOPIC_BUNDLE
              && $topic->hasField('field_domain_source') && !$topic->get('field_domain_source')->isEmpty()
              && $this->siteManager->getSiteKeyValueForDomainId((string) $topic->get('field_domain_source')->target_id) === 'forum') {
              $post_topic_by_id[$post_id] = (string) $topic->id();
            }
          }
        }
        foreach ($post_markers as $marker) {
          $post_id = $marker['post_id'];
          if (array_key_exists($post_id, $post_topic_by_id)) {
            $result['post_markers_in_scope']++;
            if ($post_topic_by_id[$post_id] !== $marker['topic_id']) {
              $result['post_markers_for_other_topic']++;
            }
          }
          elseif (array_key_exists($post_id, $existing_posts)) {
            $result['post_markers_outside_scope']++;
          }
          else {
            $result['post_markers_missing']++;
          }
        }
      }
    }
    return $result;
  }

  /**
   * Creates a zeroed response-notice report with current availability.
   *
   * @return ResponseNoticeReport
   *   Empty aggregate report.
   */
  private function emptyResponseNoticeReport(): array {
    return [
      'available' => $this->moduleHandler->moduleExists('aculta_messenger'),
      'records' => 0,
      'records_with_comment_marker' => 0,
      'records_with_post_marker' => 0,
      'markers_in_scope' => 0,
      'markers_outside_scope' => 0,
      'markers_missing' => 0,
      'markers_for_other_topic' => 0,
      'post_markers_in_scope' => 0,
      'post_markers_outside_scope' => 0,
      'post_markers_missing' => 0,
      'post_markers_for_other_topic' => 0,
    ];
  }

  /**
   * Creates the source query for response notices on a bounded set of topics.
   *
   * @param list<string> $topic_ids
   *   Eligible public Forum topic IDs.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   Entity query for response notices.
   */
  private function responseNoticeQuery(array $topic_ids): QueryInterface {
    return $this->entityTypeManager->getStorage('aculta_aviso')->getQuery()
      ->accessCheck(FALSE)
      ->condition('tipo', 'forum_resposta')
      ->condition('assunto_nid', $topic_ids, 'IN');
  }

  /**
   * Adds Media and Gallery embed counts without exposing source markup.
   *
   * @param string $body
   *   Filtered-text source stored in the Comment body.
   * @param AuditReport $report
   *   The audit report being built.
   * @param array<string, bool> $media_uuids
   *   The unique Media UUIDs encountered.
   * @param array<string, bool> $gallery_uuids
   *   The unique Gallery UUIDs encountered.
   */
  private function countEmbeds(
    string $body,
    array &$report,
    array &$media_uuids,
    array &$gallery_uuids,
  ): void {
    $document = Html::load($body);
    $xpath = new \DOMXPath($document);
    $elements = $xpath->query('//*[@data-entity-uuid and @data-entity-type]');
    if ($elements === FALSE) {
      return;
    }
    foreach ($elements as $element) {
      if (!$element instanceof \DOMElement) {
        continue;
      }
      if ($element->getAttribute('data-entity-type') === 'media') {
        $report['media_embeds']++;
        $uuid = $element->getAttribute('data-entity-uuid');
        if ($uuid !== '') {
          $media_uuids[$uuid] = TRUE;
        }
        else {
          $report['media_embeds_without_uuid']++;
        }
      }
      elseif ($element->getAttribute('data-entity-type') === 'media_gallery') {
        $report['gallery_embeds']++;
        $uuid = $element->getAttribute('data-entity-uuid');
        if ($uuid !== '') {
          $gallery_uuids[$uuid] = TRUE;
        }
        else {
          $report['gallery_embeds_without_uuid']++;
        }
      }
    }
  }

  /**
   * Counts referenced UUIDs absent from an entity type, without returning IDs.
   *
   * @param string $entity_type
   *   The referenced entity type.
   * @param list<string> $uuids
   *   Unique UUIDs found in the source markup.
   * @param int<1, 1000> $batch_size
   *   Maximum number of UUIDs checked per query.
   *
   * @return int
   *   Number of UUIDs that do not resolve to an entity.
   */
  private function countMissingEntities(string $entity_type, array $uuids, int $batch_size): int {
    if ($uuids === [] || $this->entityTypeManager->getDefinition($entity_type, FALSE) === NULL) {
      return count($uuids);
    }
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $found = [];
    foreach (array_chunk($uuids, $batch_size) as $batch) {
      $ids = $this->queryIds($storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('uuid', $batch, 'IN')
        ->execute());
      foreach ($storage->loadMultiple($ids) as $entity) {
        $found[$entity->uuid()] = TRUE;
      }
    }
    return count(array_diff_key(array_fill_keys($uuids, TRUE), $found));
  }

  /**
   * Validates and narrows a requested batch size.
   *
   * @return int<1, 1000>
   *   A positive batch size bounded by the audit limit.
   */
  private function validateBatchSize(int $batch_size): int {
    if ($batch_size < 1 || $batch_size > 1000) {
      throw new \InvalidArgumentException('O tamanho do lote deve ficar entre 1 e 1000.');
    }
    return $batch_size;
  }

  /**
   * Converts a scalar entity field value to a string.
   */
  private function fieldString(mixed $value): string {
    if ($value === NULL) {
      return '';
    }
    if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
      throw new \UnexpectedValueException('Valor escalar inválido em campo da auditoria.');
    }
    return (string) $value;
  }

  /**
   * Converts a scalar entity field value to an integer.
   */
  private function fieldInteger(mixed $value): int {
    if ($value === NULL) {
      return 0;
    }
    if (!is_int($value) && !is_string($value) && !is_float($value) && !is_bool($value)) {
      throw new \UnexpectedValueException('Identificador inválido em campo da auditoria.');
    }
    return (int) $value;
  }

  /**
   * Narrows entity query output to a list of IDs, rejecting count results.
   *
   * @param int|array<array-key, mixed> $ids
   *   Result returned by an entity query.
   *
   * @return list<int|string>
   *   Entity IDs.
   */
  private function queryIds(int|array $ids): array {
    if (!is_array($ids)) {
      throw new \UnexpectedValueException('A consulta de entidades deveria retornar uma lista de IDs.');
    }
    $values = array_values($ids);
    foreach ($values as $value) {
      if (!is_int($value) && !is_string($value)) {
        throw new \UnexpectedValueException('A consulta de entidades retornou um ID inválido.');
      }
    }
    return $values;
  }

}
