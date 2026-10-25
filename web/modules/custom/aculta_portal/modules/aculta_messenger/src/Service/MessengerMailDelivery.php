<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MessageIdFactory;

/**
 * Sends Messenger-owned direct email with stable retry idempotency.
 */
final class MessengerMailDelivery {

  /**
   * Delivers one logical notice and recognizes accepted legacy v4 mail.
   *
   * The UUID v5 is derived from a stable source identity, not email content or
   * recipient data. Old workers used a source UUID v4 directly; checking that
   * correlation first prevents a rollout from retransmitting a legacy message
   * whose local delivery state was not saved after Mailer acceptance.
   *
   * @param \Drupal\aculta_mailer\Api\AcultaMailerInterface $mailer
   *   ACULTA Mailer delivery service.
   * @param string $type
   *   Registered transactional mail type.
   * @param string $recipient
   *   Current recipient address, revalidated by the caller.
   * @param string $langcode
   *   Mail language code.
   * @param array $context
   *   Minimal template context for the email.
   * @param string $idempotencyKey
   *   Opaque identity of the logical delivery, stable across retries.
   * @param string|null $legacyCorrelation
   *   Correlation used by the previous worker implementation, if any.
   */
  public static function deliver(
    AcultaMailerInterface $mailer,
    string $type,
    string $recipient,
    string $langcode,
    array $context,
    string $idempotencyKey,
    ?string $legacyCorrelation = NULL,
  ): SendResult {
    if ($idempotencyKey === '') {
      throw new \InvalidArgumentException('Messenger mail idempotency key cannot be empty.');
    }
    if ($legacyCorrelation !== NULL
      && $mailer->wasAcceptedCorrelation($legacyCorrelation, $type, $recipient)) {
      return SendResult::sent();
    }

    $correlation = MessageIdFactory::correlationIdFor('aculta_messenger:' . $idempotencyKey);
    return $mailer->deliver(new MailMessage($type, $recipient, $langcode, $context, $correlation));
  }

}
