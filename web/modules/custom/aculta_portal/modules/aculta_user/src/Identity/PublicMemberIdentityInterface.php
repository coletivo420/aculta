<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Identity;

use Drupal\user\UserInterface;

/**
 * Resolves only the member name approved for public presentation.
 */
interface PublicMemberIdentityInterface {

  /**
   * Returns the public nickname, or NULL when no public identity is available.
   */
  public function publicDisplayName(UserInterface $account): ?string;

}
