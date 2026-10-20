<?php

declare(strict_types=1);

namespace Drupal\aculta_mail_confirmer_user\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Utility\Token;

/**
 * Mensagens da troca de e-mail da conta.
 */
final class UserEmailChangeMailHooks {

  /**
   * Creates the hooks.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(private readonly Token $token) {
  }

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail(string $key, array &$message, array $params): void {
    switch ($key) {
      case 'notify_current':
        $context = $params['context'];

        // @todo recipient name?
        $message['to'] = $context['user']->getEmail();
        $message['subject'] = $this->token->replace(t('Email change request for your user account at [site:name]'), $context, ['sanitize' => FALSE]);
        $message['body'][] = $this->token->replace(t('A request to change your email address has been made at [site:name]. In order to confirm the update of your email address you will need to follow the instructions sent to your new email address.'), $context, ['sanitize' => FALSE]);
        break;
    }
  }

}
