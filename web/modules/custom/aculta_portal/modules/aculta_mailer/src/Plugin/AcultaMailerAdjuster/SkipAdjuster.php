<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Plugin\AcultaMailerAdjuster;

use Drupal\aculta_mailer\Attribute\AcultaMailerAdjuster;
use Drupal\aculta_mailer\Policy\MailPolicy;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Suspende o envio do tipo (a mensagem é cancelada, não reenviada).
 */
#[AcultaMailerAdjuster(
  id: 'skip',
  label: new TranslatableMarkup('Suspender envio'),
  description: new TranslatableMarkup('Quando verdadeiro, a mensagem não é enviada.'),
)]
final class SkipAdjuster extends PluginBase implements AcultaMailerAdjusterInterface {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value): bool {
    return is_bool($value);
  }

  /**
   * {@inheritdoc}
   */
  public function adjust(MailPolicy $policy, mixed $value): MailPolicy {
    return $this->validate($value) ? $policy->withSkip($value) : $policy;
  }

}
