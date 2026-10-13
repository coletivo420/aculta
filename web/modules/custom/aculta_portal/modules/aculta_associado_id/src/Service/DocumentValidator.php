<?php

declare(strict_types=1);

namespace Drupal\aculta_associado_id\Service;

use Drupal\aculta_associado_id\Enum\DocumentType;

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

  /**
   * Detects CPF or CNPJ from a complete representation, with or without the full mask.
   *
   * The formats do not overlap: CPF has 11 digits or the 14-character masked form, CNPJ has 14
   * alphanumeric characters or the 18-character masked form. Check digits are not verified here.
   *
   * @throws \InvalidArgumentException
   *   When the input matches neither format; never includes the input.
   */
  public function detectType(string $input): DocumentType {
    foreach (DocumentType::cases() as $type) {
      try {
        $this->normalizer->normalize($input, $type);
        return $type;
      }
      catch (\InvalidArgumentException) {
        continue;
      }
    }
    throw new \InvalidArgumentException('Invalid document format.');
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
