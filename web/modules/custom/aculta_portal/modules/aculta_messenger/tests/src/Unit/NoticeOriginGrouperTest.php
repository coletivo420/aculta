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
   * Groups notices by origin, ordering origin sections by their newest item.
   */
  public function testGroupsOriginsByLatestNoticeAndPreservesRecencyWithinOrigins(): void {
    $forumRecent = ['#attributes' => ['data-aculta-notice-origin' => 'forum', 'data-aculta-notice-recency' => '300'], 'id' => 'forum-recent'];
    $forumOlder = ['#attributes' => ['data-aculta-notice-origin' => 'forum', 'data-aculta-notice-recency' => '200'], 'id' => 'forum-older'];
    $wikiRecent = ['#attributes' => ['data-aculta-notice-origin' => 'wiki', 'data-aculta-notice-recency' => '400'], 'id' => 'wiki-recent'];
    $groups = NoticeOriginGrouper::group([$forumOlder, $wikiRecent, $forumRecent]);

    self::assertCount(2, $groups);
    self::assertSame('wiki', $groups[0]['#attributes']['data-aculta-notice-origin-section']);
    self::assertSame('wiki-recent', $groups[0]['items']['#items'][0]['id']);
    self::assertArrayNotHasKey('separator', $groups[0]);
    self::assertSame('forum', $groups[1]['#attributes']['data-aculta-notice-origin-section']);
    self::assertSame('Fórum', $groups[1]['heading']['#value']->getUntranslatedString());
    self::assertSame('forum-recent', $groups[1]['items']['#items'][0]['id']);
    self::assertSame('forum-older', $groups[1]['items']['#items'][1]['id']);
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

  /**
   * Friendship notices have a dedicated section between site alerts and LMS.
   */
  public function testGroupsFriendshipNoticesUnderTheirOwnOrigin(): void {
    $item = ['#attributes' => ['data-aculta-notice-origin' => 'amizade'], 'id' => 'friendship'];

    $groups = NoticeOriginGrouper::group([$item]);

    self::assertCount(1, $groups);
    self::assertSame('amizade', $groups[0]['#attributes']['data-aculta-notice-origin-section']);
    self::assertSame('Amizades', $groups[0]['heading']['#value']->getUntranslatedString());
    self::assertSame([$item], $groups[0]['items']['#items']);
  }

}
