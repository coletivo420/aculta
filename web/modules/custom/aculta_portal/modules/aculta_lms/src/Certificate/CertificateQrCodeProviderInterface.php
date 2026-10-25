<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Certificate;

use Drupal\aculta_qrcode\Value\QrCodeFormat;

/**
 * Supplies generated QR representations for public certificate checks. */
interface CertificateQrCodeProviderInterface {

  /**
   * Generates a QR image that resolves to the certificate's public check. */
  public function generate(string $verificationCode, QrCodeFormat $format): string;

}
