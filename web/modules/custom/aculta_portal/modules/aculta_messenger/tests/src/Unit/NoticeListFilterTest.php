<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Service\NoticeListFilter;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests shared notice source and read-state filtering.
 */
#[CoversClass(NoticeListFilter::class)]
#[Group('aculta_messenger')]
final class NoticeListFilterTest extends UnitTestCase {

  /**
   * The default list preserves all items, including untagged extensions.
   */
  public function testEmptyFiltersPreserveAllItems(): void {
    $items = [
      ['#attributes' => ['data-aculta-notice-origin' => 'forum']],
      ['#plain_text' => 'Extension item'],
    ];

    self::assertSame($items, (new NoticeListFilter())->apply($items, []));
  }

  /**
   * Both filters are applied against producer metadata.
   */
  public function testFiltersByOriginAndReadState(): void {
    $items = [
      ['#attributes' => ['data-aculta-notice-origin' => 'forum', 'data-aculta-notice-read' => 'unread']],
      ['#attributes' => ['data-aculta-notice-origin' => 'forum', 'data-aculta-notice-read' => 'read']],
      ['#attributes' => ['data-aculta-notice-origin' => 'wiki', 'data-aculta-notice-read' => 'unread']],
    ];

    self::assertSame([$items[0]], (new NoticeListFilter())->apply($items, ['origem' => 'forum', 'leitura' => 'unread']));
  }

  /**
   * Unknown query values do not hide the default notice list.
   */
  public function testInvalidFiltersAreIgnored(): void {
    $items = [
      ['#attributes' => ['data-aculta-notice-origin' => 'forum']],
      ['#plain_text' => 'Extension item'],
    ];
    $filter = new NoticeListFilter();

    self::assertSame(['origem' => '', 'leitura' => ''], $filter->normalize([
      'origem' => ['forum'],
      'leitura' => 'unread-now',
    ]));
    self::assertSame($items, $filter->apply($items, ['origem' => 'forged']));
  }

  /**
   * Valid filters are appended without losing an existing query or fragment.
   */
  public function testAppendToUrlPreservesQueryAndFragment(): void {
    $filter = new NoticeListFilter();

    self::assertSame(
      '/avisos?keep=1&origem=wiki&leitura=unread#lista',
      $filter->appendToUrl('/avisos?keep=1#lista', ['origem' => 'wiki', 'leitura' => 'unread']),
    );
    self::assertSame(
      '/avisos?keep=1#lista',
      $filter->appendToUrl('/avisos?keep=1#lista', ['origem' => 'invalid']),
    );
    self::assertSame(
      '/avisos?keep=1&origem=wiki&leitura=unread#lista',
      $filter->appendToUrl('/avisos?keep=1&origem=forum&leitura=read#lista', ['origem' => 'wiki', 'leitura' => 'unread']),
    );
  }

}
