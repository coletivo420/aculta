<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\ContentIndex;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Contract for an optional module that projects source content into the index.
 *
 * The API lives in the Portal kernel so source modules can implement it while
 * `aculta_index` remains optional.
 */
interface ContentIndexProviderInterface {

  /**
   * Returns a stable machine name unique among enabled providers.
   */
  public function getProviderId(): string;

  /**
   * Returns supported entity types and their bundles.
   *
   * @return array<string, list<string>>
   *   Entity type IDs mapped to supported bundle IDs.
   */
  public function getSupportedEntityTypes(): array;

  /**
   * Projects one entity translation, or returns NULL when it is not indexable.
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection;

}
