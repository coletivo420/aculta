<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Plugin\AcultaMailerAdjuster;

use Drupal\aculta_mailer\Attribute\AcultaMailerAdjuster;
use Drupal\aculta_mailer\Policy\MailPolicy;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Transporte do envio. Aceita somente os transportes do projeto (padrão e contingência).
 */
#[AcultaMailerAdjuster(
  id: 'transport',
  label: new TranslatableMarkup('Transporte'),
  description: new TranslatableMarkup('Transporte de envio: smtp (padrão) ou smtp2go (contingência).'),
)]
final class TransportAdjuster extends PluginBase implements AcultaMailerAdjusterInterface {

  /**
   * Transportes aceitos.
   */
  private const ALLOWED = ['smtp', 'smtp2go'];

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value): bool {
    return is_string($value) && in_array($value, self::ALLOWED, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function adjust(MailPolicy $policy, mixed $value): MailPolicy {
    return $this->validate($value) ? $policy->withTransport($value) : $policy;
  }

}
