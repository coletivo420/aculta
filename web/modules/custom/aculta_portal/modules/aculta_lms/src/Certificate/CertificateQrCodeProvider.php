<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Certificate;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_qrcode\Service\QrCodeGenerator;
use Drupal\aculta_qrcode\Service\QrCodePayloadValidator;
use Drupal\aculta_qrcode\Service\QrCodePresetRegistry;
use Drupal\aculta_qrcode\Value\QrCodeFormat;

/**
 * Integrates certificate verification with the shared QR Code service. */
final class CertificateQrCodeProvider implements CertificateQrCodeProviderInterface {

  public function __construct(
    private readonly ?QrCodeGenerator $generator,
    private readonly ?QrCodePayloadValidator $payloadValidator,
    private readonly ?QrCodePresetRegistry $presets,
    private readonly ?DomainSiteManager $siteManager,
  ) {}

  /**
   * {@inheritdoc} */
  public function generate(string $verificationCode, QrCodeFormat $format): string {
    if ($this->generator === NULL || $this->payloadValidator === NULL || $this->presets === NULL || $this->siteManager === NULL) {
      throw new \InvalidArgumentException('Certificate QR services are unavailable.');
    }

    $url = $this->siteManager->routeUrl(SiteKey::Main,
      'aculta_lms.certificate_verify',
      ['codigo' => $verificationCode],
    );
    if ($url === NULL) {
      throw new \InvalidArgumentException('Certificate verification URL is unavailable.');
    }
    $payload = $this->payloadValidator->validateUrl($url->toString());
    return $this->generator->generate($payload, $this->presets->default(), $format);
  }

}
