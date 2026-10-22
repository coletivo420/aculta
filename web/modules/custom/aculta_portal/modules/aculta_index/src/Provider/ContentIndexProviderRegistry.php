<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Provider;

use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;

/**
 * Registry for providers contributed by enabled source modules.
 */
final class ContentIndexProviderRegistry {

  /**
   * The providers indexed by stable provider ID.
   *
   * @var array<string, \Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface>
   */
  private array $providers = [];

  /**
   * Provider that owns each entity type and bundle pair.
   *
   * @var array<string, array<string, string>>
   */
  private array $bundleOwners = [];

  /**
   * Builds the registry from tagged services.
   *
   * @param iterable<\Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface> $providers
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
      foreach ($provider->getSupportedEntityTypes() as $entityTypeId => $bundles) {
        if (!is_string($entityTypeId) || preg_match('/^[a-z][a-z0-9_]*$/', $entityTypeId) !== 1 || !is_array($bundles) || $bundles === []) {
          throw new \InvalidArgumentException(sprintf('Provider %s declares an invalid entity type or bundle list.', $id));
        }
        foreach ($bundles as $bundle) {
          if (!is_string($bundle) || preg_match('/^[a-z][a-z0-9_]*$/', $bundle) !== 1) {
            throw new \InvalidArgumentException(sprintf('Provider %s declares an invalid bundle for entity type %s.', $id, $entityTypeId));
          }
          $owner = $this->bundleOwners[$entityTypeId][$bundle] ?? NULL;
          if ($owner !== NULL) {
            throw new \InvalidArgumentException(sprintf('Entity bundle %s:%s is declared by both providers %s and %s.', $entityTypeId, $bundle, $owner, $id));
          }
          $this->bundleOwners[$entityTypeId][$bundle] = $id;
        }
      }
      $this->providers[$id] = $provider;
    }
  }

  /**
   * Returns registered providers keyed by provider ID.
   *
   * @return array<string, \Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface>
   *   The registered providers.
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
