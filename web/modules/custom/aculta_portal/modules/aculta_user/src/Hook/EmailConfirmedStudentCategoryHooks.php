<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Assigns the LMS student category when an account confirms its email address. */
final class EmailConfirmedStudentCategoryHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** Implements hook_aculta_portal_email_confirmed(). */
  #[Hook('aculta_portal_email_confirmed')]
  public function emailConfirmed(int $uid): void {
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account instanceof UserInterface) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('profile');
    $existing = $storage->getQuery()->accessCheck(FALSE)
      ->condition('uid', $account->id())->condition('type', 'estudante')->range(0, 1)->execute();
    if ($existing === []) {
      $storage->create(['type' => 'estudante', 'uid' => $account->id()])->save();
    }
  }

}
