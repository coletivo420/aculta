<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Access;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Route access for the authenticated Forum reading controls. */
final class ForumReadAccess {

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * Allows reading controls only to the signed-in user on the Forum purpose.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $allowed = $account->isAuthenticated()
      && $this->domainPurposeManager->getCurrentPurpose() === 'forum';
    return ($allowed ? AccessResult::allowed() : AccessResult::forbidden())
      ->addCacheContexts(['user', 'domain']);
  }

}
