<?php

declare(strict_types=1);

namespace Drupal\aculta_id_cpf\Service;

use Drupal\aculta_id_cpf\Enum\DocumentType;

/**
 * Recognizes complete ASCII input without silently repairing malformed data.
 */
final class DocumentNormalizer {

  /**
   * Returns the unpunctuated value, preserving leading zeroes and CNPJ letters.
   *
   * @throws \InvalidArgumentException
   *   When the input does not match a supported complete representation.
   */
  public function normalize(string $input, DocumentType $type): string {
    if (strlen($input) > 64) {
      throw new \InvalidArgumentException('Invalid document format.');
    }

    // Only ASCII spaces around the value are accepted. Control characters,
    // internal whitespace and look-alike Unicode digits must not be removed.
    $value = trim($input, ' ');
    $patterns = match ($type) {
      DocumentType::CPF => [
        '/\A[0-9]{11}\z/',
        '/\A[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}\z/',
      ],
      DocumentType::CNPJ => [
        '/\A[A-Za-z0-9]{12}[0-9]{2}\z/',
        '/\A[A-Za-z0-9]{2}\.[A-Za-z0-9]{3}\.[A-Za-z0-9]{3}\/[A-Za-z0-9]{4}-[0-9]{2}\z/',
      ],
    };
    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $value) === 1) {
        return strtoupper(str_replace(['.', '/', '-'], '', $value));
      }
    }
    // Deliberately do not include the input in exceptions or logs.
    throw new \InvalidArgumentException('Invalid document format.');
  }

}
