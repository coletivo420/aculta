<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Service\SiteAlertNoticeSorter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\site_alert\Entity\SiteAlert;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests chronological ordering for paginated site alerts.
 */
#[CoversClass(SiteAlertNoticeSorter::class)]
#[Group('aculta_messenger')]
final class SiteAlertNoticeSorterTest extends UnitTestCase {

  /**
   * Creation time defines recency, with ID used only for equal timestamps.
   */
  public function testSortsNewestFirstBeforeStableIdTieBreak(): void {
    $olderHighId = $this->alert(900, 100);
    $newerLowId = $this->alert(12, 300);
    $sameTimeLowId = $this->alert(5, 200);
    $sameTimeHighId = $this->alert(8, 200);

    $sorted = SiteAlertNoticeSorter::sort([
      $olderHighId,
      $sameTimeLowId,
      $newerLowId,
      $sameTimeHighId,
    ]);

    self::assertSame([$newerLowId, $sameTimeHighId, $sameTimeLowId, $olderHighId], $sorted);
  }

  /**
   * Creates an active alert with deterministic creation metadata.
   */
  private function alert(int $id, int $created): SiteAlert {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(FALSE);
    $field->method('__get')->with('value')->willReturn($created);

    $alert = $this->getMockBuilder(SiteAlert::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['id', 'hasField', 'get'])
      ->getMock();
    $alert->method('id')->willReturn((string) $id);
    $alert->method('hasField')->with('created')->willReturn(TRUE);
    $alert->method('get')->with('created')->willReturn($field);
    return $alert;
  }

}
