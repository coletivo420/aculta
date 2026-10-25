<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_links\Unit;

use Drupal\aculta_links\Service\QrCodeSvgGenerator;
use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;
use Drupal\aculta_qrcode\Service\QrCodeGenerator;
use Drupal\aculta_qrcode\Service\QrCodePayloadValidator;
use Drupal\aculta_qrcode\Service\QrCodePresetRegistry;
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
    $svg = $this->generator()->generate('https://links.example.test/pagina/projeto-cultural');

    self::assertStringContainsString('<svg', $svg);
    self::assertMatchesRegularExpression('/width="[0-9]+px"/', $svg);
    self::assertMatchesRegularExpression('/height="[0-9]+px"/', $svg);
    self::assertStringContainsString('</svg>', $svg);
  }

  /**
   * Ensures the shared QR validator rejects unsafe or relative destinations.
   */
  public function testRejectsNonAbsoluteAndExecutableUrls(): void {
    $generator = $this->generator();

    $this->expectException(InvalidQrCodeException::class);
    $generator->generate('javascript:alert(1)');
  }

  /**
   * Creates the adapter with the production QR Code services.
   */
  private function generator(): QrCodeSvgGenerator {
    return new QrCodeSvgGenerator(
      new QrCodePayloadValidator(),
      new QrCodePresetRegistry(),
      new QrCodeGenerator(),
    );
  }

}
