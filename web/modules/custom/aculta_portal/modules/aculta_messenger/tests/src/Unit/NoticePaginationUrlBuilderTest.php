<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Service\NoticePaginationUrlBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests pagination links preserve filters and independent origin offsets. */
#[CoversClass(NoticePaginationUrlBuilder::class)]
#[Group('aculta_messenger')]
final class NoticePaginationUrlBuilderTest extends UnitTestCase {

  /**
   * Global paging updates one source and retains the other source positions.
   */
  public function testGlobalPagingPreservesSiblingOffsetsAndFilters(): void {
    $url = NoticePaginationUrlBuilder::globalPage(
      ['forum' => 200, 'wiki' => 100, 'unknown' => 900],
      'wiki',
      200,
      ['origem' => '', 'leitura' => 'unread'],
    );

    self::assertSame('aculta_messenger.avisos', $url->getRouteName());
    self::assertSame(['aba' => 'avisos'], $url->getRouteParameters());
    self::assertSame([
      'leitura' => 'unread',
      'notice_offsets' => ['forum' => 200, 'wiki' => 200],
    ], $url->getOptions()['query']);
  }

  /**
   * Returning to a source's first page removes only that source's offset.
   */
  public function testGlobalPreviousPageRemovesZeroOffsetOnlyForSelectedOrigin(): void {
    $url = NoticePaginationUrlBuilder::globalPage(
      ['forum' => 200, 'wiki' => 100],
      'wiki',
      0,
      ['leitura' => 'read'],
    );

    self::assertSame([
      'leitura' => 'read',
      'notice_offsets' => ['forum' => 200],
    ], $url->getOptions()['query']);
  }

  /**
   * Filtered pages use the correct legacy offset key per source.
   */
  public function testFilteredPagingUsesSourceSpecificOffset(): void {
    $forum = NoticePaginationUrlBuilder::filteredPage('forum', 300, ['origem' => 'forum', 'leitura' => 'unread']);
    $wiki = NoticePaginationUrlBuilder::filteredPage('wiki', 200, ['origem' => 'wiki', 'leitura' => 'read']);

    self::assertSame([
      'origem' => 'forum',
      'leitura' => 'unread',
      'forum_offset' => 300,
    ], $forum->getOptions()['query']);
    self::assertSame([
      'origem' => 'wiki',
      'leitura' => 'read',
      'notice_offset' => 200,
    ], $wiki->getOptions()['query']);
  }

  /**
   * Unknown origins cannot create arbitrary pager links.
   */
  public function testRejectsUnsupportedOrigin(): void {
    $this->expectException(\InvalidArgumentException::class);
    NoticePaginationUrlBuilder::globalPage([], 'unknown', 100, []);
  }

}
