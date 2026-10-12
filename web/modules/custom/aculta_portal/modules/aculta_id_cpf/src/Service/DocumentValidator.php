<?php

declare(strict_types=1);

namespace Drupal\aculta_id_cpf\Service;

use Drupal\aculta_id_cpf\Enum\DocumentType;

/**
 * Shared entry point for aculta_portal forms and persistence integrations.
 */
final class DocumentValidator {

  public function __construct(
    private readonly DocumentNormalizer $normalizer,
    private readonly CpfValidator $cpfValidator,
    private readonly CnpjValidator $cnpjValidator,
  ) {}

  /**
   * Normalizes and validates, returning a canonical string only on success.
   *
   * @throws \InvalidArgumentException
   *   When the format or check digits are invalid; never includes the input.
   */
  public function canonicalize(string $input, DocumentType $type): string {
    $value = $this->normalizer->normalize($input, $type);
    $valid = match ($type) {
      DocumentType::CPF => $this->cpfValidator->isValid($value),
      DocumentType::CNPJ => $this->cnpjValidator->isValid($value),
    };
    if (!$valid) {
      throw new \InvalidArgumentException('Invalid document check digits.');
    }
    return $value;
  }

  public function isValid(string $input, DocumentType $type): bool {
    try {
      $this->canonicalize($input, $type);
      return TRUE;
    }
    catch (\InvalidArgumentException) {
      return FALSE;
    }
  }

}
