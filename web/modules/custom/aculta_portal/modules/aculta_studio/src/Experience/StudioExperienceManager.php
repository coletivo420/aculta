<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Registry of independent Studio experiences, including Ganjalab and WE SATIVA.
 */
final class StudioExperienceManager {

  /** @var array<string, \Drupal\aculta_studio\Experience\StudioExperienceProviderInterface> */
  private array $providers = [];

  /**
   * Adds an experience without silently shadowing an existing identifier.
   */
  public function addProvider(StudioExperienceProviderInterface $provider): void {
    $id = $provider->id();
    if ($id === '' || isset($this->providers[$id])) {
      throw new \InvalidArgumentException(sprintf('Invalid or duplicate Studio experience ID: %s', $id));
    }
    $this->providers[$id] = $provider;
  }

  /**
   * Registered providers in their configured order.
   *
   * Availability, authorization and route existence must still be checked
   * by consumers; this inventory grants no permissions.
   *
   * @return array<string, \Drupal\aculta_studio\Experience\StudioExperienceProviderInterface>
   *   Providers keyed by stable experience ID.
   */
  public function all(): array {
    return $this->providers;
  }

  public function get(string $id): ?StudioExperienceProviderInterface {
    return $this->providers[$id] ?? NULL;
  }

  /**
   * Checks availability, domain purpose and user access; always cacheable.
   */
  public function access(string $id, string $purpose, AccountInterface $account): AccessResultInterface {
    $provider = $this->get($id);
    if ($provider === NULL || !$provider->available() || $provider->purpose() !== $purpose) {
      return AccessResult::forbidden()->addCacheContexts(['user', 'url.site']);
    }
    return $provider->access($account);
  }

}
