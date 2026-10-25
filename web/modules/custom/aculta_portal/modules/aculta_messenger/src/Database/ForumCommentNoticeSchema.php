<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Database;

/**
 * Defines storage for idempotent Forum Comment notice events.
 */
final class ForumCommentNoticeSchema {

  /**
   * Database table for per-recipient Comment notice event keys.
   */
  public const TABLE = 'aculta_messenger_forum_comment_notice';

  /**
   * Returns the Drupal schema definition for the event ledger.
   */
  public static function definition(): array {
    return [
      'description' => 'Idempotency keys for published Forum Comment notices.',
      'fields' => [
        'id' => [
          'type' => 'serial',
          'not null' => TRUE,
        ],
        'uid' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
        'cid' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
        'nid' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
        'created' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => [
        'recipient_comment' => ['uid', 'cid'],
      ],
      'indexes' => [
        'created' => ['created'],
      ],
    ];
  }

}
