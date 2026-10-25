<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Database;

/**
 * Defines storage for idempotent Forum notice events.
 */
final class ForumNoticeEventSchema {

  /**
   * Database table for per-recipient Forum notice event keys.
   */
  public const TABLE = 'aculta_messenger_forum_notice_event';

  /**
   * Returns the Drupal schema definition for the event ledger.
   */
  public static function definition(): array {
    return [
      'description' => 'Idempotency keys for per-recipient Forum notice events.',
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
        'event_type' => [
          'type' => 'varchar',
          'length' => 32,
          'not null' => TRUE,
        ],
        'event_id' => [
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
        'email_status' => [
          'type' => 'varchar',
          'length' => 16,
          'not null' => TRUE,
          'default' => 'none',
        ],
        'email_attempts' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
        'email_correlation' => [
          'type' => 'varchar',
          'length' => 36,
          'not null' => TRUE,
          'default' => '',
        ],
        'email_updated' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => [
        'recipient_event' => ['uid', 'event_type', 'event_id'],
      ],
      'indexes' => [
        'created' => ['created'],
        'topic' => ['nid'],
        'email_status' => ['email_status', 'email_updated'],
      ],
    ];
  }

}
