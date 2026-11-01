<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\user\UserInterface;

/**
 * Cadastro com e-mail já usado (DT-P06): quem já tem conta é avisado; ninguém é revelado.
 *
 * Não cria conta, não loga ninguém e não diz se o e-mail existe. A resposta ao visitante é a mesma nos dois
 * casos (ver PortalFormCallbacks). Quem tem conta recebe um aviso no próprio endereço, enviado pelo aculta_mailer.
 */
final class RegistrationMailPolicy {

  public const MAIL_KEY = 'registration_existing_mail';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AcultaMailerInterface $mailer,
    private readonly UrlGeneratorInterface $urlGenerator,
    private readonly UuidInterface $uuid,
  ) {}

  /**
   * Conta que já usa este e-mail, ou NULL.
   */
  public function existingAccount(string $mail): ?UserInterface {
    $mail = trim($mail);
    if ($mail === '') {
      return NULL;
    }
    $found = $this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $mail]);
    $account = reset($found);
    return $account instanceof UserInterface ? $account : NULL;
  }

  /**
   * Envia o aviso à conta existente. Retorna TRUE se o envio foi aceito.
   */
  public function notifyExistingAccount(UserInterface $account): bool {
    $message = new MailMessage(
      self::MAIL_KEY,
      (string) $account->getEmail(),
      $account->getPreferredLangcode() ?: 'und',
      [
        'login_url' => $this->urlGenerator->generateFromRoute('user.login', [], ['absolute' => TRUE]),
        'recovery_url' => $this->urlGenerator->generateFromRoute('user.pass', [], ['absolute' => TRUE]),
      ],
      $this->uuid->generate(),
    );
    return $this->mailer->send($message);
  }

}
