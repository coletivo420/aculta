<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Events;

use Drupal\aculta_messenger\Aviso\PaginatedPersonalNoticeAdapterInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;

/**
 * Owner contract for private Events contribution outcome notices. */
interface EventsContributionNoticeManagerInterface extends PaginatedPersonalNoticeAdapterInterface {

  /**
   * Stores an allowlisted proposal outcome after source access validation. */
  public function record(MessengerNoticeEvent $event): ?MessageInterface;

  /**
   * Resolves a currently authorized notice into safe presentation data. */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array;

  /**
   * Sets the read state after revalidating ownership and proposal access. */
  public function setRead(MessageInterface $message, AccountInterface $account, bool $read): bool;

}
