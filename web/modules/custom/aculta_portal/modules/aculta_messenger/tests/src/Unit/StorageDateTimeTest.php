<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Support\StorageDateTime;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Stored datetime values are UTC whatever the viewer's timezone is.
 */
#[Group('aculta_messenger')]
final class StorageDateTimeTest extends UnitTestCase {

  public function testStoredValueIsReadAsUtcForAnyDefaultTimezone(): void {
    $previous = date_default_timezone_get();
    try {
      date_default_timezone_set('America/Sao_Paulo');
      $saoPaulo = StorageDateTime::toTimestamp('2026-10-15T12:00:00');
      date_default_timezone_set('UTC');
      $utc = StorageDateTime::toTimestamp('2026-10-15T12:00:00');
    }
    finally {
      date_default_timezone_set($previous);
    }
    $this->assertSame($utc, $saoPaulo);
    $this->assertSame(1792065600, $utc);
  }

  public function testEmptyAndInvalidValuesAreNull(): void {
    $this->assertNull(StorageDateTime::toTimestamp(NULL));
    $this->assertNull(StorageDateTime::toTimestamp(''));
    $this->assertNull(StorageDateTime::toTimestamp('não é data'));
  }

}
