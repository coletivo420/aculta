<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Service\NoticeOriginGrouper;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests source grouping and separator structure in the unified notice list.
 */
#[CoversClass(NoticeOriginGrouper::class)]
#[Group('aculta_messenger')]
final class NoticeOriginGrouperTest extends UnitTestCase {

  /**
   * Groups origins in the central order and preserves recency within origins.
   */
  public function testGroupsOriginsAndPreservesSourceRecencyOrder(): void {
    $forumRecent = ['#attributes' => ['data-aculta-notice-origin' => 'forum'], 'id' => 'forum-recent'];
    $forumOlder = ['#attributes' => ['data-aculta-notice-origin' => 'forum'], 'id' => 'forum-older'];
    $wikiRecent = ['#attributes' => ['data-aculta-notice-origin' => 'wiki'], 'id' => 'wiki-recent'];
    $groups = NoticeOriginGrouper::group([$wikiRecent, $forumRecent, $forumOlder]);

    self::assertCount(2, $groups);
    self::assertSame('forum', $groups[0]['#attributes']['data-aculta-notice-origin-section']);
    self::assertSame('Fórum', $groups[0]['heading']['#value']->getUntranslatedString());
    self::assertSame('forum-recent', $groups[0]['items']['#items'][0]['id']);
    self::assertSame('forum-older', $groups[0]['items']['#items'][1]['id']);
    self::assertArrayNotHasKey('separator', $groups[0]);
    self::assertSame('wiki', $groups[1]['#attributes']['data-aculta-notice-origin-section']);
    self::assertSame('hr', $groups[1]['separator']['#tag']);
  }

  /**
   * Keeps unclassified legacy extension items visible in their own section.
   */
  public function testKeepsUnclassifiedItemsInOtherNoticesSection(): void {
    $groups = NoticeOriginGrouper::group([['#plain_text' => 'aviso legado sem origem']]);

    self::assertCount(1, $groups);
    self::assertSame('outros', $groups[0]['#attributes']['data-aculta-notice-origin-section']);
    self::assertSame('aviso legado sem origem', $groups[0]['items']['#items'][0]['#plain_text']);
  }

}
