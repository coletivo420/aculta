<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Service\NoticeRelatedGrouper;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests safe grouping of notices related to the same source entity.
 */
#[CoversClass(NoticeRelatedGrouper::class)]
#[Group('aculta_messenger')]
final class NoticeRelatedGrouperTest extends UnitTestCase {

  /**
   * Keeps individual notices and actions inside the accessible disclosure.
   */
  public function testGroupsRelatedItemsInOriginalOrderAndKeepsPerItemActions(): void {
    $key = NoticeRelatedGrouper::sourceKey('wiki', 'source-uuid');
    self::assertNotNull($key);
    $first = [
      '#attributes' => ['data-aculta-notice-group-key' => $key, 'data-aculta-notice-read' => 'unread'],
      'read_state_action' => ['#type' => 'form', '#id' => 'read-1'],
    ];
    $unrelated = ['#attributes' => ['data-aculta-notice-origin' => 'forum']];
    $second = [
      '#attributes' => ['data-aculta-notice-group-key' => $key, 'data-aculta-notice-read' => 'read'],
      'read_state_action' => ['#type' => 'form', '#id' => 'read-2'],
    ];
    $single = [
      '#attributes' => ['data-aculta-notice-group-key' => NoticeRelatedGrouper::sourceKey('doacao', 'source-uuid')],
    ];

    $grouped = NoticeRelatedGrouper::group([$first, $unrelated, $second, $single]);

    self::assertCount(3, $grouped);
    $firstGroup = self::arrayAt($grouped, 0);
    self::assertSame('details', $firstGroup['#type']);
    $itemsElement = self::arrayAt($firstGroup, 'items');
    self::assertIsArray($itemsElement['#items']);
    self::assertCount(2, $itemsElement['#items']);
    $firstItem = self::arrayAt($itemsElement['#items'], 0);
    $secondItem = self::arrayAt($itemsElement['#items'], 1);
    self::assertSame('read-1', self::arrayAt($firstItem, 'read_state_action')['#id']);
    self::assertSame('read-2', self::arrayAt($secondItem, 'read_state_action')['#id']);
    self::assertArrayNotHasKey('data-aculta-notice-group-key', self::arrayAt($firstItem, '#attributes'));
    self::assertSame($unrelated, self::arrayAt($grouped, 1));
    self::assertArrayNotHasKey('data-aculta-notice-group-key', self::arrayAt(self::arrayAt($grouped, 2), '#attributes'));
  }

  /**
   * Rejects unsupported origins and malformed source identifiers.
   */
  public function testSourceKeyRequiresSupportedOriginAndCleanIdentifier(): void {
    self::assertNull(NoticeRelatedGrouper::sourceKey('unknown', 'source-uuid'));
    self::assertNull(NoticeRelatedGrouper::sourceKey('wiki', "source\n-uuid"));
    self::assertNull(NoticeRelatedGrouper::sourceKey('wiki', '  '));
    self::assertNotSame(
      NoticeRelatedGrouper::sourceKey('wiki', 'same-uuid'),
      NoticeRelatedGrouper::sourceKey('doacao', 'same-uuid'),
    );
  }

  /**
   * Validates and returns one item from the generated render array.
   *
   * @param array<mixed> $items
   *   Render items produced by the grouper.
   *
   * @return array<mixed>
   *   One render array.
   */
  private static function arrayAt(array $items, int|string $key): array {
    self::assertArrayHasKey($key, $items);
    self::assertIsArray($items[$key]);
    return $items[$key];
  }

}
