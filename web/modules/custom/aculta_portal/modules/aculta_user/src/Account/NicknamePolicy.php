<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Account;

use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Shared lookup for nickname collisions during account creation and editing.
 *
 * Keeps the existing Profile field, type scope and case-insensitive storage.
 * The grammar and slug policy remain in AccountNames.
 */
final class NicknamePolicy {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /**
   * Whether another participant already uses this nickname.
   *
   * The current account's role profiles are excluded during an edit.
   */
  public function isTaken(string $nickname, ?int $excludeUid = NULL): bool {
    $candidates = [$nickname];
    if (AccountNames::isHashSuffixNickname($nickname)
      && preg_match('/^(.+)#((?:[2-9]|[1-9][0-9]+))$/', $nickname, $matches)) {
      $candidates[] = $matches[1] . '-' . $matches[2];
    }
    elseif (preg_match('/^(.+)-((?:[2-9]|[1-9][0-9]+))$/', $nickname, $matches)) {
      $generated = $matches[1] . '#' . $matches[2];
      if (AccountNames::isHashSuffixNickname($generated)) {
        $candidates[] = $generated;
      }
    }
    foreach (array_unique($candidates) as $candidate) {
      if ($this->profileNicknameExists($candidate, $excludeUid)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks one stored display value against the owning role Profiles.
   */
  private function profileNicknameExists(string $nickname, ?int $excludeUid): bool {
    $query = $this->entities->getStorage('profile')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', PerfilDoPapel::TIPOS, 'IN')
      ->condition('field_nickname', $nickname);
    if ($excludeUid !== NULL) {
      $query->condition('uid', $excludeUid, '<>');
    }
    return $query->range(0, 1)->count()->execute() > 0;
  }

}
