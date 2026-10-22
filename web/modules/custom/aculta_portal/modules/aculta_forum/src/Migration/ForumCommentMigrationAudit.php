<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Migration;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\node\NodeInterface;

/**
 * Builds a read-only inventory of Forum Comments for migration planning.
 */
final class ForumCommentMigrationAudit {

  private const COMMENT_TYPE = 'aculta_discussion_post';

  private const COMMENT_FIELD = 'field_aculta_discussion';

  private const TOPIC_BUNDLE = 'aculta_forum_topic';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Inspects source Comments in bounded batches without changing entities.
   *
   * @return array<string, mixed>
   *   Counts only; no content, names, email addresses, or entity IDs.
   */
  public function inspect(int $batch_size = 200): array {
    if ($batch_size < 1 || $batch_size > 1000) {
      throw new \InvalidArgumentException('O tamanho do lote deve ficar entre 1 e 1000.');
    }

    $storage = $this->entityTypeManager->getStorage('comment');
    $last_source_ids = $this->sourceCommentQuery()
      ->sort('cid', 'DESC')
      ->range(0, 1)
      ->execute();
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
      'topics' => 0,
      'parent_outside_scope' => 0,
      'parent_missing' => 0,
      'parent_cross_topic' => 0,
      'self_parent' => 0,
      'body_formats' => [],
      'languages' => [],
      'existing_revisionable_posts' => 0,
    ];
    $topic_ids = [];
    $media_uuids = [];
    $gallery_uuids = [];

