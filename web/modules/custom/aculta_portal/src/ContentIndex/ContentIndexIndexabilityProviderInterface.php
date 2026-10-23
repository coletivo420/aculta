<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\ContentIndex;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Optional fast eligibility check for providers with expensive projections.
 *
 * The integrity checker uses this contract while scanning existing catalog
 * rows. Full projections are still recalculated in the source-coverage pass,
 * which detects stale metadata and relationships.
 */
interface ContentIndexIndexabilityProviderInterface {

  /**
   * Determines whether an entity translation remains indexable.
   *
   * Implementations should check only eligibility conditions and avoid
   * resolving projected relationships or constructing a projection.
   */
  public function isIndexable(ContentEntityInterface $entity): bool;

}
