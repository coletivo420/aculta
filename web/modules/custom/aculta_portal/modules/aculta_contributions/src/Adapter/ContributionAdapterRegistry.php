<?php

declare(strict_types=1);

namespace Drupal\aculta_contributions\Adapter;

use Drupal\aculta_contributions\Contract\ContributionAdapterInterface;

/**
 * Registry of domain-owned contribution adapters. */
final class ContributionAdapterRegistry {

  /**
   * Registered adapters keyed by their stable machine names.
   *
   * @var array<string, \Drupal\aculta_contributions\Contract\ContributionAdapterInterface>
   */
  private array $adapters = [];

  /**
   * Registers an adapter discovered from a trusted service tag. */
  public function addAdapter(ContributionAdapterInterface $adapter): void {
    $id = $adapter->id();
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $id) || isset($this->adapters[$id])) {
      throw new \InvalidArgumentException('Contribution adapter IDs must be unique stable machine names.');
    }
    $this->adapters[$id] = $adapter;
  }

  /**
   * Returns a registered adapter; unknown IDs fail closed. */
  public function get(string $id): ContributionAdapterInterface {
    if (!isset($this->adapters[$id])) {
      throw new \InvalidArgumentException('No contribution adapter is registered for this source.');
    }
    return $this->adapters[$id];
  }

}
