<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Wiki;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;

/**
 * Restricts Wiki notice opening to its owner with current source access. */
final class AccessWikiNotice {

  public function __construct(
    private readonly WikiNoticeManagerInterface $notices,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Checks ownership and source authorization for a Message entity. */
  public function access(MessageInterface $message): AccessResult {
    return AccessResult::allowedIf($this->notices->resolve($message, $this->currentUser) !== NULL)
      ->addCacheableDependency($message)
      ->cachePerUser()
      ->setCacheMaxAge(0);
  }

}
