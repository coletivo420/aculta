<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Events;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;

/**
 * Restricts opening an Events contribution notice to its authorized author. */
final class AccessEventsContributionNotice {

  /**
   * Creates the access checker with the current account and notice manager.
   */
  public function __construct(
    private readonly EventsContributionNoticeManagerInterface $notices,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Checks current ownership and source access before allowing the route. */
  public function access(MessageInterface $message): AccessResult {
    return AccessResult::allowedIf($this->notices->resolve($message, $this->currentUser) !== NULL)
      ->addCacheableDependency($message)
      ->cachePerUser()
      ->setCacheMaxAge(0);
  }

}
