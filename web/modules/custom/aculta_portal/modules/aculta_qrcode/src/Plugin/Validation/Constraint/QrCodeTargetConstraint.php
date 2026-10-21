<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Exige destino `http` ou `https` sem credenciais para um código QR dinâmico.
 */
#[Constraint(
  id: 'AcultaQrCodeTarget',
  label: new TranslatableMarkup('Destino seguro de código QR', [], ['context' => 'Validation']),
)]
final class QrCodeTargetConstraint extends SymfonyConstraint {

  /**
   * Mensagem de violação para destinos inválidos.
   */
  public string $message = 'Informe uma URL http ou https absoluta, sem credenciais e com até 2048 caracteres.';

}
