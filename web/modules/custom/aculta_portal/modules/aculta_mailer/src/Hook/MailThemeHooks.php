<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Envelope HTML dos e-mails do módulo (hook de tema `aculta_mailer_wrap`).
 *
 * A apresentação fica no tema, com o mesmo nome de template; sem tema que a implemente, o template do módulo usa
 * estrutura neutra.
 */
final class MailThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'aculta_mailer_wrap' => [
        'variables' => [
          'body' => '',
          'subject' => '',
          'type' => '',
          'sub_type' => '',
          'is_html' => TRUE,
          'public_base' => '',
          'layout' => FALSE,
        ],
      ],
    ];
  }

}
