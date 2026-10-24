<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Commerce;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;
use Drupal\aculta_messenger\Aviso\PersonalNoticeAdapterInterface;

/**
 * Shared interface for private Commerce order notices. */
interface CommerceOrderNoticeManagerInterface extends PersonalNoticeAdapterInterface {

  /**
   * Persists an eligible event for the order's active customer. */
  public function record(MessengerNoticeEvent $event): ?MessageInterface;

  /**
   * Resolves the order and current access for an account. */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array;

  /**
   * Sets the read state after revalidating ownership and order access.
   */
  public function setRead(MessageInterface $message, AccountInterface $account, bool $read): bool;

}
