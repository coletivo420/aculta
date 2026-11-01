<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Webhook;

use Drupal\aculta_mailer\Delivery\MailSuppression;
use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Aplica um evento validado ao registro do envio, somente quando a transição é permitida (ver MailStatus).
 *
 * Devolução permanente, endereço inválido e spam também suprimem o destinatário para envios futuros.
 */
final class DeliveryEventApplier {

  /**
   * Códigos que suprimem o destinatário: devolução permanente, endereço inválido e reclamação de spam.
   */
  private const SUPPRESSING_CODES = ['hard', 'invalid', 'spam'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MailSuppression $suppression,
  ) {}

  /**
   * Aplica o evento. Retorna TRUE quando o registro mudou.
   *
   * Evento sem registro correspondente, ou com transição não permitida, é ignorado (retorna FALSE). Eventos de provedor
   * podem chegar fora de ordem ou repetidos; a regra de transição protege o estado.
   */
  public function apply(ProviderEvent $event): bool {
    $log = $this->find($event);
    if ($log === NULL) {
      return FALSE;
    }
    $current = MailStatus::from((string) $log->get('status')->value);
    $next = $event->type->status();
    if (!$current->canTransitionTo($next)) {
      return FALSE;
    }
    $log->set('status', $next->value);
    // Estado confirmado pelo provedor: o conteúdo para reenvio já não é necessário e sai do registro.
    if (in_array($next, [MailStatus::Delivered, MailStatus::Bounced, MailStatus::Complained], TRUE)) {
      $log->set('payload_cipher', NULL);
      $log->set('payload_expires', NULL);
    }
    if ($event->providerMessageId !== NULL && $event->providerMessageId !== '') {
      $log->set('provider_message_id', $event->providerMessageId);
    }
    if ($event->code !== NULL && $event->code !== '') {
      $log->set('error_code', 'provedor_' . $event->code);
    }
    $log->save();
    if (in_array($event->code, self::SUPPRESSING_CODES, TRUE) && $log->get('recipient_hash')->value) {
      $this->suppression->add((string) $log->get('recipient_hash')->value);
    }
    return TRUE;
  }

  /**
   * Registro pelo correlation ID, ou pelo ID do provedor quando o correlation não veio.
   */
  private function find(ProviderEvent $event): ?object {
    $storage = $this->entityTypeManager->getStorage(MailLogRecorder::ENTITY_TYPE);
    if ($event->correlationId !== NULL && $event->correlationId !== '') {
      $found = $storage->loadByProperties(['correlation_id' => $event->correlationId]);
    }
    elseif ($event->providerMessageId !== NULL && $event->providerMessageId !== '') {
      $found = $storage->loadByProperties(['provider_message_id' => $event->providerMessageId]);
    }
    else {
      return NULL;
    }
    return $found === [] ? NULL : reset($found);
  }

}
