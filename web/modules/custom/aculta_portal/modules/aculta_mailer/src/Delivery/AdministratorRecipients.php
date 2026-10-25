<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * E-mails dos administradores do site (papel `administrator`, conta ativa), para alertas e resumos.
 */
class AdministratorRecipients {

  /**
   * Papel de administrador do site.
   */
  public const ROLE = 'administrator';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypes,
  ) {}

  /**
   * Endereços dos administradores ativos com e-mail, em minúsculas e sem repetição.
   *
   * @return string[]
   *   Endereços.
   */
  public function emails(): array {
    $storage = $this->entityTypes->getStorage('user');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('roles', self::ROLE)
      ->execute();
    $emails = [];
    foreach ($storage->loadMultiple($ids) as $account) {
      $email = strtolower(trim((string) $account->getEmail()));
      if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== FALSE) {
        $emails[$email] = $email;
      }
    }
    return array_values($emails);
  }

}
