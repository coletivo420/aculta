<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\EventSubscriber;

use Drupal\aculta_mailer\Delivery\DeliverySettings;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;

/**
 * Endereço de retorno no envelope (MAIL FROM) dos transportes SMTP.
 *
 * O Drupal monta o remetente do site (DefaultsEmailProcessor) depois de qualquer cabeçalho que o módulo tenha
 * definido, e isso sobrescreve o Sender. A troca fica no evento de envio do Symfony, que é o último ponto antes do
 * transporte. Transportes por API (DeliverySettings::API_TRANSPORTS) não são alterados.
 */
final class ReturnPathSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [MessageEvent::class => ['onMessage', 0]];
  }

  /**
   * Troca o envelope pelo endereço de retorno, quando configurado e o transporte é SMTP.
   */
  public function onMessage(MessageEvent $event): void {
    if ($event->isQueued() || $this->isApiTransport($event->getTransport())) {
      return;
    }
    $address = trim((string) $this->configFactory->get(DeliverySettings::CONFIG)->get('bounce_address'));
    if ($address === '' || filter_var($address, FILTER_VALIDATE_EMAIL) === FALSE) {
      return;
    }
    $envelope = $event->getEnvelope();
    $envelope->setSender(new Address($address));
    $event->setEnvelope($envelope);
  }

  /**
   * Transporte por API, pelo prefixo do nome (ex.: brevo+api://...).
   */
  private function isApiTransport(string $name): bool {
    foreach (DeliverySettings::API_TRANSPORTS as $api) {
      if (str_starts_with(strtolower($name), $api)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
