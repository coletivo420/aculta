<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_legacy\Unit;

use Drupal\aculta_forum_legacy\Migration\LegacyArchiveTopicMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Proves the archive topic mapping is stable, bounded and rejects unidentifiable rows.
 */
#[CoversClass(LegacyArchiveTopicMapper::class)]
#[Group('aculta_forum_legacy')]
final class LegacyArchiveTopicMapperTest extends TestCase {

  /**
   * A complete source row maps to the legacy topic values with the stable key.
   */
  public function testMapsCompleteRowWithStableKey(): void {
    $values = LegacyArchiveTopicMapper::map([
      'nid' => 42,
      'title' => '  Tópico do acervo  ',
      'body' => 'Texto histórico',
      'author_id' => 7,
      'domain' => 'forum_aculta_org',
      'created' => 1700000000,
    ]);
    self::assertIsArray($values);
    self::assertSame('node:42', $values['origem_id']);
    self::assertSame('Tópico do acervo', $values['titulo']);
    self::assertSame('Texto histórico', $values['corpo']);
    self::assertSame(7, $values['autor']);
    self::assertSame('forum_aculta_org', $values['dominio_origem']);
    self::assertSame(1700000000, $values['created']);
  }

  /**
   * Titles are bounded to the entity limit and empty titles get a stable placeholder.
   */
  public function testBoundsTitleAndFillsEmptyTitle(): void {
    $long = LegacyArchiveTopicMapper::map(['nid' => 1, 'title' => str_repeat('a', 300)]);
    self::assertIsArray($long);
    self::assertSame(LegacyArchiveTopicMapper::TITLE_MAX, mb_strlen(is_string($long['titulo'] ?? NULL) ? $long['titulo'] : ''));
    $empty = LegacyArchiveTopicMapper::map(['nid' => 2, 'title' => '   ']);
    self::assertIsArray($empty);
    self::assertSame('Tópico sem título 2', $empty['titulo']);
  }

  /**
   * Rows without a positive integer id are rejected, so nothing unidentifiable is migrated.
   */
  public function testRejectsRowsWithoutStableId(): void {
    self::assertNull(LegacyArchiveTopicMapper::map(['title' => 'sem id']));
    self::assertNull(LegacyArchiveTopicMapper::map(['nid' => 0]));
    self::assertNull(LegacyArchiveTopicMapper::map(['nid' => '5']));
  }

}
