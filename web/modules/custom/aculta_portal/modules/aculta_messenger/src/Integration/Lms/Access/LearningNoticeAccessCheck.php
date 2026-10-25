<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Lms\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;
use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManagerInterface;

/**
 * Restricts opening a learning notice to its owner and current source access.
 */
final class LearningNoticeAccessCheck {

  public function __construct(
    private readonly LearningNoticeManagerInterface $notices,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Checks ownership and current access to the referenced LMS source.
   */
  public function access(MessageInterface $message): AccessResult {
    $allowed = (int) $message->getOwnerId() === (int) $this->currentUser->id()
      && $this->notices->resolve($message, $this->currentUser) !== NULL;
    return AccessResult::allowedIf($allowed)
      ->addCacheableDependency($message)
      ->cachePerUser()
      ->setCacheMaxAge(0);
  }

}
