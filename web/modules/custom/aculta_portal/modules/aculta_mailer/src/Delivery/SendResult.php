<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

/**
 * Resultado de uma tentativa de envio: aceito, ou falha com classe e código SMTP quando conhecidos.
 */
final readonly class SendResult {

  private function __construct(
    public bool $sent,
    public ?DeliveryError $error,
    public ?int $smtpCode,
    public bool $cancelled = FALSE,
  ) {}

  /**
   * Não enviado por decisão da política (suspensão). Não é falha: não há retry.
   */
  public static function cancelled(): self {
    return new self(FALSE, NULL, NULL, TRUE);
  }

  /**
   * Aceito pelo sistema de e-mail (status `sent`).
   */
  public static function sent(): self {
    return new self(TRUE, NULL, NULL);
  }

  /**
   * Falha, com a classe do erro e, quando o transporte informou, o código de resposta SMTP.
   */
  public static function failed(DeliveryError $error, ?int $smtpCode): self {
    return new self(FALSE, $error, $smtpCode);
  }

}
