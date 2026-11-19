<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Unit\Route;

use Drupal\aculta_events\Route\EventRouteContext;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests route selection for the Events purpose.
 */
#[CoversClass(EventRouteContext::class)]
#[Group('aculta_events')]
final class EventRouteContextTest extends UnitTestCase {

  public function testUsesEventsRouteWhenEventsPurposeIsActive(): void {
    $siteManager = $this->createMock(DomainSiteManagerInterface::class);
    $siteManager->method('getCurrentSiteKey')->willReturn(SiteKey::Eventos);

    self::assertSame('organizer.route', (new EventRouteContext($siteManager))->route('main.route', 'organizer.route'));
  }

  public function testUsesMainRouteWhenEventsPurposeIsInactive(): void {
    $siteManager = $this->createMock(DomainSiteManagerInterface::class);
    $siteManager->method('getCurrentSiteKey')->willReturn(SiteKey::Main);

    self::assertSame('main.route', (new EventRouteContext($siteManager))->route('main.route', 'organizer.route'));
  }

}