    $last_comment_id = 0;
    while ($last_comment_id < $max_source_id) {
      $batch = $this->sourceCommentQuery()
        ->condition('cid', $last_comment_id, '>')
        ->condition('cid', $max_source_id, '<=')
        ->sort('cid')
        ->range(0, $batch_size)
        ->execute();
      if ($batch === []) {
        break;
      }
      $last_comment_id = (int) end($batch);
      $eligible_comment_ids = [];
      $parent_references = [];
      foreach ($storage->loadMultiple($batch) as $comment) {
        if (!$comment instanceof CommentInterface) {
          continue;
        }
        $topic = $comment->getCommentedEntity();
        if (!$topic instanceof NodeInterface || $topic->bundle() !== self::TOPIC_BUNDLE) {
          $report['invalid_topic']++;
          continue;
        }
        if (!$topic->hasField('field_domain_source') || $topic->get('field_domain_source')->isEmpty()
          || $this->domainPurposeManager->getPurposeForDomainId((string) $topic->get('field_domain_source')->target_id) !== 'forum') {
          $report['wrong_purpose']++;
          continue;
        }

        $comment_id = (string) $comment->id();
        $topic_id = (string) $topic->id();
        $report['eligible_comments']++;
        $eligible_comment_ids[] = $comment_id;
        $topic_ids[$topic_id] = TRUE;
        $translations = $comment->getTranslationLanguages();
        if (count($translations) > 1) {
          $report['comments_with_translations']++;
        }
        foreach ($translations as $language) {
          $language_id = $language->getId();
          $report['languages'][$language_id] = ($report['languages'][$language_id] ?? 0) + 1;
          $translation = $comment->getTranslation($language_id);
          if ($translation->hasField('comment_body') && !$translation->get('comment_body')->isEmpty()) {
            $format = (string) $translation->get('comment_body')->format;
            $report['body_formats'][$format] = ($report['body_formats'][$format] ?? 0) + 1;
            $this->countEmbeds(
              (string) $translation->get('comment_body')->value,
              $report,
              $media_uuids,
              $gallery_uuids,
            );
          }
        }
        if ($comment->isPublished()) {
          $report['published_comments']++;
        }
        else {
          $report['unpublished_comments']++;
        }
        if ($comment->hasField('field_aculta_rejected') && (bool) $comment->get('field_aculta_rejected')->value) {
          $report['rejected_comments']++;
        }
        if ($comment->hasField('field_autor_legado') && !$comment->get('field_autor_legado')->isEmpty()) {
          $report['comments_with_legacy_author']++;
        }
        if ($comment->hasField('field_aculta_published_at') && !$comment->get('field_aculta_published_at')->isEmpty()) {
          $report['comments_with_publication_timestamp']++;
        }

        $parent_id = (string) ($comment->get('pid')->target_id ?? '');
        if ($parent_id !== '') {
          $parent_references[$comment_id] = [
            'parent_id' => $parent_id,
            'topic_id' => $topic_id,
          ];
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
        }
      }

      if ($eligible_comment_ids !== []) {
        $flagging_storage = $this->entityTypeManager->getStorage('flagging');
        $flagging_ids = $flagging_storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('entity_type', 'comment')
          ->condition('entity_id', $eligible_comment_ids, 'IN')
          ->execute();
        foreach ($flagging_storage->loadMultiple($flagging_ids) as $flagging) {
          $flag_id = (string) $flagging->getFlagId();
          $report['flaggings']++;
          $report['flaggings_by_flag'][$flag_id] = ($report['flaggings_by_flag'][$flag_id] ?? 0) + 1;
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
    if ($this->domainPurposeManager->getPurposeForDomainId((string) $topic->get('field_domain_source')->target_id) !== 'forum') {
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
   * @param int $batch_size
   *   Bounded number of notices or Comments loaded in each batch.
   *
   * @return array<string, int|bool>
   *   Aggregate counts only, or an unavailable marker when Messenger is off.
   */
  private function inspectResponseNotices(array $topic_ids, int $batch_size): array {
    $result = [
      'available' => $this->moduleHandler->moduleExists('aculta_messenger'),
      'records' => 0,
      'records_with_comment_marker' => 0,
      'markers_in_scope' => 0,
      'markers_outside_scope' => 0,
      'markers_missing' => 0,
      'markers_for_other_topic' => 0,
    ];
    if (!$result['available'] || $topic_ids === []) {
      return $result;
    }

    $notice_storage = $this->entityTypeManager->getStorage('aculta_aviso');
    $comment_storage = $this->entityTypeManager->getStorage('comment');

    foreach (array_chunk($topic_ids, $batch_size) as $topic_batch) {
      $max_notice_ids = $this->responseNoticeQuery($topic_batch)
        ->sort('id', 'DESC')
        ->range(0, 1)
        ->execute();
      $max_notice_id = $max_notice_ids === [] ? 0 : (int) reset($max_notice_ids);
      $last_notice_id = 0;
      while ($last_notice_id < $max_notice_id) {
        $notice_ids = $this->responseNoticeQuery($topic_batch)
          ->condition('id', $last_notice_id, '>')
          ->condition('id', $max_notice_id, '<=')
          ->sort('id')
          ->range(0, $batch_size)
          ->execute();
        if ($notice_ids === []) {
          break;
        }
        $last_notice_id = (int) end($notice_ids);

        $markers = [];
        $marker_comment_ids = [];
        foreach ($notice_storage->loadMultiple($notice_ids) as $notice) {
          $result['records']++;
          $comment_id = (int) $notice->get('ultimo_comentario')->value;
          if ($comment_id > 0) {
            $result['records_with_comment_marker']++;
            $comment_id = (string) $comment_id;
            $markers[$notice->id()] = [
              'comment_id' => $comment_id,
              'topic_id' => (string) $notice->get('assunto_nid')->value,
            ];
            $marker_comment_ids[$comment_id] = $comment_id;
          }
        }

        $eligible_topic_by_comment = [];
        $existing_comment_ids = [];
        foreach (array_chunk(array_values($marker_comment_ids), $batch_size) as $comment_batch) {
          foreach ($comment_storage->loadMultiple($comment_batch) as $comment) {
            if ($comment instanceof CommentInterface) {
              $comment_id = (string) $comment->id();
              $existing_comment_ids[$comment_id] = TRUE;
              $topic_id = $this->eligibleTopicId($comment);
              if ($topic_id !== NULL) {
                $eligible_topic_by_comment[$comment_id] = $topic_id;
              }
            }
          }
        }
        foreach ($markers as $marker) {
          $comment_id = $marker['comment_id'];
          if (isset($eligible_topic_by_comment[$comment_id])) {
            $result['markers_in_scope']++;
            if ($eligible_topic_by_comment[$comment_id] !== $marker['topic_id']) {
              $result['markers_for_other_topic']++;
            }
          }
          elseif (isset($existing_comment_ids[$comment_id])) {
            $result['markers_outside_scope']++;
          }
          else {
            $result['markers_missing']++;
          }
        }
      }
    }
    return $result;
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
   * @param array<string, mixed> $report
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
    foreach ($xpath->query('//*[@data-entity-uuid and @data-entity-type]') as $element) {
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
   * @param int $batch_size
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
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('uuid', $batch, 'IN')
        ->execute();
      foreach ($storage->loadMultiple($ids) as $entity) {
        $found[$entity->uuid()] = TRUE;
      }
    }
    return count(array_diff_key(array_fill_keys($uuids, TRUE), $found));
  }

}
