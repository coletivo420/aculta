<?php

declare(strict_types=1);

namespace Drupal\aculta_associado_id\Service;

use Drupal\aculta_associado_id\Enum\DocumentType;

/**
 * Pure formatting helpers; authorization belongs to callers, not the mask.
 */
final class DocumentFormatter {

  public function __construct(private readonly DocumentValidator $validator) {}

  public function format(string $input, DocumentType $type): string {
    $value = $this->validator->canonicalize($input, $type);
    return match ($type) {
      DocumentType::CPF => substr($value, 0, 3) . '.' . substr($value, 3, 3) . '.' . substr($value, 6, 3) . '-' . substr($value, 9, 2),
      DocumentType::CNPJ => substr($value, 0, 2) . '.' . substr($value, 2, 3) . '.' . substr($value, 5, 3) . '/' . substr($value, 8, 4) . '-' . substr($value, 12, 2),
    };
  }

  /**
   * Shows the first five characters and hides the rest with '*', keeping the mask punctuation.
   * Example: 974.12*.***-**. Does not grant read access; callers decide who may see it.
   */
  public function partial(string $input, DocumentType $type): string {
    $formatted = $this->format($input, $type);
    $visible = 5;
    $position = 0;
    $out = '';
    foreach (str_split($formatted) as $char) {
      if ($char === '.' || $char === '/' || $char === '-') {
        $out .= $char;
        continue;
      }
      $out .= $position < $visible ? $char : '*';
      $position++;
    }
    return $out;
  }

  /**
   * Hides the body, leaving only the check digits; does not grant read access.
   */
  public function redact(string $input, DocumentType $type): string {
    $value = $this->validator->canonicalize($input, $type);
    return match ($type) {
      DocumentType::CPF => '***.***.***-' . substr($value, -2),
      DocumentType::CNPJ => '**.***.***/****-' . substr($value, -2),
    };
  }

}
