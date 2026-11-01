<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Plugin\Validation\Constraint;

use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;
use Drupal\aculta_qrcode\Service\QrCodePayloadValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Aplica `QrCodePayloadValidator::validateUrl()` ao destino do código.
 *
 * O validador é sem estado e não depende de serviços; é instanciado pelo
 * Core sem container, por isso usa o validador diretamente.
 */
final class QrCodeTargetConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof QrCodeTargetConstraint || $value === NULL || $value === '') {
      return;
    }
    try {
      (new QrCodePayloadValidator())->validateUrl((string) $value);
    }
    catch (InvalidQrCodeException) {
      $this->context->addViolation($constraint->message);
    }
  }

}
