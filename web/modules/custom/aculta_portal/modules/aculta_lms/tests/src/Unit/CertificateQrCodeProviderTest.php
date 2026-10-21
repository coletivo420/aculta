<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Certificate\CertificateQrCodeProvider;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_qrcode\Service\QrCodeGenerator;
use Drupal\aculta_qrcode\Service\QrCodePayloadValidator;
use Drupal\aculta_qrcode\Service\QrCodePresetRegistry;
use Drupal\aculta_qrcode\Value\QrCodeFormat;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the certificate-to-QR integration with purpose-aware URLs. */
#[CoversClass(CertificateQrCodeProvider::class)]
#[Group('aculta_lms')]
final class CertificateQrCodeProviderTest extends UnitTestCase {

  /**
   * Verifies that the QR payload is the absolute main-purpose verification URL.
   */
  public function testGeneratesSvgForAbsoluteMainPurposeVerificationUrl(): void {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('isHttps')->willReturn(TRUE);
    $domain_storage = $this->createMock(EntityStorageInterface::class);
    $domain_storage->expects(self::once())->method('load')->with('aculta_org')->willReturn($domain);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::once())->method('getStorage')->with('domain')->willReturn($domain_storage);
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->expects(self::once())->method('getActiveDomain')->willReturn(NULL);
    $purpose = new DomainPurposeManager(
      $entity_type_manager,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );

    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $url_generator->expects(self::once())
      ->method('generateFromRoute')
      ->with($this->identicalTo('aculta_lms.certificate_verify'), $this->identicalTo(['codigo' => 'opaque-code']), $this->callback(
        static fn(array $options): bool => ($options['absolute'] ?? FALSE) === TRUE
          && ($options['https'] ?? FALSE) === TRUE
          && ($options['domain'] ?? NULL) instanceof DomainInterface,
      ), FALSE)
      ->willReturn('https://aculta.org/verificar-certificado/opaque-code');
    $container = new ContainerBuilder();
    $container->set('url_generator', $url_generator);
    \Drupal::setContainer($container);

    $provider = new CertificateQrCodeProvider(
      new QrCodeGenerator(),
      new QrCodePayloadValidator(),
      new QrCodePresetRegistry(),
      $purpose,
    );

    $svg = $provider->generate('opaque-code', QrCodeFormat::Svg);

    self::assertStringContainsString('<svg', $svg);
    self::assertStringContainsString('</svg>', $svg);
  }

  /**
   * Fails closed when the purpose domain is unavailable.
   */
  public function testFailsClosedWhenMainPurposeDomainIsMissing(): void {
    $domain_storage = $this->createMock(EntityStorageInterface::class);
    $domain_storage->expects(self::once())->method('load')->with('aculta_org')->willReturn(NULL);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::once())->method('getStorage')->with('domain')->willReturn($domain_storage);
    $purpose = new DomainPurposeManager(
      $entity_type_manager,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $provider = new CertificateQrCodeProvider(
      new QrCodeGenerator(),
      new QrCodePayloadValidator(),
      new QrCodePresetRegistry(),
      $purpose,
    );

    $this->expectException(\InvalidArgumentException::class);
    $provider->generate('opaque-code', QrCodeFormat::Svg);
  }

}
