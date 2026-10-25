<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Webhook;

/**
 * Evento já validado e normalizado.
 *
 * Identifica a mensagem pelo correlation ID (cabeçalho X-Aculta-Mail-ID) ou pelo ID que o provedor atribuiu à mensagem.
 */
final readonly class ProviderEvent {

  /**
   * Evento normalizado.
   *
   * @param \Drupal\aculta_mailer\Webhook\DeliveryEventType $type
   *   Tipo do evento.
   * @param string|null $correlationId
   *   Correlation ID do ACULTA, quando o provedor o devolveu.
   * @param string|null $providerMessageId
   *   ID da mensagem no provedor.
   * @param string|null $code
   *   Código curto do provedor (ex.: tipo de bounce). Sem texto livre.
   * @param int $occurredAt
   *   Instante do evento, em timestamp Unix.
   */
  public function __construct(
    public DeliveryEventType $type,
    public ?string $correlationId,
    public ?string $providerMessageId,
    public ?string $code,
    public int $occurredAt,
  ) {}

}
