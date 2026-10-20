<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Service;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Generates dependency-free-to-render SVG QR codes for validated URLs.
 */
final class QrCodeSvgGenerator {

  /**
   * Generates an SVG QR code for a URL already resolved by Drupal.
   */
  public function generate(string $url): string {
    $code = new QrCode(
      data: $url,
      encoding: new Encoding('UTF-8'),
      errorCorrectionLevel: ErrorCorrectionLevel::Medium,
      size: 320,
      margin: 12,
      roundBlockSizeMode: RoundBlockSizeMode::Margin,
    );

    return (new SvgWriter())->write($code)->getString();
  }

}
