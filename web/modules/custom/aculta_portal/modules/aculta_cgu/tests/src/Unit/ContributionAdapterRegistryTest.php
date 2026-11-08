<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_cgu\Unit;

use Drupal\aculta_cgu\Adapter\ContributionAdapterRegistry;
use Drupal\aculta_cgu\Contract\ContributionAdapterInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests trusted adapter registration and fail-closed discovery. */
final class ContributionAdapterRegistryTest extends UnitTestCase {

  /**
   * Unknown IDs and duplicate IDs cannot resolve to an adapter. */
  public function testRegistryRejectsUnknownAndDuplicateAdapters(): void {
    $registry = new ContributionAdapterRegistry();
    $adapter = $this->createMock(ContributionAdapterInterface::class);
    $adapter->method('id')->willReturn('wiki_entry');
    $registry->addAdapter($adapter);
    self::assertSame($adapter, $registry->get('wiki_entry'));

    $this->expectException(\InvalidArgumentException::class);
    $registry->get('client_supplied_service');
  }

  /**
   * Duplicate service IDs fail instead of silently replacing domain policy. */
  public function testDuplicateAdapterIdIsRejected(): void {
    $registry = new ContributionAdapterRegistry();
    $adapter = $this->createMock(ContributionAdapterInterface::class);
    $adapter->method('id')->willReturn('wiki_entry');
    $registry->addAdapter($adapter);
    $this->expectException(\InvalidArgumentException::class);
    $registry->addAdapter($adapter);
  }

}
