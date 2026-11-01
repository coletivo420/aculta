<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Resolves configurable Magazine capabilities without replacing Drupal ACLs. */
final class EditorialCapability {

  public const AUTHORING = 'authoring';
  public const REVIEW = 'review';
  public const COMMENTS = 'comments';

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  /**
   * Whether the account has a configured ACULTA capability. */
  public function access(string $capability, AccountInterface $account): AccessResultInterface {
    if (!in_array($capability, [self::AUTHORING, self::REVIEW, self::COMMENTS], TRUE)) {
      return AccessResult::forbidden()->addCacheContexts(['user.roles']);
    }
    $roles = $this->configFactory->get('aculta_magazine.settings')->get($capability . '_roles') ?? [];
    $allowed = array_intersect($account->getRoles(), is_array($roles) ? $roles : []) !== [];
    return AccessResult::allowedIf($allowed)->addCacheContexts(['user.roles'])->addCacheTags(['config:aculta_magazine.settings']);
  }

  /**
   * Whether the account may administer the Magazine control panel. */
  public function administer(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->isAuthenticated() && $account->hasPermission('administer site configuration'))
      ->addCacheContexts(['user.permissions']);
  }

}
