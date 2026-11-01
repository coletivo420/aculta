<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Wiki;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;
use Drupal\aculta_messenger\Aviso\PaginatedPersonalNoticeAdapterInterface;

/**
 * Public adapter contract for Wiki notices in the Messenger. */
interface WikiNoticeManagerInterface extends PaginatedPersonalNoticeAdapterInterface {

  /**
   * Persists an eligible Wiki event for its recipient. */
  public function record(MessengerNoticeEvent $event): ?MessageInterface;

  /**
   * Resolves a current, accessible source and its safe presentation. */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array;

  /**
   * Sets the read state after revalidating ownership and source access.
   */
  public function setRead(MessageInterface $message, AccountInterface $account, bool $read): bool;

}
