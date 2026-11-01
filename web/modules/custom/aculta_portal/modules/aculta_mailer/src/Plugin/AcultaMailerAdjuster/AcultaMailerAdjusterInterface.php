<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Plugin\AcultaMailerAdjuster;

use Drupal\aculta_mailer\Policy\MailPolicy;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Ajuste de política: valida o valor configurado e aplica-o à política efetiva.
 */
interface AcultaMailerAdjusterInterface extends PluginInspectionInterface {

  /**
   * Indica se o valor configurado é aceito por este ajuste.
   */
  public function validate(mixed $value): bool;

  /**
   * Política com o ajuste aplicado. Valores inválidos são ignorados.
   */
  public function adjust(MailPolicy $policy, mixed $value): MailPolicy;

}
