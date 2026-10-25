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
    self::assertSame('details', $grouped[0]['#type']);
    self::assertCount(2, $grouped[0]['items']['#items']);
    self::assertSame('read-1', $grouped[0]['items']['#items'][0]['read_state_action']['#id']);
    self::assertSame('read-2', $grouped[0]['items']['#items'][1]['read_state_action']['#id']);
    self::assertArrayNotHasKey('data-aculta-notice-group-key', $grouped[0]['items']['#items'][0]['#attributes']);
    self::assertSame($unrelated, $grouped[1]);
    self::assertArrayNotHasKey('data-aculta-notice-group-key', $grouped[2]['#attributes']);
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

}
