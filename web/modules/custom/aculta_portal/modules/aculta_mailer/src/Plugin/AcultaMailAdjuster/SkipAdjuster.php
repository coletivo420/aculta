<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Plugin\AcultaMailAdjuster;

use Drupal\aculta_mailer\Attribute\AcultaMailAdjuster;
use Drupal\aculta_mailer\Policy\MailPolicy;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Suspende o envio do tipo (a mensagem é cancelada, não reenviada).
 */
#[AcultaMailAdjuster(
  id: 'skip',
  label: new TranslatableMarkup('Suspender envio'),
  description: new TranslatableMarkup('Quando verdadeiro, a mensagem não é enviada.'),
)]
final class SkipAdjuster extends PluginBase implements AcultaMailAdjusterInterface {

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
