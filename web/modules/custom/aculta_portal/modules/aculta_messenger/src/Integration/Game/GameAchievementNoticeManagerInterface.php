<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Game;

use Drupal\aculta_messenger\Aviso\PaginatedPersonalNoticeAdapterInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;

/**
 * Owns the shared notice adapter for ACULTA Game achievement awards. */
interface GameAchievementNoticeManagerInterface extends PaginatedPersonalNoticeAdapterInterface {

  /**
   * Persists an eligible achievement event idempotently. */
  public function record(MessengerNoticeEvent $event): ?MessageInterface;

  /**
   * Revalidates and changes the notice read state for its owner. */
  public function setRead(MessageInterface $message, AccountInterface $account, bool $read): bool;

}
