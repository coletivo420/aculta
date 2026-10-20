<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Delivery\DeliverySettings;
use Drupal\aculta_mailer\EventSubscriber\ReturnPathSubscriber;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Envelope;

/**
 * Envelope de retorno: troca só em SMTP, com endereço configurado e válido.
 */
#[CoversClass(ReturnPathSubscriber::class)]
#[Group('aculta_mailer')]
final class ReturnPathSubscriberTest extends UnitTestCase {

  /**
   * Evento de envio com o remetente do site no envelope.
   */
  private function event(string $transport, bool $queued = FALSE): MessageEvent {
    $email = (new Email())->from('noreply@example.invalid')->to('destino@example.invalid')->text('x');
    return new MessageEvent($email, Envelope::create($email), $transport, $queued);
  }

  /**
   * Assinante com o endereço de retorno informado.
   */
  private function subscriber(string $bounce): ReturnPathSubscriber {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturn($bounce);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn($config);
    return new ReturnPathSubscriber($factory);
  }

  /**
   * SMTP com endereço configurado: o envelope passa a ser o endereço de retorno.
   */
  public function testSmtpEnvelopeUsesReturnPath(): void {
    $event = $this->event('smtp://mail.example.invalid');
    $this->subscriber('dmarc@example.invalid')->onMessage($event);
    $this->assertSame('dmarc@example.invalid', $event->getEnvelope()->getSender()->getAddress());
  }

  /**
   * Transporte por API não muda o envelope.
   */
  public function testApiTransportIsNotChanged(): void {
    $event = $this->event('brevo+api://api.brevo.com');
    $this->subscriber('dmarc@example.invalid')->onMessage($event);
    $this->assertSame('noreply@example.invalid', $event->getEnvelope()->getSender()->getAddress());
  }

  /**
   * Sem endereço configurado, nada muda.
   */
  public function testEmptyAddressChangesNothing(): void {
    $event = $this->event('smtp://mail.example.invalid');
    $this->subscriber('')->onMessage($event);
    $this->assertSame('noreply@example.invalid', $event->getEnvelope()->getSender()->getAddress());
  }

  /**
   * Envio em fila (ainda não enviado) não é alterado aqui.
   */
  public function testQueuedEventIsSkipped(): void {
    $event = $this->event('smtp://mail.example.invalid', TRUE);
    $this->subscriber('dmarc@example.invalid')->onMessage($event);
    $this->assertSame('noreply@example.invalid', $event->getEnvelope()->getSender()->getAddress());
  }

}
