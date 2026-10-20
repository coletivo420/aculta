<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_links\Unit;

use Drupal\aculta_links\Service\QrCodeSvgGenerator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests SVG QR generation without image extensions or external services.
 */
#[Group('aculta_links')]
final class QrCodeSvgGeneratorTest extends UnitTestCase {

  /**
   * Tests the output structure for a public canonical URL.
   */
  public function testGeneratesSvgFromCanonicalUrl(): void {
    $svg = (new QrCodeSvgGenerator())->generate('https://links.example.test/pagina/projeto-cultural');

    self::assertStringContainsString('<svg', $svg);
    self::assertMatchesRegularExpression('/width="[0-9]+px"/', $svg);
    self::assertMatchesRegularExpression('/height="[0-9]+px"/', $svg);
    self::assertStringContainsString('</svg>', $svg);
  }

}
