<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Unit\Service;

use Drupal\aculta_events\Service\WebsiteUrl;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the allowed schemes for public event website links.
 */
#[CoversClass(WebsiteUrl::class)]
#[Group('aculta_events')]
final class WebsiteUrlTest extends UnitTestCase {

  public function testAcceptsAbsoluteHttpAndHttpsUrls(): void {
    self::assertTrue(WebsiteUrl::isValid('http://example.org/evento'));
    self::assertTrue(WebsiteUrl::isValid('https://example.org/evento'));
    self::assertTrue(WebsiteUrl::isValid('HTTPS://example.org/evento'));
  }

  public function testRejectsExecutableAndNonWebSchemes(): void {
    self::assertFalse(WebsiteUrl::isValid('javascript://example.org/%0Aalert(1)'));
    self::assertFalse(WebsiteUrl::isValid('data://example.org/evento'));
    self::assertFalse(WebsiteUrl::isValid('ftp://example.org/evento'));
    self::assertFalse(WebsiteUrl::isValid('/evento'));
  }

}
