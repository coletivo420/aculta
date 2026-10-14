<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\UserInterface;

/** Maintains friendship data through account deletion and module lifecycle. */
final class FriendshipLifecycleHooks {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  /** Removes private relationships when either member account is deleted. */
  #[Hook('user_delete')]
  public function removeRelationshipsForDeletedMember(UserInterface $account): void {
    $uid = (int) $account->id();
    if ($uid < 1) {
      return;
    }
    $query = $this->entityTypeManager->getStorage('aculta_friendship')->getQuery();
    $eitherParticipant = $query->orConditionGroup()
      ->condition('member_low', $uid)
      ->condition('member_high', $uid);
    $ids = $query->accessCheck(FALSE)->condition($eitherParticipant)->execute();
    if ($ids !== []) {
      $this->entityTypeManager->getStorage('aculta_friendship')->delete(
        $this->entityTypeManager->getStorage('aculta_friendship')->loadMultiple($ids),
      );
    }
  }

  /** Stops uninstall if friendship records would otherwise be discarded. */
  #[Hook('module_preuninstall')]
  public function protectFriendshipData(string $module, bool $isSyncing): void {
    if ($module !== 'aculta_user') {
      return;
    }
    $count = $this->entityTypeManager->getStorage('aculta_friendship')->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    if ($count > 0) {
      throw new \RuntimeException('ACULTA User cannot be uninstalled while friendship records remain. Export or remove the records explicitly first.');
    }
  }

}
