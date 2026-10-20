<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\EventSubscriber;

use Drupal\aculta_mailer\Delivery\SmtpFailureRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\FailedMessageEvent;

/**
 * Lê o código SMTP de falhas de transporte e o registra por correlação (ver SmtpFailureRegistry).
 */
final class MailFailureSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly SmtpFailureRegistry $failures,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [FailedMessageEvent::class => 'onFailedMessage'];
  }

  /**
   * Registra o código de resposta (ex.: 550) quando a exceção o informa; 0 vira NULL.
   */
  public function onFailedMessage(FailedMessageEvent $event): void {
    $correlation = $event->getMessage()->getHeaders()->getHeaderBody('X-Aculta-Mail-ID');
    if (!is_string($correlation) || $correlation === '') {
      return;
    }
    $code = (int) $event->getError()->getCode();
    $this->failures->remember($correlation, $code > 0 ? $code : NULL);
  }

}
