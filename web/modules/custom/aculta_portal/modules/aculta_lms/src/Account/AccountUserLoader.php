<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Account;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Resolves session accounts to cacheable Drupal user entities.
 */
final class AccountUserLoader implements AccountUserLoaderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function load(AccountInterface $account): ?UserInterface {
    if ($account instanceof UserInterface) {
      return $account;
    }
    $id = $account->id();
    if ($id === NULL) {
      return NULL;
    }
    $user = $this->entityTypeManager->getStorage('user')->load($id);
    return $user instanceof UserInterface ? $user : NULL;
  }

}
