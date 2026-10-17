<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Account\RegistrationMailPolicy;
use Drupal\aculta_portal\Mail\TestMailRedirect;
use Drupal\Core\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/** Textos dos e-mails do Portal. */
final class MailHooks {

  public function __construct(
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
    #[Autowire(service: 'settings')]
    private readonly Settings $settings,
  ) {}

  /** Redirecionamento de e-mails do ambiente de testes (inativo sem a configuração local). */
  #[Hook('mail_alter')]
  public function redirecionarTeste(array &$message): void {
    (new TestMailRedirect($this->settings))->aplicar($message);
  }

  #[Hook('mail')]
  public function mail(string $key, array &$message, array $params): void {
    if ($key !== RegistrationMailPolicy::MAIL_KEY) {
      return;
    }
    $login = Url::fromRoute('user.login', [], ['absolute' => TRUE])->toString();
    $recovery = Url::fromRoute('user.pass', [], ['absolute' => TRUE])->toString();
    $message['subject'] = $this->translation->translate('Tentativa de cadastro com o seu e-mail');
    $message['body'][] = $this->translation->translate("Olá,\n\nAlguém tentou criar uma conta na Associação Cultural Antiproibicionista usando este endereço de e-mail. Ele já está cadastrado, por isso nenhuma conta nova foi criada.\n\nSe foi você, acesse a sua conta:\n@login\n\nSe você esqueceu a senha, recupere-a:\n@recovery\n\nSe não foi você, não é preciso fazer nada. Sua conta continua como está.\n\nAssociação Cultural Antiproibicionista", ['@login' => $login, '@recovery' => $recovery]);
  }

}
