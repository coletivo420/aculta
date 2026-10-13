<?php

declare(strict_types=1);

namespace Drupal\aculta_associado_id\Service;

/**
 * Validates the check digits of a canonical CPF; does not verify ownership.
 */
final class CpfValidator {

  public function isValid(string $value): bool {
    if (preg_match('/\A[0-9]{11}\z/', $value) !== 1
      || preg_match('/\A([0-9])\1{10}\z/', $value) === 1) {
      return FALSE;
    }
    for ($length = 9; $length <= 10; $length++) {
      $sum = 0;
      for ($i = 0; $i < $length; $i++) {
        $sum += (int) $value[$i] * ($length + 1 - $i);
      }
      $remainder = $sum % 11;
      $digit = $remainder < 2 ? 0 : 11 - $remainder;
      if ((int) $value[$length] !== $digit) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
