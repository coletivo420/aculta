<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Experience;

use Drupal\aculta_events\Access\EventPermissionPolicy;
use Drupal\aculta_studio\Experience\StudioExperienceProviderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Entry adapter for the Events owner's authorized organizer workspace. */
final class CulturasExperienceProvider implements StudioExperienceProviderInterface {

  public function __construct(private readonly EventPermissionPolicy $policy) {}

  /**
   * {@inheritdoc} */
  public function id(): string {
    return 'culturas';
  }

  /**
   * {@inheritdoc} */
  public function label(): string {
    return 'ACULTA Studio Culturas';
  }

  /**
   * {@inheritdoc} */
  public function purpose(): string {
    return 'eventos';
  }

  /**
   * {@inheritdoc} */
  public function routeName(): string {
    return 'aculta_events.organizer_dashboard';
  }

  /**
   * {@inheritdoc} */
  public function available(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc} */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->isAuthenticated())
      ->andIf(AccessResult::allowedIfHasPermission($account, 'access aculta portal'))
      ->andIf($this->policy->createEventAccessResult($account))
      ->addCacheContexts(['user']);
  }

  /**
   * {@inheritdoc} */
  public function build(): array {
    return [];
  }

}
