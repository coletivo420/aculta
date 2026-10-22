<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Provider;

/**
 * Registry for providers contributed by enabled source modules.
 */
final class ContentIndexProviderRegistry {

  /**
   * The providers indexed by stable provider ID.
   *
   * @var array<string, \Drupal\aculta_index\Provider\ContentIndexProviderInterface>
   */
  private array $providers = [];

  /**
   * Builds the registry from tagged services.
   *
   * @param iterable<\Drupal\aculta_index\Provider\ContentIndexProviderInterface> $providers
   *   Services tagged `aculta_index.provider`.
   */
  public function __construct(iterable $providers = []) {
    foreach ($providers as $provider) {
      if (!$provider instanceof ContentIndexProviderInterface) {
        throw new \InvalidArgumentException('Every aculta_index.provider service must implement ContentIndexProviderInterface.');
      }
      $id = $provider->getProviderId();
      if ($id === '' || preg_match('/^[a-z][a-z0-9_]*$/', $id) !== 1) {
        throw new \InvalidArgumentException('Index provider IDs must be lowercase machine names.');
      }
      if (isset($this->providers[$id])) {
        throw new \InvalidArgumentException(sprintf('Duplicate ACULTA Content Index provider ID: %s.', $id));
      }
      $this->providers[$id] = $provider;
    }
  }

  /**
   * Returns registered providers keyed by provider ID.
   *
   * @return array<string, \Drupal\aculta_index\Provider\ContentIndexProviderInterface>
   */
  public function all(): array {
    return $this->providers;
  }

  /**
   * Returns whether a provider is registered.
   */
  public function has(string $providerId): bool {
    return isset($this->providers[$providerId]);
  }

  /**
   * Gets a provider by its stable machine name.
   */
  public function get(string $providerId): ContentIndexProviderInterface {
    if (!isset($this->providers[$providerId])) {
      throw new \InvalidArgumentException(sprintf('Unknown ACULTA Content Index provider: %s.', $providerId));
    }
    return $this->providers[$providerId];
  }

}
