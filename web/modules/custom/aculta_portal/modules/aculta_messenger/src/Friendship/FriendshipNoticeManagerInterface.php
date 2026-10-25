<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Friendship;

use Drupal\aculta_messenger\Aviso\PaginatedPersonalNoticeAdapterInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;

/**
 * Public read-state contract for site notices about friendship transitions.
 */
interface FriendshipNoticeManagerInterface extends PaginatedPersonalNoticeAdapterInterface {

  /**
   * Changes a notice's read state after checking its owner and event type.
   */
  public function setRead(MessageInterface $message, AccountInterface $account, bool $read): bool;

}
