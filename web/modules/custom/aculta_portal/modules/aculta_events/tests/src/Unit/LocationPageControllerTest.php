<?php

declare(strict_types=1);

namespace DrupalTests\aculta_events\Unit;

use Drupal\aculta_events\Controller\LocationPageController;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests public site URL filtering for location pages.
 */
#[CoversClass(LocationPageController::class)]
final class LocationPageControllerTest extends UnitTestCase {

  /**
   * Invalid site values must not reach public href or structured data output.
   */
  #[DataProvider('siteUrlProvider')]
  public function testSafeSiteUrl(?string $expected, string $value): void {
    $method = new \ReflectionMethod(LocationPageController::class, 'safeSiteUrl');
    $method->setAccessible(TRUE);

    $this->assertSame($expected, $method->invoke(NULL, $value));
  }

  /**
   * Site URL cases accepted or omitted by the public page.
   */
  public static function siteUrlProvider(): iterable {
    yield 'http' => ['http://example.com/local', 'http://example.com/local'];
    yield 'https' => ['https://example.com/local', 'https://example.com/local'];
    yield 'javascript' => [NULL, 'javascript:alert(1)'];
    yield 'data' => [NULL, 'data:text/html,<script>alert(1)</script>'];
    yield 'malformed' => [NULL, 'https://'];
    yield 'markup' => [NULL, 'https://example.com/\"><script>alert(1)</script>'];
    yield 'empty' => [NULL, ''];
  }

}
