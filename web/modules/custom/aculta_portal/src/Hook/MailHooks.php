<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Email\AccountMailRouter;
use Drupal\aculta_portal\Mail\TestMailRedirect;
use Drupal\Core\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Site\Settings;

/**
 * Ajustes de e-mail do Portal.
 *
 * Os textos dos e-mails do Portal estão nos tipos registrados no aculta_mailer.
 */
final class MailHooks {

  public function __construct(
    #[Autowire(service: 'settings')]
    private readonly Settings $settings,
    private readonly AccountMailRouter $accountMail,
  ) {}

  /**
   * Redirecionamento de e-mails do ambiente de testes (inativo sem a configuração local).
   */
  #[Hook('mail_alter')]
  public function redirecionarTeste(array &$message): void {
    (new TestMailRedirect($this->settings))->aplicar($message);
  }

  /**
   * Textos de conta do Core (reset e cadastro) apresentados pela camada do aculta_mailer.
   */
  #[Hook('mail_alter')]
  public function textosDeConta(array &$message): void {
    $this->accountMail->apply($message);
  }

}
