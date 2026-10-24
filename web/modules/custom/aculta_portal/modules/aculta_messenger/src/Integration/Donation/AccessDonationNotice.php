<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Donation;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;

/**
 * Restricts Donation notices to their owner and accessible source. */
final class AccessDonationNotice {

  public function __construct(
    private readonly DonationNoticeManagerInterface $notices,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Checks ownership and current source authorization. */
  public function access(MessageInterface $message): AccessResult {
    return AccessResult::allowedIf($this->notices->resolve($message, $this->currentUser) !== NULL)
      ->addCacheableDependency($message)
      ->cachePerUser()
      ->setCacheMaxAge(0);
  }

}
