<?php

declare(strict_types=1);

namespace Drupal\aculta_id_cpf\Form;

use Drupal\aculta_id_cpf\Enum\DocumentType;
use Drupal\aculta_id_cpf\Service\DocumentValidator;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Serializable service callback for the CPF/CNPJ Form API elements.
 */
final class DocumentElementValidation {

  public function __construct(
    private readonly DocumentValidator $validator,
    private readonly TranslationInterface $translation,
  ) {}

  public function validate(array &$element, FormStateInterface $formState, array &$completeForm): void {
    $type = DocumentType::from($element['#aculta_document_type']);
    $value = $element['#value'] ?? '';
    if ($value === '' && empty($element['#aculta_invalid_input'])) {
      // Form API enforces #required; optional empty fields stay empty.
      return;
    }
    if (!is_string($value) || !empty($element['#aculta_invalid_input'])) {
      $this->invalid($element, $formState, $type);
      return;
    }
    try {
      $canonical = $this->validator->canonicalize($value, $type);
      $formState->setValueForElement($element, $canonical);
    }
    catch (\InvalidArgumentException) {
      $this->invalid($element, $formState, $type);
    }
  }

  private function invalid(array &$element, FormStateInterface $formState, DocumentType $type): void {
    $message = match ($type) {
      DocumentType::CPF => $this->translation->translate('Informe um CPF válido, com ou sem pontuação.'),
      DocumentType::CNPJ => $this->translation->translate('Informe um CNPJ válido, com ou sem pontuação. Letras são aceitas nas primeiras 12 posições.'),
    };
    $formState->setError($element, $message);
  }

}
