<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Service;

use Drupal\aculta_qrcode\Service\QrCodeGenerator as CentralQrCodeGenerator;
use Drupal\aculta_qrcode\Service\QrCodePayloadValidator;
use Drupal\aculta_qrcode\Service\QrCodePresetRegistry;
use Drupal\aculta_qrcode\Value\QrCodeFormat;

/**
 * Generates dependency-free-to-render SVG QR codes for validated URLs.
 */
final class QrCodeSvgGenerator {

  public function __construct(
    private readonly QrCodePayloadValidator $payloadValidator,
    private readonly QrCodePresetRegistry $presetRegistry,
    private readonly CentralQrCodeGenerator $generator,
  ) {}

  /**
   * Generates a default-preset SVG through the shared ACULTA QR service.
   */
  public function generate(string $url): string {
    $payload = $this->payloadValidator->validateUrl($url);
    return $this->generator->generate(
      $payload,
      $this->presetRegistry->default(),
      QrCodeFormat::Svg,
    );
  }

}
