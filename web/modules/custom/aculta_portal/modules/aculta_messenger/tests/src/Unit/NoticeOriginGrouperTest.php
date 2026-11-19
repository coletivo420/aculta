<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Service\NoticeOriginGrouper;
use Drupal\aculta_messenger\Service\NoticeListFilter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
   * Both the drawer and account center receive every allowed origin. */
  public function testOriginOptionsMatchTheFilterAllowlist(): void {
    $options = NoticeOriginGrouper::originOptions();

    self::assertSame(NoticeListFilter::ORIGINS, array_keys($options));
    self::assertSame('Revista', $options['revista']->getUntranslatedString());
  }

  /**
   * Groups notices by origin, ordering origin sections by their newest item.
   */
  public function testGroupsOriginsByLatestNoticeAndPreservesRecencyWithinOrigins(): void {
    $forumRecent = [
      '#attributes' => ['data-aculta-notice-origin' => 'forum', 'data-aculta-notice-recency' => '300'],
      'id' => 'forum-recent',
    ];
    $forumOlder = [
      '#attributes' => ['data-aculta-notice-origin' => 'forum', 'data-aculta-notice-recency' => '200'],
      'id' => 'forum-older',
    ];
    $wikiRecent = [
      '#attributes' => ['data-aculta-notice-origin' => 'wiki', 'data-aculta-notice-recency' => '400'],
      'id' => 'wiki-recent',
    ];
    $groups = NoticeOriginGrouper::group([$forumOlder, $wikiRecent, $forumRecent]);

    self::assertCount(2, $groups);
    self::assertSame('wiki', self::sectionOrigin($groups, 0));
    self::assertSame('wiki-recent', self::itemId($groups, 0, 0));
    self::assertArrayNotHasKey('separator', self::section($groups, 0));
    self::assertSame('forum', self::sectionOrigin($groups, 1));
    self::assertSame('Fórum', self::sectionLabel($groups, 1)->getUntranslatedString());
    self::assertSame('forum-recent', self::itemId($groups, 1, 0));
    self::assertSame('forum-older', self::itemId($groups, 1, 1));
    self::assertSame('hr', self::separatorTag($groups, 1));
  }

  /**
   * Keeps unclassified legacy extension items visible in their own section.
   */
  public function testKeepsUnclassifiedItemsInOtherNoticesSection(): void {
    $groups = NoticeOriginGrouper::group([['#plain_text' => 'aviso legado sem origem']]);

    self::assertCount(1, $groups);
    self::assertSame('outros', self::sectionOrigin($groups, 0));
    self::assertSame('aviso legado sem origem', self::itemText($groups, 0, 0));
  }

  /**
   * Friendship notices have a dedicated section between site alerts and LMS.
   */
  public function testGroupsFriendshipNoticesUnderTheirOwnOrigin(): void {
    $item = ['#attributes' => ['data-aculta-notice-origin' => 'amizade'], 'id' => 'friendship'];

    $groups = NoticeOriginGrouper::group([$item]);

    self::assertCount(1, $groups);
    self::assertSame('amizade', self::sectionOrigin($groups, 0));
    self::assertSame('Amizades', self::sectionLabel($groups, 0)->getUntranslatedString());
    self::assertSame([$item], self::sectionItems($groups, 0));
  }

  /**
   * Achievement notices have a labeled section in the same recency order.
   */
  public function testGroupsAchievementNoticesUnderTheirOwnOrigin(): void {
    $item = [
      '#attributes' => ['data-aculta-notice-origin' => 'conquistas', 'data-aculta-notice-recency' => '500'],
      'id' => 'award',
    ];

    $groups = NoticeOriginGrouper::group([$item]);

    self::assertCount(1, $groups);
    self::assertSame('conquistas', self::sectionOrigin($groups, 0));
    self::assertSame('Conquistas', self::sectionLabel($groups, 0)->getUntranslatedString());
    self::assertSame([$item], self::sectionItems($groups, 0));
  }

  /**
   * Revista uses a separated group, ordered by its latest notice.
   */
  public function testGroupsMagazineNoticesUnderTheirOwnOrigin(): void {
    $item = [
      '#attributes' => ['data-aculta-notice-origin' => 'revista', 'data-aculta-notice-recency' => '700'],
      'id' => 'publication',
    ];

    $groups = NoticeOriginGrouper::group([$item]);

    self::assertCount(1, $groups);
    self::assertSame('revista', self::sectionOrigin($groups, 0));
    self::assertSame('Revista', self::sectionLabel($groups, 0)->getUntranslatedString());
    self::assertSame([$item], self::sectionItems($groups, 0));
  }

  /**
   * Keeps extension items visible when their optional render attributes differ.
   */
  public function testMalformedAttributeMetadataRemainsStableAndDoesNotBreakGrouping(): void {
    $items = [
      ['#attributes' => 'legacy attributes', 'id' => 'string-attributes'],
      ['#attributes' => new \stdClass(), 'id' => 'object-attributes'],
    ];

    $groups = NoticeOriginGrouper::group($items);

    self::assertCount(1, $groups);
    self::assertSame('outros', self::sectionOrigin($groups, 0));
    self::assertSame($items, self::sectionItems($groups, 0));
  }

  /**
   * Returns a grouped section after proving its runtime shape.
   *
   * @param array<mixed> $groups
   *   Grouping result from the service under test.
   *
   * @return array<mixed>
   *   One render array section.
   */
  private static function section(array $groups, int $index): array {
    self::assertArrayHasKey($index, $groups);
    $section = $groups[$index];
    self::assertIsArray($section);
    return $section;
  }

  /**
   * Reads the section's origin marker.
   *
   * @param array<mixed> $groups
   *   Grouping result from the service under test.
   */
  private static function sectionOrigin(array $groups, int $index): string {
    $section = self::section($groups, $index);
    self::assertArrayHasKey('#attributes', $section);
    self::assertIsArray($section['#attributes']);
    self::assertArrayHasKey('data-aculta-notice-origin-section', $section['#attributes']);
    self::assertIsString($section['#attributes']['data-aculta-notice-origin-section']);
    return $section['#attributes']['data-aculta-notice-origin-section'];
  }

  /**
   * Reads the section heading after validating its render value.
   *
   * @param array<mixed> $groups
   *   Grouping result from the service under test.
   */
  private static function sectionLabel(array $groups, int $index): TranslatableMarkup {
    $section = self::section($groups, $index);
    self::assertArrayHasKey('heading', $section);
    self::assertIsArray($section['heading']);
    self::assertArrayHasKey('#value', $section['heading']);
    self::assertInstanceOf(TranslatableMarkup::class, $section['heading']['#value']);
    return $section['heading']['#value'];
  }

  /**
   * Returns the notices preserved in one section.
   *
   * @param array<mixed> $groups
   *   Grouping result from the service under test.
   *
   * @return array<mixed>
   *   Notices assigned to the section.
   */
  private static function sectionItems(array $groups, int $index): array {
    $section = self::section($groups, $index);
    self::assertArrayHasKey('items', $section);
    self::assertIsArray($section['items']);
    self::assertArrayHasKey('#items', $section['items']);
    self::assertIsArray($section['items']['#items']);
    return $section['items']['#items'];
  }

  /**
   * Reads a string ID from an item in the section.
   *
   * @param array<mixed> $groups
   *   Grouping result from the service under test.
   */
  private static function itemId(array $groups, int $sectionIndex, int $itemIndex): string {
    $items = self::sectionItems($groups, $sectionIndex);
    self::assertArrayHasKey($itemIndex, $items);
    self::assertIsArray($items[$itemIndex]);
    self::assertArrayHasKey('id', $items[$itemIndex]);
    self::assertIsString($items[$itemIndex]['id']);
    return $items[$itemIndex]['id'];
  }

  /**
   * Reads the plain text from a legacy notice item.
   *
   * @param array<mixed> $groups
   *   Grouping result from the service under test.
   */
  private static function itemText(array $groups, int $sectionIndex, int $itemIndex): string {
    $items = self::sectionItems($groups, $sectionIndex);
    self::assertArrayHasKey($itemIndex, $items);
    self::assertIsArray($items[$itemIndex]);
    self::assertArrayHasKey('#plain_text', $items[$itemIndex]);
    self::assertIsString($items[$itemIndex]['#plain_text']);
    return $items[$itemIndex]['#plain_text'];
  }

  /**
   * Reads the optional separator tag.
   *
   * @param array<mixed> $groups
   *   Grouping result from the service under test.
   */
  private static function separatorTag(array $groups, int $index): string {
    $section = self::section($groups, $index);
    self::assertArrayHasKey('separator', $section);
    self::assertIsArray($section['separator']);
    self::assertArrayHasKey('#tag', $section['separator']);
    self::assertIsString($section['separator']['#tag']);
    return $section['separator']['#tag'];
  }

}
