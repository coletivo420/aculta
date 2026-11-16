<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\Tests\UnitTestCase;

require_once DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_forum/aculta_forum.install';

/**
 * Tests the data validation used by Forum update hooks.
 */
final class ForumInstallHookTest extends UnitTestCase {

  /**
   * Normalizes the typed values returned by the database driver.
   */
  public function testNormalizesCommentRows(): void {
    $row = (object) [
      'cid' => '42',
      'langcode' => 'pt-br',
      'created' => 1700000000,
    ];

    self::assertSame(
      ['cid' => 42, 'langcode' => 'pt-br', 'created' => 1700000000],
      aculta_forum_update_9001_normalize_comment_row($row),
    );
  }

  /**
   * Rejects rows without the fields required for the backfill.
   */
  public function testRejectsIncompleteCommentRows(): void {
    $this->expectException(\RuntimeException::class);

    aculta_forum_update_9001_normalize_comment_row((object) ['cid' => 42]);
  }

  /**
   * Rejects database rows with a non-numeric comment ID.
   */
  public function testRejectsInvalidCommentIds(): void {
    $this->expectException(\RuntimeException::class);

    aculta_forum_update_9001_normalize_comment_row((object) [
      'cid' => 'not-a-comment-id',
      'langcode' => 'pt-br',
      'created' => 1700000000,
    ]);
  }

}
