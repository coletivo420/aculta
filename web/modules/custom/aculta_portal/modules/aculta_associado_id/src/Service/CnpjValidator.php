<?php

declare(strict_types=1);

namespace Drupal\aculta_associado_id\Service;

/**
 * Validates numeric and alphanumeric CNPJ using the Receita/Serpro algorithm.
 */
final class CnpjValidator {

  private const WEIGHTS = [
    [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
    [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
  ];

  public function isValid(string $value): bool {
    if (preg_match('/\A[A-Z0-9]{12}[0-9]{2}\z/', $value) !== 1
      || $value === '00000000000000') {
      return FALSE;
    }
    foreach (self::WEIGHTS as $stage => $weights) {
      $sum = 0;
      foreach ($weights as $i => $weight) {
        // This maps 0..9 to 0..9 and A..Z to 17..42 (not base 36).
        $sum += (ord($value[$i]) - 48) * $weight;
      }
      $remainder = $sum % 11;
      $digit = $remainder < 2 ? 0 : 11 - $remainder;
      if ((int) $value[12 + $stage] !== $digit) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
