<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_legacy\Migration;

/**
 * Maps one archive topic row (from the shared node tables) to the legacy topic entity values.
 *
 * Pure function: no database or entity access, so the mapping is testable and the source is never written.
 */
final class LegacyArchiveTopicMapper {

  /**
   * Title limit of the legacy topic entity.
   */
  public const TITLE_MAX = 255;

  /**
   * Maps a source row to entity values. Returns NULL when the row has no stable source id.
   *
   * @param array<string, mixed> $row
   *   Source values: nid, title, body, author_id (legacy user id), domain, created.
   *
   * @return array<string, mixed>|null
   *   Values for aculta_forum_legacy_topics, or NULL if the row is not migratable.
   */
  public static function map(array $row): ?array {
    $nid = $row['nid'] ?? NULL;
    if (!is_int($nid) || $nid <= 0) {
      return NULL;
    }
    $title = is_string($row['title'] ?? NULL) ? trim($row['title']) : '';
    if ($title === '') {
      $title = 'Tópico sem título ' . $nid;
    }
    $author = $row['author_id'] ?? NULL;
    return [
      'origem_id' => LegacyArchiveOrigin::topicKey($nid),
      'titulo' => mb_substr($title, 0, self::TITLE_MAX),
      'corpo' => is_string($row['body'] ?? NULL) ? $row['body'] : '',
      'autor' => is_int($author) && $author > 0 ? $author : NULL,
      'dominio_origem' => is_string($row['domain'] ?? NULL) ? mb_substr($row['domain'], 0, 64) : NULL,
      'created' => is_int($row['created'] ?? NULL) ? $row['created'] : 0,
    ];
  }

}
