<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Delivery\DeliverySettings;
use Drupal\aculta_mailer\Entity\MailTransport;
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

  public function testSmtpUsesTheConfiguredBounceAddressAsEnvelopeSender(): void {
    $envelope = $this->sender('dmarc@aculta.org')->envelopeFor($this->transport('smtp'), $this->email());

    $this->assertNotNull($envelope);
    $this->assertSame('dmarc@aculta.org', $envelope->getSender()->getAddress());
    $this->assertSame(['destino@example.invalid'], array_map(static fn($address) => $address->getAddress(), $envelope->getRecipients()));
  }

  public function testApiTransportKeepsTheSenderDefault(): void {
    $this->assertNull($this->sender('dmarc@aculta.org')->envelopeFor($this->transport('brevo'), $this->email()));
  }

  public function testEmptyOrInvalidBounceAddressKeepsTheSenderDefault(): void {
    $this->assertNull($this->sender('')->envelopeFor($this->transport('smtp'), $this->email()));
    $this->assertNull($this->sender('nao-e-email')->envelopeFor($this->transport('smtp'), $this->email()));
  }

  private function sender(string $bounce): MailTransportSender {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->with('bounce_address')->willReturn($bounce);
    $config = $this->createMock(ConfigFactoryInterface::class);
    $config->method('get')->with(DeliverySettings::CONFIG)->willReturn($settings);
    // A fábrica é final e não é usada por envelopeFor(): instância sem construtor.
    $factory = (new \ReflectionClass(MailTransportFactory::class))->newInstanceWithoutConstructor();
    return new MailTransportSender($factory, $config);
  }

  private function transport(string $plugin): MailTransport {
    return new MailTransport(['id' => 'teste_' . $plugin, 'plugin' => $plugin], 'aculta_mailer_transport');
  }

  private function email(): Email {
    return (new Email())->from('naoresponda@aculta.org')->to('destino@example.invalid')->subject('teste')->text('teste');
  }

}
