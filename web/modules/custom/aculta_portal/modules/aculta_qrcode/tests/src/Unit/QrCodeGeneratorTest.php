<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_qrcode\Unit;

use Drupal\aculta_qrcode\Service\QrCodeGenerator;
use Drupal\aculta_qrcode\Service\QrCodePresetRegistry;
use Drupal\aculta_qrcode\Value\QrCodeFormat;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Testa a geração de QR Codes SVG e PNG sem gravação e sem serviços externos.
 */
#[Group('aculta_qrcode')]
final class QrCodeGeneratorTest extends UnitTestCase {

  /**
   * Gera o SVG com as cores do preset padrão.
   */
  public function testGeneratesSvgWithPresetColors(): void {
    $preset = (new QrCodePresetRegistry())->default();
    $svg = (new QrCodeGenerator())->generate('https://aculta.org/', $preset, QrCodeFormat::Svg);

    self::assertStringContainsString('<svg', $svg);
    self::assertStringContainsString('</svg>', $svg);
    self::assertStringContainsString('#1b1b1b', $svg);
    self::assertStringContainsString('#ffffff', $svg);
  }

  /**
   * Gera PNG com a assinatura do formato.
   */
  public function testGeneratesPngSignature(): void {
    $preset = (new QrCodePresetRegistry())->default();
    $png = (new QrCodeGenerator())->generate('texto de teste', $preset, QrCodeFormat::Png);

    self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
  }

  /**
   * Gera WebP com o contêiner RIFF/WEBP.
   */
  public function testGeneratesWebpContainer(): void {
    $preset = (new QrCodePresetRegistry())->default();
    $webp = (new QrCodeGenerator())->generate('texto de teste', $preset, QrCodeFormat::Webp);

    self::assertStringStartsWith('RIFF', $webp);
    self::assertSame('WEBP', substr($webp, 8, 4));
  }

  /**
   * Associa cada formato ao tipo MIME esperado.
   */
  public function testFormatsDeclareMimeType(): void {
    self::assertSame('image/svg+xml', QrCodeFormat::Svg->mimeType());
    self::assertSame('image/png', QrCodeFormat::Png->mimeType());
    self::assertSame('image/webp', QrCodeFormat::Webp->mimeType());
  }

  /**
   * Gera o mesmo conteúdo de forma determinística.
   */
  public function testOutputIsDeterministicForSameInput(): void {
    $preset = (new QrCodePresetRegistry())->default();
    $generator = new QrCodeGenerator();

    self::assertSame(
      $generator->generate('https://aculta.org/qr', $preset, QrCodeFormat::Svg),
      $generator->generate('https://aculta.org/qr', $preset, QrCodeFormat::Svg),
    );
  }

}
