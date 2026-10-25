<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Delivery\DeliverySettings;
use Drupal\aculta_mailer\Entity\MailTransport;
use Drupal\aculta_mailer\Transport\BrevoSecretTransportFactory;
use Drupal\aculta_mailer\Transport\MailTransportFactory;
use Drupal\aculta_mailer\Transport\MailTransportSender;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Mime\Email;

/**
 * O MAIL FROM do SMTP usa o endereço de retorno configurado (o subscriber não alcança o transporte da fábrica).
 */
#[Group('aculta_mailer')]
final class MailTransportSenderEnvelopeTest extends UnitTestCase {

  /**
   * SMTP envelope uses the configured return address.
   */
  public function testSmtpUsesTheConfiguredBounceAddressAsEnvelopeSender(): void {
    $envelope = $this->sender('dmarc@example.invalid')->envelopeFor($this->transport('smtp'), $this->email());

    $this->assertNotNull($envelope);
    $this->assertSame('dmarc@example.invalid', $envelope->getSender()->getAddress());
    $this->assertSame(['destino@example.invalid'], array_map(static fn($address) => $address->getAddress(), $envelope->getRecipients()));
  }

  /**
   * API transport leaves envelope selection to its sender default.
   */
  public function testApiTransportKeepsTheSenderDefault(): void {
    $this->assertNull($this->sender('dmarc@example.invalid')->envelopeFor($this->transport('brevo'), $this->email()));
  }

  /**
   * Empty and invalid return paths leave envelope selection to the sender.
   */
  public function testEmptyOrInvalidBounceAddressKeepsTheSenderDefault(): void {
    $this->assertNull($this->sender('')->envelopeFor($this->transport('smtp'), $this->email()));
    $this->assertNull($this->sender('nao-e-email')->envelopeFor($this->transport('smtp'), $this->email()));
  }

  /**
   * Transport failure exposes the exception class for safe diagnostics.
   */
  public function testTransportFailureExposesSafeExceptionClassForDiagnostics(): void {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturn('');
    $config = $this->createMock(ConfigFactoryInterface::class);
    $config->method('get')->willReturn($settings);
    $factory = new MailTransportFactory(NULL, new BrevoSecretTransportFactory(NULL));
    $sender = new MailTransportSender($factory, $config);
    $result = $sender->send($this->transport('unknown'), $this->email());

    $this->assertFalse($result['sent']);
    $this->assertSame(\RuntimeException::class, $result['error_class']);
    $this->assertStringContainsString('unknown', $result['message']);
  }

  /**
   * Creates a sender for the provided bounce address.
   */
  private function sender(string $bounce): MailTransportSender {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->with('bounce_address')->willReturn($bounce);
    $config = $this->createMock(ConfigFactoryInterface::class);
    $config->method('get')->with(DeliverySettings::CONFIG)->willReturn($settings);
    // A fábrica é final e não é usada por envelopeFor(): instância sem construtor.
    $factory = (new \ReflectionClass(MailTransportFactory::class))->newInstanceWithoutConstructor();
    return new MailTransportSender($factory, $config);
  }

  /**
   * Creates a test transport with the given plugin.
   */
  private function transport(string $plugin): MailTransport {
    return new MailTransport(['id' => 'teste_' . $plugin, 'plugin' => $plugin], 'aculta_mailer_transport');
  }

  /**
   * Creates an email used by envelope tests.
   */
  private function email(): Email {
    return (new Email())->from('naoresponda@example.invalid')->to('destino@example.invalid')->subject('teste')->text('teste');
  }

}
