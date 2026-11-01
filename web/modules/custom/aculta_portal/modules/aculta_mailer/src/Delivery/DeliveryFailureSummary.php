<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

/**
 * Builds a persisted failure summary without exception text or personal data.
 */
final class DeliveryFailureSummary {

  /**
   * Returns only a validated exception class and an SMTP response code.
   */
  public static function create(?string $errorClass, ?int $smtpCode): string {
    $class = is_string($errorClass)
      && preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/D', $errorClass) === 1
      ? $errorClass
      : 'UnknownTransportFailure';
    $code = $smtpCode !== NULL && $smtpCode >= 100 && $smtpCode <= 599 ? $smtpCode : NULL;
    return $code !== NULL ? $class . ' (SMTP ' . $code . ')' : $class;
  }

}
