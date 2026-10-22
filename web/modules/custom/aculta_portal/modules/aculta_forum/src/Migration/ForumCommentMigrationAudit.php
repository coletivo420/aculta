<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Migration;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
    $comment_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', self::COMMENT_TYPE)
      ->condition('entity_type', 'node')
      ->condition('field_name', self::COMMENT_FIELD)
      ->sort('cid')
      ->execute();
    $report = [
      'source_comments' => count($comment_ids),
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
    $topic_by_comment = [];
    $parent_references = [];

    foreach (array_chunk($comment_ids, $batch_size) as $batch) {
      $eligible_comment_ids = [];
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
        $topic_by_comment[$comment_id] = $topic_id;
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
            $this->countEmbeds((string) $translation->get('comment_body')->value, $report);
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

    $external_parent_ids = [];
    foreach ($parent_references as $reference) {
      // A source query can include comments whose host is outside the eligible
      // Forum purpose. Resolve every parent not present in the eligible map so
      // those existing Comments are not reported as missing.
      if (!isset($topic_by_comment[$reference['parent_id']])) {
        $external_parent_ids[$reference['parent_id']] = $reference['parent_id'];
      }
    }
    $external_parents = [];
    foreach (array_chunk(array_values($external_parent_ids), $batch_size) as $batch) {
      foreach ($storage->loadMultiple($batch) as $parent) {
        if ($parent instanceof CommentInterface) {
          $external_parents[(string) $parent->id()] = TRUE;
        }
      }
    }
    foreach ($parent_references as $comment_id => $reference) {
      $parent_id = $reference['parent_id'];
      if ($parent_id === $comment_id) {
        $report['self_parent']++;
      }
      elseif (isset($topic_by_comment[$parent_id])) {
        if ($topic_by_comment[$parent_id] !== $reference['topic_id']) {
          $report['parent_cross_topic']++;
        }
      }
      elseif (isset($external_parents[$parent_id])) {
        $report['parent_outside_scope']++;
      }
      else {
        $report['parent_missing']++;
      }
    }

    $report['topics'] = count($topic_ids);
    $report['response_notices'] = $this->inspectResponseNotices(
      array_keys($topic_ids),
      $topic_by_comment,
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
   * Inventories Messenger response aggregates and their latest Comment IDs.
   *
   * @param list<string> $topic_ids
   *   Eligible public Forum topic IDs.
   * @param array<string, string> $topic_by_comment
   *   Eligible source Comment ID to topic ID map.
   * @param int $batch_size
   *   Bounded number of notices or Comments loaded in each batch.
   *
   * @return array<string, int|bool>
   *   Aggregate counts only, or an unavailable marker when Messenger is off.
   */
  private function inspectResponseNotices(array $topic_ids, array $topic_by_comment, int $batch_size): array {
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
    $notice_ids = $notice_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('tipo', 'forum_resposta')
      ->condition('assunto_nid', $topic_ids, 'IN')
      ->execute();
    $markers = [];
    foreach (array_chunk($notice_ids, $batch_size) as $batch) {
      foreach ($notice_storage->loadMultiple($batch) as $notice) {
        $result['records']++;
        $comment_id = (int) $notice->get('ultimo_comentario')->value;
        if ($comment_id > 0) {
          $result['records_with_comment_marker']++;
          $markers[$notice->id()] = [
            'comment_id' => (string) $comment_id,
            'topic_id' => (string) $notice->get('assunto_nid')->value,
          ];
        }
      }
    }

    $out_of_scope_ids = [];
    foreach ($markers as $marker) {
      if (!isset($topic_by_comment[$marker['comment_id']])) {
        $out_of_scope_ids[$marker['comment_id']] = $marker['comment_id'];
      }
    }
    $existing_comments = [];
    $comment_storage = $this->entityTypeManager->getStorage('comment');
    foreach (array_chunk(array_values($out_of_scope_ids), $batch_size) as $batch) {
      foreach ($comment_storage->loadMultiple($batch) as $comment) {
        if ($comment instanceof CommentInterface) {
          $existing_comments[(string) $comment->id()] = TRUE;
        }
      }
    }
    foreach ($markers as $marker) {
      $comment_id = $marker['comment_id'];
      if (isset($topic_by_comment[$comment_id])) {
        $result['markers_in_scope']++;
        if ($topic_by_comment[$comment_id] !== $marker['topic_id']) {
          $result['markers_for_other_topic']++;
        }
      }
      elseif (isset($existing_comments[$comment_id])) {
        $result['markers_outside_scope']++;
      }
      else {
        $result['markers_missing']++;
      }
    }
    return $result;
  }

  /**
   * Adds Media and Gallery embed counts without exposing source markup.
   *
   * @param string $body
   *   Filtered-text source stored in the Comment body.
   * @param array<string, mixed> $report
   *   The audit report being built.
   */
  private function countEmbeds(string $body, array &$report): void {
    $document = Html::load($body);
    $xpath = new \DOMXPath($document);
    foreach ($xpath->query('//*[@data-entity-uuid and @data-entity-type]') as $element) {
      if (!$element instanceof \DOMElement) {
        continue;
      }
      if ($element->getAttribute('data-entity-type') === 'media') {
        $report['media_embeds']++;
      }
      elseif ($element->getAttribute('data-entity-type') === 'media_gallery') {
        $report['gallery_embeds']++;
      }
    }
  }

}
