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
   * Returns entity types and bundles exclusively owned by this projection.
   *
   * @return array<string, list<string>>
   *   Entity type IDs mapped to supported bundle IDs.
   */
  public function getSupportedEntityTypes(): array;

  /**
   * Returns the projection schema/semantics version for rebuild fingerprinting.
   *
   * Increment this value whenever projected fields, relations, or indexability
   * semantics change in a way that makes a partially built generation stale.
   */
  public function getProjectionVersion(): int;

  /**
   * Projects one entity translation, or returns NULL when it is not indexable.
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection;

}
