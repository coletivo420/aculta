<?php

declare(strict_types=1);

namespace Drupal\aculta_mail_confirmer\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Utility\Token;

/**
 * Mensagens do pedido de confirmação de e-mail (módulo aculta_mail_confirmer).
 */
final class MailHooks {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly Token $token,
  ) {}

  /**
   * Monta o pedido de confirmação com o texto e os tokens do próprio módulo.
   */
  #[Hook('mail')]
  public function mail(string $key, array &$message, array $params): void {
    if ($key !== 'confirmation_request') {
      return;
    }
    $context = $params['context'];
    $settings = $this->configFactory->get('aculta_mail_confirmer.settings');
    $message['to'] = $context['aculta_mail_confirmer']->getEmail();
    $message['subject'] = $this->token->replace($settings->get('confirmation_request.subject'), $context, ['sanitize' => FALSE]);
    $message['body'][] = $this->token->replace($settings->get('confirmation_request.body'), $context, ['sanitize' => FALSE]);
  }

}
