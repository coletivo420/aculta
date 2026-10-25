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
