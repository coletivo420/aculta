<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Provider;

/**
 * Identifies the source entity types handled by an index provider.
 *
 * Providers are optional services tagged `aculta_index.provider`. Projection
 * and relationship methods will be added with the storage contract in IDX 0.1-C.
 */
interface ContentIndexProviderInterface {

  /**
   * Returns a stable machine name unique among enabled providers.
   */
  public function getProviderId(): string;

  /**
   * Returns supported entity types and their bundles.
   *
   * Every key is an entity type ID and each value is a non-empty list of bundle
   * IDs. An empty array means the provider does not support any entity bundle.
   *
   * @return array<string, list<string>>
   */
  public function getSupportedEntityTypes(): array;

}
