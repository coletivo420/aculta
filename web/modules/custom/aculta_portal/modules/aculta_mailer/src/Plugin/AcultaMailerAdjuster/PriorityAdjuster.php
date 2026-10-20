<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Plugin\AcultaMailerAdjuster;

use Drupal\aculta_mailer\Attribute\AcultaMailerAdjuster;
use Drupal\aculta_mailer\Policy\MailPolicy;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Prioridade do envio, de 1 (alta) a 5 (baixa).
 */
#[AcultaMailerAdjuster(
  id: 'priority',
  label: new TranslatableMarkup('Prioridade'),
  description: new TranslatableMarkup('Prioridade de 1 (alta) a 5 (baixa).'),
)]
final class PriorityAdjuster extends PluginBase implements AcultaMailerAdjusterInterface {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value): bool {
    return is_int($value) && $value >= 1 && $value <= 5;
  }

  /**
   * {@inheritdoc}
   */
  public function adjust(MailPolicy $policy, mixed $value): MailPolicy {
    return $this->validate($value) ? $policy->withPriority($value) : $policy;
  }

}
