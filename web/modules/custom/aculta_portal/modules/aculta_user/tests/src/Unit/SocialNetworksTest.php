<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Social\SocialNetworks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that profile social links are restricted to supported HTTPS/HTTP hosts.
 */
#[Group('aculta_user')]
final class SocialNetworksTest extends UnitTestCase {

  /**
   * Checks that only validated supported social-network addresses are accepted.
   */
  #[DataProvider('socialAddressProvider')]
  public function testKeyForUri(string $uri, ?string $expected): void {
    self::assertSame($expected, (new SocialNetworks())->keyForUri($uri));
  }

  /**
   * Supplies valid and invalid network addresses.
   *
   * @return array<string, array{string, ?string}>
   *   Address and expected network key pairs.
   */
  public static function socialAddressProvider(): array {
    return [
      'https instagram' => ['https://instagram.com/aculta', 'instagram'],
      'http facebook subdomain' => ['http://m.facebook.com/aculta', 'facebook'],
      'unsupported scheme' => ['javascript://instagram.com/aculta', NULL],
      'malformed URL' => ['https://instagram.com:invalid/aculta', NULL],
      'unsupported host' => ['https://instagram.example/aculta', NULL],
    ];
  }

}
