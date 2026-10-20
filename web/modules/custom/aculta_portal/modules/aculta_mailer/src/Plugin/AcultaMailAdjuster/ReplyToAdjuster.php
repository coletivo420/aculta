<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Plugin\AcultaMailAdjuster;

use Drupal\aculta_mailer\Attribute\AcultaMailAdjuster;
use Drupal\aculta_mailer\Policy\MailPolicy;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Endereço de resposta (Reply-To) do envio.
 */
#[AcultaMailAdjuster(
  id: 'reply_to',
  label: new TranslatableMarkup('Responder para'),
  description: new TranslatableMarkup('Endereço de resposta. Deve ser um e-mail válido.'),
)]
final class ReplyToAdjuster extends PluginBase implements AcultaMailAdjusterInterface {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value): bool {
    return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function adjust(MailPolicy $policy, mixed $value): MailPolicy {
    return $this->validate($value) ? $policy->withReplyTo($value) : $policy;
  }

}
