<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\UserInterface;

/** Applies the selected account-lifetime retention period to personal evidence. */
final class AcultaUserAgreementHooks {

  public function __construct(private readonly Connection $database) {}

  /** Removes a person's evidence when their account is deleted. */
  #[Hook('user_delete')]
  public function userDelete(UserInterface $account): void {
    $this->database->delete('aculta_user_agreement')->condition('uid', $account->id())->execute();
  }

}
