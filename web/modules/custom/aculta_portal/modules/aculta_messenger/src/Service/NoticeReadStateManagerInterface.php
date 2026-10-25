<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\Session\AccountInterface;

/**
 * Changes read state through a currently enabled, authorized source adapter.
 */
interface NoticeReadStateManagerInterface {

  /**
   * Sets read state for an owned notice whose source still grants access.
   */
  public function setRead(int $messageId, AccountInterface $account, bool $read): bool;

}
